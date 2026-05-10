<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts | GenifyAI Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
    </style>
</head>
<body class="bg-[#020617] text-slate-200 min-h-screen pb-20">

    <!-- Navigation -->
    <nav class="bg-slate-900/40 border-b border-slate-800 p-4 sticky top-0 z-50 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <h1 class="text-xl font-extrabold tracking-tight text-white">GenifyAI <span class="text-emerald-400">Contacts</span></h1>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-400 hover:text-white transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Dashboard
            </a>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">

        @if(session('success'))
        <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ session('success') }}
        </div>
        @endif

        <!-- Header -->
        <div class="mb-6">
            <h2 class="text-2xl font-extrabold text-white">Auto-Reply Contacts</h2>
            <p class="text-slate-400 mt-1 text-sm">Block specific contacts from receiving bot replies. Personal messages won't get automated responses.</p>
        </div>

        <!-- Add Blocked Contact -->
        <div class="glass-card rounded-2xl p-5 mb-6">
            <h3 class="text-sm font-bold text-slate-300 mb-3 uppercase tracking-wider">Block a Number Manually</h3>
            <form method="POST" action="{{ route('contacts.add-blocked') }}" class="flex gap-3">
                @csrf
                <input type="text" name="phone" placeholder="e.g. 0771234567 or 94771234567"
                    class="flex-1 bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                <button type="submit"
                    class="px-5 py-2.5 bg-red-500/20 hover:bg-red-500/30 border border-red-500/40 text-red-400 rounded-xl text-sm font-semibold transition-all">
                    Block
                </button>
            </form>
            <p class="text-slate-500 text-xs mt-2">Blocked contacts can still message you — the bot just won't reply to them.</p>
        </div>

        <!-- Contact List -->
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800/60">
                <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider">
                    All Contacts
                    <span class="ml-2 text-xs font-normal text-slate-500">({{ $contacts->count() }} total)</span>
                </h3>
            </div>

            @forelse($contacts as $contact)
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800/40 hover:bg-slate-800/20 transition-colors">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold
                        {{ $contact->is_blocked ? 'bg-red-500/20 text-red-400' : 'bg-emerald-500/20 text-emerald-400' }}">
                        {{ strtoupper(substr($contact->name ?? $contact->phone, 0, 1)) }}
                    </div>
                    <div>
                        @if($contact->name)
                        <p class="text-sm font-semibold text-white">{{ $contact->name }}</p>
                        <p class="text-xs text-slate-500">{{ $contact->phone }}</p>
                        @else
                        <p class="text-sm font-semibold text-white">{{ $contact->phone }}</p>
                        @endif
                        @if($contact->last_messaged_at)
                        <p class="text-xs text-slate-600 mt-0.5">Last: {{ $contact->last_messaged_at->diffForHumans() }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    @if($contact->is_blocked)
                    <span class="text-xs px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/30 text-red-400 font-semibold">Blocked</span>
                    @else
                    <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-semibold">Active</span>
                    @endif
                    <form method="POST" action="{{ route('contacts.toggle-block', $contact->id) }}">
                        @csrf
                        <button type="submit"
                            class="text-xs px-3 py-1.5 rounded-lg font-medium transition-all
                            {{ $contact->is_blocked
                                ? 'bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 border border-emerald-500/30'
                                : 'bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20' }}">
                            {{ $contact->is_blocked ? 'Unblock' : 'Block' }}
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <div class="px-5 py-12 text-center">
                <p class="text-slate-500 text-sm">No contacts yet. Contacts appear automatically when someone messages you.</p>
            </div>
            @endforelse
        </div>
    </div>
</body>
</html>
