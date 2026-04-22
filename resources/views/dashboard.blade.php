<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | GenifyAI Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .tab-active { color: #10b981; border-bottom: 2px solid #10b981; }
        .input-focus { focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all; }
    </style>
</head>

<body class="bg-[#020617] text-slate-200 min-h-screen">

    <!-- Navigation -->
    <nav class="bg-slate-900/40 border-b border-slate-800 p-4 sticky top-0 z-50 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h1 class="text-xl font-extrabold tracking-tight text-white">GenifyAI <span class="text-emerald-400">Bridge</span></h1>
            </div>
            <div class="flex items-center space-x-6">
                <div class="hidden sm:flex items-center space-x-2 px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 rounded-full">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                    <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider">System Live</span>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Sign Out</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-6 py-12">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-12">
            <div>
                <h2 class="text-4xl font-extrabold text-white tracking-tight">Organization Console</h2>
                <p class="text-slate-400 mt-2 text-lg">Configure your AI automation and real-time data bridging.</p>
            </div>
            <div class="bg-slate-900/50 p-4 rounded-2xl border border-slate-800 text-right">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Authenticated Account</p>
                <div class="flex items-center space-x-3 justify-end">
                    <span class="text-emerald-400 font-bold text-lg">{{ $user->name }}</span>
                    <div class="w-8 h-8 bg-gradient-to-tr from-emerald-500 to-teal-400 rounded-full"></div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-10 p-5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-2xl flex items-center shadow-lg shadow-emerald-500/5">
                <div class="w-8 h-8 bg-emerald-500/20 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span class="font-bold tracking-tight">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <div class="glass-card p-6 rounded-3xl group">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Orders Today</p>
                <div class="flex items-end space-x-2">
                    <span class="text-4xl font-extrabold text-white">{{ $totalOrders }}</span>
                    <span class="text-emerald-400 text-xs font-bold mb-1">+12%</span>
                </div>
            </div>
            <div class="glass-card p-6 rounded-3xl border-l-4 border-l-emerald-500">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Account Balance (LKR)</p>
                <div class="flex items-baseline space-x-2">
                    <span class="text-4xl font-extrabold text-white">Rs. {{ number_format($user->balance, 2) }}</span>
                </div>
            </div>
            <div class="glass-card p-6 rounded-3xl">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Bridge Status</p>
                <span class="text-xl font-bold text-emerald-400 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    Operational
                </span>
            </div>
            <div class="glass-card p-6 rounded-3xl bg-emerald-500/5">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Plan Mode</p>
                <span class="text-xl font-extrabold text-white">ENTERPRISE</span>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            
            <div class="lg:col-span-8 space-y-8">
                <div class="glass-card rounded-[2rem] overflow-hidden">
                    <!-- Tab Headers -->
                    <div class="flex border-b border-slate-800 px-8">
                        <button onclick="switchTab('meta')" id="tab-meta" class="px-6 py-6 text-sm font-bold transition-all border-b-2 border-transparent hover:text-white text-slate-400 tab-active">WhatsApp Setup</button>
                        <button onclick="switchTab('target')" id="tab-target" class="px-6 py-6 text-sm font-bold transition-all border-b-2 border-transparent hover:text-white text-slate-400">Destination Settings</button>
                        <button onclick="switchTab('profile')" id="tab-profile" class="px-6 py-6 text-sm font-bold transition-all border-b-2 border-transparent hover:text-white text-slate-400">Company Profile</button>
                        <button onclick="switchTab('security')" id="tab-security" class="px-6 py-6 text-sm font-bold transition-all border-b-2 border-transparent hover:text-white text-slate-400">Security / Keys</button>
                        <button onclick="switchTab('auto')" id="tab-auto" class="px-6 py-6 text-sm font-bold transition-all border-b-2 border-transparent hover:text-white text-slate-400">Auto Automation</button>
                        <button type="button" onclick="switchTab('billing')" id="tab-billing" class="px-6 py-6 text-sm font-bold transition-all border-b-2 border-transparent hover:text-emerald-400 text-slate-400">Billing Setup</button>
                    </div>
                    
                    <form action="{{ route('settings.update') }}" method="POST" id="mainForm" class="p-10">
                        @csrf

                        <!-- Tab: Meta Setup -->
                        <div id="section-meta" class="space-y-8">
                            <!-- Connection Type Display -->
                            <div class="p-6 bg-slate-900/50 border border-slate-800 rounded-2xl">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Connection Method</label>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        @if($user->connection_type === 'web_automation')
                                            <span class="px-4 py-2 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400 text-sm font-bold flex items-center">
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                📱 QR Scan Mode
                                            </span>
                                            @if($user->whatsapp_connected_at)
                                                <span class="text-xs text-emerald-400 flex items-center">
                                                    <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2"></span>
                                                    Connected {{ $user->whatsapp_connected_at->diffForHumans() }}
                                                </span>
                                            @else
                                                <span class="text-xs text-amber-400">Not connected</span>
                                            @endif
                                        @else
                                            <span class="px-4 py-2 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-sm font-bold flex items-center">
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                                                ☁️ Cloud API Mode
                                            </span>
                                        @endif
                                    </div>
                                    @if($user->connection_type === 'web_automation')
                                        <a href="{{ route('whatsapp.connect') }}" 
                                           class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition shadow-lg shadow-emerald-500/20">
                                            @if($user->whatsapp_connected_at)
                                                🔄 Manage Connection
                                            @else
                                                📲 Connect WhatsApp
                                            @endif
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 text-emerald-400">WhatsApp Phone Number ID </label>
                                    <input type="text" name="whatsapp_phone_number_id" value="{{ $user->whatsapp_phone_number_id }}"
                                        class="w-full bg-slate-900/80 border border-slate-800 rounded-2xl px-5 py-4 text-slate-500 cursor-not-allowed font-mono text-xs"
                                        placeholder="Assigned by Admin" readonly>
                                    <p class="text-[10px] text-slate-500 mt-2 font-medium">Contact administration to update this ID.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Public WhatsApp Number</label>
                                    <input type="text" name="whatsapp_number" value="{{ $user->whatsapp_number }}"
                                        class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                        placeholder="+94 77 XXXXXXX">
                                    <p class="text-[10px] text-slate-500 mt-2 font-medium">This is the number customers send messages to.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Target Settings -->
                        <div id="section-target" class="hidden space-y-8">
                            <div class="p-6 bg-emerald-500/5 border border-emerald-500/10 rounded-2xl relative">
                                <label class="block text-xs font-bold text-emerald-400 uppercase tracking-widest mb-4">Data Destinations (Select Both if needed)</label>
                                <p class="text-[10px] text-slate-500 mb-4">Note: If you enable both destinations, an order saved to both will consume 2 credits.</p>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label class="relative flex items-center p-4 border border-slate-800 rounded-xl cursor-pointer hover:bg-slate-800/30 transition group">
                                        <input type="checkbox" id="toggle_sheet" onchange="toggleTargetFields()" class="w-4 h-4 text-emerald-500 focus:ring-emerald-500 bg-slate-900 border-slate-700" {{ $user->google_sheet_name ? 'checked' : '' }}>
                                        <div class="ml-4">
                                            <span class="block text-sm font-bold text-white">Save to Google Sheets</span>
                                            <span class="block text-[10px] text-slate-500">Fast, accessible and ready for Excel processing.</span>
                                        </div>
                                    </label>
                                    <label class="relative flex items-center p-4 border border-slate-800 rounded-xl cursor-pointer hover:bg-slate-800/30 transition group">
                                        <input type="checkbox" id="toggle_api" onchange="toggleTargetFields()" class="w-4 h-4 text-emerald-500 focus:ring-emerald-500 bg-slate-900 border-slate-700" {{ $user->order_api_url ? 'checked' : '' }}>
                                        <div class="ml-4">
                                            <span class="block text-sm font-bold text-white">Save to Custom API</span>
                                            <span class="block text-[10px] text-slate-500">Connect to your own POS, ERP or custom website.</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Google Sheets Fields -->
                            <div id="fields-excel" class="{{ $user->google_sheet_name ? '' : 'hidden' }} space-y-8">
                                <div class="bg-indigo-500/5 border border-indigo-500/10 p-6 rounded-2xl flex items-start">
                                    <div class="w-10 h-10 bg-indigo-500/20 rounded-xl flex items-center justify-center mr-4 shrink-0">
                                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-2">Google Sheets Access Required</p>
                                        <p class="text-sm text-slate-400">Share your target Google Sheet with 'Editor' permissions to our Service Automator:</p>
                                        <div class="mt-3 flex items-center space-x-2">
                                            <code class="bg-slate-950 px-3 py-2 rounded-lg text-emerald-400 text-xs font-mono select-all">sheet-bot@my-ai-bridge-491318.iam.gserviceaccount.com</code>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Google Sheet Name</label>
                                    <input type="text" name="google_sheet_name" id="google_sheet_name" value="{{ $user->google_sheet_name }}"
                                        class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                        placeholder="Enter the exact name of your Google Sheet">
                                    <div class="mt-4 flex flex-col items-start gap-2">
                                        <p class="text-[10px] text-slate-500 font-medium">To work perfectly, your sheet must contain specific columns (like Date, Name, Quantity, Total).</p>
                                        <a href="https://docs.google.com/spreadsheets/d/1JWEat7YSYctFnbnJR_KvwZaDTV7axzvJGjCJ4xNSMCg/edit?usp=sharing" target="_blank" class="inline-flex items-center space-x-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 px-4 py-2 rounded-lg text-emerald-400 text-xs font-bold transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                            <span>Download / View Sample Sheet Template</span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- API Fields -->
                            <div id="fields-api" class="{{ $user->order_api_url ? '' : 'hidden' }} space-y-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Order API URL (To Save Orders)</label>
                                        <input type="text" name="order_api_url" id="order_api_url" value="{{ $user->order_api_url }}"
                                            class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                            placeholder="https://yourwebsite.com/api/orders">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Inventory API URL (For Stock Check)</label>
                                        <input type="text" name="inventory_api_url" id="inventory_api_url" value="{{ $user->inventory_api_url }}"
                                            class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                            placeholder="https://yourwebsite.com/api/products">
                                    </div>
                                    <div class="col-span-1 md:col-span-2">
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Your Website API Key</label>
                                        <input type="text" name="target_api_key" value="{{ $user->target_api_key }}"
                                            class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                            placeholder="X-API-KEY Value">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab: Profile Settings -->
                        <div id="section-profile" class="hidden space-y-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Company Display Name</label>
                                    <input type="text" name="name" value="{{ $user->name }}"
                                        class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Email Address</label>
                                    <input type="email" value="{{ $user->email }}" disabled
                                        class="w-full bg-slate-900/80 border border-slate-800 rounded-2xl px-5 py-4 text-slate-500 cursor-not-allowed">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Physical / Head Office Address</label>
                                <input type="text" name="address" value="{{ $user->address }}"
                                    class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                    placeholder="Enter your company address">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Company Information / Description</label>
                                <textarea name="company_details" rows="6" 
                                    class="w-full bg-slate-900/50 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                    placeholder="Briefly describe your company for better AI processing context...">{{ $user->company_details }}</textarea>
                            </div>
                        </div>

                        <!-- Tab: Security Settings -->
                        <div id="section-security" class="hidden space-y-8">
                            <div class="p-8 bg-slate-900/50 border border-slate-800 rounded-[2rem]">
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    <h5 class="text-sm font-bold text-white uppercase tracking-widest">Main Bridge API Key</h5>
                                </div>
                                <p class="text-xs text-slate-500 mb-6 leading-relaxed">This key is used by the Python Bridge to authorize data transfers. Keep it secure and never share it publicly.</p>
                                <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                                    <input type="password" id="bridgeKey" readonly value="{{ $user->api_key }}"
                                        class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-5 py-4 text-blue-400 font-mono text-sm focus:outline-none">
                                    <button type="button" onclick="copyBridgeKey()" class="bg-slate-800 hover:bg-slate-700 px-8 py-4 rounded-xl font-bold transition-all flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                        Copy Secret Key
                                    </button>
                                </div>
                                <button type="button" onclick="toggleBridgeKey()" class="mt-6 text-[10px] font-bold text-slate-500 hover:text-white underline">Show/Hide Secret Key</button>
                            </div>
                        </div>

                    <!-- Tab: Auto Automation -->
                    <div id="section-auto" class="hidden space-y-8">
                        <div class="p-8 bg-slate-900/50 border border-slate-800 rounded-[2rem]">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                                <div>
                                    <h3 class="text-lg font-extrabold text-white tracking-tight">Auto-Reply Configuration</h3>
                                    <p class="text-xs text-slate-500 mt-1">Automatically respond to incoming customer messages.</p>
                                </div>
                                @if(!$user->has_claimed_autoreply_bonus)
                                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-3 rounded-xl text-xs font-bold flex items-center shadow-lg shadow-emerald-500/5">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"></path></svg>
                                    Activation Bonus: Get 20 FREE Credits on first activation!
                                </div>
                                @endif
                            </div>

                            <div class="space-y-6">
                                <label class="relative inline-flex items-center cursor-pointer group">
                                    <input type="checkbox" name="is_autoreply_enabled" value="1" class="sr-only peer" {{ $user->is_autoreply_enabled ? 'checked' : '' }}>
                                    <div class="w-14 h-7 bg-slate-800 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-400 peer-checked:after:bg-white after:border-slate-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-500"></div>
                                    <span class="ml-4 text-sm font-bold text-white uppercase tracking-widest group-hover:text-emerald-400 transition-colors">Enable Auto-Reply</span>
                                </label>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Custom Reply Message</label>
                                    <textarea name="autoreply_message" rows="4" 
                                        class="w-full bg-slate-950 border border-slate-800 rounded-2xl px-5 py-4 text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all placeholder-slate-600"
                                        placeholder="e.g., Thank you for your order! We are processing it.">{{ $user->autoreply_message }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Billing Details -->
                    <div id="section-billing" class="hidden space-y-8">
                        <div class="p-8 bg-slate-900/50 border border-slate-800 rounded-[2rem]">
                            <div class="mb-8">
                                <h3 class="text-lg font-extrabold text-white tracking-tight">Real-Time Cost Analysis</h3>
                                <p class="text-xs text-slate-500 mt-1">Review the active costs running per transaction from your Rs. {{ number_format($user->balance, 2) }} balance.</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Order Save Cost -->
                                <div class="p-6 border {{ $user->order_api_url || $user->google_sheet_name ? 'border-emerald-500/50 bg-emerald-500/5' : 'border-slate-800 bg-slate-900/50' }} rounded-2xl relative overflow-hidden transition-all duration-300">
                                    <div class="flex items-center space-x-3 mb-4">
                                        <div class="w-10 h-10 {{ $user->order_api_url || $user->google_sheet_name ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-500' }} rounded-xl flex items-center justify-center">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </div>
                                        <h4 class="font-bold text-white">Order Extraction</h4>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-xs text-slate-400">Status: <span class="font-bold {{ $user->order_api_url || $user->google_sheet_name ? 'text-emerald-400' : 'text-slate-500' }}">{{ $user->order_api_url || $user->google_sheet_name ? 'ACTIVE' : 'INACTIVE' }}</span></p>
                                        <p class="text-xs text-slate-400">Flat Rate Deduction:</p>
                                        <h2 class="text-2xl font-black text-white mt-2">Rs. 5.00 <span class="text-xs font-medium text-slate-500">/ per order</span></h2>
                                    </div>
                                </div>
                                
                                <!-- AI Token Cost -->
                                <div class="p-6 border {{ $user->is_autoreply_enabled ? 'border-purple-500/50 bg-purple-500/5' : 'border-slate-800 bg-slate-900/50' }} rounded-2xl relative overflow-hidden transition-all duration-300">
                                    <div class="flex items-center space-x-3 mb-4">
                                        <div class="w-10 h-10 {{ $user->is_autoreply_enabled ? 'bg-purple-500/20 text-purple-400' : 'bg-slate-800 text-slate-500' }} rounded-xl flex items-center justify-center">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        </div>
                                        <h4 class="font-bold text-white">Smart AI Reply</h4>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-xs text-slate-400">Status: <span class="font-bold {{ $user->is_autoreply_enabled ? 'text-purple-400' : 'text-slate-500' }}">{{ $user->is_autoreply_enabled ? 'ACTIVE' : 'INACTIVE' }}</span></p>
                                        <p class="text-xs text-slate-400">Token-Based Deduction:</p>
                                        <h2 class="text-2xl font-black text-white mt-2">Rs. 0.0001 <span class="text-xs font-medium text-slate-500">/ per token</span></h2>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 p-5 rounded-xl border border-blue-500/20 bg-blue-500/5 flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-blue-500/20 shrink-0 flex justify-center items-center font-black text-blue-400">i</div>
                                <div class="text-xs text-slate-300 leading-relaxed">
                                    <p class="font-bold text-white mb-1">How Token Pricing Works</p>
                                    <p>A standard conversation consumes about 500 tokens (for reading context + generating reply). This equates to roughly <strong>Rs. 0.05 LKR</strong> total. Your initial signup balance provides approximately 10,000 automated intelligent replies completely free!</p>
                                </div>
                            </div>

                            @if($user->balance < 100)
                            <div class="mt-6 p-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 flex items-center gap-3 font-bold text-sm">
                                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                <span>Low Balance Alert: Top up your account soon to avoid service disruption! Contact Administration to recharge your balance.</span>
                            </div>
                            @endif

                        </div>
                    </div>                        <div class="mt-12">
                            <button type="submit" onclick="prepareSubmit()" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-black py-5 rounded-2xl text-xl shadow-2xl shadow-emerald-500/20 transform transition active:scale-[0.98]">
                                COMMIT ALL CHANGES
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Last 7 Days Activity -->
                <div class="glass-card p-10 rounded-[2rem]">
                    <div class="flex items-center justify-between mb-8">
                        <h4 class="text-2xl font-extrabold text-white">Network Flow <span class="text-slate-500 font-normal">/ Active Timeline</span></h4>
                        <div class="flex items-center space-x-2">
                            <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase">Incoming Streams</span>
                        </div>
                    </div>
                    <div class="h-[340px]">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="lg:col-span-4 space-y-6">
                <div class="glass-card p-8 rounded-[2.5rem] bg-gradient-to-br from-emerald-500/10 to-teal-500/5 relative overflow-hidden">
                    <div class="relative z-10">
                        <h3 class="text-emerald-400 font-black text-lg flex items-center mb-6">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            Deployment Guide
                        </h3>
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div class="w-6 h-6 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-xs font-bold mr-4">1</div>
                                <p class="text-sm text-slate-400 leading-relaxed"><span class="text-white font-bold">Connect your sheet:</span> Share it with our service bot to allow AI data writes.</p>
                            </div>
                            <div class="flex items-start">
                                <div class="w-6 h-6 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-xs font-bold mr-4">2</div>
                                <p class="text-sm text-slate-400 leading-relaxed"><span class="text-white font-bold">Point Webhooks:</span> In Meta Dashboard, set your endpoint to the Bridge callback URL.</p>
                            </div>
                            <div class="flex items-start">
                                <div class="w-6 h-6 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-xs font-bold mr-4">3</div>
                                <p class="text-sm text-slate-400 leading-relaxed"><span class="text-white font-bold">Test AI Flow:</span> Send a WhatsApp message to yourself as a customer would.</p>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-emerald-500/5 rounded-full blur-3xl"></div>
                </div>

                <div class="glass-card p-8 rounded-[2.5rem]">
                    <h5 class="text-sm font-black text-slate-500 uppercase tracking-widest mb-4">Support Ecosystem</h5>
                    <div class="space-y-4">
                        <a href="#" class="block p-4 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded-2xl transition">
                            <span class="block text-xs font-bold text-white mb-1">Developer Documentation</span>
                            <span class="block text-[10px] text-slate-500">API guides and integration tutorials</span>
                        </a>
                        <a href="#" class="block p-4 bg-slate-900 hover:bg-slate-800 border border-slate-800 rounded-2xl transition">
                            <span class="block text-xs font-bold text-white mb-1">Priority Helpdesk</span>
                            <span class="block text-[10px] text-slate-500">24/7 technical support for Enterprise</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('[id^="section-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="tab-"]').forEach(el => el.classList.remove('tab-active'));
            
            document.getElementById('section-' + tab).classList.remove('hidden');
            document.getElementById('tab-' + tab).classList.add('tab-active');
        }

        function toggleTargetFields() {
            const sheetChecked = document.getElementById('toggle_sheet').checked;
            const apiChecked = document.getElementById('toggle_api').checked;
            
            const excelSect = document.getElementById('fields-excel');
            const apiSect = document.getElementById('fields-api');
            
            if (sheetChecked) {
                excelSect.classList.remove('hidden');
            } else {
                excelSect.classList.add('hidden');
                document.getElementById('google_sheet_name').value = '';
            }

            if (apiChecked) {
                apiSect.classList.remove('hidden');
            } else {
                apiSect.classList.add('hidden');
                document.getElementById('order_api_url').value = '';
            }
        }

        function prepareSubmit() {
            // No longer needed
        }

        function toggleBridgeKey() {
            const keyEl = document.getElementById('bridgeKey');
            if (keyEl.type === 'password') {
                keyEl.type = 'text';
            } else {
                keyEl.type = 'password';
            }
        }

        function copyBridgeKey() {
            const keyEl = document.getElementById('bridgeKey');
            const originalType = keyEl.type;
            keyEl.type = 'text';
            keyEl.select();
            document.execCommand('copy');
            keyEl.type = originalType;
            alert('Bridge API Key copied to clipboard!');
        }

        // Chart Initialization
        const ctx = document.getElementById('ordersChart').getContext('2d');
        const labels = {!! json_encode($chartData->pluck('date')) !!};
        const counts = {!! json_encode($chartData->pluck('count')) !!};

        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Incoming Orders',
                    data: counts,
                    borderColor: '#3b82f6',
                    borderWidth: 4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#0f172a',
                    pointBorderWidth: 4,
                    pointRadius: 6,
                    pointHoverRadius: 9,
                    tension: 0.4,
                    fill: true,
                    backgroundColor: gradient
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 10, weight: '700' }, stepSize: 1 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 10, weight: '700' } }
                    }
                }
            }
        });
    </script>
</body>
</html>