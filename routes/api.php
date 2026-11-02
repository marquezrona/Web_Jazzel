<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\MobileController;
use App\Http\Controllers\Cashier\SaleController;
use App\Http\Controllers\SalesReportExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [MobileController::class, 'updateProfile']);

    Route::get('/dashboard', [MobileController::class, 'dashboard']);
    Route::get('/products', [MobileController::class, 'products']);
    Route::get('/sales', [MobileController::class, 'sales']);
    Route::get('/reports', [MobileController::class, 'report']);
    Route::get('/reports/export/pdf', [SalesReportExportController::class, 'cashierPdf']);
    Route::get('/reports/export/excel', [SalesReportExportController::class, 'cashierExcel']);
    Route::post('/checkout', [SaleController::class, 'store'])->middleware('role:kasir');
    Route::post('/nfc/balance', [SaleController::class, 'nfcBalance'])->middleware('role:kasir');
    Route::get('/customers', [MobileController::class, 'customers'])->middleware('role:kasir');
    Route::post('/customers', [MobileController::class, 'storeCustomer'])->middleware('role:kasir');
    Route::post('/customers/{customer}/top-up', [MobileController::class, 'topUp'])->middleware('role:kasir');

    Route::middleware('role:admin')->group(function () {
        Route::post('/products', [MobileController::class, 'storeProduct']);
        Route::put('/products/{product}', [MobileController::class, 'updateProduct']);
        Route::delete('/products/{product}', [MobileController::class, 'destroyProduct']);
        Route::get('/admin/cashiers', [MobileController::class, 'cashiers']);
        Route::post('/admin/cashiers', [MobileController::class, 'storeCashier']);
        Route::get('/admin/members', [MobileController::class, 'members']);
        Route::get('/admin/settings', [MobileController::class, 'settings']);
        Route::put('/admin/settings', [MobileController::class, 'updateSettings']);
        Route::put('/admin/account', [MobileController::class, 'updateAccount']);
        Route::get('/admin/reports/export/pdf', [SalesReportExportController::class, 'adminPdf']);
        Route::get('/admin/reports/export/excel', [SalesReportExportController::class, 'adminExcel']);
    });

    Route::apiResource('books', BookController::class)
        ->only(['index', 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('books', BookController::class)
            ->only(['store', 'update', 'destroy']);
    });
});
