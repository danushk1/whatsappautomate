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
use Illuminate\Support\Facades\Storage;

class ProcessBulkMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    protected $user;
    protected $contacts;
    protected $message;
    protected $imageUrl;

    public function __construct(User $user, array $contacts, string $message, ?string $imageUrl = null)
    {
        $this->user     = $user;
        $this->contacts = $contacts;
        $this->message  = $message;
        $this->imageUrl = $imageUrl;
    }

    public function handle(): void
    {
        Log::info("Starting Bulk Broadcast for User ID: {$this->user->id}", [
            'total'     => count($this->contacts),
            'has_image' => !empty($this->imageUrl),
        ]);

        foreach ($this->contacts as $phone) {
            try {
                if ($this->user->connection_type === 'web_automation') {
                    $this->sendViaNodeBridge($phone, $this->message, $this->imageUrl);
                } else {
                    $this->sendViaCloudApi($phone, $this->message, $this->imageUrl);
                }

                sleep(2); // Avoid WhatsApp rate limiting
            } catch (\Exception $e) {
                Log::error("Bulk message failed for {$phone}: " . $e->getMessage());
            }
        }

        Log::info("Finished Bulk Broadcast for User ID: {$this->user->id}");

        // Delete image from storage after all messages sent
        if ($this->imageUrl) {
            $path = str_replace(url('storage') . '/', '', $this->imageUrl);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                Log::info("Bulk image deleted: {$path}");
            }
        }

        $this->checkAndNotifyLowBalance();
    }

    private function checkAndNotifyLowBalance(): void
    {
        $user = $this->user->fresh();

        if ($user->balance <= 0) return;

        $notifyPhone = $user->private_phone ?: $user->whatsapp_number;
        if (!$notifyPhone) return;

        // Normalize 077... → 9477...
        $notifyPhone = preg_replace('/[^0-9]/', '', $notifyPhone);
        if (strlen($notifyPhone) === 10 && str_starts_with($notifyPhone, '0')) {
            $notifyPhone = '94' . substr($notifyPhone, 1);
        }

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
            Log::error("Balance notification failed (bulk): " . $e->getMessage());
        }
    }

    private function sendViaNodeBridge(string $phone, string $text, ?string $imageUrl = null): void
    {
        $nodeBridgeUrl = config('services.node_bridge.url');
        $apiKey        = config('services.node_bridge.secret_key');

        $payload = [
            'user_id' => $this->user->id,
            'phone'   => $phone,
            'message' => $text,
        ];

        if ($imageUrl) {
            $payload['image_url'] = $imageUrl;
        }

        Log::info("Bulk send payload", ['phone' => $phone, 'image_url' => $imageUrl ?? 'none']);

        $response = Http::withHeaders([
            'x-api-key'    => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("{$nodeBridgeUrl}/send-message", $payload);

        if (!$response->successful()) {
            Log::error("Node Bridge bulk send failed for {$phone}", ['body' => $response->body()]);
        }
    }

    private function sendViaCloudApi(string $phone, string $text, ?string $imageUrl = null): void
    {
        $phoneId = $this->user->whatsapp_phone_number_id;
        $token   = $this->user->target_api_key;

        if (!$phoneId || !$token) return;

        $authHeader = str_starts_with($token, 'Bearer ') ? $token : "Bearer {$token}";

        if ($imageUrl) {
            Http::withHeaders(['Authorization' => $authHeader])
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'image',
                    'image'             => [
                        'link'    => $imageUrl,
                        'caption' => $text,
                    ],
                ]);
        } else {
            Http::withHeaders(['Authorization' => $authHeader])
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $phone,
                    'type'              => 'text',
                    'text'              => ['body' => $text],
                ]);
        }
    }
}
