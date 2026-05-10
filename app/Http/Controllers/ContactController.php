<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        $contacts = Contact::where('user_id', auth()->id())
            ->orderByDesc('is_blocked')
            ->orderByDesc('last_messaged_at')
            ->get();

        return view('contacts.index', compact('contacts'));
    }

    public function toggleBlock(int $id)
    {
        $contact = Contact::where('user_id', auth()->id())->findOrFail($id);
        $contact->update(['is_blocked' => !$contact->is_blocked]);

        $status = $contact->is_blocked ? 'blocked' : 'unblocked';
        return back()->with('success', "Contact {$status} successfully.");
    }

    public function addBlocked(Request $request)
    {
        $request->validate(['phone' => 'required|string|max:20']);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '94' . substr($phone, 1);
        }

        Contact::updateOrCreate(
            ['user_id' => auth()->id(), 'phone' => $phone],
            ['is_blocked' => true, 'wa_id' => $phone]
        );

        return back()->with('success', "Contact {$phone} blocked.");
    }
}
