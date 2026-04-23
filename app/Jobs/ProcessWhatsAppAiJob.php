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

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, array $msg, User $user)
    {
        $this->payload = $payload;
        $this->msg = $msg;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $phone = $this->msg['from'] ?? null;
        if (!$phone) {
            Log::warning("JOB_ABORTED: No phone in message");
            return;
        }

        Log::debug("JOB_STARTED", [
            'user_id' => $this->user->id,
            'phone'   => $phone,
            'type'    => $this->msg['type'] ?? 'unknown',
            'balance' => $this->user->balance,
            'autoreply' => $this->user->is_autoreply_enabled,
            'has_api_key' => !empty($this->user->target_api_key),
            'has_sheet' => !empty($this->user->google_sheet_name),
        ]);

        // Initialize variables
        $text = '';
        $mediaService = new WhatsAppMediaService();
        // Silent mode = no reply, only extract order (when auto-reply is OFF or balance is depleted)
        $isSilentExtraction = !$this->user->is_autoreply_enabled || ($this->user->balance <= 0);

        // 1. Check if Audio
        $msgType = $this->msg['type'] ?? 'text';
        if ($msgType === 'audio') {
            $audioId = $this->msg['audio']['id'] ?? null;
            if ($audioId && $this->user->target_api_key) {
                try {
                    $localPath = $mediaService->downloadMedia($audioId, $this->user->target_api_key);
                    Log::info("Downloaded Audio for parsing: {$localPath}");

                    // Transcribe Audio using OpenAI Whisper Native HTTP
                    $response = Http::withToken(env('OPENAI_API_KEY'))
                        ->withoutVerifying()
                        ->attach('file', file_get_contents($localPath), 'audio.ogg')
                        ->post('https://api.openai.com/v1/audio/transcriptions', [
                            'model' => 'whisper-1',
                        ]);

                    $text = $response->json('text') ?? '';
                    Log::info("Transcribed Audio: {$text}");

                    // Clean up downloaded file
                    unlink($localPath);
                } catch (Exception $e) {
                    Log::error("Failed to parse audio: " . $e->getMessage());
                    return; // Ignore message if we can't parse it
                }
            }
        } elseif ($msgType === 'text') {
            $text = $this->msg['text']['body'] ?? '';
        }

        if (empty(trim($text))) {
            Log::warning("JOB_ABORTED: Empty text", ['msg_type' => $msgType]);
            return;
        }

        try {
        // Save User Message to DB
        $this->saveChatHistory($phone, 'user', $text);

        // Load full inventory ONCE upfront and pass to prompt
        $inventoryList = $this->loadFullInventory();

        // Assemble Prompt
        $systemPrompt = $this->getSystemPrompt($isSilentExtraction, $inventoryList);

        // Fetch History
        $history = ChatHistory::where('user_id', $this->user->id)
            ->where('phone', $phone)
            ->where('timestamp', '>', now()->subHours(24))
            ->orderBy('timestamp', 'asc')
            ->get()
            ->map(function ($item) {
                $msg = [
                    'role' => $item->role,
                    'content' => $item->content ?? '',
                ];
                if ($item->tool_call_id) {
                    $msg['tool_call_id'] = $item->tool_call_id;
                    $msg['name'] = $item->tool_name;
                }
                return $msg;
            })->toArray();

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        $messages = array_merge($messages, $history);
        $messages[] = ['role' => 'user', 'content' => $text];

        // Tools
        $tools = [
            [
                "type" => "function",
                "function" => [
                    "name" => "confirm_order",
                    "description" => "Saves the order when prices are clear or agreed.",
                    "parameters" => [
                        "type" => "object",
                        "properties" => [
                            "items" => [
                                "type" => "array",
                                "items" => [
                                    "type" => "object",
                                    "properties" => [
                                        "name" => ["type" => "string"],
                                        "quantity" => ["type" => "number"],
                                        "price_breakdown" => ["type" => "string"],
                                        "total_price" => ["type" => "number"]
                                    ]
                                ]
                            ],
                            "address" => ["type" => "string"]
                        ],
                        "required" => ["items"]
                    ]
                ]
            ],
            [
                "type" => "function",
                "function" => [
                    "name" => "search_inventory",
                    "description" => "Search inventory to match local names to English items and retrieve product details.",
                    "parameters" => [
                        "type" => "object",
                        "properties" => [
                            "query" => ["type" => "string", "description" => "The item name to search for."]
                        ],
                        "required" => ["query"]
                    ]
                ]
            ]
        ];

        // 1st AI Call Native HTTP
        $result = Http::withToken(env('OPENAI_API_KEY'))
            ->withoutVerifying()
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto'
            ]);

        $aiMsg = $result->json('choices.0.message') ?? [];
        $totalTokens = $result->json('usage.total_tokens') ?? 0;
        
        $toolCalls = $aiMsg['tool_calls'] ?? null;
        
        $finalReply = $aiMsg['content'] ?? '';
        $extractedOrder = null;

        if ($toolCalls && count($toolCalls) > 0) {
            // Append assistant message with tool_calls
            $assistantToolMsg = ['role' => 'assistant', 'tool_calls' => []];
            foreach ($toolCalls as $tc) {
                $assistantToolMsg['tool_calls'][] = [
                    'id' => $tc['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $tc['function']['name'],
                        'arguments' => $tc['function']['arguments']
                    ]
                ];
            }
            $messages[] = $assistantToolMsg;

            // Handle Tools
            foreach ($toolCalls as $toolCall) {
                if ($toolCall['function']['name'] === "search_inventory") {
                    $args = json_decode($toolCall['function']['arguments'], true);
                    $query = $args['query'] ?? '';
                    
                    // Call Laravel inventory logic
                    $searchResults = $this->searchInventory($query);
                    $contentStr = json_encode($searchResults);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => 'search_inventory',
                        'content' => $contentStr
                    ];
                } elseif ($toolCall['function']['name'] === "confirm_order") {
                    $args = json_decode($toolCall['function']['arguments'], true);
                    $extractedOrder = $args;
                    
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => 'confirm_order',
                        'content' => json_encode(["status" => "processing"])
                    ];
                }
            }

            // 2nd AI Call Native HTTP
            $finalResult = Http::withToken(env('OPENAI_API_KEY'))
                ->withoutVerifying()
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages
                ]);
            $aiMsg = $finalResult->json('choices.0.message') ?? [];
            $totalTokens += $finalResult->json('usage.total_tokens') ?? 0;
            $finalReply = $aiMsg['content'] ?? '';
        }

        // If silent and empty reply, fallback for auto response based on extraction
        if ($extractedOrder && !$finalReply) {
            $itemNames = collect($extractedOrder['items'] ?? [])->pluck('name')->implode(', ');
            $totalVal = collect($extractedOrder['items'] ?? [])->sum('total_price');
            $finalReply = "හරි, ඔයා ඉල්ලපු {$itemNames} ඇණවුම මම සටහන් කරගත්තා. මුළු මුදල රු. {$totalVal}. ඉක්මනින්ම එවන්නම්.";
        }

        if ($isSilentExtraction) {
            $finalReply = ''; // Force silence
        }

        // Perform Meta Send
        if (!empty($finalReply)) {
            $this->sendWhatsApp(
                $phone, 
                $this->user->target_api_key, 
                $this->payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null, 
                $finalReply
            );
            $this->saveChatHistory($phone, 'assistant', $finalReply);
            
            // Deduct AI Token cost from LKR Balance
            if ($this->user->is_autoreply_enabled) {
                $costPerToken = 0.0001; // Rs. 0.0001 per token (Rs 100 per 1M tokens)
                if (isset($totalTokens)) {
                    $tokenDeduction = $totalTokens * $costPerToken;
                    $this->user->balance = max(0, $this->user->balance - $tokenDeduction);
                    $this->user->save();
                }
            }
        }

        // Process Saving Orders
        if ($extractedOrder) {
            $orderCreditsUsed = $this->saveOrder($phone, $extractedOrder);
            if ($orderCreditsUsed > 0) {
                $costPerOrder = 5.00;
                $orderDeduction = $orderCreditsUsed * $costPerOrder;
                $this->user->balance = max(0, $this->user->balance - $orderDeduction);
                $this->user->credits = max(0, $this->user->credits - $orderCreditsUsed);
                $this->user->save();
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

    private function getSystemPrompt(bool $isSilent, array $inventory = [])
    {
        $invCtx = $this->buildInventoryTable($inventory);

        if ($isSilent) {
            return "You are a silent order extraction bot. Extract order ONLY.\n"
                 . $invCtx
                 . "Match item names to the inventory table above using your multilingual knowledge.\n"
                 . "Call 'confirm_order' immediately with items, quantities, address. If price unknown, use 0.\n"
                 . "DO NOT send any text reply. Tool calls only.";
        }

        $greeting = $this->user->autoreply_message ?: '';
        $companyName = $this->user->name ?: 'our company';
        $companyDetails = $this->user->company_details ?: 'We sell various products and provide excellent service.';

        $prompt  = "You are a helpful, polite, and professional customer service representative for '{$companyName}'.\n";
        $prompt .= "Company Background: {$companyDetails}\n";
        $prompt .= "You assist customers via WhatsApp, answering general questions beautifully and shortly, and taking orders if requested.\n\n";

        // Inventory context FIRST — AI sees stock before processing any request
        if ($invCtx) {
            $prompt .= $invCtx;
            $prompt .= "\n⚠️  Item names in the inventory above might be in ENGLISH.\n";
            $prompt .= "The customer may write in Sinhala, Singlish, Tamil, or English. Match items using your multilingual language knowledge.\n";
            $prompt .= "Apply logic to map local language terms to inventory items (e.g., sapaththu=shoes, gawuma=dress, parippu=dhal, karavila=bitter gourd).\n\n";
        }

        $prompt .= "════ RULES ════\n\n";

        $prompt .= "LANGUAGE: Reply in the EXACT language the customer used (Sinhala→Sinhala, Singlish→Singlish, English→English). Never switch.\n\n";

        $prompt .= "GENERAL QUESTIONS (e.g., what do you sell?, where are you located?, etc.):\n";
        $prompt .= "→ Answer beautifully and shortly based on the Company Background. Act naturally like a human employee. Do not mention stock/inventory if not asked.\n\n";

        $prompt .= "INQUIRY (customer asks 'thiyenvada?', 'price?', 'ganna puluwanda?'):\n";
        $prompt .= "→ Answer naturally with price and available stock. ONE short reply. No bill.\n";
        $prompt .= "→ IMPORTANT: Check the requested QUANTITY against 'Stock Qty'. If they ask for 60kg but only 30kg is available, clearly say 'We only have 30kg available' and give the price for 30kg.\n\n";

        $prompt .= "ORDER (customer gives items + quantities, with or without address):\n";
        $prompt .= "→ Match each item to inventory using your language knowledge.\n";
        $prompt .= "→ STOCK CHECK: Never bill for more than the 'Stock Qty'. If they order 60 but stock is 30, only bill for 30 and politely mention the shortage.\n";
        $prompt .= "→ BATCH COMBINING: If there are multiple batches of the SAME item with the SAME PRICE, COMBINE them into a single line in the bill. Do NOT list the same item twice if the price is identical.\n";
        $prompt .= "→ Build bill preview. DO NOT call confirm_order yet.\n";
        $prompt .= "→ Bill format:\n\n";
        $prompt .= "🛒 Bill Preview:\n";
        $prompt .= "──────────────────\n";
        $prompt .= "Item Name 3kg × Rs.300 = Rs.900\n";
        $prompt .= "Item 2 6kg × Rs.300 = Rs.1,800\n";
        $prompt .= "──────────────────\n";
        $prompt .= "📦 Total: Rs.2,700\n";
        $prompt .= "📍 [Address if provided]\n\n";
        $prompt .= "Confirm karannada? 'OK' or 'Yes' 👍\n\n";

        $prompt .= "→ Stock Qty = 0: Skip from bill. Add at end warmly: '[Item] dan nathi sir 🙏 Laba una gaman kiyannm'\n";
        $prompt .= "→ Address missing: ask ONLY 'Address kiyanna sir?'\n\n";

        $prompt .= "CONFIRMATION (customer says ok / yes / ow / හා / gena enna):\n";
        $prompt .= "→ NOW call confirm_order tool to save. Reply: '✅ Order confirm! Thanks 🚚'\n\n";

        $prompt .= "STRICT RULES:\n";
        $prompt .= "- NEVER say: 'balanna puluwn nehe', 'database', 'system', 'inventory', 'I cannot find'\n";
        $prompt .= "- Answer naturally like a real human employee of '{$companyName}'.\n";
        $prompt .= "- ONE message only per reply. Keep it short and beautiful.\n";

        if ($greeting) {
            $prompt .= "\nFor the customer's VERY FIRST message only, start with: \"{$greeting}\"\n";
        }

        return $prompt;
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
            'user_id' => $this->user->id,
            'phone' => $phone,
            'role' => $role,
            'content' => $content,
            'tool_call_id' => $toolCallId,
            'tool_name' => $toolName,
            'timestamp' => now()
        ]);

        // Save to Contacts for Bulk Broadcasting
        \App\Models\Contact::updateOrCreate(
            ['user_id' => $this->user->id, 'phone' => $phone],
            ['last_messaged_at' => now(), 'updated_at' => now()]
        );

        // Auto-clear chat history older than 3 days
        ChatHistory::where('user_id', $this->user->id)
            ->where('timestamp', '<', now()->subDays(3))
            ->delete();

        broadcast(new MessageReceived($chat));
    }

    /**
     * Load the FULL inventory from Google Sheet once at job start.
     * Returns all rows as array. No alias map needed — AI matches by its own multilingual knowledge.
     * Returns [] if sheet not configured or not accessible.
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

            $client = $this->getGoogleClient();
            $sheetId = $this->getSheetId($client, $this->user->google_sheet_name);
            if (!$sheetId) {
                Log::warning("INVENTORY_LOAD: Sheet '{$this->user->google_sheet_name}' not found in Drive");
                return [];
            }

            $sheets = new GoogleSheets($client);
            $response = $sheets->spreadsheets_values->get($sheetId, 'Inventory!A:Z');
            $values = $response->getValues();

            if (empty($values) || count($values) < 2) {
                Log::warning("INVENTORY_LOAD: Inventory tab empty");
                return [];
            }

            $headers = array_map(fn($h) => strtolower(trim($h)), array_shift($values));
            $rows = [];
            foreach ($values as $row) {
                $item = [];
                foreach ($headers as $i => $key) {
                    $item[$key] = $row[$i] ?? '';
                }
                $rows[] = $item;
            }

            Log::info("INVENTORY_LOAD: Loaded " . count($rows) . " items into AI context");
            return $rows;

        } catch (Exception $e) {
            Log::error("INVENTORY_LOAD: Failed — " . $e->getMessage());
            return [];
        }
    }

    private function sendWhatsApp($phone, $token, $phoneId, $text)
    {
        if (!$text) return;

        // 🔄 Check connection type and route accordingly
        if ($this->user->connection_type === 'web_automation') {
            // Option B: Send via Node.js Bridge
            $this->sendViaNodeBridge($phone, $text);
        } else {
            // Option A: Send via Meta Cloud API (existing method)
            if (!$phoneId || !$token) return;
            
            $authHeader = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";
            Http::withHeaders(['Authorization' => $authHeader])->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                "messaging_product" => "whatsapp",
                "to" => $phone,
                "type" => "text",
                "text" => ["body" => $text]
            ]);
        }
    }

    /**
     * Send WhatsApp reply via Node.js Bridge (Option B)
     */
    private function sendViaNodeBridge($phone, $text)
    {
        $nodeBridgeUrl = env('NODE_BRIDGE_URL', 'http://127.0.0.1:3000');
        $apiKey = env('NODE_BRIDGE_SECRET_KEY', 'genify-node-bridge-secret-2026');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$nodeBridgeUrl}/send-message", [
                'user_id' => $this->user->id,
                'phone' => $phone,
                'message' => $text,
            ]);

            if ($response->successful()) {
                Log::info("✅ Reply sent via Node Bridge", [
                    'user_id' => $this->user->id,
                    'phone' => $phone,
                ]);
            } else {
                Log::error("❌ Node Bridge send failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
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
                $payload = ["phone" => $phone, "order" => $orderData];
                $response = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post($this->user->order_api_url, $payload);
                    
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
                $client = $this->getGoogleClient();
                $sheetId = $this->getSheetId($client, $this->user->google_sheet_name);

                if ($sheetId) {
                    $sheets = new GoogleSheets($client);
                    $values = [];
                    foreach ($orderData['items'] as $item) {
                        $values[] = [
                            now()->format("Y-m-d H:i:s"),
                            $phone,
                            $item['name'] ?? '',
                            $item['quantity'] ?? '',
                            $item['price_breakdown'] ?? '',
                            $item['total_price'] ?? '',
                            $orderData['address'] ?? 'N/A',
                            "Pending"
                        ];
                    }
                    
                    $body = new \Google\Service\Sheets\ValueRange([
                        'values' => $values
                    ]);
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
                    return collect($items)->filter(function($item) use ($query) {
                        $str = strtolower(json_encode($item));
                        return str_contains($str, strtolower($query));
                    })->values()->toArray();
                }
            } catch (Exception $e) {
                Log::error("Inventory API failed: " . $e->getMessage());
            }
            return []; // Fallback empty if API explicitly chosen but failed
        }

        // 2. Default to Google Sheets
        if (empty($this->user->google_sheet_name)) {
            Log::warning("SHEET_SEARCH: No google_sheet_name set for user {$this->user->id}");
            return [];
        }

        Log::info("SHEET_SEARCH: Searching for '{$query}' in sheet '{$this->user->google_sheet_name}'");

        try {
            // Check if service_account.json exists
            $serviceAccountPath = storage_path('app/service_account.json');
            if (!file_exists($serviceAccountPath)) {
                Log::error("SHEET_SEARCH: service_account.json NOT FOUND at: {$serviceAccountPath}");
                return [];
            }
            Log::info("SHEET_SEARCH: service_account.json found ✅");

            $client = $this->getGoogleClient();
            $sheetId = $this->getSheetId($client, $this->user->google_sheet_name);

            if (!$sheetId) {
                Log::error("SHEET_SEARCH: Sheet NOT FOUND in Drive — name: '{$this->user->google_sheet_name}'. Check service account sharing.");
                return [];
            }
            Log::info("SHEET_SEARCH: Sheet found ✅, ID: {$sheetId}");

            $sheets = new GoogleSheets($client);
            $response = $sheets->spreadsheets_values->get($sheetId, 'Inventory!A:Z');
            $values = $response->getValues();

            if (empty($values) || count($values) < 2) {
                Log::warning("SHEET_SEARCH: Inventory tab is empty or has no data rows. Rows found: " . count($values ?? []));
                return [];
            }

            $headers = array_shift($values);
            Log::info("SHEET_SEARCH: Inventory loaded ✅", [
                'total_rows' => count($values),
                'headers'    => $headers,
            ]);

            // Build all search terms: original query + known aliases
            $searchTerms = $this->getSearchAliases($query);
            Log::info("SHEET_SEARCH: Search terms to try", ['terms' => $searchTerms]);

            $results = [];
            foreach ($values as $row) {
                $itemData = [];
                foreach ($headers as $index => $key) {
                    $itemData[strtolower(trim($key))] = $row[$index] ?? '';
                }
                $rowStr = strtolower(implode(" ", $row));

                // Match if ANY of the search terms is found in this row
                $matched = false;
                foreach ($searchTerms as $term) {
                    if (str_contains($rowStr, strtolower($term))) {
                        $matched = true;
                        break;
                    }
                }
                if ($matched) {
                    $results[] = $itemData;
                }
            }

            Log::info("SHEET_SEARCH: Query '{$query}' → found " . count($results) . " row(s)");
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
     * Returns all known aliases for a search query,
     * covering Sinhala, Singlish, English, and Tamil names
     * for common Sri Lankan grocery items.
     */
    private function getSearchAliases(string $query): array
    {
        $q = strtolower(trim($query));

        // Map: each key is a search term → list of all aliases to also try
        $aliasMap = [
            // Lentils / Dhal
            'parippu'   => ['dhal', 'dal', 'lentil', 'parippu'],
            'dhal'      => ['dhal', 'dal', 'parippu', 'lentil'],
            'dal'       => ['dhal', 'dal', 'parippu', 'lentil'],
            // Sugar
            'sini'      => ['sugar', 'sini'],
            'sugar'     => ['sugar', 'sini'],
            // Onion
            'luunu'     => ['onion', 'luunu', 'lunu'],
            'lunu'      => ['onion', 'luunu', 'lunu'],
            'onion'     => ['onion', 'luunu', 'lunu'],
            // Potato
            'ala'       => ['potato', 'potatoes', 'ala'],
            'potato'    => ['potato', 'potatoes', 'ala'],
            'potatoes'  => ['potato', 'potatoes', 'ala'],
            // Rice
            'hal'       => ['rice', 'hal'],
            'rice'      => ['rice', 'hal'],
            // Flour / Bread flour
            'paan piti' => ['paan piti', 'bread flour', 'flour'],
            'flour'     => ['flour', 'paan piti', 'bread flour'],
            // Chickpea / Kadala
            'kadala'    => ['kadala', 'chickpea', 'gram'],
            'chickpea'  => ['chickpea', 'kadala'],
            // Salt
            'lunu'      => ['salt', 'lunu'],
            'salt'      => ['salt', 'lunu'],
            // Coconut oil
            'pol tel'   => ['coconut oil', 'pol tel'],
            'coconut oil' => ['coconut oil', 'pol tel'],
            // Eggs
            'biththara' => ['egg', 'eggs', 'biththara'],
            'egg'       => ['egg', 'eggs', 'biththara'],
            'eggs'      => ['egg', 'eggs', 'biththara'],
            // Garlic
            'sudulunu'  => ['garlic', 'sudulunu'],
            'garlic'    => ['garlic', 'sudulunu'],
            // Turmeric
            'kaha'      => ['turmeric', 'kaha'],
            'turmeric'  => ['turmeric', 'kaha'],
            // Chili powder
            'miris'     => ['chili', 'chilli', 'miris', 'red chili'],
            'chili'     => ['chili', 'chilli', 'miris'],
        ];

        // If we have aliases for this query, return them; otherwise just return the original
        return $aliasMap[$q] ?? [$q];
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
        $q = "name='" . str_replace("'", "\'", $name) . "' and mimeType='application/vnd.google-apps.spreadsheet' and trashed=false";
        
        $files = $drive->files->listFiles([
            'q' => $q,
            'spaces' => 'drive',
            'fields' => 'files(id, name)'
        ]);

        if (count($files->getFiles()) > 0) {
            return $files->getFiles()[0]->getId();
        }
        
        Log::warning("No Google Sheet found for name: {$name}. Check permissions to the service account bot.");
        return null;
    }
}
