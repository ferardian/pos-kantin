<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashboxController;
use App\Http\Controllers\ConsignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeReceivableController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\CanteenOrderManageController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::middleware('role:kasir')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    // Buku Kas
    Route::middleware('role:kasir')->group(function () {
        Route::get('/cashboxes', [CashboxController::class, 'index'])->name('cashboxes.index');
        Route::post('/cashboxes', [CashboxController::class, 'store'])->name('cashboxes.store');
        Route::post('/cashboxes/transaction', [CashboxController::class, 'storeTransaction'])->name('cashboxes.transaction');
        Route::post('/cashboxes/transfer', [CashboxController::class, 'transferBalance'])->name('cashboxes.transfer');
    });

    // Titip Jual / Konsinyasi (Jajan Penitip)
    Route::middleware('role:kasir')->group(function () {
        Route::get('/consignments', [ConsignmentController::class, 'index'])->name('consignments.index');
        Route::post('/consignments/batches', [ConsignmentController::class, 'storeBatch'])->name('consignments.batches.store');
        Route::post('/consignments/batches/{id}/settle', [ConsignmentController::class, 'settleBatch'])->name('consignments.batches.settle');
        Route::delete('/consignments/batches/{id}', [ConsignmentController::class, 'destroyBatch'])->name('consignments.batches.destroy');
        Route::post('/consignments/consignors', [ConsignmentController::class, 'storeConsignor'])->name('consignments.consignors.store');
        Route::put('/consignments/consignors/{id}', [ConsignmentController::class, 'updateConsignor'])->name('consignments.consignors.update');
        Route::delete('/consignments/consignors/{id}', [ConsignmentController::class, 'destroyConsignor'])->name('consignments.consignors.destroy');
        // Consignment Products (Katalog Jajan)
        Route::post('/consignments/products', [ConsignmentController::class, 'storeConsignmentProduct'])->name('consignments.products.store');
        Route::put('/consignments/products/{id}', [ConsignmentController::class, 'updateConsignmentProduct'])->name('consignments.products.update');
        Route::delete('/consignments/products/{id}', [ConsignmentController::class, 'destroyConsignmentProduct'])->name('consignments.products.destroy');
        // Batch Susulan & Merge
        Route::post('/consignments/batches/{id}/add-items', [ConsignmentController::class, 'addItemsToBatch'])->name('consignments.batches.add-items');
        Route::post('/consignments/batches/merge', [ConsignmentController::class, 'mergeBatches'])->name('consignments.batches.merge');
        Route::delete('/consignments/batches/{batchId}/items/{itemId}', [ConsignmentController::class, 'destroyBatchItem'])->name('consignments.batches.items.destroy');
        Route::put('/consignments/batches/{batchId}/items/{itemId}', [ConsignmentController::class, 'updateBatchItem'])->name('consignments.batches.items.update');
    });

    // POS Kasir Kantin
    Route::middleware('role:kasir')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/checkout', [PosController::class, 'store'])->name('pos.checkout');
    });

    // Pesanan Online Karyawan (Dari Mess / Ruangan)
    Route::middleware('role:kasir')->group(function () {
        Route::get('/canteen-orders', [CanteenOrderManageController::class, 'index'])->name('canteen-orders.index');
        Route::post('/canteen-orders/{id}/status', [CanteenOrderManageController::class, 'updateStatus'])->name('canteen-orders.update-status');
        Route::get('/canteen-orders/check-pending', [CanteenOrderManageController::class, 'checkPendingCount'])->name('canteen-orders.check-pending');
    });

    // Penerimaan Barang (Belanja Stok Masuk) & Master Supplier
    Route::middleware('role:admin,kasir,gudang')->group(function () {
        Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
        Route::post('/goods-receipts/{id}/approve', [GoodsReceiptController::class, 'approve'])->name('goods-receipts.approve');
        Route::post('/goods-receipts/{id}/reject', [GoodsReceiptController::class, 'reject'])->name('goods-receipts.reject');
        Route::post('/suppliers', [GoodsReceiptController::class, 'storeSupplier'])->name('suppliers.store');
    });

    // Master Produk, Kategori, Merek & Stok (Bisa diakses Kasir, Gudang, Admin)
    Route::middleware('role:gudang,kasir')->group(function () {
        Route::get('/products/export-excel', [ProductController::class, 'exportExcel'])->name('products.exportExcel');
        Route::post('/products/generate-all-barcodes', [ProductController::class, 'generateAllBarcodes'])->name('products.generateAllBarcodes');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/brands', [ProductController::class, 'storeBrand'])->name('brands.store');
        Route::put('/brands/{id}', [ProductController::class, 'updateBrand'])->name('brands.update');
        Route::delete('/brands/{id}', [ProductController::class, 'destroyBrand'])->name('brands.destroy');
        Route::post('/categories', [ProductController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{id}', [ProductController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{id}', [ProductController::class, 'destroyCategory'])->name('categories.destroy');
        Route::post('/units', [ProductController::class, 'storeUnit'])->name('units.store');
        Route::put('/units/{id}', [ProductController::class, 'updateUnit'])->name('units.update');
        Route::delete('/units/{id}', [ProductController::class, 'destroyUnit'])->name('units.destroy');
    });

    // Modul Stok Opname & Riwayat Dokumen Audit (Gudang, Admin & Kasir jika diizinkan Admin)
    Route::middleware('role:gudang,kasir')->group(function () {
        Route::get('/stock-opnames', [StockOpnameController::class, 'index'])->name('stock-opnames.index');
        Route::post('/stock-opnames', [StockOpnameController::class, 'store'])->name('stock-opnames.store');
        Route::post('/stock-opnames/{id}/void', [StockOpnameController::class, 'void'])->name('stock-opnames.void');
        Route::post('/stock-opnames/items/{id}/void', [StockOpnameController::class, 'voidItem'])->name('stock-opnames.items.void');
    });

    // Penyesuaian / Edit Stok Fisik (Hanya Gudang & Admin - Kasir Tetap Dilarang)
    Route::middleware('role:gudang')->group(function () {
        Route::post('/products/{id}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjustStock');
    });

    // Piutang Karyawan (Data Pegawai tersinkronisasi dari RSIA API)
    Route::middleware('role:kasir')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees/sync', [EmployeeController::class, 'sync'])->name('employees.sync');
        Route::get('/api/employees', [EmployeeController::class, 'apiList'])->name('api.employees');

        Route::get('/receivables', [EmployeeReceivableController::class, 'index'])->name('receivables.index');
        Route::post('/receivables', [EmployeeReceivableController::class, 'store'])->name('receivables.store');
        Route::post('/receivables/{receivable}/pay', [EmployeeReceivableController::class, 'pay'])->name('receivables.pay');
    });

    // Rekap Setoran & Detail Penjualan Shift Kasir (Kasir & Admin)
    Route::middleware('role:kasir,admin')->group(function () {
        Route::get('/cashier/settlement', [\App\Http\Controllers\CashierSettlementController::class, 'index'])->name('cashier.settlement');
        Route::get('/cashier/settlement/export-excel', [\App\Http\Controllers\CashierSettlementController::class, 'exportExcel'])->name('cashier.settlement.exportExcel');
        Route::get('/api/cashier/current-shift', [\App\Http\Controllers\CashierSettlementController::class, 'currentShift'])->name('cashier.settlement.currentShift');
    });

    // Laporan & Analisis Omset Keseluruhan (Khusus Admin / Keuangan Eksekutif)
    Route::middleware('role:admin')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export-excel', [ReportController::class, 'exportExcel'])->name('reports.exportExcel');
        Route::get('/reports/export-settlement', [ReportController::class, 'exportSettlementExcel'])->name('reports.exportSettlement');
    });

    // Pengaturan & Pengguna (Admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->name('users.resetPassword');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
