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
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = trim($request->input('username'));
        $password = $request->input('password');
        $remember = $request->boolean('remember', true);

        // Attempt login by username first, fallback to email
        $attempt = Auth::attempt(['username' => $loginInput, 'password' => $password], $remember)
                || Auth::attempt(['email' => $loginInput, 'password' => $password], $remember);

        if ($attempt) {
            $user = Auth::user();
            if (!$user->is_active) {
                Auth::logout();
                return back()->withErrors([
                    'username' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Administrator.',
                ]);
            }

            $request->session()->regenerate();
            
            if ($user->role === 'sales') {
                return redirect()->intended(route('sales.index'));
            }
            return redirect()->intended(route('pos.index'));
        }

        return back()->withErrors([
            'username' => 'Username atau kata sandi yang Anda masukkan salah.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
