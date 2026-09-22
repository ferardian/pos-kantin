<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('product_units')->whereNull('price_employee')->update(['price_employee' => 0]);

        Schema::table('product_units', function (Blueprint $table) {
            $table->decimal('price_employee', 15, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->decimal('price_employee', 15, 2)->nullable()->change();
        });
    }
};
