<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->boolean('is_voided')->default(false)->after('subtotal_cost_diff');
            $table->timestamp('voided_at')->nullable()->after('is_voided');
            $table->string('void_reason', 500)->nullable()->after('voided_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['is_voided', 'voided_at', 'void_reason']);
        });
    }
};
