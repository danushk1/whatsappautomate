<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | GenifyAI Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen">

    <div class="bg-slate-800 p-8 rounded-2xl shadow-2xl w-full max-w-md border border-slate-700">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-white">Welcome Back</h2>
            <p class="text-gray-400 mt-2">Login to manage your AI Bridge</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-500/20 border border-red-500 text-red-400 rounded-lg text-sm">
                {{ $errors->first() }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 bg-red-500/20 border border-red-500 text-red-400 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        <!-- Normal Login Form Hidden -->
        <form action="{{ url('/login') }}" method="POST" class="space-y-6 hidden">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-300">Email Address</label>
                <input type="email" name="email" 
                    class="w-full mt-1 p-3 bg-slate-700 border border-slate-600 rounded-xl text-white focus:outline-none focus:border-blue-500 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300">Password</label>
                <input type="password" name="password" 
                    class="w-full mt-1 p-3 bg-slate-700 border border-slate-600 rounded-xl text-white focus:outline-none focus:border-blue-500 transition">
            </div>

            <button type="submit" 
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition duration-200 transform hover:scale-[1.02]">
                Sign In
            </button>
        </form>

        <div class="mt-4">
            <a href="{{ url('/auth/google') }}" class="w-full flex items-center justify-center gap-3 bg-white text-gray-800 font-bold py-3 px-4 rounded-xl transition duration-200 transform hover:scale-[1.02] shadow-sm">
                <svg viewBox="0 0 48 48" class="w-6 h-6"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path></svg>
                Continue with Google
            </a>
        </div>

        <div class="mt-8 text-center">
            <a href="{{ url('/') }}" class="text-sm text-gray-500 hover:text-gray-300 transition">
                &larr; Back to Home
            </a>
        </div>
    </div>

</body>
</html>