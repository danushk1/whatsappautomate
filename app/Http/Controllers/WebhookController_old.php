<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class WebhookController extends Controller
{
    /**
     * Meta (WhatsApp) Webhook එක භාරගෙන අදාළ සමාගම හඳුනාගෙන Python වෙත යැවීම.
     */
    public function processIncomingWebhook(Request $request)
    {set_time_limit(120); // තත්පර 120ක් දක්වා කාලය වැඩි කිරීම
        // 1. Meta එවූ මුළු JSON දත්තය ලබා ගැනීම
        $payload = $request->all();

        // 2. මැසේජ් එක ආපු Phone Number ID එක සොයා ගැනීම
        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');

        if (!$phoneNumberId) {
            return response()->json(['status' => 'no_phone_id'], 200);
        }

        // 3. Database එකේ මෙම Phone ID එක ඇති පරිශීලකයා (Company) සෙවීම
        $user = User::where('whatsapp_phone_number_id', $phoneNumberId)->first();

        if (!$user) {
            Log::warning('ලියාපදිංචි නැති Phone ID එකක්:', ['phone_id' => $phoneNumberId]);
            return response()->json(['error' => 'Unauthorized Phone ID'], 200);
        }

        // 4. පරිශීලකයාට ප්‍රමාණවත් Credits තිබේදැයි බැලීම
        if ($user->credits <= 0 && $user->autoreply_credits <= 0) {
            Log::warning('Credits ඉවරයි:', ['user_id' => $user->id]);
            return response()->json(['status' => 'out_of_credits'], 200);
        }

        // 5. Python FastAPI වෙත දත්ත යැවීම
        try {
            // Python එකේ GATEWAY_API_KEY එක මෙතනට දාන්න
            $pythonUrl = env('PYTHON_SERVICE_URL', 'http://127.0.0.1:8000/v1/whatsapp/webhook');
            $pythonApiKey = env('PYTHON_SERVICE_API_KEY', 'SuperBridge#99!Admin');
            // Python Code එකේ බලාපොරොත්තු වන්නේ 'user_settings' කියන නමයි
            $payload['user_settings'] = [
                'mode'       => $user->target_mode ?? 'EXCEL', // EXCEL හෝ API
                'sheet_name' => $user->google_sheet_name,
                'order_api_url' => $user->order_api_url,
                'inventory_api_url' => $user->inventory_api_url,
                'user_id'    => $user->id,
                'whatsapp_token' => $user->target_api_key,     // Use target_api_key for Meta token
                'autoreply_message' => $user->autoreply_message,
                'is_autoreply_enabled' => $user->is_autoreply_enabled,
                'autoreply_credits' => $user->autoreply_credits
            ];

            Log::info('Python සර්වර් එකට දත්ත යවමින් පවතී...', ['url' => $pythonUrl]);

            $response = Http::withHeaders([
                    'x-api-key' => $pythonApiKey, // Python Authentication එක සඳහා
                    'Accept'    => 'application/json',
                ])
            ->connectTimeout(10) // සර්වර් එකට සම්බන්ධ වීමට උපරිම තත්පර 10යි
                ->timeout(15)       // 15s — Python returns immediately now (BackgroundTasks), no need to wait longer
                ->post($pythonUrl, $payload);

            if ($response->failed()) {
                Log::error('Python Service Error:', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            } else {
                $responseData = $response->json();
                
                // Deduct AI credits
                if (isset($responseData['credits_used']) && $responseData['credits_used'] > 0) {
                    $user->autoreply_credits -= $responseData['credits_used'];
                    $user->save();
                    Log::info('Deducted AI credits exact amount:', ['amount' => $responseData['credits_used'], 'user_id' => $user->id]);
                }

                // If an order was extracted, deduct an order credit
                if (isset($responseData['extracted_data']) && !empty($responseData['extracted_data']['items'])) {
                    $user->decrement('credits'); // Flat 1 credit for an order success
                    Log::info('Deducted Order credit (1)', ['user_id' => $user->id]);
                }
            }

            return response()->json(['status' => 'success'], 200);
            
        } catch (ConnectionException $e) {
            Log::error('Python සර්වර් එක Offline:', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'python_offline'], 200);
        }
    }

    /**
     * Webhook Verification
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
     * Credit Deduction Callback — called by Python after background AI processing.
     * Python sends: { user_id, autoreply_credits_used, order_extracted }
     */
    public function deductCredits(Request $request)
    {
        // Validate internal API key (same key Python uses)
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




