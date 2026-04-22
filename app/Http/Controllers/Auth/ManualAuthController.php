<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManualAuthController extends Controller
{
    // Login පේජ් එක පෙන්වීමට
    public function showLogin() {
        return view('auth.login');
    }

    // Login logic එක
    public function login(Request $request) {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            if ($user->is_admin) {
                $user->update(['session_id' => session()->getId()]);
            }
            
            return redirect()->intended('dashboard');
        }

        return back()->withErrors(['email' => 'විස්තර වැරදියි, නැවත උත්සාහ කරන්න.']);
    }

    // Logout logic එක
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}