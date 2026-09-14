<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->intended(route('pos.index'));
        }
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->remember ?? true)) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            if ($user->role === 'sales') {
                return redirect()->intended(route('sales.index'));
            }
            return redirect()->intended(route('pos.index'));
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ]);
    }

    public function quickLogin(Request $request)
    {
        $request->validate(['role' => 'required|string']);
        $user = User::where('role', $request->role)->first();
        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();
            if ($user->role === 'sales') {
                return redirect()->route('sales.index');
            }
            return redirect()->route('pos.index');
        }
        return back();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
