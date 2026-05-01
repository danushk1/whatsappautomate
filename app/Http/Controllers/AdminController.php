<?php

namespace App\Http\Controllers;

use App\Models\AdminMessage;
use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Admin Dashboard - List all users.
     */
    public function index()
    {
        $users     = User::where('is_admin', false)->orderBy('created_at', 'DESC')->get();
        $setting   = AdminSetting::first() ?? new AdminSetting();
        $messages  = AdminMessage::where('expires_at', '>', now())
            ->orderBy('received_at', 'desc')
            ->get();
        /** @var User $adminUser */
        $adminUser = User::find(auth()->id());
        return view('admin.dashboard', compact('users', 'setting', 'messages', 'adminUser'));
    }

    /**
     * Store a newly created user (Admin action).
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:4', // Basic for testing
            'balance' => 'required|numeric|min:0',
            'whatsapp_phone_number_id' => 'nullable|string',
            'target_value' => 'nullable|string',
            'google_sheet_name' => 'nullable|string',
            'order_api_url' => 'nullable|string',
            'inventory_api_url' => 'nullable|string',
            'bulk_message_cost' => 'nullable|numeric|min:0',
            'connection_type' => 'nullable|in:cloud_api,web_automation',
            'plan_type' => 'nullable|in:free,premium',
            'private_phone' => 'nullable|string|max:20',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'balance' => $request->balance,
            'whatsapp_phone_number_id' => $request->whatsapp_phone_number_id,
            'is_admin' => false,
            'status' => 'active',
            'target_value' => $request->target_value,
            'google_sheet_name' => $request->google_sheet_name,
            'order_api_url' => $request->order_api_url,
            'inventory_api_url' => $request->inventory_api_url,
            'connection_type' => $request->connection_type ?? 'web_automation',
            'plan_type' => $request->plan_type ?? 'free',
            'bulk_message_cost' => $request->bulk_message_cost ?? 0.30,
            'private_phone' => $request->private_phone,
        ]);

        return back()->with('success', 'New client created successfully.');
    }

    public function updateUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'balance' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'whatsapp_phone_number_id' => 'nullable|string',
            'is_autoreply_enabled' => 'nullable|boolean',
            'target_api_key' => 'nullable|string|max:1000',
            'autoreply_message' => 'nullable|string',
            'target_value' => 'nullable|string',
            'google_sheet_name' => 'nullable|string',
            'order_api_url' => 'nullable|string',
            'inventory_api_url' => 'nullable|string',
            'target_mode' => 'nullable|in:EXCEL,API',
            'bulk_message_cost' => 'nullable|numeric|min:0',
            'connection_type' => 'required|in:cloud_api,web_automation',
            'plan_type' => 'required|in:free,premium',
            'private_phone' => 'nullable|string|max:20',
        ]);

        $resetNotifications = $request->balance > 10;

        $user->update([
            'balance' => $request->balance,
            'status' => $request->status,
            'whatsapp_phone_number_id' => $request->whatsapp_phone_number_id,
            'is_autoreply_enabled' => $request->has('is_autoreply_enabled') ? true : false,
            'target_api_key' => $request->target_api_key,
            'autoreply_message' => $request->autoreply_message,
            'target_value' => $request->target_value,
            'google_sheet_name' => $request->google_sheet_name,
            'order_api_url' => $request->order_api_url,
            'inventory_api_url' => $request->inventory_api_url,
            'connection_type' => $request->connection_type,
            'plan_type' => $request->plan_type,
            'bulk_message_cost' => $request->bulk_message_cost ?? 0.30,
            'private_phone' => $request->private_phone,
            'low_balance_notified_at' => $resetNotifications ? null : $user->low_balance_notified_at,
            'suspended_notified_at'   => $resetNotifications ? null : $user->suspended_notified_at,
        ]);

        return back()->with('success', 'User ' . $user->name . ' updated successfully.');
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'admin_whatsapp'      => 'nullable|string|max:20',
            'bank_name'           => 'nullable|string|max:100',
            'bank_account_no'     => 'nullable|string|max:50',
            'bank_account_name'   => 'nullable|string|max:100',
            'bank_branch'         => 'nullable|string|max:100',
            'low_balance_message' => 'nullable|string|max:1000',
            'suspended_message'   => 'nullable|string|max:1000',
        ]);

        AdminSetting::updateOrCreate(['id' => 1], $request->only([
            'admin_whatsapp',
            'bank_name',
            'bank_account_no',
            'bank_account_name',
            'bank_branch',
            'low_balance_message',
            'suspended_message',
        ]));

        return back()->with('success', 'Settings saved successfully.');
    }

    public function markMessageRead(int $id)
    {
        AdminMessage::where('id', $id)->update([
            'is_read'   => true,
            'expires_at' => now()->addDay(),
        ]);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->is_admin) {
            return back()->with('error', 'Cannot delete an admin.');
        }
        
        $user->delete();
        return back()->with('success', 'User deleted successfully.');
    }
}
