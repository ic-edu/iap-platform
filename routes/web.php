<?php

use App\Http\Controllers\Admin\MonitoringDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HealthCheckController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = Auth::user();

    if ($user->hasRole('student')) {
        return redirect()->route('candidate.portal');
    }

    if ($user->hasRole('teacher')) {
        return redirect()->route('admin.question-banks.index');
    }

    if ($user->hasRole('finance')) {
        return redirect()->route('admin.commerce.index');
    }

    if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
        return redirect()->route('admin.monitoring.index');
    }

    return redirect()->route('candidate.portal');
});

// Observability Probes
Route::get('/health', [HealthCheckController::class, 'health']);
Route::get('/ready', [HealthCheckController::class, 'ready']);
Route::get('/live', [HealthCheckController::class, 'live']);

// Admin Monitoring Dashboard & User Management (Protected with role middleware)
Route::middleware(['web', 'auth', 'role:admin|super-admin'])->group(function () {
    Route::get('/admin/monitoring', [MonitoringDashboardController::class, 'index'])
        ->name('admin.monitoring.index');

    Route::prefix('admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
