<?php

use App\Modules\Commerce\Controllers\CandidateCommerceController;
use App\Modules\Commerce\Controllers\CommerceController;
use App\Modules\Commerce\Middleware\EnsureStandaloneCandidate;
use Illuminate\Support\Facades\Route;

// 1. Routine Operational Mutation routes (Operational Admin / RA ONLY)
Route::middleware(['web', 'auth', 'role:admin'])->prefix('admin/commerce')->group(function () {
    // Voucher Campaigns (Primary Generator Engine) - Create route MUST precede {campaign} parameter
    Route::get('/campaigns/create', [CommerceController::class, 'createCampaign'])->name('admin.commerce.campaigns.create');
    Route::post('/campaigns', [CommerceController::class, 'storeCampaign'])->name('admin.commerce.campaigns.store');
    Route::post('/campaigns/{campaign}/toggle', [CommerceController::class, 'toggleCampaignStatus'])->name('admin.commerce.campaigns.toggle');
    Route::delete('/campaigns/{campaign}', [CommerceController::class, 'destroyCampaign'])->name('admin.commerce.campaigns.destroy');

    // Standalone / Legacy Vouchers
    Route::post('/vouchers', [CommerceController::class, 'storeVoucher'])->name('admin.commerce.vouchers.store');
    Route::put('/vouchers/{coupon}', [CommerceController::class, 'updateVoucher'])->name('admin.commerce.vouchers.update');
    Route::post('/vouchers/{coupon}/toggle', [CommerceController::class, 'toggleVoucherStatus'])->name('admin.commerce.vouchers.toggle');
    Route::delete('/vouchers/{coupon}', [CommerceController::class, 'destroyVoucher'])->name('admin.commerce.vouchers.destroy');

    // Product & Package Management
    Route::post('/products', [CommerceController::class, 'storeProduct'])->name('admin.commerce.products.store');
    Route::put('/products/{product}', [CommerceController::class, 'updateProduct'])->name('admin.commerce.products.update');
    Route::post('/products/{product}/toggle', [CommerceController::class, 'toggleProductStatus'])->name('admin.commerce.products.toggle');
    Route::post('/products/{product}/propose-price', [CommerceController::class, 'proposePriceChange'])->name('admin.commerce.products.propose-price');
});

// 2. Read & Oversight routes (Operational Admin & Super Admin Oversight)
Route::middleware(['web', 'auth', 'role:admin|super-admin'])->prefix('admin/commerce')->group(function () {
    Route::get('/', [CommerceController::class, 'index'])->name('admin.commerce.index');
    Route::get('/campaigns/{campaign}', [CommerceController::class, 'showCampaign'])->name('admin.commerce.campaigns.show');
});

Route::middleware(['web', 'auth', 'role:student', EnsureStandaloneCandidate::class])->prefix('candidate')->name('candidate.')->group(function () {
    // Store & Product Detail
    Route::get('/store', [CandidateCommerceController::class, 'store'])->name('store');
    Route::get('/store/{product}', [CandidateCommerceController::class, 'showProduct'])->name('store.show');

    // Checkout & Quote
    Route::get('/checkout/{product}', [CandidateCommerceController::class, 'checkoutForm'])->name('checkout.show');
    Route::post('/checkout/{product}/quote', [CandidateCommerceController::class, 'quote'])->name('checkout.quote');
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
