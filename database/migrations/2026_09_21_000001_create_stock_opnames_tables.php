<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number', 50)->unique();
            $table->date('opname_date');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->integer('total_items_checked')->default(0);
            $table->integer('total_items_diff')->default(0);
            $table->decimal('total_qty_system', 15, 2)->default(0);
            $table->decimal('total_qty_physical', 15, 2)->default(0);
            $table->decimal('total_qty_diff', 15, 2)->default(0);
            $table->decimal('total_cost_diff', 15, 2)->default(0);
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit_name', 50)->nullable();
            $table->decimal('qty_system', 15, 2)->default(0);
            $table->decimal('qty_physical', 15, 2)->default(0);
            $table->decimal('qty_difference', 15, 2)->default(0);
            $table->decimal('cost_price_per_unit', 15, 2)->default(0);
            $table->decimal('subtotal_cost_diff', 15, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
