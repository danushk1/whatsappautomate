<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI WhatsApp Bridge | Smart Order Automation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="bg-slate-900 text-white overflow-x-hidden">

    <nav class="flex justify-between items-center p-6 max-w-7xl mx-auto">
        <div class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-transparent">
            GenifyAI Bridge
        </div>
        <div>
            @auth
                <a href="{{ url('/dashboard') }}" class="px-5 py-2 rounded-full border border-blue-400 text-blue-400 hover:bg-blue-400 hover:text-white transition">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="px-6 py-2 bg-white text-slate-900 font-semibold rounded-full hover:bg-gray-200 transition">Get Started</a>
            @endauth
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 pt-20 pb-32 text-center">
        <h1 class="text-5xl md:text-7xl font-extrabold mb-6 tracking-tight">
            Automate WhatsApp Orders <br>
            <span class="text-blue-500">With AI Magic.</span>
        </h1>
        <p class="text-gray-400 text-lg md:text-xl max-w-2xl mx-auto mb-10">
            Convert Singlish/English WhatsApp messages into structured Excel rows or API calls instantly. No manual typing. No mistakes.
        </p>

        {{-- <div class="inline-block p-1 rounded-3xl bg-gradient-to-r from-blue-500 via-purple-500 to-emerald-500 shadow-2xl">
            <div class="bg-slate-800 rounded-[22px] px-8 py-10">
               
                
                {{-- <a href="{{ route('google.login') }}" class="flex items-center justify-center space-x-3 bg-white text-slate-900 px-8 py-3 rounded-xl font-bold hover:scale-105 transition transform duration-200 shadow-lg">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="20" alt="Google">
                    <span>Continue with Google</span>
                </a> 
                
                <p class="mt-4 text-sm text-gray-500 italic">No credit card required • 50 Free Credits</p>
            </div>
        </div> --}}
<div class="inline-block p-1 rounded-3xl bg-gradient-to-r from-blue-500 via-purple-500 to-emerald-500 shadow-2xl">
    <div class="bg-slate-800 rounded-[22px] px-8 py-10 w-full max-w-sm">
        <h3 class="text-xl font-semibold mb-6">Access your Bridge</h3>
        
        <div class="flex flex-col space-y-4">
            <a href="{{ route('login') }}" class="flex items-center justify-center bg-blue-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-blue-700 transition transform hover:scale-105 duration-200 shadow-lg">
                <span>Login to Account</span>
            </a>

            <a href="{{ url('/auth/google') }}" class="flex items-center justify-center bg-transparent border border-slate-600 text-gray-300 px-8 py-3 rounded-xl font-bold hover:bg-slate-700 transition duration-200">
                <svg viewBox="0 0 48 48" class="w-5 h-5 mr-3"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path></svg>
                <span>Create with Google</span>
            </a>
        </div>
        
        <p class="mt-6 text-sm text-gray-500 italic">Manage your WhatsApp automation settings</p>
    </div>
</div>
        <div class="grid md:grid-cols-3 gap-8 mt-32 text-left">
            <div class="p-8 rounded-2xl bg-slate-800 border border-slate-700 hover:border-blue-500 transition">
                <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h4 class="text-xl font-bold mb-2">Instant AI Parsing</h4>
                <p class="text-gray-400 text-sm">Our AI understands Singlish. "Mata shirt 2k danna" becomes an order in 0.5 seconds.</p>
            </div>

            <div class="p-8 rounded-2xl bg-slate-800 border border-slate-700 hover:border-emerald-500 transition">
                <div class="w-12 h-12 bg-emerald-500/20 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h4 class="text-xl font-bold mb-2">Google Sheets Sync</h4>
                <p class="text-gray-400 text-sm">Don't have a system? No problem. We update your Excel sheet automatically in the cloud.</p>
            </div>

            <div class="p-8 rounded-2xl bg-slate-800 border border-slate-700 hover:border-purple-500 transition">
                <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h4 class="text-xl font-bold mb-2">Developer Friendly</h4>
                <p class="text-gray-400 text-sm">Already have an ERP? Use our Webhook forwarding to push data directly to your API.</p>
            </div>
        </div>
    </main>

    <footer class="p-10 text-center text-gray-600 border-t border-slate-800">
        &copy; 2026 GenifyAI Bridge. Built for Sri Lankan Entrepreneurs.
    </footer>

</body>
</html>