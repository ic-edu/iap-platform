<?php

use App\Modules\Commerce\Controllers\CommerceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:admin|super-admin|finance'])->prefix('admin/commerce')->group(function () {
    Route::get('/', [CommerceController::class, 'index'])->name('admin.commerce.index');
    Route::post('/vouchers', [CommerceController::class, 'storeVoucher'])->name('admin.commerce.vouchers.store');
});
