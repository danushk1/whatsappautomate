<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // Dashboard එක පෙන්වීම
    public function index()
    {
       
    $user = auth()->user();

    // Force new users to connect WhatsApp first
    if ($user->connection_type === 'web_automation' && !$user->whatsapp_connected_at) {
        return redirect()->route('whatsapp.connect')->with('info', 'Please scan the QR code to connect your WhatsApp first.');
    }

   $totalOrdersToday = Order::where('user_id', $user->id)
        ->whereDate('created_at', now()->today())
        ->count();

    // පහුගිය දින 7ක දත්ත (Chart එක සඳහා)
    $chartData = Order::where('user_id', $user->id)
        ->where('created_at', '>=', now()->subDays(6))
        ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date', 'ASC')
        ->get();

    return view('dashboard', [
        'user' => $user,
        'totalOrders' => $totalOrdersToday,
        'chartData' => $chartData
    ]);
        
}
    

    // Settings save කිරීම
    public function updateSettings(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'private_phone' => 'nullable|string|max:20',
            'target_value' => 'nullable|string',
            'google_sheet_name' => 'nullable|string',
            'order_api_url' => 'nullable|string',
            'target_api_key' => 'nullable|string',
            'inventory_api_url' => 'nullable|string',
            'company_details' => 'required|string|min:10',
            'is_autoreply_enabled' => 'nullable',
            'autoreply_message' => 'nullable|string',
        ]);

        $user = Auth::user();

        // Prevent saving the same number as both primary and secondary
        $primaryClean   = preg_replace('/[^0-9]/', '', $request->whatsapp_number ?? '');
        $secondaryClean = preg_replace('/[^0-9]/', '', $request->private_phone ?? '');
        if ($secondaryClean && $primaryClean && $secondaryClean === $primaryClean) {
            return back()->withErrors(['private_phone' => 'Secondary number must be different from the primary WhatsApp number.'])->withInput();
        }

        $isAutoreplyEnabled = $request->has('is_autoreply_enabled');

        $updateData = [
            'name' => $request->name,
            'address' => $request->address,
            'whatsapp_number' => $request->whatsapp_number,
            'private_phone' => $request->private_phone ?: null,
            'target_value' => $request->target_value,
            'google_sheet_name' => $request->google_sheet_name,
            'order_api_url' => $request->order_api_url,
            'target_api_key' => $request->target_api_key,
            'inventory_api_url' => $request->inventory_api_url,
            'company_details' => $request->company_details,
            'is_autoreply_enabled' => $isAutoreplyEnabled,
            'autoreply_message' => $request->autoreply_message,
        ];

        // පළමු වතාවට Auto-Reply Enable කරන විට Credits 20ක් ලබා දීම
        if ($isAutoreplyEnabled && !$user->has_claimed_autoreply_bonus) {
            $updateData['autoreply_credits'] = 20;
            $updateData['has_claimed_autoreply_bonus'] = true;
        }

        $user->update($updateData);

        return back()->with('success', 'Settings updated successfully!');
    }

    /**
     * ✅ Show WhatsApp Connect Page (Option B - QR Scan)
     * පාරිභෝගිකයාට QR code scan කරන්න page එක පෙන්වනවා
     */
    public function showConnect()
    {
        $user = Auth::user();
        return view('whatsapp-connect', [
            'user' => $user,
            'nodeBridgeUrl' => env('NODE_BRIDGE_URL', 'http://localhost:3000'),
        ]);
    }

    /**
     * ✅ Initiate WhatsApp connection (Call Node.js Bridge)
     * Node.js Bridge එකට connect command එකක් යවනවා
     */
    public function initiateConnect(Request $request)
    {
        $user = Auth::user();
        $nodeBridgeUrl = env('NODE_BRIDGE_URL', 'http://127.0.0.1:3000');
        $apiKey = env('NODE_BRIDGE_SECRET_KEY', 'genify-node-bridge-secret-2026');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$nodeBridgeUrl}/connect", [
                'user_id' => $user->id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'status' => 'connecting',
                    'qr_data_url' => $data['qr_data_url'] ?? null,
                    'qr_file_path' => $data['qr_file_path'] ?? null,
                    'qr_ready' => $data['qr_ready'] ?? false,
                ]);
            }

            return response()->json([
                'error' => 'Bridge connection failed',
                'details' => $response->body(),
            ], 500);

        } catch (\Exception $e) {
            Log::error("Node Bridge connect error: " . $e->getMessage());
            return response()->json([
                'error' => 'Node Bridge is offline',
                'details' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * ✅ Check WhatsApp connection status
     * Node.js Bridge එකෙන් status එක check කරනවා
     */
    public function connectionStatus()
    {
        $user = Auth::user();
        $nodeBridgeUrl = env('NODE_BRIDGE_URL', 'http://127.0.0.1:3000');
        $apiKey = env('NODE_BRIDGE_SECRET_KEY', 'genify-node-bridge-secret-2026');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$nodeBridgeUrl}/status", [
                'user_id' => $user->id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'connected' => $data['connected'] ?? false,
                    'status' => $data['status'] ?? 'unknown',
                    'qr_ready' => $data['qr_ready'] ?? false,
                    'whatsapp_connected_at' => $user->whatsapp_connected_at,
                ]);
            }

            return response()->json([
                'connected' => false,
                'status' => 'bridge_error',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'connected' => false,
                'status' => 'bridge_offline',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ✅ Disconnect WhatsApp
     */
    public function disconnectWhatsApp(Request $request)
    {
        $user = Auth::user();
        $nodeBridgeUrl = env('NODE_BRIDGE_URL', 'http://127.0.0.1:3000');
        $apiKey = env('NODE_BRIDGE_SECRET_KEY', 'genify-node-bridge-secret-2026');

        try {
            Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$nodeBridgeUrl}/disconnect", [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            // Ignore - just clean up locally
        }

        $user->whatsapp_connected_at = null;
        $user->whatsapp_session = null;
        $user->whatsapp_qr_code_path = null;
        $user->save();

        return response()->json(['status' => 'disconnected']);
    }
}