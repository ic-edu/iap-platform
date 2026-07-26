<?php

use App\Modules\Assessment\Controllers\TestBuilderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin/tests')->group(function () {
    Route::get('/', [TestBuilderController::class, 'index'])->name('admin.tests.index');
    Route::post('/', [TestBuilderController::class, 'store'])->name('admin.tests.store');
    Route::post('/{test}/publish', [TestBuilderController::class, 'publish'])->name('admin.tests.publish');
    Route::delete('/{test}', [TestBuilderController::class, 'destroy'])->name('admin.tests.destroy');
});
