<?php

use App\Modules\Assessment\Controllers\CandidatePortalController;
use App\Modules\Assessment\Controllers\TestBuilderController;
use Illuminate\Support\Facades\Route;

// Admin & Teacher Test Builder Routes
Route::middleware(['web', 'auth', 'role:teacher|admin|super-admin|repository-manager'])->prefix('admin/tests')->group(function () {
    Route::get('/', [TestBuilderController::class, 'index'])->name('admin.tests.index');
    Route::post('/', [TestBuilderController::class, 'store'])->name('admin.tests.store');
    Route::get('/{test}', [TestBuilderController::class, 'show'])->name('admin.tests.show');
    Route::post('/{test}/duplicate', [TestBuilderController::class, 'duplicate'])->name('admin.tests.duplicate');
    Route::post('/{test}/submit-approval', [TestBuilderController::class, 'submitForApproval'])->name('admin.tests.submit-approval');
    Route::post('/{test}/publish', [TestBuilderController::class, 'publish'])->name('admin.tests.publish');
    Route::post('/{test}/sections/{section}/media', [TestBuilderController::class, 'attachSectionMedia'])->name('admin.tests.sections.media.attach');
    Route::delete('/{test}/sections/{section}/media/{media}', [TestBuilderController::class, 'detachSectionMedia'])->name('admin.tests.sections.media.detach');
    Route::delete('/{test}/sections/{section}', [TestBuilderController::class, 'destroySection'])->name('admin.tests.sections.destroy');
    Route::delete('/{test}', [TestBuilderController::class, 'destroy'])->name('admin.tests.destroy');
    Route::post('/{test}/assign-candidate', [TestBuilderController::class, 'assignCandidate'])->name('admin.tests.assign-candidate');
    Route::delete('/{test}/unassign-candidate/{user}', [TestBuilderController::class, 'unassignCandidate'])->name('admin.tests.unassign-candidate');
});

// Candidate Portal & CBT Delivery Engine Routes
Route::middleware(['web', 'auth'])->prefix('candidate')->group(function () {
    Route::get('/portal', [CandidatePortalController::class, 'portal'])->name('candidate.portal');
    Route::get('/available-tests', [CandidatePortalController::class, 'availableTests'])->name('candidate.available-tests');
    Route::get('/my-attempts', [CandidatePortalController::class, 'myAttempts'])->name('candidate.my-attempts');
    Route::get('/my-certificates', [CandidatePortalController::class, 'myCertificates'])->name('candidate.my-certificates');

    Route::get('/tests/{test}/instructions', [CandidatePortalController::class, 'instructions'])->name('candidate.tests.instructions');
    Route::post('/tests/{test}/start', [CandidatePortalController::class, 'startAttempt'])->name('candidate.tests.start');
    Route::get('/exam/{attempt}', [CandidatePortalController::class, 'exam'])->name('candidate.exam');
    Route::post('/exam/{attempt}/autosave', [CandidatePortalController::class, 'autoSave'])->name('candidate.exam.autosave');
    Route::post('/exam/{attempt}/flag', [CandidatePortalController::class, 'toggleFlag'])->name('candidate.exam.flag');
    Route::post('/exam/{attempt}/violation', [CandidatePortalController::class, 'recordViolation'])->name('candidate.exam.violation');
    Route::get('/exam/{attempt}/questions/{question}/audio-stream', [CandidatePortalController::class, 'streamAudio'])->name('candidate.exam.audio-stream');
    Route::post('/exam/{attempt}/submit', [CandidatePortalController::class, 'submit'])->name('candidate.exam.submit');
    Route::get('/exam/{attempt}/review', [CandidatePortalController::class, 'review'])->name('candidate.review');
});
