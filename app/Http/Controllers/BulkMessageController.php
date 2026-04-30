<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BulkMessageController extends Controller
{
    public function index()
    {
        $user     = auth()->user();
        $contacts = \App\Models\Contact::where('user_id', $user->id)
            ->orderBy('last_messaged_at', 'desc')
            ->get();

        return view('bulk-message', [
            'user'           => $user,
            'contacts'       => $contacts,
            'costPerMessage' => $user->bulk_message_cost ?? 0.30,
        ]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'message'    => 'nullable|string|max:4096',
            'contacts'   => 'required|array|min:1',
            'contacts.*' => 'string',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        // At least one of message or image is required
        if (empty($request->message) && !$request->hasFile('image')) {
            return back()->withErrors(['message' => 'Please provide a message or an image.']);
        }

        $user           = auth()->user();
        $message        = $request->message ?? '';
        $costPerMessage = $user->bulk_message_cost ?? 0.30;

        // Normalize all phone numbers to international format
        $contacts = array_values(array_unique(
            array_filter(array_map([$this, 'normalizePhone'], $request->contacts))
        ));

        if (empty($contacts)) {
            return back()->withErrors(['contacts' => 'No valid contacts selected.']);
        }

        $totalCost = count($contacts) * $costPerMessage;

        if ($user->balance < $totalCost) {
            return back()->with('error', 'Insufficient balance. Need Rs. ' . number_format($totalCost, 2) . ' for ' . count($contacts) . ' contacts.');
        }

        // Handle image upload
        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('bulk-images', 'public');
            $imageUrl = asset('storage/' . $path);
        }

        // Deduct balance
        $user->balance -= $totalCost;
        $user->save();

        \App\Jobs\ProcessBulkMessageJob::dispatch($user, $contacts, $message, $imageUrl);

        return redirect()->route('bulk-message.index')
            ->with('success', 'Queued ' . count($contacts) . ' messages! Sending shortly.');
    }

    /**
     * Normalize phone number to international format.
     * e.g. 0714563456 → 94714563456
     *      +94714563456 → 94714563456
     *      94714563456@c.us → 94714563456
     */
    private function normalizePhone(string $phone): string
    {
        // Remove @c.us or any @... suffix
        $phone = preg_replace('/@.*$/', '', trim($phone));
        // Keep digits only
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Convert local 10-digit format starting with 0 → international
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '94' . substr($phone, 1);
        }
        // Reject clearly invalid numbers (too short)
        if (strlen($phone) < 10) {
            return '';
        }
        return $phone;
    }
}
