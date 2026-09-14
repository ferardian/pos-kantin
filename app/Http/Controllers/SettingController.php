<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Index', [
            'settings' => Setting::getSettings(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'store_name' => 'required|string|max:100',
            'store_tagline' => 'nullable|string|max:200',
            'store_address' => 'required|string|max:500',
            'store_phone' => 'required|string|max:50',
            'store_email' => 'nullable|email|max:100',
            'receipt_footer' => 'nullable|string|max:1000',
            'invoice_terms' => 'nullable|string|max:2000',
            'bank_info' => 'nullable|string|max:1000',
            'default_print_format' => 'required|in:thermal,invoice,dot_matrix',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'remove_logo' => 'nullable|boolean',
            'kasir_can_access_products' => 'nullable|boolean',
            'kasir_can_see_cost_price' => 'nullable|boolean',
        ]);

        if ($request->boolean('remove_logo')) {
            $oldLogo = Setting::get('store_logo');
            if ($oldLogo && str_starts_with($oldLogo, '/uploads/logo/') && file_exists(public_path(ltrim($oldLogo, '/')))) {
                @unlink(public_path(ltrim($oldLogo, '/')));
            }
            Setting::set('store_logo', null);
        } elseif ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/logo');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            $oldLogo = Setting::get('store_logo');
            if ($oldLogo && str_starts_with($oldLogo, '/uploads/logo/') && file_exists(public_path(ltrim($oldLogo, '/')))) {
                @unlink(public_path(ltrim($oldLogo, '/')));
            }
            
            $file->move($destinationPath, $filename);
            Setting::set('store_logo', '/uploads/logo/' . $filename);
        }

        foreach (['store_name', 'store_tagline', 'store_address', 'store_phone', 'store_email', 'receipt_footer', 'invoice_terms', 'bank_info', 'default_print_format'] as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::set($key, $validated[$key]);
            }
        }

        Setting::set('kasir_can_access_products', $request->boolean('kasir_can_access_products') ? '1' : '0');
        Setting::set('kasir_can_see_cost_price', $request->boolean('kasir_can_see_cost_price') ? '1' : '0');

        return back()->with('success', 'Pengaturan toko, hak akses kasir, dan format cetak berhasil diperbarui.');
    }
}
