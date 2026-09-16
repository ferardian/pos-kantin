<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        DB::table('users')->insert([
            'name'       => 'Admin Koperasi',
            'email'      => 'admin@rsia-aisyiyah.com',
            'password'   => Hash::make('password123'),
            'role'       => 'admin',
            'phone'      => '08111000001',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Kasir default
        DB::table('users')->insert([
            'name'       => 'Kasir Kantin',
            'email'      => 'kasir@rsia-aisyiyah.com',
            'password'   => Hash::make('kasir123'),
            'role'       => 'kasir',
            'phone'      => '08111000002',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Categories
        $categories = [
            ['name' => 'Makanan', 'slug' => 'makanan', 'icon' => '🍱'],
            ['name' => 'Minuman', 'slug' => 'minuman', 'icon' => '🥤'],
            ['name' => 'Perlengkapan Bayi', 'slug' => 'perlengkapan-bayi', 'icon' => '🍼'],
            ['name' => 'Kebutuhan Umum', 'slug' => 'kebutuhan-umum', 'icon' => '🛒'],
            ['name' => 'Obat Bebas', 'slug' => 'obat-bebas', 'icon' => '💊'],
            ['name' => 'Snack', 'slug' => 'snack', 'icon' => '🍪'],
        ];
        foreach ($categories as $cat) {
            DB::table('categories')->insert(array_merge($cat, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Cashbox default
        DB::table('cashboxes')->insert([
            'name'           => 'Kas Utama Kantin',
            'type'           => 'cash',
            'account_number' => 'KAS-01',
            'balance'        => 0,
            'is_default'     => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Settings default
        $settings = [
            ['key' => 'store_name',         'value' => 'Koperasi RSIA Aisyiyah Pekajangan'],
            ['key' => 'store_address',      'value' => 'Jl. Pekajangan, Pekalongan'],
            ['key' => 'store_phone',        'value' => '0285-000000'],
            ['key' => 'receipt_footer',     'value' => 'Terima kasih telah berbelanja di Koperasi RSIA Aisyiyah'],
            ['key' => 'default_print_format', 'value' => 'thermal'],
            ['key' => 'logo_url',           'value' => ''],
        ];
        foreach ($settings as $s) {
            DB::table('settings')->insert(array_merge($s, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
