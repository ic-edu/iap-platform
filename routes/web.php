<?php

use App\Http\Controllers\Admin\MonitoringDashboardController;
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

    if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
        return redirect()->route('admin.monitoring.index');
    }

    return redirect()->route('dashboard');
});

// Observability Probes
Route::get('/health', [HealthCheckController::class, 'health']);
Route::get('/ready', [HealthCheckController::class, 'ready']);
Route::get('/live', [HealthCheckController::class, 'live']);

// Admin Monitoring Dashboard
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/admin/monitoring', [MonitoringDashboardController::class, 'index'])
        ->name('admin.monitoring.index');
});
