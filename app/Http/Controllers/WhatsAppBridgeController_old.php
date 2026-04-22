<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        // Python සර්වර් එකට යවන දත්ත
        $pythonPayload = [
            "object" => "whatsapp_business_account",
            "entry" => $request->input('entry'),
            "user_settings" => [
                "mode" => $user->target_mode,
                "sheet_name" => $user->google_sheet_name,
                "order_api_url" => $user->order_api_url,
                "inventory_api_url" => $user->inventory_api_url,
                "whatsapp_token" => $user->target_api_key,
                "autoreply_message" => $user->autoreply_message,
                "is_autoreply_enabled" => $user->is_autoreply_enabled,
                "autoreply_credits" => $user->autoreply_credits,
                "user_id" => $user->id
            ]
        ];

        try {
            $pythonUrl = env('PYTHON_SERVICE_URL', 'http://127.0.0.1:8000/v1/whatsapp/webhook');
            $pythonApiKey = env('PYTHON_SERVICE_API_KEY', 'SuperBridge#99!Admin');
            
            Log::info('Python API ඇමතුම ආරම්භ විය...', ['url' => $pythonUrl]);

            $response = Http::withHeaders(['x-api-key' => $pythonApiKey])
                             ->connectTimeout(10) // සර්වර් එකට සම්බන්ධ වීමට උපරිම තත්පර 10යි
                            ->timeout(100) 
                            ->post($pythonUrl, $pythonPayload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['credits_used']) && $responseData['credits_used'] > 0) {
                    $user->autoreply_credits -= $responseData['credits_used'];
                }
                
                if (isset($responseData['extracted_data']) && !empty($responseData['extracted_data']['items'])) {
                    $user->decrement('credits'); // Flat 1 credit for an order success
                }
                
                $newOrder = new \App\Models\Order();
    $newOrder->user_id = $user->id;
    
    // WhatsApp එකෙන් එන sender ගේ අංකය ගැනීම
    $newOrder->customer_phone = $request->input('entry.0.changes.0.value.messages.0.from') ?? 'Unknown';
    
    // WhatsApp මැසේජ් එකේ body එක ගැනීම
    $newOrder->order_details = $request->input('entry.0.changes.0.value.messages.0.text.body') ?? 'No Details';
    
    $newOrder->status = 'processed';
    
    // Database එකට සේව් කිරීම
    $newOrder->save();
                return response()->json(['status' => 'success', 'processed_for' => $user->name]);
            }
        } catch (\Exception $e) {
            Log::error('Python Connection Failed: ' . $e->getMessage());
            return response()->json(['error' => 'Python Service Connection Failed'], 500);
        }

        return response()->json(['error' => 'Python Service Error'], 500);
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