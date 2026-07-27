<?php

use App\Modules\Certificate\Controllers\CertificateAdminController;
use App\Modules\Certificate\Controllers\PublicVerificationController;
use Illuminate\Support\Facades\Route;

// Public Verification Portal (No Auth Required)
Route::get('/verify', [PublicVerificationController::class, 'show'])->name('public.verify');
Route::get('/verify/{code}', [PublicVerificationController::class, 'show'])->name('public.verify.code');

// Candidate Certificate Download
Route::get('/candidate/certificates/{certificate}/download', [CertificateAdminController::class, 'download'])
    ->middleware('auth')
    ->name('candidate.certificates.download');

// Admin Certificate Management
Route::middleware('auth')->prefix('admin/certificates')->group(function () {
    Route::get('/', [CertificateAdminController::class, 'index'])->name('admin.certificates.index');
    Route::post('/{certificate}/reissue', [CertificateAdminController::class, 'reissue'])->name('admin.certificates.reissue');
    Route::post('/{certificate}/revoke', [CertificateAdminController::class, 'revoke'])->name('admin.certificates.revoke');
    Route::get('/{certificate}/download', [CertificateAdminController::class, 'download'])->name('admin.certificates.download');
});
