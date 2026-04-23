<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Broadcast | GenifyAI Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.5); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(16, 185, 129, 0.3); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.6); }
    </style>
</head>
<body class="bg-[#020617] text-slate-200 min-h-screen pb-20">

    <!-- Navigation -->
    <nav class="bg-slate-900/40 border-b border-slate-800 p-4 sticky top-0 z-50 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                </div>
                <h1 class="text-xl font-extrabold tracking-tight text-white">GenifyAI <span class="text-emerald-400">Broadcast</span></h1>
            </div>
            <div class="flex items-center space-x-6">
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-400 hover:text-white transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-6 py-8">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
            <div>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">Mass Broadcast Campaign</h2>
                <p class="text-slate-400 mt-2">Send bulk promotional messages to your past customers.</p>
            </div>
            <div class="bg-emerald-500/10 p-4 rounded-2xl border border-emerald-500/20 text-right min-w-[200px]">
                <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest mb-1">Available Balance</p>
                <div class="flex items-baseline space-x-2 justify-end">
                    <span class="text-2xl font-black text-white">Rs. {{ number_format($user->balance, 2) }}</span>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-8 p-5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-2xl flex items-center shadow-lg shadow-emerald-500/5">
                <div class="w-8 h-8 bg-emerald-500/20 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span class="font-bold tracking-tight">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-8 p-5 bg-red-500/10 border border-red-500/30 text-red-400 rounded-2xl flex items-center shadow-lg shadow-red-500/5">
                <div class="w-8 h-8 bg-red-500/20 rounded-lg flex items-center justify-center mr-4 shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <span class="font-bold tracking-tight">{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-8 p-5 bg-red-500/10 border border-red-500/30 text-red-400 rounded-2xl flex items-center shadow-lg shadow-red-500/5">
                <ul class="list-disc list-inside font-bold text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('bulk-message.send') }}" method="POST" id="broadcastForm">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <!-- Left Column: Recipients -->
                <div class="lg:col-span-5 flex flex-col h-[600px]">
                    <div class="glass-card rounded-[2rem] p-6 flex flex-col h-full border-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-white">Audience List</h3>
                                <p class="text-xs text-slate-500 mt-1">Total Audience: <span class="text-white font-bold">{{ $contacts->count() }}</span></p>
                            </div>
                            <div class="bg-blue-500/10 text-blue-400 px-3 py-1 rounded-lg text-xs font-bold border border-blue-500/20">
                                Rs. {{ number_format($costPerMessage, 2) }} / msg
                            </div>
                        </div>

                        <!-- Search & Controls -->
                        <div class="space-y-4 mb-4">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <input type="text" id="searchInput" onkeyup="filterContacts()" placeholder="Search by phone number..." 
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl pl-11 pr-4 py-3 text-sm text-white focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all placeholder-slate-600">
                            </div>
                            
                            <div class="flex items-center justify-between bg-slate-900/30 p-3 rounded-xl border border-slate-800/50">
                                <label class="flex items-center cursor-pointer group">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" checked class="w-4 h-4 text-emerald-500 focus:ring-emerald-500 bg-slate-900 border-slate-700 rounded">
                                    <span class="ml-3 text-xs font-bold text-slate-400 group-hover:text-white transition-colors">Select All Visible</span>
                                </label>
                                <span class="text-xs font-bold text-emerald-400"><span id="selectedCount">{{ $contacts->count() }}</span> Selected</span>
                            </div>
                        </div>

                        <!-- Contacts List -->
                        <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-2" id="contactsList">
                            @forelse($contacts as $contact)
                                <label class="contact-item flex items-center justify-between p-4 rounded-xl border border-slate-800 bg-slate-900/20 hover:bg-slate-800/40 cursor-pointer transition-all group">
                                    <div class="flex items-center space-x-4">
                                        <input type="checkbox" name="contacts[]" value="{{ $contact->phone }}" onchange="updateCalculations()" checked 
                                            class="contact-checkbox w-4 h-4 text-emerald-500 focus:ring-emerald-500 bg-slate-900 border-slate-700 rounded">
                                        <div>
                                            <div class="text-sm font-bold text-white tracking-wide contact-phone">{{ $contact->phone }}</div>
                                            <div class="text-[10px] text-slate-500 mt-0.5">Last msg: {{ $contact->last_messaged_at ? $contact->last_messaged_at->diffForHumans() : 'Unknown' }}</div>
                                        </div>
                                    </div>
                                    <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-500 group-hover:text-emerald-400 group-hover:bg-emerald-500/10 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    </div>
                                </label>
                            @empty
                                <div class="text-center py-10">
                                    <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-600">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    </div>
                                    <p class="text-sm font-bold text-slate-400">No contacts found.</p>
                                    <p class="text-xs text-slate-500 mt-1">Wait for customers to message you first.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Right Column: Message & Billing -->
                <div class="lg:col-span-7 flex flex-col space-y-6">
                    
                    <!-- Message Composer -->
                    <div class="glass-card rounded-[2rem] p-8 border-slate-800 flex-1">
                        <div class="flex items-center space-x-3 mb-6">
                            <div class="w-10 h-10 bg-purple-500/20 rounded-xl flex items-center justify-center text-purple-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">Message Composer</h3>
                                <p class="text-xs text-slate-500">Draft the message you want to broadcast</p>
                            </div>
                        </div>

                        <div class="relative">
                            <textarea name="message" id="messageInput" rows="8" required
                                class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl p-5 text-white focus:ring-2 focus:ring-purple-500/50 outline-none transition-all placeholder-slate-600 resize-none"
                                placeholder="Type your promotional message, announcement, or greeting here..."></textarea>
                            <div class="absolute bottom-4 right-4 text-xs font-bold text-slate-500 bg-slate-900 px-3 py-1 rounded-lg border border-slate-800">
                                <span id="charCount">0</span> chars
                            </div>
                        </div>

                        <div class="mt-4 p-4 rounded-xl border border-amber-500/20 bg-amber-500/5 flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                <strong class="text-amber-400">Anti-Spam Warning:</strong> Sending too many messages to people who haven't saved your number might result in Meta restricting your WhatsApp account. Ensure your messages are relevant and not overly frequent.
                            </p>
                        </div>
                    </div>

                    <!-- Billing & Submit -->
                    <div class="glass-card rounded-[2rem] p-8 border-emerald-500/20 bg-gradient-to-br from-emerald-500/5 to-teal-500/5 relative overflow-hidden">
                        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                            
                            <div class="space-y-1">
                                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Estimated Cost</p>
                                <div class="flex items-end space-x-2">
                                    <h2 class="text-4xl font-black text-white" id="totalCostDisplay">Rs. 0.00</h2>
                                </div>
                                <p class="text-xs font-medium text-emerald-400 mt-1">
                                    <span id="selectedCountFooter">0</span> recipients × Rs. {{ number_format($costPerMessage, 2) }}
                                </p>
                            </div>

                            <button type="submit" id="sendBtn" class="bg-emerald-500 hover:bg-emerald-600 text-white font-black py-4 px-10 rounded-2xl text-lg shadow-2xl shadow-emerald-500/20 transform transition active:scale-[0.98] flex items-center justify-center group disabled:opacity-50 disabled:cursor-not-allowed">
                                <span>SEND BROADCAST</span>
                                <svg class="w-5 h-5 ml-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </button>

                        </div>
                        <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl"></div>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <script>
        const costPerMessage = {{ $costPerMessage }};
        const currentBalance = {{ $user->balance }};
        
        function updateCalculations() {
            const checkboxes = document.querySelectorAll('.contact-checkbox:not(.hidden-contact-checkbox)');
            let checkedCount = 0;
            
            checkboxes.forEach(cb => {
                if (cb.checked) checkedCount++;
            });
            
            document.getElementById('selectedCount').innerText = checkedCount;
            document.getElementById('selectedCountFooter').innerText = checkedCount;
            
            const totalCost = (checkedCount * costPerMessage).toFixed(2);
            document.getElementById('totalCostDisplay').innerText = 'Rs. ' + totalCost;

            const sendBtn = document.getElementById('sendBtn');
            if (checkedCount === 0) {
                sendBtn.disabled = true;
                sendBtn.innerHTML = 'Select Recipients';
            } else if (totalCost > currentBalance) {
                sendBtn.disabled = true;
                sendBtn.innerHTML = 'Insufficient Balance';
                document.getElementById('totalCostDisplay').classList.replace('text-white', 'text-red-400');
            } else {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<span>SEND BROADCAST</span><svg class="w-5 h-5 ml-3 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>';
                document.getElementById('totalCostDisplay').classList.replace('text-red-400', 'text-white');
            }
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAllCheckbox').checked;
            const checkboxes = document.querySelectorAll('.contact-item:not(.hidden) .contact-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll;
            });
            
            updateCalculations();
        }

        function filterContacts() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const items = document.querySelectorAll('.contact-item');
            
            let visibleCount = 0;
            
            items.forEach(item => {
                const phone = item.querySelector('.contact-phone').innerText.toLowerCase();
                const checkbox = item.querySelector('.contact-checkbox');
                
                if (phone.includes(input)) {
                    item.classList.remove('hidden');
                    checkbox.classList.remove('hidden-contact-checkbox');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                    checkbox.classList.add('hidden-contact-checkbox');
                }
            });
            
            // Uncheck "Select All" if we are filtering
            if (input !== '') {
                document.getElementById('selectAllCheckbox').checked = false;
            }
            
            updateCalculations();
        }

        // Character counter
        document.getElementById('messageInput').addEventListener('input', function() {
            document.getElementById('charCount').innerText = this.value.length;
        });

        // Prevent double submission
        document.getElementById('broadcastForm').addEventListener('submit', function() {
            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.innerHTML = 'Processing...';
        });

        // Initialize calculations on load
        updateCalculations();
    </script>
</body>
</html>
