<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('contact_person')->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('type')->default('store');
                $table->text('address')->nullable();
                $table->boolean('is_default')->default(true);
                $table->timestamps();
            });

            // Default location
            DB::table('locations')->insert([
                'name' => 'Kantin Utama',
                'code' => 'KNT-01',
                'type' => 'store',
                'address' => 'Gedung Koperasi RSIA Aisyiyah',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasTable('product_locations')) {
            Schema::create('product_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->decimal('stock_physical', 12, 2)->default(0);
                $table->decimal('min_stock', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'location_id']);
            });
        }

        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number')->unique();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->string('supplier_name');
                $table->string('supplier_invoice_number')->nullable();
                $table->date('receipt_date');
                $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->decimal('total_cost_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_unit_id')->constrained('product_units')->cascadeOnDelete();
                $table->decimal('qty_received', 10, 2);
                $table->decimal('conversion_ratio', 10, 2)->default(1);
                $table->decimal('base_qty_added', 10, 2);
                $table->decimal('cost_price_per_unit', 15, 2)->default(0);
                $table->decimal('subtotal_cost', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('product_locations');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('suppliers');
    }
};
