<?php

use App\Modules\QuestionBank\Controllers\QuestionBankController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:teacher|admin|super-admin'])->prefix('admin/question-banks')->group(function () {
    Route::get('/', [QuestionBankController::class, 'index'])->name('admin.question-banks.index');
    Route::post('/', [QuestionBankController::class, 'store'])->name('admin.question-banks.store');
    Route::get('/{questionBank}', [QuestionBankController::class, 'show'])->name('admin.question-banks.show');
    Route::post('/{questionBank}/questions', [QuestionBankController::class, 'storeQuestion'])->name('admin.question-banks.store-question');
    Route::put('/questions/{question}', [QuestionBankController::class, 'updateQuestion'])->name('admin.question-banks.update-question');
    Route::post('/questions/{question}/duplicate', [QuestionBankController::class, 'duplicateQuestion'])->name('admin.question-banks.duplicate-question');
    Route::post('/{questionBank}/import', [QuestionBankController::class, 'importQuestions'])->name('admin.question-banks.import');
    Route::post('/{questionBank}/duplicate', [QuestionBankController::class, 'duplicate'])->name('admin.question-banks.duplicate');
    Route::delete('/questions/{question}', [QuestionBankController::class, 'destroyQuestion'])->name('admin.question-banks.destroy-question');
    Route::delete('/{questionBank}', [QuestionBankController::class, 'destroy'])->name('admin.question-banks.destroy');
});
