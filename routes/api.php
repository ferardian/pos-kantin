<?php

use App\Http\Controllers\Api\CanteenApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes untuk Aplikasi Mobile Karyawan RSIA
|--------------------------------------------------------------------------
*/

Route::prefix('kantin')->group(function () {
    // Katalog Makanan/Minuman & Lokasi RSIA
    Route::get('/menu', [CanteenApiController::class, 'menu']);
    Route::get('/locations', [CanteenApiController::class, 'locations']);

    // Order Mandiri dari Mess / Ruangan
    Route::post('/orders', [CanteenApiController::class, 'createOrder']);
    Route::get('/orders/my-orders', [CanteenApiController::class, 'myOrders']);
    Route::get('/orders/{orderNumber}', [CanteenApiController::class, 'showOrder']);
    Route::post('/orders/{orderNumber}/cancel', [CanteenApiController::class, 'cancelOrder']);
});
