<?php

use App\Modules\Certificate\Controllers\CertificateAdminController;
use App\Modules\Certificate\Controllers\PublicVerificationController;
use Illuminate\Support\Facades\Route;

// Public Verification Portal (No Auth Required)
Route::middleware('web')->get('/verify', [PublicVerificationController::class, 'show'])->name('public.verify');

// Admin Certificate Management
Route::middleware(['web', 'auth'])->prefix('admin/certificates')->group(function () {
    Route::get('/', [CertificateAdminController::class, 'index'])->name('admin.certificates.index');
    Route::post('/{certificate}/reissue', [CertificateAdminController::class, 'reissue'])->name('admin.certificates.reissue');
    Route::post('/{certificate}/revoke', [CertificateAdminController::class, 'revoke'])->name('admin.certificates.revoke');
    Route::get('/{certificate}/download', [CertificateAdminController::class, 'download'])->name('admin.certificates.download');
});
