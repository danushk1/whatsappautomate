<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppMediaService
{
    /**
     * Downloads WhatsApp media (e.g. audio note) from Meta Graph API.
     *
     * @param string $mediaId The Media ID sent by WhatsApp Webhook.
     * @param string $token The User's Meta API Token (target_api_key).
     * @return string|null The exact local filesystem path of the downloaded file.
     * @throws Exception
     */
    public function downloadMedia(string $mediaId, string $token): ?string
    {
        // 1. Fetch the media URL metadata from Graph API
        $response = Http::withToken($token)
            ->get("https://graph.facebook.com/v19.0/{$mediaId}");

        if ($response->failed()) {
            throw new Exception("Failed to fetch media metadata for ID {$mediaId}. Error: " . $response->body());
        }

        $mediaData = $response->json();
        $mediaUrl = $mediaData['url'] ?? null;
        $mimeType = $mediaData['mime_type'] ?? '';

        if (!$mediaUrl) {
            throw new Exception("No media URL found in the response for ID {$mediaId}.");
        }

        // 2. Download the binary media content using the URL and token
        $mediaResponse = Http::withToken($token)->get($mediaUrl);

        if ($mediaResponse->failed()) {
            throw new Exception("Failed to download media binary from URL. Error: " . $mediaResponse->body());
        }

        // 3. Determine the file extension based on mime_type (e.g., audio/ogg; codecs=opus)
        $extension = 'ogg'; // Default for voice notes
        if (Str::contains($mimeType, 'mp4')) {
            $extension = 'mp4';
        } elseif (Str::contains($mimeType, 'jpeg')) {
            $extension = 'jpg';
        }

        $fileName = 'wa_media_' . $mediaId . '_' . time() . '.' . $extension;
        $path = 'whatsapp_media/' . $fileName;

        // 4. Save to local storage
        Storage::put($path, $mediaResponse->body());

        // Return the absolute path for OpenAI Whisper
        return Storage::path($path);
    }
}
