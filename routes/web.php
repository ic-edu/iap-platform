<?php

use App\Http\Controllers\Admin\MonitoringDashboardController;
use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
