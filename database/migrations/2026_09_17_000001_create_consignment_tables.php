<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Penitip (Consignor / Vendor Jajan)
        Schema::create('consignors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tambah kolom consignor_id & is_consignment ke tabel products
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('consignor_id')->nullable()->after('brand_id')->constrained('consignors')->nullOnDelete();
            $table->boolean('is_consignment')->default(false)->after('consignor_id');
        });

        // 3. Sesi / Batch Titipan Harian
        Schema::create('consignment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->foreignId('consignor_id')->constrained('consignors')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Kasir penerima
            $table->date('dropoff_date');
            $table->dateTime('settlement_date')->nullable();
            $table->integer('total_qty_dropped')->default(0);
            $table->integer('total_qty_sold')->default(0);
            $table->integer('total_qty_returned')->default(0);
            $table->decimal('total_payable', 15, 2)->default(0); // Uang yang diserahkan ke penitip
            $table->decimal('total_canteen_profit', 15, 2)->default(0); // Laba kantin
            $table->foreignId('cashbox_id')->nullable()->constrained('cashboxes')->nullOnDelete();
            $table->enum('status', ['active', 'settled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Rincian Item Jajan dalam Sesi Titipan
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_batch_id')->constrained('consignment_batches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->integer('qty_dropped')->default(0);
            $table->integer('qty_sold')->default(0);
            $table->integer('qty_returned')->default(0);
            $table->decimal('cost_price', 15, 2)->default(0); // Harga setor penitip per pcs
            $table->decimal('selling_price', 15, 2)->default(0); // Harga jual kantin per pcs
            $table->decimal('subtotal_payable', 15, 2)->default(0); // qty_sold * cost_price
            $table->decimal('subtotal_profit', 15, 2)->default(0); // qty_sold * (selling_price - cost_price)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignment_batches');
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['consignor_id']);
            $table->dropColumn(['consignor_id', 'is_consignment']);
        });
        Schema::dropIfExists('consignors');
    }
};
