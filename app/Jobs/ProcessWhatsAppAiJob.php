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
                return;
            }
        }

        // Daily rate limit: free plan = max 50 messages per contact per day
        if ($this->hasExceededDailyLimit($phone)) {
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
                    $response = Http::withToken(config('services.openai.key'))
                        ->withoutVerifying()
                        ->attach('file', file_get_contents($localPath), 'audio.ogg')
                        ->post('https://api.openai.com/v1/audio/transcriptions', [
                            'model' => 'whisper-1',
                        ]);
                    $text = $response->json('text') ?? '';
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
            return;
        }

        try {
            // Save User Message to DB
            $this->saveChatHistory($phone, 'user', $text);

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
                        "description" => "Search live inventory for a product's current price and stock quantity. MANDATORY: call this before quoting any price or confirming stock availability. Use the EXACT item name from the inventory table. If result is empty, the item does not exist — do NOT invent an answer.",
                        "parameters"  => [
                            "type"       => "object",
                            "properties" => [
                                "query" => [
                                    "type"        => "string",
                                    "description" => "Exact item name from the inventory table to look up.",
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
                [
                    "type" => "function",
                    "function" => [
                        "name"        => "notify_stock_alert",
                        "description" => "Silently notify the shop owner that a customer requested a quantity that exceeds available stock. Call this FIRST before replying to the customer. Do NOT tell the customer that someone will contact them — you must still answer the customer yourself about the stock situation.",
                        "parameters"  => [
                            "type"       => "object",
                            "properties" => [
                                "item_name"     => ["type" => "string", "description" => "The item the customer requested."],
                                "requested_qty" => ["type" => "number", "description" => "Quantity the customer asked for."],
                                "available_qty" => ["type" => "number", "description" => "Current stock available. Use 0 if out of stock."],
                            ],
                            "required" => ["item_name", "requested_qty", "available_qty"],
                        ],
                    ],
                ],
                [
                    "type" => "function",
                    "function" => [
                        "name"        => "escalate_to_admin",
                        "description" => "Notify a human admin and tell the customer a team member will contact them. Call this ONLY when: customer explicitly asks for a phone call or to speak to a person, requests credit or payment delay, or expresses genuine frustration/complaint. Do NOT call this for stock/quantity issues — use notify_stock_alert instead. Do NOT call for items not in inventory — just redirect to available items.",
                        "parameters"  => [
                            "type"       => "object",
                            "properties" => [
                                "reason" => [
                                    "type"        => "string",
                                    "description" => "Short English summary of what the customer needs (e.g. 'Wants a phone call', 'Requesting credit/delay').",
                                ],
                            ],
                            "required" => ["reason"],
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

            $toolCalls        = $aiMsg['tool_calls'] ?? null;
            $finalReply       = $aiMsg['content'] ?? '';
            $extractedOrder   = null;
            $escalationCalled = false;
            $stockAlertCalled = false;

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
                        $this->notifyNewOrder($phone, $args);

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

                    } elseif ($toolCall['function']['name'] === 'notify_stock_alert') {
                        $args = json_decode($toolCall['function']['arguments'], true);
                        $this->notifyStockAlert(
                            $phone,
                            $args['item_name']     ?? '',
                            $args['requested_qty'] ?? 0,
                            $args['available_qty'] ?? 0
                        );
                        $stockAlertCalled = true;

                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name'         => 'notify_stock_alert',
                            'content'      => json_encode(['status' => 'logged', 'instruction' => 'Logged internally. Now ask the customer WHEN they need the quantity ("kawadata gannada sir?") and tell them warmly that our team member will contact them to arrange. Do NOT say how much stock is available.']),
                        ];

                    } elseif ($toolCall['function']['name'] === 'escalate_to_admin') {
                        $args   = json_decode($toolCall['function']['arguments'], true);
                        $reason = $args['reason'] ?? 'Customer needs assistance';
                        $this->escalateToAdmin($phone, $reason);
                        $escalationCalled = true;

                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name'         => 'escalate_to_admin',
                            'content'      => json_encode(['status' => 'notified', 'instruction' => 'Admin has been notified. Now tell the customer warmly that a team member will contact them shortly.']),
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

            // Safety net: AI sometimes writes "poddak inna" without calling escalate_to_admin tool.
            // Skip if a stock alert was already sent (reply will naturally contain "ape kenek katha karai").
            // Skip if reply is a "not in inventory" message — those should never trigger escalation.
            if (!$escalationCalled && !$stockAlertCalled && !empty($finalReply)) {
                $lower = mb_strtolower($finalReply);
                $notInInventorySignals = ['nathi athi sir', 'api laga na', 'langa na sir', 'api langa na', 'nathi athi madam'];
                $isNotInInventoryReply = false;
                foreach ($notInInventorySignals as $niSignal) {
                    if (str_contains($lower, $niSignal)) {
                        $isNotInInventoryReply = true;
                        break;
                    }
                }
                if (!$isNotInInventoryReply) {
                    $escalationSignals = [
                        'poddak inna', 'katha karai', 'katha karannam', 'katha karavi',
                        'contact karai', 'reach you', 'get back to you', 'someone will contact',
                        'team member', 'obata katha', 'ape kenek',
                    ];
                    foreach ($escalationSignals as $signal) {
                        if (str_contains($lower, $signal)) {
                            $this->escalateToAdmin($phone, 'Customer needs human help (auto-detected from reply)');
                            break;
                        }
                    }
                }
            }

            // Send reply — save BEFORE sending so from_me event dedup finds it already saved
            if (!empty($finalReply)) {
                $this->saveChatHistory($phone, 'assistant', $finalReply);
                $this->sendWhatsApp(
                    $phone,
                    $this->user->target_api_key,
                    $this->payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null,
                    $finalReply
                );

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

    $p  = "You are a friendly, helpful WhatsApp sales assistant for '{$companyName}'.\n";
    $p .= "About: {$companyDetails}\n\n";

    if ($invCtx) {
        $p .= $invCtx . "\n";
        $p .= "━━━ PRODUCT MATCHING (CRITICAL) ━━━\n";
        $p .= "• The inventory table above is the ONLY source of truth for products.\n";
        $p .= "• Before answering ANY price or stock question, ALWAYS call search_inventory with the exact item name from the table. Never use the table values directly — they may be stale.\n";
        $p .= "• If an item shows [OUT OF STOCK] in the table, do NOT offer it. Smoothly redirect to what IS available.\n";
        $p .= "• If the customer asks for a specific item that is NOT in the inventory (or no match found): simply say we don't have it — e.g. 'Laptop nam dan api laga nathi athi sir 🙏' or 'Api laga na sir' — short and polite. Do NOT list other items. Do NOT promise anyone will contact them. Do NOT say 'ape kenek katha karai' or 'sambanda karagani'. No escalation, no notification.\n";
        $p .= "• If the customer's word does NOT clearly match any inventory item name, ASK them to clarify. Never guess or rename.\n";
        $p .= "• ITEM NOT IN INVENTORY — Item simply not stocked: say 'api laga na sir' or '[Item] nam dan api laga nathi athi sir 🙏' — keep it short. NEVER list other products. NEVER say 'ape kenek katha karai', 'api team eke kenek', 'sambanda karagani', or anything that implies someone will contact. No second-number notification.\n";
        $p .= "• STOCK QUANTITY — Never mention batch dates or batch codes to the customer. Only say how much stock is available when the customer asks for a specific quantity — and only to tell them whether it can be fulfilled or how much IS available so they can decide. Never volunteer stock numbers otherwise.\n";
        $p .= "• QUANTITY NOT AVAILABLE — If the requested quantity exceeds available stock: call notify_stock_alert FIRST (silent). Then your reply MUST: (1) ask WHEN they need it, (2) say OUR PERSON WILL CONTACT THEM. Exact format: '[Item] [qty]kg kawadata gannada sir? Poddak inna, ape kenek obava ikmanin sambanda karagani 😊' — do NOT say 'api laga na sir' (the item IS in stock, just not that quantity). NEVER say 'sadaha apata ekka sambandha karanna'. NEVER reveal how much stock is available.\n";
        $p .= "• MULTIPLE PRICE BATCHES — If the same item has multiple price rows: always quote the LOWEST price first. If quantity spans both batches, explain simply without mentioning dates: e.g. '[X]kg Rs.300 ge denna puluwa, ethanin vadi gennavnam aluth stock eke Rs.350 ge — combine wenava. Mokakda one?'\n";
        $p .= "• When customer asks broadly what's available (e.g. 'monava thiyenva?', 'what do you have?', 'amak thiyenvada?') — pick 2 or 3 IN-STOCK items from the inventory table (skip [OUT OF STOCK]) and mention them naturally. Do NOT call search_inventory for this.\n";
        $p .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }

    $p .= "━━━ CONVERSATION CONTEXT ━━━\n";
    $p .= "• You have this customer's chat history (last 3 days, max 20 messages).\n";
    $p .= "• If the customer's new message is part of the SAME ongoing conversation (same item, same order, continuing discussion), use the history for context.\n";
    $p .= "• If the new message is clearly a NEW topic or unrelated to recent history, treat it as a fresh conversation — do not bring up old details.\n";
    $p .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    $p .= "━━━ RESPONSE STYLE ━━━\n";
    $p .= "• Language: detect the customer's language from their message and reply in that SAME language. Sinhala → Sinhala. English → English. Never mix.\n";
    $p .= "• Tone: warm, natural, like a real Sri Lankan shopkeeper chatting on WhatsApp. Sound like a person — not a form or a script.\n";
    $p .= "• Length: 1 to 3 sentences max. Short and conversational. Never write a paragraph.\n";
    $p .= "• Format: plain text ONLY. No markdown, no asterisks (*), no dashes (-) for lists, no bold. Emojis are fine 😊 but don't overuse them.\n";
    $p .= "• Vary your replies — don't always start with 'Ow' or use the same sentence structure every time. Sound natural and alive.\n";
    $p .= "• When confirming a product is available and the customer has NOT stated a quantity yet: weave the price naturally and ask how many — e.g. 'Paan Piti thiyenava 😊 Rs. 260 ge. Kopamana one?' — not a stiff list.\n";
    $p .= "• When the customer's message ALREADY includes a quantity (e.g. 'onion 50kg', '9k denna', 'Sugar 5kg one'): confirm the price and ask for the delivery address IMMEDIATELY — do NOT ask 'kopamana one?' or 'kite gannada?' — you already have item + quantity. e.g. 'Ow sir, Onion 50kg Rs. 150 ge denna puluwan 😊 Deliver address eka denna puluvanada?'\n";
    $p .= "• Never mention 'AI', 'bot', 'system', 'database', 'inventory', or any technical terms.\n";
    $p .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

    $p .= "━━━ ORDER FLOW ━━━\n";
    $p .= "• Collect: item name + quantity + delivery address.\n";
    $p .= "• If the customer states a quantity (e.g. '9kg', 'ehenam 9k denna'), you already have item + quantity — DO NOT ask 'kopamana ganna oni?' again. Confirm price and immediately ask for the delivery address: e.g. 'Ow sir, Sugar 9kg Rs. 300 ge denna puluwan 😊 Deliver address eka denna puluvanada?'\n";
    $p .= "• Multiple price batches for the same item: ask which batch before quoting.\n";
    $p .= "• Bill (plain text):\n";
    $p .= "  🛒 Bill:\n";
    $p .= "  [item] [qty]kg x Rs.[price] = Rs.[total]\n";
    $p .= "  Total: Rs.[grand total]\n";
    $p .= "  Address: [address]\n";
    $p .= "  Confirm karannada? 😊\n";
    $p .= "• Call confirm_order ONLY after: customer says YES/OK/hari AND address is provided.\n";
    $p .= "• If customer asks about past orders, call get_order_history.\n";
    $p .= "━━━━━━━━━━━━━━━━━\n\n";

    $p .= "━━━ ESCALATION ━━━\n";
    $p .= "RULE: Call escalate_to_admin ONLY when the customer:\n";
    $p .= "  - Explicitly asks for a phone call or to speak to a real person\n";
    $p .= "  - Wants to return a product or order\n";
    $p .= "  - Requests credit, payment delay, or to pay later\n";
    $p .= "  - Is frustrated, complaining, or expresses strong dissatisfaction\n";
    $p .= "DO NOT escalate for: items not in inventory (just say nathi athi sir), quantity > stock (use notify_stock_alert instead), pricing questions, or normal product inquiries.\n";
    $p .= "• The escalate_to_admin call MUST happen BEFORE writing any reply in those cases — do NOT say 'poddak inna' or 'ape kenek katha karai' unless escalate_to_admin was called in this same turn.\n";
    $p .= "• After escalate_to_admin succeeds, tell the customer warmly in their language that someone will contact them shortly.\n";
    $p .= "━━━━━━━━━━━━━━━━━\n";

    if ($isNewCustomer) {
        if ($greeting) {
            $p .= "\nFIRST MESSAGE: This is the customer's very first message. Begin your reply with: \"{$greeting}\" — then answer their question.\n";
        } else {
            $p .= "\nFIRST MESSAGE: This is the customer's very first message. Start with a short warm welcome that naturally includes the shop name '{$companyName}' and invites them to share what they need. Write it like a real person would greet a new WhatsApp customer — one or two sentences, friendly, in the customer's language. Then answer their question.\n";
        }
    }

    return $p;
}
    private function buildInventoryTable(array $inventory): string
    {
        if (empty($inventory)) {
            return '';
        }

        $out  = "=== CURRENT SHOP INVENTORY ===\n";
        $out .= "Item Name | Price (Rs) | Stock | Batch\n";
        $out .= "--------- | ---------- | ----- | -----\n";

        foreach ($inventory as $row) {
            $name  = trim($row['item name'] ?? $row['name'] ?? '');
            $price = $row['price'] ?? '';
            $qty   = $row['stock qty'] ?? $row['qty'] ?? $row['stock'] ?? '';
            $date  = $row['batch date'] ?? $row['batch'] ?? '';

            if (empty($name)) continue;

            $stockLabel = '';
            if ($qty !== '' && is_numeric($qty) && (float)$qty <= 0) {
                $stockLabel = '[OUT OF STOCK]';
            } else {
                $stockLabel = $qty;
            }

            $out .= "{$name} | {$price} | {$stockLabel} | {$date}\n";
        }

        $out .= "==============================\n";
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

        // Save to Contacts — wa_id = WhatsApp chat ID (@lid/@c.us), phone = real digits
        $waId      = $phone;                                       // WhatsApp chat ID (e.g. "34445839093921@lid")
        $rawPhone  = $this->msg['real_phone'] ?? $phone;
        $realPhone = preg_replace('/@.*$/', '', $rawPhone);        // strip @c.us / @lid
        $realPhone = preg_replace('/[^0-9]/', '', $realPhone);     // digits only
        if (strlen($realPhone) === 10 && str_starts_with($realPhone, '0')) {
            $realPhone = '94' . substr($realPhone, 1);             // 07X → 94X
        }
        if (strlen($realPhone) >= 10 && strlen($realPhone) <= 15) {
            \App\Models\Contact::updateOrCreate(
                ['user_id' => $this->user->id, 'wa_id' => $waId],
                ['phone' => $realPhone, 'last_messaged_at' => now(), 'updated_at' => now()]
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
                return [];
            }

            $client  = $this->getGoogleClient();
            $sheetId = $this->getSheetId($client, $this->user->google_sheet_name);
            if (!$sheetId) {
                return [];
            }

            $sheets   = new GoogleSheets($client);
            $response = $sheets->spreadsheets_values->get($sheetId, 'Inventory!A:Z');
            $values   = $response->getValues();

            if (empty($values) || count($values) < 2) {
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

            if (!$response->successful()) {
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

        Log::error("SHEET_NOT_FOUND: '{$name}' — check service account permissions.");
        return null;
    }

    private function notifyStockAlert(string $customerPhone, string $itemName, float $requestedQty, float $_availableQty = 0): void
    {
        try {
            $user        = $this->user->fresh();
            $notifyPhone = $user->private_phone;
            if (!$notifyPhone) return;

            $notifyPhone = preg_replace('/[^0-9]/', '', $notifyPhone);
            if (strlen($notifyPhone) === 10 && str_starts_with($notifyPhone, '0')) {
                $notifyPhone = '94' . substr($notifyPhone, 1);
            }

            $cleanPhone = preg_replace('/@.*$/', '', $customerPhone);
            $cleanPhone = preg_replace('/[^0-9]/', '', $cleanPhone);

            $contact = \App\Models\Contact::where('user_id', $user->id)
                ->where(function ($q) use ($cleanPhone, $customerPhone) {
                    $q->where('phone', $cleanPhone)
                      ->orWhere('wa_id', $customerPhone)
                      ->orWhere('wa_id', $cleanPhone);
                })->first();

            $displayPhone = $cleanPhone;
            if ($contact && $contact->phone && strlen($contact->phone) >= 10) {
                $p = $contact->phone;
                $displayPhone = (str_starts_with($p, '94') && strlen($p) === 11) ? '0' . substr($p, 2) : $p;
            } elseif (str_starts_with($cleanPhone, '94') && strlen($cleanPhone) === 11) {
                $displayPhone = '0' . substr($cleanPhone, 2);
            }

            $nameLine = $contact?->name ? "👤 {$contact->name}\n" : '';

            $msg = "📦 Bulk Request\n"
                . $nameLine
                . "📱 {$displayPhone}\n"
                . "🛒 {$itemName} — {$requestedQty}kg needed\n"
                . "⚡ Contact to arrange manually";

            Http::withHeaders([
                'x-api-key'    => config('services.node_bridge.secret_key'),
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(config('services.node_bridge.url') . '/send-message', [
                'user_id' => $user->id,
                'phone'   => $notifyPhone,
                'message' => $msg,
            ]);

        } catch (\Throwable $e) {
            Log::error("STOCK_ALERT_FAIL: " . $e->getMessage());
        }
    }

    private function notifyNewOrder(string $customerPhone, array $orderData): void
    {
        try {
            $user        = $this->user->fresh();
            $notifyPhone = $user->private_phone;
            if (!$notifyPhone) return;

            $notifyPhone = preg_replace('/[^0-9]/', '', $notifyPhone);
            if (strlen($notifyPhone) === 10 && str_starts_with($notifyPhone, '0')) {
                $notifyPhone = '94' . substr($notifyPhone, 1);
            }

            $cleanPhone = preg_replace('/@.*$/', '', $customerPhone);
            $cleanPhone = preg_replace('/[^0-9]/', '', $cleanPhone);

            $contact = \App\Models\Contact::where('user_id', $user->id)
                ->where(function ($q) use ($cleanPhone, $customerPhone) {
                    $q->where('phone', $cleanPhone)
                      ->orWhere('wa_id', $customerPhone)
                      ->orWhere('wa_id', $cleanPhone);
                })->first();

            $displayPhone = $cleanPhone;
            if ($contact && $contact->phone && strlen($contact->phone) >= 10) {
                $p = $contact->phone;
                $displayPhone = (str_starts_with($p, '94') && strlen($p) === 11) ? '0' . substr($p, 2) : $p;
            } elseif (str_starts_with($cleanPhone, '94') && strlen($cleanPhone) === 11) {
                $displayPhone = '0' . substr($cleanPhone, 2);
            }

            $nameLine  = $contact?->name ? "👤 {$contact->name}\n" : '';
            $itemLines = '';
            $total     = 0;
            foreach ($orderData['items'] ?? [] as $item) {
                $itemLines .= "• {$item['name']} {$item['quantity']}kg — Rs.{$item['total_price']}\n";
                $total     += (float) ($item['total_price'] ?? 0);
            }

            $address = $orderData['address'] ?? 'N/A';

            $msg = "📋 New Order!\n"
                . $nameLine
                . "📱 {$displayPhone}\n"
                . $itemLines
                . "💰 Total: Rs.{$total}\n"
                . "📍 {$address}";

            Http::withHeaders([
                'x-api-key'    => config('services.node_bridge.secret_key'),
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(config('services.node_bridge.url') . '/send-message', [
                'user_id' => $user->id,
                'phone'   => $notifyPhone,
                'message' => $msg,
            ]);

        } catch (\Throwable $e) {
            Log::error("ORDER_NOTIFY_FAIL: " . $e->getMessage());
        }
    }

    private function escalateToAdmin(string $customerPhone, string $reason): void
    {
        try {
            $user        = $this->user->fresh();
            $notifyPhone = $user->private_phone;
            $nodeBridgeUrl = config('services.node_bridge.url');
            $apiKey        = config('services.node_bridge.secret_key');

            if (!$notifyPhone) {
                return;
            }

            // Normalize owner number: 077... → 9477...
            $notifyPhone = preg_replace('/[^0-9]/', '', $notifyPhone);
            if (strlen($notifyPhone) === 10 && str_starts_with($notifyPhone, '0')) {
                $notifyPhone = '94' . substr($notifyPhone, 1);
            }

            // Clean customer phone to digits only
            $cleanPhone = preg_replace('/@.*$/', '', $customerPhone);
            $cleanPhone = preg_replace('/[^0-9]/', '', $cleanPhone);

            // Reformat as readable: 94771234567 → 0771234567
            $displayPhone = $cleanPhone;
            if (str_starts_with($cleanPhone, '94') && strlen($cleanPhone) === 11) {
                $displayPhone = '0' . substr($cleanPhone, 2);
            }

            // Look up contact — match by real phone, LID digits, or original wa_id
            $contact = \App\Models\Contact::where('user_id', $user->id)
                ->where(function ($q) use ($cleanPhone, $customerPhone) {
                    $q->where('phone', $cleanPhone)
                      ->orWhere('wa_id', $customerPhone)
                      ->orWhere('wa_id', $cleanPhone);
                })->first();
            $customerName = $contact?->name ?? null;

            // Prefer the real resolved phone from contact record if available
            if ($contact && $contact->phone && strlen($contact->phone) >= 10) {
                $realContactPhone = $contact->phone;
                $displayPhone = (str_starts_with($realContactPhone, '94') && strlen($realContactPhone) === 11)
                    ? '0' . substr($realContactPhone, 2)
                    : $realContactPhone;
            }

            $nameLine = $customerName ? "👤 {$customerName}\n" : '';

            $msg = "📞 Customer needs help\n"
                . $nameLine
                . "📱 {$displayPhone}\n"
                . "❓ {$reason}";

            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$nodeBridgeUrl}/send-message", [
                'user_id' => $user->id,
                'phone'   => $notifyPhone,
                'message' => $msg,
            ]);

            if (!$response->successful()) {
                Log::error("ESCALATE_FAIL: status={$response->status()} body=" . $response->body()
                    . " user_id={$user->id} to={$notifyPhone}");
            }
        } catch (\Throwable $e) {
            Log::error("ESCALATE_EXCEPTION: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    private function checkAndNotifyLowBalance(): void
    {
        $user = $this->user->fresh();

        if ($user->balance <= 0) return;

        // Target: user's private_phone → fallback to whatsapp_number
        $notifyPhone = $user->private_phone ?: $user->whatsapp_number;
        if (!$notifyPhone) return;

        // Normalize 077... → 9477...
        $notifyPhone = preg_replace('/[^0-9]/', '', $notifyPhone);
        if (strlen($notifyPhone) === 10 && str_starts_with($notifyPhone, '0')) {
            $notifyPhone = '94' . substr($notifyPhone, 1);
        }

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
        } catch (\Throwable $e) {
            Log::error("Balance notification failed: " . $e->getMessage());
        }
    }
}
