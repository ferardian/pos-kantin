<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend users table
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'kasir', 'gudang'])->default('kasir')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');
        });

        // Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        // Brands
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->integer('min_stock')->default(5);
            $table->decimal('stock_physical', 12, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Product Units -- 1 tier harga saja
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit_name'); // Pcs, Lusin, Karton, Porsi, Gelas, Bungkus
            $table->decimal('conversion_ratio', 10, 2)->default(1);
            $table->decimal('cost_price', 15, 2)->default(0);   // Harga Modal
            $table->decimal('price_retail', 15, 2)->default(0); // Harga Jual
            $table->boolean('is_base_unit')->default(false);
            $table->timestamps();
        });

        // Transactions -- POS kantin
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->enum('payment_method', ['cash', 'qris'])->default('cash');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Transaction Items
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->cascadeOnDelete();
            $table->decimal('qty', 10, 2);
            $table->decimal('conversion_ratio', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->timestamps();
        });

        // Employees -- Karyawan RSIA
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Employee Receivables -- Piutang kembalian karyawan
        Schema::create('employee_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('remaining', 15, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->timestamps();
        });

        // Receivable Payments -- Pelunasan piutang karyawan
        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_receivable_id')->constrained('employee_receivables')->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Stock Adjustments
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment'])->default('adjustment');
            $table->decimal('qty_change', 10, 2);
            $table->string('reason');
            $table->timestamps();
        });

        // Cashboxes
        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['cash', 'bank', 'other'])->default('cash');
            $table->string('account_number')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Cash Transactions
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbox_id')->constrained('cashboxes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->string('category');
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Settings
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cashboxes');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('employee_receivables');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};
