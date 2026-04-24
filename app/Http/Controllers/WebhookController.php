<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\ProcessWhatsAppAiJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * ✅ Option A: Meta Cloud API Webhook (දැනටමත් තියෙන ක්‍රමය)
     * Meta එකෙන් එන messages handle කරනවා
     */
    public function processIncomingWebhook(Request $request)
    {
        set_time_limit(120);
        $payload = $request->all();
        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');

        if (!$phoneNumberId) {
            return response()->json(['status' => 'no_phone_id'], 200);
        }

        $user = User::where('whatsapp_phone_number_id', $phoneNumberId)->first();

        if (!$user) {
            Log::warning('ලියාපදිංචි නැති Phone ID එකක්:', ['phone_id' => $phoneNumberId]);
            return response()->json(['error' => 'Unauthorized Phone ID'], 200);
        }

        if ($user->balance <= 0) {
            Log::warning('Insufficient LKR balance:', ['user_id' => $user->id, 'balance' => $user->balance]);
            return response()->json(['status' => 'insufficient_balance'], 200);
        }

        try {
            $msg = data_get($payload, 'entry.0.changes.0.value.messages.0');
            if ($msg) {
                ProcessWhatsAppAiJob::dispatch($payload, $msg, $user);
                Log::info('Dispatched ProcessWhatsAppAiJob for phone_id:', ['phone_id' => $phoneNumberId]);
            }
            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Native Queue Dispatch Error:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * ✅ Option B: whatsapp-web.js Automation Webhook
     * Node.js Bridge එකෙන් එන messages handle කරනවා
     */
    public function processAutomationWebhook(Request $request)
    {
        set_time_limit(120);
        
        $payload = $request->all();
        
        $userId = $payload['user_id'] ?? null;
        $from = $payload['from'] ?? null;
        $body = $payload['body'] ?? '';
        $msgType = $payload['type'] ?? 'text';

        if (!$userId || !$from) {
            Log::warning('Automation webhook: Missing user_id or from');
            return response()->json(['error' => 'Missing required fields'], 400);
        }

        // If the message was sent by the owner from their phone, just save it to chat history and stop.
        if (!empty($payload['from_me']) && $payload['from_me'] == true) {
            $user = User::find($userId);
            if ($user && $body) {
                $chat = \App\Models\ChatHistory::create([
                    'user_id' => $user->id,
                    'phone' => $from,
                    'role' => 'assistant',
                    'content' => $body,
                    'timestamp' => now()
                ]);
                broadcast(new \App\Events\MessageReceived($chat));
            }
            return response()->json(['status' => 'saved'], 200);
        }

        $user = User::find($userId);
        if (!$user) {
            Log::warning('Automation webhook: User not found', ['user_id' => $userId]);
            return response()->json(['error' => 'User not found'], 404);
        }

        if ($user->balance <= 0) {
            Log::warning('Insufficient LKR balance:', ['user_id' => $user->id, 'balance' => $user->balance]);
            return response()->json(['status' => 'insufficient_balance'], 200);
        }

        // Node.js payload එක Job එකට ගැලපෙන format එකට හරවනවා
        $formattedPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => 'automation_' . $user->id,
                                ],
                                'messages' => [
                                    [
                                        'from' => $from,
                                        'id' => 'auto_' . time() . '_' . $from,
                                        'type' => $msgType,
                                        'text' => [
                                            'body' => $body,
                                        ],
                                        'timestamp' => $payload['timestamp'] ?? time(),
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $formattedMsg = $formattedPayload['entry'][0]['changes'][0]['value']['messages'][0];

        try {
            ProcessWhatsAppAiJob::dispatch($formattedPayload, $formattedMsg, $user);
            Log::info('Dispatched from automation webhook:', [
                'user_id' => $userId,
                'from' => $from
            ]);
            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Automation webhook error:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Webhook Verification (Meta Webhook Setup සඳහා)
     */
    public function verifyWebhook(Request $request)
    {
        $verifyToken = env('WEBHOOK_VERIFY_TOKEN', 'my_secret_token_123');

        if ($request->query('hub_mode') == 'subscribe' && $request->query('hub_verify_token') == $verifyToken) {
            return response($request->query('hub_challenge'));
        }

        return response('Invalid token', 403);
    }

    /**
     * Credit Deduction Callback
     */
    public function deductCredits(Request $request)
    {
        $apiKey = env('PYTHON_SERVICE_API_KEY', 'SuperBridge#99!Admin');
        if ($request->header('x-api-key') !== $apiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = \App\Models\User::find($request->input('user_id'));
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $autoReplyCreditsUsed = (float) $request->input('autoreply_credits_used', 0);
        $orderCreditsUsed     = (int)   $request->input('order_credits_used', 0);

        if ($autoReplyCreditsUsed > 0) {
            $user->autoreply_credits -= $autoReplyCreditsUsed;
        }

        if ($orderCreditsUsed > 0) {
            $user->credits = max(0, $user->credits - $orderCreditsUsed); 
        }

        $user->save();

        Log::info('Credits deducted via Python callback', [
            'user_id'       => $user->id,
            'ai_credits'    => $autoReplyCreditsUsed,
            'order_credits' => $orderCreditsUsed,
        ]);

        return response()->json(['status' => 'ok']);
    }
}




