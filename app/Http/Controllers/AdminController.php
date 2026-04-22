<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Admin Dashboard - List all users.
     */
    public function index()
    {
        $users = User::where('is_admin', false)->orderBy('created_at', 'DESC')->get();
        return view('admin.dashboard', compact('users'));
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
        ]);

        return back()->with('success', 'New client created successfully.');
    }

    public function updateUser(Request $request, $id)
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
        ]);

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
        ]);

        return back()->with('success', 'User ' . $user->name . ' updated successfully.');
    }

    /**
     * Delete a user.
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        if ($user->is_admin) {
            return back()->with('error', 'Cannot delete an admin.');
        }
        
        $user->delete();
        return back()->with('success', 'User deleted successfully.');
    }
}
