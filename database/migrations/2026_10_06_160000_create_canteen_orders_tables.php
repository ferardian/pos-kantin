<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canteen_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('order_type', ['delivery', 'pickup'])->default('delivery');
            $table->string('delivery_location')->nullable(); // e.g. "Mess Putra - Kamar 3", "Poli Anak", etc.
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->enum('payment_method', ['bon', 'qris', 'cash'])->default('bon');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->enum('status', [
                'pending',
                'confirmed',
                'preparing',
                'on_delivery',
                'ready_for_pickup',
                'completed',
                'cancelled'
            ])->default('pending');
            $table->integer('total_items')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('canteen_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canteen_order_id')->constrained('canteen_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('unit_name');
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('notes')->nullable(); // e.g. "pedas sedang", "es sedikit"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canteen_order_items');
        Schema::dropIfExists('canteen_orders');
    }
};
