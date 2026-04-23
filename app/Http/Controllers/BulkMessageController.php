<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BulkMessageController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $contacts = \App\Models\Contact::where('user_id', $user->id)
            ->orderBy('last_messaged_at', 'desc')
            ->get();

        return view('bulk-message', [
            'user' => $user,
            'contacts' => $contacts,
            'costPerMessage' => $user->bulk_message_cost ?? 0.30,
        ]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'contacts' => 'required|array|min:1',
            'contacts.*' => 'string'
        ]);

        $user = auth()->user();
        $message = $request->message;
        $contacts = $request->contacts;
        $costPerMessage = $user->bulk_message_cost ?? 0.30;

        $totalCost = count($contacts) * $costPerMessage;

        if ($user->balance < $totalCost) {
            return back()->with('error', 'Insufficient balance. You need Rs. ' . number_format($totalCost, 2) . ' to send to ' . count($contacts) . ' contacts.');
        }

        // Deduct balance
        $user->balance -= $totalCost;
        $user->save();

        // Dispatch Job
        \App\Jobs\ProcessBulkMessageJob::dispatch($user, $contacts, $message);

        return redirect()->route('bulk-message.index')->with('success', 'Successfully queued ' . count($contacts) . ' messages! They will be sent shortly.');
    }
}
