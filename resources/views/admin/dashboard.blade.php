<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management | GenifyAI Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .tab-active { color: #3b82f6; border-bottom: 2px solid #3b82f6; }
    </style>
</head>

<body class="bg-[#020617] text-slate-200 min-h-screen pb-20">

    <nav class="bg-slate-900/40 border-b border-slate-800 p-4 sticky top-0 z-50 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-600/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <h1 class="text-xl font-extrabold tracking-tight text-white">GenifyAI <span class="text-blue-500">Admin</span></h1>
            </div>
            <div class="flex items-center space-x-6">
            <a href="{{ route('dashboard', ['view' => 'client']) }}" class="text-xs font-bold text-slate-500 hover:text-white transition-colors uppercase tracking-widest">Client View</a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs font-bold text-red-500/80 hover:text-red-500 transition-colors uppercase tracking-widest">Sign Out</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-6 py-12">
        
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
            <div>
                <h2 class="text-4xl font-extrabold text-white tracking-tight">Client Infrastructure</h2>
                <p class="text-slate-400 mt-2 text-lg">Manage all client organizations, quotas, and service configurations.</p>
            </div>
            <button onclick="openAddModal()" class="px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-2xl shadow-xl shadow-blue-600/20 transform transition active:scale-[0.98]">
                + ADD NEW CLIENT
            </button>
        </div>

        @if (session('success'))
            <div class="mb-10 p-5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-2xl flex items-center shadow-lg animate-pulse">
                <span class="font-bold tracking-tight">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-10 p-5 bg-red-500/10 border border-red-500/30 text-red-400 rounded-2xl flex items-center shadow-lg animate-pulse">
                <span class="font-bold tracking-tight">{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-10 p-5 bg-red-500/10 border border-red-500/30 text-red-400 rounded-2xl flex flex-col shadow-lg">
                <span class="font-bold tracking-tight mb-2">කරුණාකර පහත දෝෂ නිවැරදි කරන්න:</span>
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Management Table -->
        <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-2xl">
            <div class="p-8 border-b border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h3 class="text-xl font-bold text-white">Active Organizations</h3>
                <div class="relative w-full md:w-72">
                    <input type="text" id="userSearch" onkeyup="searchUsers()" placeholder="Search by name or email..." 
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm text-slate-300 focus:ring-1 focus:ring-blue-500 outline-none">
                    <svg class="absolute right-3 top-2.5 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
            
            <table class="w-full text-left border-collapse" id="userTable">
                <thead>
                    <tr class="bg-slate-900/50 border-b border-slate-800">
                        <th class="px-8 py-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">Organization / Client</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">Status</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">WhatsApp ID</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-500 uppercase tracking-widest">Balance (LKR)</th>
                        <th class="px-8 py-6 text-[10px] font-black text-slate-500 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                    <tr class="border-b border-slate-800/50 hover:bg-slate-800/20 transition-all group">
                        <td class="px-8 py-6">
                            <div class="flex flex-col">
                                <span class="text-white font-bold group-hover:text-blue-400 transition-colors">{{ $u->name }}</span>
                                <span class="text-[10px] text-slate-500 font-mono">{{ $u->email }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            @if($u->status == 'active')
                                <span class="px-3 py-1 bg-emerald-500/10 text-emerald-500 text-[10px] font-bold rounded-full border border-emerald-500/20 uppercase tracking-widest">ACTIVE</span>
                            @else
                                <span class="px-3 py-1 bg-red-500/10 text-red-500 text-[10px] font-bold rounded-full border border-red-500/20 uppercase tracking-widest">INACTIVE</span>
                            @endif
                        </td>
                        <td class="px-8 py-6">
                            <span class="text-xs font-mono text-slate-400 bg-slate-900/80 px-2 py-1 rounded border border-slate-800">{{ $u->whatsapp_phone_number_id ?: 'UNASSIGNED' }}</span>
                        </td>
                        <td class="px-8 py-6">
                            <span class="text-emerald-400 font-black text-lg">Rs. {{ number_format($u->balance, 2) }}</span>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex items-center justify-end space-x-3">
                                <button onclick='openEditModal(@json($u))' class="p-2 bg-blue-600/10 hover:bg-blue-600/20 text-blue-500 rounded-xl transition font-bold text-xs px-4 py-2 border border-blue-600/20">MANAGE</button>
                                <form action="{{ route('admin.users.delete', $u->id) }}" method="POST" onsubmit="return confirm('SURE ABOUT DELETING THIS CLIENT? ALL DATA WILL BE LOST.');">
                                    @csrf
                                    <button type="submit" class="p-2 bg-red-600/10 hover:bg-red-600/20 text-red-500 rounded-xl transition border border-red-600/20">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add New Client Modal -->
    <div id="addModal" class="fixed inset-0 bg-black/80 backdrop-blur-md hidden z-[100] flex items-center justify-center p-6">
        <div class="glass-card max-w-lg w-full rounded-[3rem] p-10 relative shadow-2xl border-blue-500/20 max-h-[95vh] overflow-y-auto">
            <h3 class="text-2xl font-extrabold text-white mb-8 border-b border-slate-800 pb-4">Register New Client</h3>
            <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Full Organization Name</label>
                        <input type="text" name="name" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Connection Method</label>
                        <select name="connection_type" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition appearance-none">
                            <option value="web_automation">📱 QR Scan Mode (Node.js)</option>
                            <option value="cloud_api">☁️ Cloud API Mode (Meta)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">WhatsApp Phone ID (For Cloud API)</label>
                    <input type="text" name="whatsapp_phone_number_id" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Login Email Address</label>
                    <input type="email" name="email" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Account Password</label>
                        <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Initial Balance (LKR)</label>
                        <input type="number" step="any" name="balance" value="500" required class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Google Sheet Name</label>
                        <input type="text" name="google_sheet_name" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition" placeholder="Optional">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Order API URL</label>
                        <input type="text" name="order_api_url" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition" placeholder="Optional">
                    </div>
                </div>
                <!-- Inventory API -->
                <div class="mb-4 mt-4">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Inventory API URL</label>
                    <input type="text" name="inventory_api_url" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-blue-500 outline-none transition" placeholder="Optional">
                </div>
                <div class="flex space-x-4 pt-6">
                    <button type="button" onclick="closeAddModal()" class="flex-1 py-4 bg-slate-800 hover:bg-slate-700 text-slate-400 rounded-2xl font-bold transition">Cancel</button>
                    <button type="submit" class="flex-1 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-black shadow-lg shadow-blue-600/20 transition">Register Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit/Manage Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/80 backdrop-blur-md hidden z-[100] flex items-center justify-center p-6">
        <div class="glass-card max-w-lg w-full rounded-[3rem] p-10 relative shadow-2xl border-emerald-500/20 max-h-[95vh] overflow-y-auto">
            <h3 class="text-2xl font-extrabold text-white mb-8 border-b border-slate-800 pb-4">Infrastucture Overhaul</h3>
            <form id="editForm" method="POST" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Account Balance (LKR)</label>
                        <input type="number" step="any" name="balance" id="editBalance" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-emerald-400 font-black focus:ring-1 focus:ring-emerald-500 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Connection Method</label>
                        <select name="connection_type" id="editConnectionType" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition appearance-none">
                            <option value="web_automation">📱 QR Scan Mode</option>
                            <option value="cloud_api">☁️ Cloud API Mode</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Operational Status</label>
                        <select name="status" id="editStatus" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition appearance-none">
                            <option value="active">ACTIVE / LIVE</option>
                            <option value="inactive">INACTIVE / FROZEN</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 mb-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">WhatsApp Business ID (For Cloud API)</label>
                        <input type="text" name="whatsapp_phone_number_id" id="editWhatsAppId" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Google Sheet Name</label>
                        <input type="text" name="google_sheet_name" id="editGoogleSheetName" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition" placeholder="Optional">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Order API URL</label>
                        <input type="text" name="order_api_url" id="editOrderApiUrl" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition" placeholder="Optional">
                    </div>
                </div>
                <!-- Inventory API -->
                <div class="mb-4 mt-4">
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Inventory API URL</label>
                    <input type="text" name="inventory_api_url" id="editInventoryApi" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition" placeholder="Optional">
                </div>

                <!-- AI Settings -->
                <div class="border-t border-slate-800 pt-4 mt-4">
                    <label class="flex items-center space-x-3 cursor-pointer mb-4">
                        <input type="checkbox" name="is_autoreply_enabled" id="editIsAutoReply" value="1" class="w-5 h-5 rounded border-slate-700 bg-slate-900 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-slate-900">
                        <span class="text-sm font-bold text-white">Enable Smart Auto-Reply (AI Bot)</span>
                    </label>

                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">WhatsApp Access Token (Meta API)</label>
                    <input type="text" name="target_api_key" id="editTargetApiKey" placeholder="EAAPuQ0pZB..." class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition mb-4">

                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">First Auto-Reply Greeting</label>
                    <textarea name="autoreply_message" id="editAutoMessage" rows="2" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition placeholder-slate-600" placeholder="e.g. Thanks for contacting us!"></textarea>
                </div>

                <div class="flex space-x-4 pt-4">
                    <button type="button" onclick="closeEditModal()" class="flex-1 py-4 bg-slate-800 hover:bg-slate-700 text-slate-400 rounded-2xl font-bold transition">Cancel</button>
                    <button type="submit" class="flex-1 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-black shadow-lg shadow-emerald-600/20 transition">Commit Overhaul</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); }
        function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }
        
        function openEditModal(user) {
            document.getElementById('editForm').action = "{{ url('/admin/users') }}/" + user.id + "/update";
            document.getElementById('editBalance').value = user.balance;
            document.getElementById('editConnectionType').value = user.connection_type || 'web_automation';
            document.getElementById('editWhatsAppId').value = user.whatsapp_phone_number_id || '';
            document.getElementById('editStatus').value = user.status;
            document.getElementById('editTargetApiKey').value = user.target_api_key || '';
            document.getElementById('editGoogleSheetName').value = user.google_sheet_name || '';
            document.getElementById('editOrderApiUrl').value = user.order_api_url || '';
            document.getElementById('editInventoryApi').value = user.inventory_api_url || '';
            document.getElementById('editAutoMessage').value = user.autoreply_message || '';
            document.getElementById('editIsAutoReply').checked = user.is_autoreply_enabled;
            document.getElementById('editModal').classList.remove('hidden');
        }
        function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

        function searchUsers() {
            let input = document.getElementById('userSearch');
            let filter = input.value.toLowerCase();
            let table = document.getElementById('userTable');
            let tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    let text = td.textContent || td.innerText;
                    if (text.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>
