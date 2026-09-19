<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashboxController;
use App\Http\Controllers\ConsignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeReceivableController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
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
        Route::post('/consignments/consignors', [ConsignmentController::class, 'storeConsignor'])->name('consignments.consignors.store');
        Route::put('/consignments/consignors/{id}', [ConsignmentController::class, 'updateConsignor'])->name('consignments.consignors.update');
        Route::delete('/consignments/consignors/{id}', [ConsignmentController::class, 'destroyConsignor'])->name('consignments.consignors.destroy');
    });

    // POS Kasir Kantin
    Route::middleware('role:kasir')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/checkout', [PosController::class, 'store'])->name('pos.checkout');
    });

    // Penerimaan Barang (Belanja Stok Masuk) & Master Supplier
    Route::middleware('role:admin,kasir,gudang')->group(function () {
        Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
        Route::post('/goods-receipts/{id}/approve', [GoodsReceiptController::class, 'approve'])->name('goods-receipts.approve');
        Route::post('/goods-receipts/{id}/reject', [GoodsReceiptController::class, 'reject'])->name('goods-receipts.reject');
        Route::post('/suppliers', [GoodsReceiptController::class, 'storeSupplier'])->name('suppliers.store');
    });

    // Master Produk, Kategori, Merek & Stok
    Route::middleware('role:gudang,kasir')->group(function () {
        Route::get('/products/export-excel', [ProductController::class, 'exportExcel'])->name('products.exportExcel');
        Route::post('/products/generate-all-barcodes', [ProductController::class, 'generateAllBarcodes'])->name('products.generateAllBarcodes');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{id}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjustStock');
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

    // Piutang Karyawan (Data Pegawai tersinkronisasi dari RSIA API)
    Route::middleware('role:kasir')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees/sync', [EmployeeController::class, 'sync'])->name('employees.sync');
        Route::get('/api/employees', [EmployeeController::class, 'apiList'])->name('api.employees');

        Route::get('/receivables', [EmployeeReceivableController::class, 'index'])->name('receivables.index');
        Route::post('/receivables', [EmployeeReceivableController::class, 'store'])->name('receivables.store');
        Route::post('/receivables/{receivable}/pay', [EmployeeReceivableController::class, 'pay'])->name('receivables.pay');
    });

    // Laporan, Pengaturan & Pengguna (Admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export-excel', [ReportController::class, 'exportExcel'])->name('reports.exportExcel');
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
