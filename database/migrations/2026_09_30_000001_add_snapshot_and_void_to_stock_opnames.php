<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah qty_snapshot: stok sistem saat MULAI opname (bukan saat terapkan)
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('qty_snapshot', 15, 2)->default(0)->after('qty_system')
                ->comment('Stok fisik sistem saat sesi opname dimulai (snapshot)');
        });

        // Tambah kolom void ke dokumen opname
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->boolean('is_voided')->default(false)->after('status');
            $table->timestamp('voided_at')->nullable()->after('is_voided');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete()->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn('qty_snapshot');
        });
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['is_voided', 'voided_at', 'voided_by', 'void_reason']);
        });
    }
};
