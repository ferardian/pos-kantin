<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::withCount(['transactions'])
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'authUserId' => Auth::id(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'role' => 'required|in:admin,kasir,gudang',
            'password' => 'required|string|min:6',
            'is_active' => 'boolean',
        ]);

        $validated['username'] = strtolower(trim($validated['username']));
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        User::create($validated);

        return back()->with('success', "Pengguna {$validated['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:50',
            'role' => 'required|in:admin,kasir,gudang',
            'password' => 'nullable|string|min:6',
            'is_active' => 'boolean',
        ]);

        $validated['username'] = strtolower(trim($validated['username']));

        // Prevent admin from deactivating or demoting themselves
        if ($user->id === Auth::id()) {
            $validated['is_active'] = true;
            $validated['role'] = 'admin';
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun yang sedang digunakan.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $statusStr = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Status akun {$user->name} berhasil {$statusStr}.");
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'new_password' => 'required|string|min:6',
        ]);

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return back()->with('success', "Password untuk pengguna {$user->name} berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // Check if user has related records (transactions or sales orders)
        $hasTransactions = $user->transactions()->exists();
        $hasOrders = method_exists($user, 'salesOrders') && $user->salesOrders()->exists();

        if ($hasTransactions || $hasOrders) {
            // Soft deactivation to maintain historical integrity
            $user->is_active = false;
            $user->save();
            return back()->with('success', "Akun {$user->name} dinonaktifkan karena memiliki riwayat transaksi sebelumnya.");
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', "Akun pengguna {$userName} berhasil dihapus permanen.");
    }
}
