<?php

use App\Modules\Academic\Controllers\AcademicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:admin|super-admin|teacher'])->prefix('admin/academic')->group(function () {
    Route::get('/', [AcademicController::class, 'index'])->name('admin.academic.index');
    Route::post('/courses', [AcademicController::class, 'storeCourse'])->name('admin.academic.courses.store');
    Route::delete('/courses/{course}', [AcademicController::class, 'destroyCourse'])->name('admin.academic.courses.destroy');
});
