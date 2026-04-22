<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            // Disabling SSL verification ('verify' => false) is a security risk and should be avoided in production.
            $googleUser = Socialite::driver('google')->user();
            
            // User database eke innawada balanna, nathi nam hadanna
            $user = User::updateOrCreate([
                'email' => $googleUser->email,
            ], [
                'name' => $googleUser->name,
                'google_id' => $googleUser->id,
                'password' => Hash::make(Str::random(16)), // Random password ekak
            ]);

            // Meeta kalin api User Model eke boot() method eka haduwa nisa 
            // API Key saha Credits tika auto-generate vevi.

            Auth::login($user);

            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }
            
            return redirect()->intended('/dashboard');
            
        } catch (\Exception $e) {
            Log::error('Google Sign-in Error', ['exception' => $e]);
            return redirect('/login')->with('error', 'Google sign-in failed!');
        }
    }
}