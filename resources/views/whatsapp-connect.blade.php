<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect WhatsApp | GenifyAI Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
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
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">← Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-6 py-12">
        <div class="glass-card rounded-[2rem] p-10">
            <!-- Header -->
            <div class="text-center mb-10">
                <div class="w-16 h-16 bg-emerald-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <h2 class="text-3xl font-extrabold text-white">Connect Your WhatsApp</h2>
                <p class="text-slate-400 mt-2">Scan the QR code with your phone's WhatsApp to start automation</p>
            </div>

            <!-- Status Display -->
            <div id="connection-status" class="mb-8 p-4 rounded-xl border border-slate-800 bg-slate-900/50 text-center hidden">
                <div class="flex items-center justify-center space-x-3">
                    <div id="status-dot" class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>
                    <span id="status-text" class="text-sm font-bold text-slate-300">Connecting...</span>
                </div>
            </div>

            <!-- QR Code Display Area -->
            <div id="qr-section" class="flex flex-col items-center justify-center">
                <div id="qr-loading" class="text-center">
                    <div class="animate-spin w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-slate-400">Generating QR Code...</p>
                </div>
                
                <div id="qr-container" class="hidden">
                    <div class="bg-white p-6 rounded-2xl shadow-2xl mb-6">
                        <img id="qr-image" src="" alt="WhatsApp QR Code" class="w-64 h-64 mx-auto">
                    </div>
                    <div class="flex items-center space-x-2 text-emerald-400 bg-emerald-500/10 px-4 py-2 rounded-full">
                        <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                        <span class="text-xs font-bold">Scan with WhatsApp → Settings → Linked Devices</span>
                    </div>
                    <p class="text-slate-500 text-xs mt-4 text-center">
                        QR Code refreshes automatically. Keep this page open.
                    </p>
                </div>

                <div id="qr-connected" class="hidden text-center">
                    <div class="w-20 h-20 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-emerald-400 mb-2">✅ Connected Successfully!</h3>
                    <p class="text-slate-400 mb-6">Your WhatsApp is now linked to GenifyAI Bridge.</p>
                    <a href="{{ route('dashboard') }}" class="inline-block bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-8 py-3 rounded-xl transition">
                        Go to Dashboard
                    </a>
                </div>

                <div id="qr-error" class="hidden text-center">
                    <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-red-400 mb-2">Connection Failed</h3>
                    <p id="error-message" class="text-slate-400 mb-6">Could not connect to Node Bridge.</p>
                    <button onclick="initiateConnection()" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-8 py-3 rounded-xl transition">
                        Try Again
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div id="action-buttons" class="flex justify-center space-x-4 mt-8">
                <button id="connect-btn" onclick="initiateConnection()" 
                    class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-8 py-3 rounded-xl transition">
                    🔗 Connect WhatsApp
                </button>
                <button id="disconnect-btn" onclick="disconnectWhatsApp()" 
                    class="bg-red-500/10 hover:bg-red-500/20 text-red-400 font-bold px-8 py-3 rounded-xl border border-red-500/20 transition hidden">
                    Disconnect
                </button>
            </div>
        </div>

        <!-- Instructions -->
        <div class="glass-card rounded-[2rem] p-8 mt-6">
            <h3 class="text-lg font-extrabold text-white mb-4">📖 How to Connect</h3>
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm font-bold mr-4">1</div>
                    <p class="text-sm text-slate-400">Open WhatsApp on your phone</p>
                </div>
                <div class="flex items-start">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm font-bold mr-4">2</div>
                    <p class="text-sm text-slate-400">Tap <strong class="text-white">Menu (⋮)</strong> or <strong class="text-white">Settings</strong></p>
                </div>
                <div class="flex items-start">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm font-bold mr-4">3</div>
                    <p class="text-sm text-slate-400">Select <strong class="text-white">Linked Devices</strong> → <strong class="text-white">Link a Device</strong></p>
                </div>
                <div class="flex items-start">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm font-bold mr-4">4</div>
                    <p class="text-sm text-slate-400">Scan the QR Code shown above</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let pollingInterval = null;

        /**
         * Initiate WhatsApp connection - call Node Bridge
         */
        async function initiateConnection() {
            const connectBtn = document.getElementById('connect-btn');
            const disconnectBtn = document.getElementById('disconnect-btn');
            const qrSection = document.getElementById('qr-section');
            const statusDiv = document.getElementById('connection-status');
            
            // Show loading state
            connectBtn.disabled = true;
            connectBtn.innerHTML = '⏳ Connecting...';
            document.getElementById('qr-loading').classList.remove('hidden');
            document.getElementById('qr-container').classList.add('hidden');
            document.getElementById('qr-connected').classList.add('hidden');
            document.getElementById('qr-error').classList.add('hidden');
            statusDiv.classList.remove('hidden');
            document.getElementById('status-text').textContent = 'Initiating connection...';

            try {
                const response = await fetch('{{ route("whatsapp.initiate") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.qr_data_url) {
                    // Show QR code
                    document.getElementById('qr-image').src = data.qr_data_url;
                    document.getElementById('qr-loading').classList.add('hidden');
                    document.getElementById('qr-container').classList.remove('hidden');
                    document.getElementById('status-text').textContent = 'Scan the QR code with your phone';
                    
                    // Start polling for connection status
                    startPolling();
                } else if (data.error) {
                    showError(data.error);
                }
            } catch (err) {
                showError('Node Bridge is offline. Make sure the Node.js server is running.');
            }

            connectBtn.disabled = false;
            connectBtn.innerHTML = '🔄 Refresh QR Code';
        }

        /**
         * Poll connection status every 3 seconds
         */
        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);

            pollingInterval = setInterval(async () => {
                try {
                    const response = await fetch('{{ route("whatsapp.status") }}', {
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                    });

                    const data = await response.json();

                    if (data.connected) {
                        // Connected!
                        clearInterval(pollingInterval);
                        document.getElementById('qr-container').classList.add('hidden');
                        document.getElementById('qr-connected').classList.remove('hidden');
                        document.getElementById('connection-status').classList.add('hidden');
                        document.getElementById('connect-btn').classList.add('hidden');
                        document.getElementById('disconnect-btn').classList.remove('hidden');
                        document.getElementById('status-dot').className = 'w-3 h-3 bg-emerald-500 rounded-full';
                        document.getElementById('status-text').textContent = '✅ Connected';
                    } else if (data.status === 'disconnected') {
                        // If disconnected, show connect button again
                        clearInterval(pollingInterval);
                        document.getElementById('qr-container').classList.add('hidden');
                        document.getElementById('connect-btn').classList.remove('hidden');
                        document.getElementById('connect-btn').innerHTML = '🔗 Connect WhatsApp';
                    }
                } catch (err) {
                    // Bridge might be temporarily unavailable
                }
            }, 3000);
        }

        /**
         * Disconnect WhatsApp
         */
        async function disconnectWhatsApp() {
            if (!confirm('Are you sure you want to disconnect WhatsApp?')) return;

            try {
                await fetch('{{ route("whatsapp.disconnect") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    },
                });

                if (pollingInterval) clearInterval(pollingInterval);
                
                document.getElementById('qr-connected').classList.add('hidden');
                document.getElementById('disconnect-btn').classList.add('hidden');
                document.getElementById('connect-btn').classList.remove('hidden');
                document.getElementById('connect-btn').innerHTML = '🔗 Connect WhatsApp';
                document.getElementById('connection-status').classList.add('hidden');
            } catch (err) {
                showError('Failed to disconnect');
            }
        }

        /**
         * Show error state
         */
        function showError(message) {
            document.getElementById('qr-loading').classList.add('hidden');
            document.getElementById('qr-container').classList.add('hidden');
            document.getElementById('qr-error').classList.remove('hidden');
            document.getElementById('error-message').textContent = message;
            document.getElementById('connection-status').classList.add('hidden');
            document.getElementById('connect-btn').disabled = false;
            document.getElementById('connect-btn').innerHTML = '🔗 Connect WhatsApp';
        }

        // Auto-connect on page load if not already connected
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                const response = await fetch('{{ route("whatsapp.status") }}', {
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                });
                const data = await response.json();
                
                if (data.connected) {
                    document.getElementById('qr-connected').classList.remove('hidden');
                    document.getElementById('connect-btn').classList.add('hidden');
                    document.getElementById('disconnect-btn').classList.remove('hidden');
                }
            } catch (err) {
                // Bridge offline - show connect button
            }
        });
    </script>
</body>
</html>
