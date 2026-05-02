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
            return response()->json(['error' => 'Unauthorized Phone ID'], 200);
        }

        if ($user->balance <= 0) {
            return response()->json(['status' => 'insufficient_balance'], 200);
        }

        try {
            $msg = data_get($payload, 'entry.0.changes.0.value.messages.0');
            if ($msg) {
                ProcessWhatsAppAiJob::dispatch($payload, $msg, $user);
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
            return response()->json(['error' => 'Missing required fields'], 400);
        }

        // Ignore WhatsApp status broadcasts (stories/status updates)
        if ($from === 'status@broadcast' || str_contains($from, 'status@broadcast')) {
            return response()->json(['status' => 'ignored_broadcast'], 200);
        }

        // If the message was sent by the owner from their phone, just save it to chat history and stop.
        if (!empty($payload['from_me']) && $payload['from_me'] == true) {
            // Skip bot-generated alerts: ⚠️ balance, 📞 escalation, 📦 stock, 📋 order notifications
            if (!str_starts_with($body, '⚠️') && !str_starts_with($body, '📞') && !str_starts_with($body, '📦') && !str_starts_with($body, '📋')) {
                $user = User::find($userId);
                if ($user && $body) {
                    // Deduplicate: job already saves bot replies — skip if same message saved in last 30s
                    $alreadySaved = \App\Models\ChatHistory::where('user_id', $user->id)
                        ->where('phone', $from)
                        ->where('role', 'assistant')
                        ->where('content', $body)
                        ->where('timestamp', '>', now()->subSeconds(30))
                        ->exists();

                    if (!$alreadySaved) {
                        $chat = \App\Models\ChatHistory::create([
                            'user_id'   => $user->id,
                            'phone'     => $from,
                            'role'      => 'assistant',
                            'content'   => $body,
                            'timestamp' => now(),
                        ]);
                        broadcast(new \App\Events\MessageReceived($chat));
                    }
                }
            }
            return response()->json(['status' => 'saved'], 200);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // If admin's WhatsApp received this message → store as admin inbox message
        if ($user->is_admin) {
            $fromClean = preg_replace('/[^0-9]/', '', $from);
            $matched   = User::where('private_phone', $fromClean)
                ->orWhereRaw("REGEXP_REPLACE(private_phone, '[^0-9]', '') = ?", [$fromClean])
                ->first();

            \App\Models\AdminMessage::create([
                'from_number' => $from,
                'user_id'     => $matched?->id,
                'message'     => $body,
                'is_read'     => false,
                'received_at' => now(),
                'expires_at'  => now()->addDays(7),
            ]);

            return response()->json(['status' => 'stored_as_admin_message'], 200);
        }

        if ($user->balance <= 0) {
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
        $formattedMsg['real_phone'] = $payload['real_phone'] ?? $from;

        try {
            ProcessWhatsAppAiJob::dispatch($formattedPayload, $formattedMsg, $user);
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
     * Resolve real phone number from WhatsApp contact.getContact()
     * Called by Node.js bridge to fix LID-format contacts.
     */
    public function resolveContact(Request $request)
    {
        $userId    = $request->input('user_id');
        $waId      = $request->input('wa_id');
        $realPhone = preg_replace('/[^0-9]/', '', $request->input('real_phone', ''));

        if (!$userId || !$realPhone) {
            return response()->json(['status' => 'skip']);
        }

        if (strlen($realPhone) === 10 && str_starts_with($realPhone, '0')) {
            $realPhone = '94' . substr($realPhone, 1);
        }

        $rawId = preg_replace('/[^0-9]/', '', $waId ?? '');

        \App\Models\Contact::where('user_id', $userId)
            ->where(function ($q) use ($waId, $rawId) {
                $q->where('wa_id', $waId)->orWhere('phone', $rawId);
            })
            ->update(['phone' => $realPhone]);

        return response()->json(['status' => 'ok']);
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

        return response()->json(['status' => 'ok']);
    }
}




