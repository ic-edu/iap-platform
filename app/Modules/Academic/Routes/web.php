<?php

use App\Modules\Academic\Controllers\AcademicController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:admin|super-admin|teacher'])->prefix('admin/academic')->group(function () {
    Route::get('/', function () {
        $user = Auth::user();
        if ($user && $user->hasRole('teacher')) {
            return redirect()->route('teacher.dashboard');
        }
        return redirect()->route('admin.academic-operations.courses');
    })->name('admin.academic.index');

    Route::get('/workspace/{course}', function ($course) {
        $user = Auth::user();
        if ($user && $user->hasRole('teacher')) {
            return redirect()->route('teacher.dashboard');
        }
        return redirect()->route('admin.academic-operations.courses');
    })->name('admin.academic.workspace');

    Route::post('/courses', [AcademicController::class, 'storeCourse'])->name('admin.academic.courses.store');
    Route::delete('/courses/{course}', [AcademicController::class, 'destroyCourse'])->name('admin.academic.courses.destroy');
});
