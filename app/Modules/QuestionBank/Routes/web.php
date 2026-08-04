<?php

use App\Modules\QuestionBank\Controllers\QuestionBankController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:teacher|admin|super-admin'])->prefix('admin/question-banks')->group(function () {
    Route::get('/', [QuestionBankController::class, 'index'])->name('admin.question-banks.index');
    Route::post('/', [QuestionBankController::class, 'store'])->name('admin.question-banks.store');
    Route::get('/{questionBank}', [QuestionBankController::class, 'show'])->name('admin.question-banks.show');
    Route::put('/{questionBank}', [QuestionBankController::class, 'update'])->name('admin.question-banks.update');
    Route::post('/{questionBank}/submit', [QuestionBankController::class, 'submitForApproval'])->name('admin.question-banks.submit');
    Route::post('/{questionBank}/review', [QuestionBankController::class, 'review'])->name('admin.question-banks.review');
    Route::post('/{questionBank}/request-revision', [QuestionBankController::class, 'requestRevision'])->name('admin.question-banks.request-revision');
    Route::post('/{questionBank}/publish', [QuestionBankController::class, 'publish'])->name('admin.question-banks.publish');
    Route::post('/{questionBank}/unpublish', [QuestionBankController::class, 'unpublish'])->name('admin.question-banks.unpublish');
    Route::post('/{questionBank}/request-archive', [QuestionBankController::class, 'requestArchive'])->name('admin.question-banks.request-archive');
    Route::post('/{questionBank}/request-restore', [QuestionBankController::class, 'requestRestore'])->name('admin.question-banks.request-restore');
    Route::post('/{questionBank}/approve-restore', [QuestionBankController::class, 'approveRestore'])->name('admin.question-banks.approve-restore');
    Route::post('/versions/{version}/rollback', [QuestionBankController::class, 'rollbackVersion'])->name('admin.question-banks.rollback-version');

    Route::post('/{questionBank}/questions', [QuestionBankController::class, 'storeQuestion'])->name('admin.question-banks.store-question');
    Route::put('/questions/{question}', [QuestionBankController::class, 'updateQuestion'])->name('admin.question-banks.update-question');
    Route::post('/questions/{question}/duplicate', [QuestionBankController::class, 'duplicateQuestion'])->name('admin.question-banks.duplicate-question');
    Route::post('/{questionBank}/import', [QuestionBankController::class, 'importQuestions'])->name('admin.question-banks.import');
    Route::post('/{questionBank}/duplicate', [QuestionBankController::class, 'duplicate'])->name('admin.question-banks.duplicate');
    Route::delete('/questions/{question}', [QuestionBankController::class, 'destroyQuestion'])->name('admin.question-banks.destroy-question');
    Route::delete('/{questionBank}', [QuestionBankController::class, 'destroy'])->name('admin.question-banks.destroy');
});
