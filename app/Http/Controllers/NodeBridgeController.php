<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NodeBridgeController extends Controller
{
    /**
     * Laravel එකෙන් Node.js Bridge එකට WhatsApp Message එකක් Send කරන්න
     * Uses: $user->connection_type == 'web_automation' විට
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $nodeBridgeUrl = env('NODE_BRIDGE_URL', 'http://172.235.19.62:3000');
        $apiKey = env('NODE_BRIDGE_SECRET_KEY', 'genify-node-bridge-secret-2026');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$nodeBridgeUrl}/send-message", [
                'user_id' => $user->id,
                'phone' => $request->phone,
                'message' => $request->message,
            ]);

            if ($response->successful()) {
                Log::info("✅ Message sent via Node Bridge", [
                    'user_id' => $user->id,
                    'phone' => $request->phone,
                ]);
                return response()->json(['status' => 'sent']);
            }

            Log::error("❌ Node Bridge send failed", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json(['error' => 'Failed to send'], 500);

        } catch (\Exception $e) {
            Log::error("❌ Node Bridge connection error: " . $e->getMessage());
            return response()->json(['error' => 'Bridge offline'], 502);
        }
    }

    /**
     * Node.js Bridge එකෙන් QR Code Status Update කරන්න
     */
    public function updateQRStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'qr_code_path' => 'nullable|string',
            'status' => 'required|string|in:generated,scanned,expired',
        ]);

        $user = User::findOrFail($request->user_id);
        
        if ($request->qr_code_path) {
            $user->whatsapp_qr_code_path = $request->qr_code_path;
        }
        
        if ($request->status === 'scanned') {
            $user->whatsapp_connected_at = now();
        }
        
        $user->save();

        Log::info("📱 QR Status Updated", [
            'user_id' => $user->id,
            'status' => $request->status,
        ]);

        return response()->json(['status' => 'updated']);
    }

    public function updateConnectionStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'event' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $event = $request->event;
        
        if ($event === 'connected') {
            if ($request->has('phone')) {
                $user->whatsapp_number = $request->phone;
            }
            $user->whatsapp_connected_at = now();
            $user->whatsapp_qr_code_path = null;
        } elseif ($event === 'disconnected' || $event === 'auth_failure') {
            $user->whatsapp_connected_at = null;
        } elseif ($event === 'qr_generated') {
            if ($request->has('qr_code_path')) {
                $user->whatsapp_qr_code_path = $request->qr_code_path;
            }
        }
        
        $user->save();

        Log::info("🔌 Connection Status Updated", [
            'user_id' => $user->id,
            'event' => $event,
        ]);

        return response()->json(['status' => 'updated']);
    }

    /**
     * Node.js Bridge එකෙන් Session Data Update කරන්න
     */
    public function updateSession(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'session_data' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->whatsapp_session = $request->session_data;
        $user->save();

        return response()->json(['status' => 'session_saved']);
    }
}
