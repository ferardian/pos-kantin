<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Seed default kantin units
        $defaultUnits = ['Pcs', 'Porsi', 'Bungkus', 'Gelas', 'Botol', 'Kaleng', 'Dus', 'Pack', 'Cup', 'Piring', 'Kotak'];
        foreach ($defaultUnits as $unit) {
            DB::table('units')->insert([
                'name'       => $unit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
