<?php

use App\Modules\Commerce\Controllers\CandidateCommerceController;
use App\Modules\Commerce\Controllers\CommerceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:admin|super-admin|finance'])->prefix('admin/commerce')->group(function () {
    Route::get('/', [CommerceController::class, 'index'])->name('admin.commerce.index');
    Route::post('/vouchers', [CommerceController::class, 'storeVoucher'])->name('admin.commerce.vouchers.store');
});

Route::middleware(['web', 'auth'])->prefix('candidate')->name('candidate.')->group(function () {
    // Store & Product Detail
    Route::get('/store', [CandidateCommerceController::class, 'store'])->name('store');
    Route::get('/store/{product}', [CandidateCommerceController::class, 'showProduct'])->name('store.show');

    // Checkout
    Route::get('/checkout/{product}', [CandidateCommerceController::class, 'checkoutForm'])->name('checkout.show');
    Route::post('/checkout/{product}', [CandidateCommerceController::class, 'processCheckout'])->name('checkout.process');

    // Orders
    Route::get('/orders', [CandidateCommerceController::class, 'myOrders'])->name('orders.index');
    Route::get('/orders/{order}', [CandidateCommerceController::class, 'showOrder'])->name('orders.show');

    // Invoices
    Route::get('/invoices', [CandidateCommerceController::class, 'myInvoices'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [CandidateCommerceController::class, 'showInvoice'])->name('invoices.show');
    Route::post('/invoices/{invoice}/pay', [CandidateCommerceController::class, 'initiatePayment'])->name('invoices.pay');

    // Payments
    Route::get('/payments', [CandidateCommerceController::class, 'myPayments'])->name('payments.index');
    Route::get('/payments/{payment}', [CandidateCommerceController::class, 'showPayment'])->name('payments.show');
    Route::post('/payments/{payment}/proof', [CandidateCommerceController::class, 'uploadProof'])->name('payments.proof');
});

