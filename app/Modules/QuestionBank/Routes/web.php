<?php

use App\Modules\QuestionBank\Controllers\QuestionBankController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin/question-banks')->group(function () {
    Route::get('/', [QuestionBankController::class, 'index'])->name('admin.question-banks.index');
    Route::post('/', [QuestionBankController::class, 'store'])->name('admin.question-banks.store');
    Route::post('/{questionBank}/duplicate', [QuestionBankController::class, 'duplicate'])->name('admin.question-banks.duplicate');
    Route::delete('/{questionBank}', [QuestionBankController::class, 'destroy'])->name('admin.question-banks.destroy');
});
