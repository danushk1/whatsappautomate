<?php

namespace App\Jobs;

use App\Events\MessageReceived;
use App\Models\ChatHistory;
use App\Models\User;
use App\Services\WhatsAppMediaService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Sheets as GoogleSheets;

class ProcessWhatsAppAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;
    protected $msg;
    protected $user;

    public function __construct(array $payload, array $msg, User $user)
    {
        $this->payload = $payload;
        $this->msg = $msg;
        $this->user = $user;
    }

    public function handle()
    {
        $phone = $this->msg['from'] ?? null;
        if (!$phone) {
            Log::warning("JOB_ABORTED: No phone in message");
            return;
        }

        // Ignore WhatsApp status broadcasts — never reply to status updates
        if ($phone === 'status@broadcast' || str_contains($phone, 'status@broadcast')) {
            return;
        }

        // Normalize phone to clean international format for contact lookups
        $rawPhone  = $this->msg['real_phone'] ?? $phone;
        $realPhone = preg_replace('/@.*$/', '', $rawPhone);
        $realPhone = preg_replace('/[^0-9]/', '', $realPhone);
        if (strlen($realPhone) === 10 && str_starts_with($realPhone, '0')) {
            $realPhone = '94' . substr($realPhone, 1);
        }

        // Enforce Free Plan Limits (Max 3 Contacts)
        if ($this->user->plan_type === 'free') {
            $contactCount  = \App\Models\Contact::where('user_id', $this->user->id)->count();
            $contactExists = \App\Models\Contact::where('user_id', $this->user->id)
                ->where(function ($q) use ($realPhone, $rawPhone) {
                    $q->where('phone', $realPhone)->orWhere('phone', $rawPhone);
                })->exists();

            if (!$contactExists && $contactCount >= 3) {
                Log::info("Free plan contact limit reached for user {$this->user->id}. Ignored message from {$realPhone}.");
                return;
            }
        }

        // Daily rate limit: free plan = max 50 messages per contact per day
        if ($this->hasExceededDailyLimit($phone)) {
            Log::info("RATE_LIMIT: Daily limit reached for user {$this->user->id}, phone {$phone}");
            return;
        }

        // Initialize variables
        $text = '';
        $mediaService = new WhatsAppMediaService();
        // Silent mode = no reply, only extract order (when auto-reply is OFF or balance is depleted)
        $isSilentExtraction = !$this->user->is_autoreply_enabled || ($this->user->balance <= 0);

        // Detect new customer: no chat history in the last 3 days
        $isNewCustomer = $this->isNewCustomer($phone);

        // Check if Audio
        $msgType = $this->msg['type'] ?? 'text';
        if ($msgType === 'audio') {
            $audioId = $this->msg['audio']['id'] ?? null;
            if ($audioId && $this->user->target_api_key) {
                try {
                    $localPath = $mediaService->downloadMedia($audioId, $this->user->target_api_key);
                    Log::info("Downloaded Audio for parsing: {$localPath}");

                    $response = Http::withToken(config('services.openai.key'))
                        ->withoutVerifying()
                        ->attach('file', file_get_contents($localPath), 'audio.ogg')
                        ->post('https://api.openai.com/v1/audio/transcriptions', [
                            'model' => 'whisper-1',
                        ]);

                    $text = $response->json('text') ?? '';
                    Log::info("Transcribed Audio: {$text}");
                    unlink($localPath);
                } catch (Exception $e) {
                    Log::error("Failed to parse audio: " . $e->getMessage());
                    return;
                }
            }
        } elseif ($msgType === 'text') {
            $text = $this->msg['text']['body'] ?? '';
        }

        if (empty(trim($text))) {
            Log::warning("JOB_ABORTED: Empty text", ['msg_type' => $msgType]);
            return;
        }

        Log::info("JOB_STARTED: Processing message", [
            'user_id' => $this->user->id,
            'phone'   => $phone,
            'text'    => substr($text, 0, 80),
        ]);

        try {
            // Save User Message to DB
            $this->saveChatHistory($phone, 'user', $text);
            Log::info("JOB_STEP: chat history saved for {$phone}");

            // Send typing indicator (web_automation only, best-effort — shows AI is "thinking")
            $this->sendTypingIndicator($phone);

            // Load full inventory ONCE upfront and pass to prompt
            $inventoryList = $this->loadFullInventory();

            // Assemble Prompt — passes new-customer flag for dynamic greeting
            $systemPrompt = $this->getSystemPrompt($isSilentExtraction, $inventoryList, $isNewCustomer);

            // Fetch last 20 messages within 3 days — cap prevents token overflow
            $history = ChatHistory::where('user_id', $this->user->id)
                ->where('phone', $phone)
                ->where('timestamp', '>', now()->subDays(3))
                ->orderBy('timestamp', 'desc')
                ->limit(20)
                ->get()
                ->reverse()
                ->values()
                ->map(function ($item) {
                    $msg = [
                        'role'    => $item->role,
                        'content' => mb_substr($item->content ?? '', 0, 500),
                    ];
                    if ($item->tool_call_id) {
                        $msg['tool_call_id'] = $item->tool_call_id;
                        $msg['name']         = $item->tool_name;
                    }
                    return $msg;
                })->toArray();

            $messages   = [['role' => 'system', 'content' => $systemPrompt]];
            $messages   = array_merge($messages, $history);
            $messages[] = ['role' => 'user', 'content' => $text];

            // Tools
            $tools = [
                [
                    "type" => "function",
                    "function" => [
                        "name"        => "confirm_order",
                        "description" => "Saves the confirmed order to the system. ONLY call this AFTER the customer has confirmed the bill AND provided their delivery address. Both items and address are mandatory.",
                        "parameters"  => [
                            "type"       => "object",
                            "properties" => [
                                "items" => [
                                    "type"  => "array",
                                    "items" => [
                                        "type"       => "object",
                                        "properties" => [
                                            "name"            => ["type" => "string"],
                                            "quantity"        => ["type" => "number"],
                                            "price_breakdown" => ["type" => "string"],
                                            "total_price"     => ["type" => "number"],
                                        ],
                                    ],
                                ],
                                "address" => [
                                    "type"        => "string",
                                    "description" => "Customer delivery address. Mandatory — do NOT call confirm_order without this.",
                                ],
                            ],
                            "required" => ["items", "address"],
                        ],
                    ],
                ],
                [
                    "type" => "function",
                    "function" => [
                        "name"        => "search_inventory",
                        "description" => "Search inventory for product details (price, stock, availability). ALWAYS call this before answering ANY question about products, prices, or stock. Never use memory for price/stock.",
                        "parameters"  => [
                            "type"       => "object",
                            "properties" => [
                                "query" => [
                                    "type"        => "string",
                                    "description" => "The item name to search for.",
                                ],
                            ],
                            "required" => ["query"],
                        ],
                    ],
                ],
                [
                    "type" => "function",
                    "function" => [
                        "name"        => "get_order_history",
                        "description" => "Retrieve the last 5 confirmed orders for this customer. Call when the customer asks about previous orders, order history, or past purchases.",
                        "parameters"  => [
                            "type"       => "object",
                            "properties" => (object)[],
                            "required"   => [],
                        ],
                    ],
                ],
            ];

            // 1st AI Call
            $result = Http::withToken(config('services.openai.key'))
                ->withoutVerifying()
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4o',
                    'messages'    => $messages,
                    'tools'       => $tools,
                    'tool_choice' => 'auto',
                ]);

            if (!$result->successful()) {
                Log::error("❌ OpenAI API Error (1st Call): " . $result->body());
            }

            $aiMsg      = $result->json('choices.0.message') ?? [];
            $totalTokens = $result->json('usage.total_tokens') ?? 0;

            $toolCalls      = $aiMsg['tool_calls'] ?? null;
            $finalReply     = $aiMsg['content'] ?? '';
            $extractedOrder = null;

            if ($toolCalls && count($toolCalls) > 0) {
                // Append assistant message with tool_calls
                $assistantToolMsg = ['role' => 'assistant', 'tool_calls' => []];
                foreach ($toolCalls as $tc) {
                    $assistantToolMsg['tool_calls'][] = [
                        'id'       => $tc['id'],
                        'type'     => 'function',
                        'function' => [
                            'name'      => $tc['function']['name'],
                            'arguments' => $tc['function']['arguments'],
                        ],
                    ];
                }
                $messages[] = $assistantToolMsg;

                // Handle each tool call
                foreach ($toolCalls as $toolCall) {
                    if ($toolCall['function']['name'] === 'search_inventory') {
                        $args    = json_decode($toolCall['function']['arguments'], true);
                        $query   = $args['query'] ?? '';
                        $results = $this->searchInventory($query);

                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name'         => 'search_inventory',
                            'content'      => json_encode($results),
                        ];

                    } elseif ($toolCall['function']['name'] === 'confirm_order') {
                        $args           = json_decode($toolCall['function']['arguments'], true);
                        $extractedOrder = $args;

                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name'         => 'confirm_order',
                            'content'      => json_encode(['status' => 'processing']),
                        ];

                    } elseif ($toolCall['function']['name'] === 'get_order_history') {
                        $orderHistory = $this->getOrderHistory($phone);

                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name'         => 'get_order_history',
                            'content'      => json_encode($orderHistory),
                        ];
                    }
                }

                // 2nd AI Call
                $finalResult = Http::withToken(config('services.openai.key'))
                    ->withoutVerifying()
                    ->timeout(60)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'    => 'gpt-4o',
                        'messages' => $messages,
                    ]);

                if (!$finalResult->successful()) {
                    Log::error("❌ OpenAI API Error (2nd Call): " . $finalResult->body());
                }

                $aiMsg        = $finalResult->json('choices.0.message') ?? [];
                $totalTokens += $finalResult->json('usage.total_tokens') ?? 0;
                $finalReply   = $aiMsg['content'] ?? '';
            }

            // Fallback reply if order extracted silently but no text generated
            if ($extractedOrder && !$finalReply) {
                $itemNames  = collect($extractedOrder['items'] ?? [])->pluck('name')->implode(', ');
                $totalVal   = collect($extractedOrder['items'] ?? [])->sum('total_price');
                $finalReply = "හරි, ඔයා ඉල්ලපු {$itemNames} ඇණවුම මම සටහන් කරගත්තා. මුළු මුදල රු. {$totalVal}. ඉක්මනින්ම එවන්නම්.";
            }

            if ($isSilentExtraction) {
                $finalReply = '';
            }

            // Send reply
            if (!empty($finalReply)) {
                $this->sendWhatsApp(
                    $phone,
                    $this->user->target_api_key,
                    $this->payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null,
                    $finalReply
                );
                $this->saveChatHistory($phone, 'assistant', $finalReply);

                // Deduct AI token cost from LKR balance
                if ($this->user->is_autoreply_enabled) {
                    $costPerToken    = 0.0001;
                    $tokenDeduction  = $totalTokens * $costPerToken;
                    $this->user->balance = max(0, $this->user->balance - $tokenDeduction);
                    $this->user->save();
                    $this->checkAndNotifyLowBalance();
                }
            }

            // Save confirmed order
            if ($extractedOrder) {
                $orderCreditsUsed = $this->saveOrder($phone, $extractedOrder);
                if ($orderCreditsUsed > 0) {
                    $costPerOrder    = 5.00;
                    $orderDeduction  = $orderCreditsUsed * $costPerOrder;
                    $this->user->balance = max(0, $this->user->balance - $orderDeduction);
                    $this->user->credits = max(0, $this->user->credits - $orderCreditsUsed);
                    $this->user->save();
                    $this->checkAndNotifyLowBalance();
                }
            }

        } catch (\Throwable $e) {
            Log::error("JOB_FATAL_ERROR: " . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
        }
    }

    // ============================================================
    // NEW HELPER METHODS
    // ============================================================

    /**
     * Returns true if this phone has NO chat history within the last 3 days.
     * Used to trigger a warm first-contact greeting.
     */
    private function isNewCustomer(string $phone): bool
    {
        return !ChatHistory::where('user_id', $this->user->id)
            ->where('phone', $phone)
            ->where('timestamp', '>', now()->subDays(3))
            ->exists();
    }

    /**
     * Free plan: allow max 50 user messages per phone per calendar day.
     * Premium plan: unlimited.
     */
    private function hasExceededDailyLimit(string $phone): bool
    {
        if ($this->user->plan_type !== 'free') {
            return false;
        }

        $todayCount = ChatHistory::where('user_id', $this->user->id)
            ->where('phone', $phone)
            ->where('role', 'user')
            ->whereDate('timestamp', today())
            ->count();

        return $todayCount >= 50;
    }

    /**
     * Sends a typing indicator to the customer via Node.js Bridge.
     * Only applies to web_automation connections. Fails silently.
     */
    private function sendTypingIndicator(string $phone): void
    {
        if ($this->user->connection_type !== 'web_automation') {
            return;
        }

        $nodeBridgeUrl = config('services.node_bridge.url');
        $apiKey        = config('services.node_bridge.secret_key');

        try {
            Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(3)->post("{$nodeBridgeUrl}/typing", [
                'user_id' => $this->user->id,
                'phone'   => $phone,
            ]);
        } catch (\Exception $e) {
            // Best effort — typing indicator failure should never block message processing
        }
    }

    /**
     * Fetches the last 5 confirmed order summaries for this phone from chat history.
     * Scans assistant messages that contain order confirmation text.
     */
    private function getOrderHistory(string $phone): array
    {
        $orders = ChatHistory::where('user_id', $this->user->id)
            ->where('phone', $phone)
            ->where('role', 'assistant')
            ->where(function ($q) {
                $q->where('content', 'like', '%✅ Order%')
                  ->orWhere('content', 'like', '%Order confirm%')
                  ->orWhere('content', 'like', '%confirm karannada%')
                  ->orWhere('content', 'like', '%ඇණවුම%');
            })
            ->orderBy('timestamp', 'desc')
            ->limit(5)
            ->get(['content', 'timestamp']);

        if ($orders->isEmpty()) {
            return ['message' => 'No recent orders found for this customer.'];
        }

        return $orders->map(fn($o) => [
            'date'    => $o->timestamp ? $o->timestamp->format('Y-m-d H:i') : 'Unknown date',
            'summary' => $o->content,
        ])->toArray();
    }

    // ============================================================
    // EXISTING METHODS (IMPROVED)
    // ============================================================

private function getSystemPrompt(bool $isSilent, array $inventory = [], bool $isNewCustomer = false)
{
    $invCtx         = $this->buildInventoryTable($inventory);
    $greeting       = $this->user->autoreply_message ?? '';
    $companyName    = $this->user->name ?? 'our company';
    $companyDetails = $this->user->company_details ?? 'We sell various products.';

    if ($isSilent) {
        return "Silent order extraction bot. {$invCtx}"
            . "Match customer words (Sinhala/Singlish/Tamil/English) to inventory names. "
            . "Call confirm_order with items+quantities+address. No text reply.";
    }

    $p  = "You are a WhatsApp customer service assistant for '{$companyName}'.\n";
    $p .= "About: {$companyDetails}\n\n";

    if ($invCtx) {
        $p .= $invCtx . "\n";
        $p .= "PRODUCT MATCHING: Customers write in Sinhala, Singlish, Tamil, or English. "
            . "Find the EXACT matching name from the inventory table above, then use that name when calling search_inventory. "
            . "If unclear, ask. Never guess.\n\n";
    }

    $p .= "RULES:\n"
        . "- Language: reply in customer's language. Never mix.\n"
        . "- Style: short (1-3 sentences), human, warm, like a real shop worker. No markdown, no asterisks, no bullet lists. Emojis ok 😊\n"
        . "- Products: ALWAYS call search_inventory before stating price/stock. Never use memory.\n"
        . "- Item not found: say it simply and naturally. Example: '[item] dan nathi sir 🙏 Vෙනත් monvada one?' — Do NOT invent category names, do NOT rename the product, do NOT suggest unrelated items as 'similar type'.\n"
        . "- NEVER make up product relationships or translations. If the inventory name is 'Paan Piti', call it 'Paan Piti' — not 'kukul piti' or any other invented name.\n"
        . "- Stock low: inform naturally. Stock 0: skip item, mention at end simply.\n"
        . "- Multiple batch prices: ask customer which they want before building bill.\n"
        . "- Order: get items + address. If address missing, ask first.\n"
        . "- Bill format (plain text only):\n"
        . "  🛒 Bill:\n"
        . "  [item] [qty] x Rs.[price] = Rs.[total]\n"
        . "  Total: Rs.[grand total]\n"
        . "  Address: [address]\n"
        . "  Confirm karannada? 😊\n"
        . "- Confirm order: ONLY after customer says yes AND address exists. Then call confirm_order.\n"
        . "- Order history: call get_order_history if customer asks.\n"
        . "- Off-topic/spam: no reply.\n"
        . "- NEVER mention system, database, or inventory.\n";

    if ($isNewCustomer && $greeting) {
        $p .= "\nFirst message: start with \"{$greeting}\"\n";
    }

    return $p;
}
    private function buildInventoryTable(array $inventory): string
    {
        if (empty($inventory)) {
            return '';
        }

        $out  = "════════════════════════════════\n";
        $out .= "CURRENT SHOP INVENTORY\n";
        $out .= "════════════════════════════════\n";
        $out .= "| Item Name | Price (Rs) | Stock Qty | Batch Date |\n";
        $out .= "|-----------|------------|-----------|------------|\n";
        foreach ($inventory as $row) {
            $name  = $row['item name'] ?? $row['name'] ?? '';
            $price = $row['price'] ?? 0;
            $qty   = $row['stock qty'] ?? $row['qty'] ?? '';
            $date  = $row['batch date'] ?? '';
            $out  .= "| {$name} | {$price} | {$qty} | {$date} |\n";
        }
        $out .= "════════════════════════════════\n";
        return $out;
    }

    private function saveChatHistory($phone, $role, $content, $toolCallId = null, $toolName = null)
    {
        $chat = ChatHistory::create([
            'user_id'      => $this->user->id,
            'phone'        => $phone,
            'role'         => $role,
            'content'      => $content,
            'tool_call_id' => $toolCallId,
            'tool_name'    => $toolName,
            'timestamp'    => now(),
        ]);

        // Save to Contacts for Bulk Broadcasting (normalize to clean international format)
        $rawPhone  = $this->msg['real_phone'] ?? $phone;
        $realPhone = preg_replace('/@.*$/', '', $rawPhone);       // strip @c.us / @g.us
        $realPhone = preg_replace('/[^0-9]/', '', $realPhone);    // digits only
        if (strlen($realPhone) === 10 && str_starts_with($realPhone, '0')) {
            $realPhone = '94' . substr($realPhone, 1);            // 07X → 94X
        }
        if (strlen($realPhone) >= 10 && strlen($realPhone) <= 15) {
            \App\Models\Contact::updateOrCreate(
                ['user_id' => $this->user->id, 'phone' => $realPhone],
                ['last_messaged_at' => now(), 'updated_at' => now()]
            );
        }

        // Auto-clear chat history older than 3 days
        ChatHistory::where('user_id', $this->user->id)
            ->where('timestamp', '<', now()->subDays(3))
            ->delete();

        broadcast(new MessageReceived($chat));
    }

    /**
     * Load the FULL inventory from Google Sheet once at job start.
     * Returns all rows as array. Returns [] if sheet not configured.
     */
    private function loadFullInventory(): array
    {
        if (empty($this->user->google_sheet_name)) {
            return [];
        }

        try {
            $serviceAccountPath = storage_path('app/service_account.json');
            if (!file_exists($serviceAccountPath)) {
                Log::warning("INVENTORY_LOAD: service_account.json not found, skipping inventory context");
                return [];
            }

            $client  = $this->getGoogleClient();
            $sheetId = $this->getSheetId($client, $this->user->google_sheet_name);
            if (!$sheetId) {
                Log::warning("INVENTORY_LOAD: Sheet '{$this->user->google_sheet_name}' not found in Drive");
                return [];
            }

            $sheets   = new GoogleSheets($client);
            $response = $sheets->spreadsheets_values->get($sheetId, 'Inventory!A:Z');
            $values   = $response->getValues();

            if (empty($values) || count($values) < 2) {
                Log::warning("INVENTORY_LOAD: Inventory tab empty");
                return [];
            }

            $headers   = array_map(fn($h) => strtolower(trim($h)), array_shift($values));
            $totalRows = count($values);
            $rows      = [];

            // Cap items sent to AI system prompt — prevents massive token usage for large catalogues.
            // search_inventory tool still searches ALL rows directly from the sheet.
            $promptLimit = 300;
            $slice       = $totalRows > $promptLimit ? array_slice($values, 0, $promptLimit) : $values;

            foreach ($slice as $row) {
                $item = [];
                foreach ($headers as $i => $key) {
                    $item[$key] = $row[$i] ?? '';
                }
                $rows[] = $item;
            }

            if ($totalRows > $promptLimit) {
                // Sentinel row so the AI knows more items exist and should use the tool
                $rows[] = array_fill_keys($headers, '');
                $rows[count($rows) - 1][array_key_first(array_flip($headers))] =
                    "... ({$totalRows} total items — use search_inventory tool for full results)";
            }

            Log::info("INVENTORY_LOAD: {$totalRows} total items, " . count($rows) . " sent to AI context");
            return $rows;

        } catch (Exception $e) {
            Log::error("INVENTORY_LOAD: Failed — " . $e->getMessage());
            return [];
        }
    }

    private function sendWhatsApp($phone, $token, $phoneId, $text)
    {
        if (!$text) return;

        if ($this->user->connection_type === 'web_automation') {
            $this->sendViaNodeBridge($phone, $text);
        } else {
            if (!$phoneId || !$token) return;

            $authHeader = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
            Http::withHeaders(['Authorization' => $authHeader])->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => ['body' => $text],
            ]);
        }
    }

    private function sendViaNodeBridge($phone, $text)
    {
        $nodeBridgeUrl = config('services.node_bridge.url');
        $apiKey        = config('services.node_bridge.secret_key');

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$nodeBridgeUrl}/send-message", [
                'user_id' => $this->user->id,
                'phone'   => $phone,
                'message' => $text,
            ]);

            if ($response->successful()) {
                Log::info("✅ Reply sent via Node Bridge", ['user_id' => $this->user->id, 'phone' => $phone]);
            } else {
                Log::error("❌ Node Bridge send failed", ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error("❌ Node Bridge connection error: " . $e->getMessage());
        }
    }

    private function saveOrder($phone, $orderData)
    {
        $creditsUsed = 0;

        // 1. Save to Custom API if enabled
        if (!empty($this->user->order_api_url)) {
            try {
                $headers = [];
                if (!empty($this->user->target_api_key)) {
                    $headers['x-api-key'] = $this->user->target_api_key;
                }
                $response = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post($this->user->order_api_url, ['phone' => $phone, 'order' => $orderData]);

                if ($response->successful()) {
                    Log::info("✅ Order sent to API for {$phone}");
                    $creditsUsed++;
                } else {
                    Log::error("Order API Write Error for {$phone}: " . $response->body());
                }
            } catch (Exception $e) {
                Log::error("Order API Request failed: " . $e->getMessage());
            }
        }

        // 2. Save to Google Sheets if enabled
        if (!empty($this->user->google_sheet_name)) {
            try {
                $client  = $this->getGoogleClient();
                $sheetId = $this->getSheetId($client, $this->user->google_sheet_name);

                if ($sheetId) {
                    $sheets = new GoogleSheets($client);
                    $values = [];
                    foreach ($orderData['items'] as $item) {
                        $values[] = [
                            now()->format('Y-m-d H:i:s'),
                            $phone,
                            $item['name'] ?? '',
                            $item['quantity'] ?? '',
                            $item['price_breakdown'] ?? '',
                            $item['total_price'] ?? '',
                            $orderData['address'] ?? 'N/A',
                            'Pending',
                        ];
                    }

                    $body   = new \Google\Service\Sheets\ValueRange(['values' => $values]);
                    $params = ['valueInputOption' => 'USER_ENTERED'];
                    $sheets->spreadsheets_values->append($sheetId, 'Orders!A:H', $body, $params);
                    Log::info("✅ Order saved to sheet for {$phone}");
                    $creditsUsed++;
                }
            } catch (Exception $e) {
                Log::error("Google Sheets append failed: " . $e->getMessage());
            }
        }

        return $creditsUsed;
    }

    private function searchInventory($query)
    {
        // 1. If an inventory API URL is provided, try that first.
        if (!empty($this->user->inventory_api_url)) {
            try {
                $response = Http::timeout(10)->get($this->user->inventory_api_url);
                if ($response->successful()) {
                    $items = $response->json();
                    $terms = $this->buildSearchTerms($query);
                    return collect($items)->filter(function ($item) use ($terms) {
                        $str = strtolower(json_encode($item));
                        foreach ($terms as $term) {
                            if (str_contains($str, $term)) return true;
                        }
                        return false;
                    })->values()->toArray();
                }
            } catch (Exception $e) {
                Log::error("Inventory API failed: " . $e->getMessage());
            }
            return [];
        }

        // 2. Default to Google Sheets
        if (empty($this->user->google_sheet_name)) {
            Log::warning("SHEET_SEARCH: No google_sheet_name set for user {$this->user->id}");
            return [];
        }

        try {
            $serviceAccountPath = storage_path('app/service_account.json');
            if (!file_exists($serviceAccountPath)) {
                Log::error("SHEET_SEARCH: service_account.json NOT FOUND at: {$serviceAccountPath}");
                return [];
            }

            $client  = $this->getGoogleClient();
            $sheetId = $this->getSheetId($client, $this->user->google_sheet_name);

            if (!$sheetId) {
                Log::error("SHEET_SEARCH: Sheet NOT FOUND — '{$this->user->google_sheet_name}'");
                return [];
            }

            $sheets   = new GoogleSheets($client);
            $response = $sheets->spreadsheets_values->get($sheetId, 'Inventory!A:Z');
            $values   = $response->getValues();

            if (empty($values) || count($values) < 2) {
                Log::warning("SHEET_SEARCH: Inventory tab is empty or has no data rows.");
                return [];
            }

            $headers = array_shift($values);
            $terms   = $this->buildSearchTerms($query);

            $results = [];
            foreach ($values as $row) {
                $itemData = [];
                foreach ($headers as $index => $key) {
                    $itemData[strtolower(trim($key))] = $row[$index] ?? '';
                }
                $rowStr = strtolower(implode(' ', $row));
                foreach ($terms as $term) {
                    if (str_contains($rowStr, $term)) {
                        $results[] = $itemData;
                        break;
                    }
                }
            }

            Log::info("SHEET_SEARCH: '{$query}' → " . count($results) . " result(s)");
            return $results;

        } catch (Exception $e) {
            Log::error("SHEET_SEARCH: Exception — " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return [];
        }
    }

    /**
     * Breaks the AI's query into search terms for substring matching.
     * Works for any language/business type — no hardcoded aliases needed.
     * The AI already sees the full inventory table and uses exact item names when calling this tool.
     */
    private function buildSearchTerms(string $query): array
    {
        $query = strtolower(trim($query));

        // Split on spaces and common separators to try each word individually
        $words = array_filter(
            preg_split('/[\s,\/\-]+/', $query),
            fn($w) => strlen($w) >= 2
        );

        // Full phrase first, then individual words — deduped
        return array_values(array_unique(array_merge([$query], $words)));
    }

    private function getGoogleClient()
    {
        $client = new GoogleClient();
        $client->setApplicationName('WhatsApp AI Bridge');
        $client->setScopes([GoogleDrive::DRIVE, GoogleSheets::SPREADSHEETS]);
        $client->setAuthConfig(storage_path('app/service_account.json'));
        $client->setAccessType('offline');
        return $client;
    }

    private function getSheetId($client, $name)
    {
        $drive = new GoogleDrive($client);
        $q     = "name='" . str_replace("'", "\'", $name) . "' and mimeType='application/vnd.google-apps.spreadsheet' and trashed=false";

        $files = $drive->files->listFiles([
            'q'      => $q,
            'spaces' => 'drive',
            'fields' => 'files(id, name)',
        ]);

        if (count($files->getFiles()) > 0) {
            return $files->getFiles()[0]->getId();
        }

        Log::warning("No Google Sheet found for name: {$name}. Check permissions to the service account bot.");
        return null;
    }

    private function checkAndNotifyLowBalance(): void
    {
        $user = $this->user->fresh();

        if ($user->balance <= 0) return;

        // Target: user's private_phone → fallback to whatsapp_number
        $notifyPhone = $user->private_phone ?: $user->whatsapp_number;
        if (!$notifyPhone) return;

        // Sender: admin's connected WhatsApp (admin must scan QR via /whatsapp/connect)
        $admin = \App\Models\User::where('is_admin', true)->first();
        if (!$admin) return;

        $setting  = \App\Models\AdminSetting::first();
        $bankLine = ($setting && $setting->bank_name)
            ? "\n\n🏦 Bank: {$setting->bank_name}\nAcc No: {$setting->bank_account_no}\nName: {$setting->bank_account_name}\nBranch: {$setting->bank_branch}"
            : '';

        $balance = number_format($user->balance, 2);

        if ($user->balance <= 5) {
            if ($user->suspended_notified_at) return; // already sent once, wait for top-up reset

            $text = $setting?->suspended_message
                ?: "⚠️ ඔබේ account balance Rs.{$balance} දක්වා පහළ ගොස් service close වී ඇත.\nReactivate karanna payment karanna.";

            $text = str_replace(['{balance}', '{name}'], [$balance, $user->name], $text);
            $full = $text . $bankLine;

            $this->sendNotification($admin->id, $notifyPhone, $full);
            $this->saveAdminMessage($user->id, $full);
            $user->suspended_notified_at = now();
            $user->save();

        } elseif ($user->balance <= 10) {
            if ($user->low_balance_notified_at) return; // already sent once, wait for top-up reset

            $text = $setting?->low_balance_message
                ?: "⚠️ ඔබේ balance Rs.{$balance} ක් ඉතිරියි.\nTop up karanna.";

            $text = str_replace(['{balance}', '{name}'], [$balance, $user->name], $text);
            $full = $text . $bankLine;

            $this->sendNotification($admin->id, $notifyPhone, $full);
            $this->saveAdminMessage($user->id, $full);
            $user->low_balance_notified_at = now();
            $user->save();
        }
    }

    private function saveAdminMessage(int $userId, string $message): void
    {
        try {
            \App\Models\AdminMessage::create([
                'from_number' => 'system',
                'user_id'     => $userId,
                'message'     => $message,
                'is_read'     => false,
                'received_at' => now(),
                'expires_at'  => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminMessage save failed: " . $e->getMessage());
        }
    }

    private function sendNotification(int $senderUserId, string $phone, string $message): void
    {
        try {
            Http::withHeaders([
                'x-api-key'    => config('services.node_bridge.secret_key'),
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(config('services.node_bridge.url') . '/send-message', [
                'user_id' => $senderUserId,
                'phone'   => $phone,
                'message' => $message,
            ]);
            Log::info("Balance notification sent via admin WhatsApp → {$phone}");
        } catch (\Throwable $e) {
            Log::error("Balance notification failed: " . $e->getMessage());
        }
    }
}
