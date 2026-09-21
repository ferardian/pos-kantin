<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Admin has access to everything
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Check dynamic restriction for kasir on products routes (default diizinkan)
        if ($user->role === 'kasir' && ($request->is('products*') || $request->is('brands*') || $request->is('categories*') || $request->is('units*'))) {
            $canAccess = \App\Models\Setting::get('kasir_can_access_products', '1');
            if ($canAccess === '0' || $canAccess === false) {
                return redirect()->route('pos.index')->with('error', 'Akses ditolak: Akun Kasir tidak memiliki izin untuk membuka Master Produk.');
            }
        }

        // Check if user's role is in the permitted roles
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // If unauthorized, redirect to their home page based on role with warning
        if ($user->role === 'sales') {
            return redirect()->route('sales.index')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk membuka halaman tersebut.');
        }

        if ($user->role === 'gudang') {
            return redirect()->route('orders.index')->with('error', 'Akses ditolak: Staf Gudang hanya berwenang untuk Antrean Pesanan & Stok Barang.');
        }

        if ($user->role === 'kasir') {
            return redirect()->route('pos.index')->with('error', 'Akses ditolak: Halaman ini hanya dapat diakses oleh Admin.');
        }

        abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}
