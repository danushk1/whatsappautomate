<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Jobs\ProcessWhatsAppAiJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppBridgeController extends Controller
{
public function handleWebhook(Request $request)
    {
        // Meta JSON එකෙන් Phone Number ID එක ගැනීම
        $incomingPhoneId = $request->input('entry.0.changes.0.value.metadata.phone_number_id');

        if (!$incomingPhoneId) {
            Log::warning('Webhook received without Phone Number ID.');
            return response()->json(['error' => 'Phone Number ID missing.'], 400);
        }

$user = User::where('whatsapp_phone_number_id', '=', trim($incomingPhoneId))->first();
        if (!$user) {
            Log::warning('Unauthorized Phone ID: ' . $incomingPhoneId);
            return response()->json(['error' => 'This WhatsApp ID is not registered.'], 404);
        }

        // Credits පරීක්ෂාව
        if ($user->credits <= 0 && $user->autoreply_credits <= 0) {
            return response()->json(['error' => 'Insufficient credits'], 402);
        }

        // 4. Native Queue Dispatch
        try {
            $msg = data_get($request->input(), 'entry.0.changes.0.value.messages.0');
            if ($msg) {
                ProcessWhatsAppAiJob::dispatch($request->input(), $msg, $user);
                Log::info('Dispatched native ProcessWhatsAppAiJob for phone_id:', ['phone_id' => $incomingPhoneId]);
            }

            $newOrder = new \App\Models\Order();
            $newOrder->user_id = $user->id;
            $newOrder->customer_phone = $msg['from'] ?? 'Unknown';
            $msgType = $msg['type'] ?? 'unknown';
            $newOrder->order_details = $msgType === 'text' ? ($msg['text']['body'] ?? 'No Details') : "Received {$msgType} message";
            $newOrder->status = 'processed';
            $newOrder->save();

            return response()->json(['status' => 'success', 'processed_for' => $user->name]);
        } catch (\Exception $e) {
            Log::error('Native Queue Dispatch Failed: ' . $e->getMessage());
            return response()->json(['error' => 'Job Dispatch Failed'], 500);
        }
    }

    // 2. Meta Verification (Webhook එක මුලින්ම setup කරන විට අවශ්‍ය වේ)
    public function verify(Request $request)
    {
        $verifyToken = env('WEBHOOK_VERIFY_TOKEN', 'my_secret_token_123'); 

        if ($request->query('hub_mode') == 'subscribe' && 
            $request->query('hub_verify_token') == $verifyToken) {
            return response($request->query('hub_challenge'), 200);
        }

        return response('Invalid token', 403);
    }
}