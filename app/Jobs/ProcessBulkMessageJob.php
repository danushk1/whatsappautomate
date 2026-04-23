<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessBulkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // Disable timeout as this could take a long time

    protected $user;
    protected $contacts;
    protected $message;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, array $contacts, string $message)
    {
        $this->user = $user;
        $this->contacts = $contacts;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting Bulk Broadcast for User ID: {$this->user->id}");

        foreach ($this->contacts as $phone) {
            try {
                if ($this->user->connection_type === 'web_automation') {
                    $this->sendViaNodeBridge($phone, $this->message);
                } else {
                    $this->sendViaCloudApi($phone, $this->message);
                }
                
                // Add a small delay to avoid rate limiting
                sleep(2);
            } catch (\Exception $e) {
                Log::error("Failed to send bulk message to {$phone}: " . $e->getMessage());
            }
        }

        Log::info("Finished Bulk Broadcast for User ID: {$this->user->id}");
    }

    private function sendViaNodeBridge($phone, $text)
    {
        $nodeBridgeUrl = env('NODE_BRIDGE_URL', 'http://127.0.0.1:3000');
        $apiKey = env('NODE_BRIDGE_SECRET_KEY', 'genify-node-bridge-secret-2026');

        Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$nodeBridgeUrl}/send-message", [
            'user_id' => $this->user->id,
            'phone' => $phone,
            'message' => $text,
        ]);
    }

    private function sendViaCloudApi($phone, $text)
    {
        $phoneId = $this->user->whatsapp_phone_number_id;
        $token = $this->user->target_api_key;
        
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
