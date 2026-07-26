<?php

use App\Modules\Assessment\Controllers\CandidatePortalController;
use App\Modules\Assessment\Controllers\TestBuilderController;
use Illuminate\Support\Facades\Route;

// Admin Test Builder Routes
Route::middleware(['web', 'auth'])->prefix('admin/tests')->group(function () {
    Route::get('/', [TestBuilderController::class, 'index'])->name('admin.tests.index');
    Route::post('/', [TestBuilderController::class, 'store'])->name('admin.tests.store');
    Route::post('/{test}/publish', [TestBuilderController::class, 'publish'])->name('admin.tests.publish');
    Route::delete('/{test}', [TestBuilderController::class, 'destroy'])->name('admin.tests.destroy');
});

// Candidate Portal & CBT Delivery Engine Routes
Route::middleware(['web', 'auth'])->prefix('candidate')->group(function () {
    Route::get('/portal', [CandidatePortalController::class, 'portal'])->name('candidate.portal');
    Route::get('/available-tests', [CandidatePortalController::class, 'availableTests'])->name('candidate.available-tests');
    Route::get('/my-attempts', [CandidatePortalController::class, 'myAttempts'])->name('candidate.my-attempts');

    Route::post('/tests/{test}/start', [CandidatePortalController::class, 'startAttempt'])->name('candidate.tests.start');
    Route::get('/exam/{attempt}', [CandidatePortalController::class, 'exam'])->name('candidate.exam');
    Route::post('/exam/{attempt}/autosave', [CandidatePortalController::class, 'autoSave'])->name('candidate.exam.autosave');
    Route::post('/exam/{attempt}/flag', [CandidatePortalController::class, 'toggleFlag'])->name('candidate.exam.flag');
    Route::post('/exam/{attempt}/violation', [CandidatePortalController::class, 'recordViolation'])->name('candidate.exam.violation');
    Route::post('/exam/{attempt}/submit', [CandidatePortalController::class, 'submit'])->name('candidate.exam.submit');
    Route::get('/exam/{attempt}/review', [CandidatePortalController::class, 'review'])->name('candidate.review');
});
