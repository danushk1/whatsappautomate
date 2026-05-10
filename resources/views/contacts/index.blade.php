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

    <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

        @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">{{ session('success') }}</div>
        @endif

        <div>
            <h2 class="text-2xl font-extrabold text-white">Auto-Reply Block List</h2>
            <p class="text-slate-400 mt-1 text-sm">Blocked contacts will not receive any automated bot replies.</p>
        </div>

        <!-- ── Tab Bar ── -->
        <div class="flex space-x-1 bg-slate-900/60 p-1 rounded-xl border border-slate-800">
            <button onclick="showTab('tab-wa')" id="btn-wa"
                class="tab-btn flex-1 py-2 px-4 rounded-lg text-sm font-semibold transition-all bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                📱 WhatsApp Contacts
            </button>
            <button onclick="showTab('tab-bulk')" id="btn-bulk"
                class="tab-btn flex-1 py-2 px-4 rounded-lg text-sm font-semibold transition-all text-slate-400 hover:text-white">
                📋 Bulk Import
            </button>
            <button onclick="showTab('tab-manual')" id="btn-manual"
                class="tab-btn flex-1 py-2 px-4 rounded-lg text-sm font-semibold transition-all text-slate-400 hover:text-white">
                ✏️ Add Single
            </button>
            <button onclick="showTab('tab-list')" id="btn-list"
                class="tab-btn flex-1 py-2 px-4 rounded-lg text-sm font-semibold transition-all text-slate-400 hover:text-white">
                🚫 Blocked ({{ $contacts->total() }})
            </button>
        </div>

        <!-- ── Tab: WhatsApp Contact Picker ── -->
        <div id="tab-wa" class="glass-card rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider">WhatsApp Contacts</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Load contacts from your connected WhatsApp account, then select and block.</p>
                </div>
                <button onclick="loadWaContacts()" id="btn-load-wa"
                    class="px-4 py-2 bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-400 rounded-xl text-sm font-semibold transition-all">
                    Load Contacts
                </button>
            </div>

            <input type="text" id="wa-search" placeholder="Search by name or number..."
                oninput="searchWaContacts()"
                class="w-full mb-4 bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">

            <div id="wa-status" class="text-center py-8 text-slate-500 text-sm">
                Click <strong class="text-slate-400">Load Contacts</strong> to fetch contacts from your connected WhatsApp.
            </div>

            <div id="wa-contact-list" class="space-y-1 max-h-80 overflow-y-auto hidden"></div>

            <div id="wa-actions" class="mt-4 flex items-center justify-between hidden">
                <span id="wa-selected-count" class="text-sm text-slate-400">0 selected</span>
                <button onclick="blockSelectedWa()"
                    class="px-5 py-2 bg-red-500/20 hover:bg-red-500/30 border border-red-500/40 text-red-400 rounded-xl text-sm font-semibold transition-all">
                    Block Selected
                </button>
            </div>
        </div>

        <!-- ── Tab: Bulk Import ── -->
        <div id="tab-bulk" class="glass-card rounded-2xl p-5 hidden">
            <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider mb-1">Bulk Block Numbers</h3>
            <p class="text-xs text-slate-500 mb-4">Paste any number of phone numbers — one per line, comma or space separated.</p>
            <form method="POST" action="{{ route('contacts.bulk-block') }}">
                @csrf
                <textarea name="numbers" rows="8" placeholder="94771234567&#10;94712345678&#10;0771234567, 0712345678&#10;..."
                    class="w-full bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 font-mono resize-y"></textarea>
                <div class="flex items-center justify-between mt-3">
                    <p class="text-xs text-slate-500">Formats: 94771234567 / 0771234567 / +94771234567</p>
                    <button type="submit"
                        class="px-5 py-2.5 bg-red-500/20 hover:bg-red-500/30 border border-red-500/40 text-red-400 rounded-xl text-sm font-semibold transition-all">
                        Block All
                    </button>
                </div>
            </form>
        </div>

        <!-- ── Tab: Add Single ── -->
        <div id="tab-manual" class="glass-card rounded-2xl p-5 hidden">
            <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider mb-3">Block a Single Number</h3>
            <form method="POST" action="{{ route('contacts.add-blocked') }}" class="flex gap-3">
                @csrf
                <input type="text" name="phone" placeholder="e.g. 0771234567 or 94771234567"
                    class="flex-1 bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500">
                <button type="submit"
                    class="px-5 py-2.5 bg-red-500/20 hover:bg-red-500/30 border border-red-500/40 text-red-400 rounded-xl text-sm font-semibold transition-all">
                    Block
                </button>
            </form>
            <p class="text-slate-500 text-xs mt-2">Blocked contacts can still message you — the bot simply won't reply.</p>
        </div>

        <!-- ── Tab: Blocked List ── -->
        <div id="tab-list" class="glass-card rounded-2xl overflow-hidden hidden">
            <div class="px-5 py-4 border-b border-slate-800/60 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-300 uppercase tracking-wider">
                    All Contacts
                    <span class="ml-2 text-xs font-normal text-slate-500">({{ $contacts->total() }})</span>
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
                <p class="text-slate-500 text-sm">No contacts yet. They appear automatically when someone messages your WhatsApp.</p>
            </div>
            @endforelse

            @if($contacts->hasPages())
            <div class="px-5 py-4">{{ $contacts->links() }}</div>
            @endif
        </div>

    </div>

    <script>
        const CSRF = '{{ csrf_token() }}';
        let waAllContacts = [];
        let waSelected = new Set();
        let searchTimer = null;

        function showTab(id) {
            ['tab-wa','tab-bulk','tab-manual','tab-list'].forEach(t => {
                document.getElementById(t).classList.add('hidden');
            });
            ['btn-wa','btn-bulk','btn-manual','btn-list'].forEach(b => {
                const el = document.getElementById(b);
                el.classList.remove('bg-emerald-500/20','text-emerald-400','border','border-emerald-500/30');
                el.classList.add('text-slate-400');
            });
            document.getElementById(id).classList.remove('hidden');
            const btnId = 'btn-' + id.replace('tab-','');
            const btn = document.getElementById(btnId);
            btn.classList.add('bg-emerald-500/20','text-emerald-400','border','border-emerald-500/30');
            btn.classList.remove('text-slate-400');
        }

        async function loadWaContacts(search = '') {
            const status = document.getElementById('wa-status');
            const list   = document.getElementById('wa-contact-list');
            const actions = document.getElementById('wa-actions');
            status.textContent = 'Loading contacts from WhatsApp...';
            status.classList.remove('hidden');
            list.classList.add('hidden');
            actions.classList.add('hidden');

            try {
                const res = await fetch('{{ route("contacts.whatsapp-list") }}?search=' + encodeURIComponent(search), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.error) {
                    status.textContent = '⚠️ ' + data.error;
                    return;
                }

                waAllContacts = data.contacts || [];
                const total   = data.total || 0;
                status.classList.add('hidden');
                list.classList.remove('hidden');
                actions.classList.remove('hidden');
                renderWaContacts();

                if (!search) {
                    status.textContent = `Showing ${waAllContacts.length} of ${total} contacts. Search to filter.`;
                    status.classList.remove('hidden');
                }
            } catch (e) {
                status.textContent = '⚠️ Failed to load: ' + e.message;
            }
        }

        function renderWaContacts() {
            const list = document.getElementById('wa-contact-list');
            list.innerHTML = '';
            waAllContacts.forEach(c => {
                const isBlocked  = c.is_blocked;
                const isSelected = waSelected.has(c.number);
                const div = document.createElement('div');
                div.className = 'flex items-center space-x-3 px-3 py-2.5 rounded-xl hover:bg-slate-800/40 cursor-pointer transition-all ' + (isSelected ? 'bg-red-500/10' : '');
                div.innerHTML = `
                    <input type="checkbox" ${isSelected ? 'checked' : ''} ${isBlocked ? 'disabled' : ''}
                        onchange="toggleWaSelect('${c.number}', this)"
                        class="w-4 h-4 accent-red-500 cursor-pointer">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">${c.name || c.number}</p>
                        ${c.name ? `<p class="text-xs text-slate-500">${c.number}</p>` : ''}
                    </div>
                    ${isBlocked ? '<span class="text-xs px-2 py-0.5 rounded-full bg-red-500/10 border border-red-500/30 text-red-400">Blocked</span>' : ''}
                `;
                list.appendChild(div);
            });
            updateWaCount();
        }

        function toggleWaSelect(number, checkbox) {
            if (checkbox.checked) waSelected.add(number);
            else waSelected.delete(number);
            updateWaCount();
        }

        function updateWaCount() {
            document.getElementById('wa-selected-count').textContent = waSelected.size + ' selected';
        }

        function searchWaContacts() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                const q = document.getElementById('wa-search').value.trim();
                loadWaContacts(q);
            }, 400);
        }

        async function blockSelectedWa() {
            if (waSelected.size === 0) return alert('No contacts selected.');
            const numbers = Array.from(waSelected);
            const res = await fetch('{{ route("contacts.whatsapp-block") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ numbers }),
            });
            const data = await res.json();
            alert(data.blocked + ' contacts blocked!');
            waSelected.clear();
            loadWaContacts(document.getElementById('wa-search').value);
        }
    </script>
</body>
</html>
