<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ContactController extends Controller
{
    public function index()
    {
        $contacts = Contact::where('user_id', auth()->id())
            ->orderByDesc('is_blocked')
            ->orderByDesc('last_messaged_at')
            ->paginate(50);

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

        $phone = $this->normalizePhone($request->phone);

        Contact::updateOrCreate(
            ['user_id' => auth()->id(), 'phone' => $phone],
            ['is_blocked' => true, 'wa_id' => $phone]
        );

        return back()->with('success', "Contact {$phone} blocked.");
    }

    /**
     * Bulk block — paste numbers (one per line, comma-separated, or space-separated)
     */
    public function bulkBlock(Request $request)
    {
        $request->validate(['numbers' => 'required|string']);

        $raw     = $request->input('numbers');
        $numbers = preg_split('/[\s,;\n\r]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $count   = 0;

        foreach ($numbers as $num) {
            $phone = $this->normalizePhone($num);
            if (strlen($phone) < 7) continue;

            Contact::updateOrCreate(
                ['user_id' => auth()->id(), 'phone' => $phone],
                ['is_blocked' => true, 'wa_id' => $phone]
            );
            $count++;
        }

        return back()->with('success', "{$count} contacts blocked successfully.");
    }

    /**
     * Fetch WhatsApp contact list from the connected account via Node bridge.
     * Returns JSON for AJAX calls.
     */
    public function whatsappList(Request $request)
    {
        $user          = auth()->user();
        $search        = $request->input('search', '');
        $nodeBridgeUrl = config('services.node_bridge.url');
        $apiKey        = config('services.node_bridge.secret_key');

        try {
            $response = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$nodeBridgeUrl}/get-contacts", [
                'user_id' => $user->id,
                'search'  => $search,
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'WhatsApp not connected or failed to fetch contacts.'], 503);
            }

            // Annotate with existing block status
            $contacts = $response->json('contacts') ?? [];
            $total    = $response->json('total') ?? 0;

            $phones = collect($contacts)->pluck('number')->all();
            $blocked = Contact::where('user_id', $user->id)
                ->whereIn('phone', $phones)
                ->where('is_blocked', true)
                ->pluck('phone')
                ->flip();

            foreach ($contacts as &$c) {
                $c['is_blocked'] = isset($blocked[$c['number']]);
            }

            return response()->json(['contacts' => $contacts, 'total' => $total]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Block selected WhatsApp contacts (submitted from the WhatsApp contact picker).
     */
    public function blockWhatsAppContacts(Request $request)
    {
        $request->validate(['numbers' => 'required|array']);

        $count = 0;
        foreach ($request->input('numbers') as $num) {
            $phone = $this->normalizePhone($num);
            if (strlen($phone) < 7) continue;

            Contact::updateOrCreate(
                ['user_id' => auth()->id(), 'phone' => $phone],
                ['is_blocked' => true, 'wa_id' => $phone]
            );
            $count++;
        }

        return response()->json(['blocked' => $count]);
    }

    private function normalizePhone(string $raw): string
    {
        $phone = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '94' . substr($phone, 1);
        }
        return $phone;
    }
}
