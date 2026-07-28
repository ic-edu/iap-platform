<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MonitoringDashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\NotificationController;
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

// Teacher & Admin Shared Workspaces
Route::middleware(['web', 'auth', 'role:admin|super-admin|teacher'])->group(function () {
    Route::prefix('admin/media')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('admin.media.index');
        Route::post('/', [MediaController::class, 'store'])->name('admin.media.store');
    });
});

// Admin & Super Admin Workspaces
Route::middleware(['web', 'auth', 'role:admin|super-admin'])->group(function () {
    Route::get('/admin/monitoring', [MonitoringDashboardController::class, 'index'])
        ->name('admin.monitoring.index');

    Route::prefix('admin/approvals')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('admin.approvals.index');
        Route::post('/{test}/approve', [ApprovalController::class, 'approve'])->name('admin.approvals.approve');
        Route::post('/{test}/reject', [ApprovalController::class, 'reject'])->name('admin.approvals.reject');
    });

    Route::prefix('admin/audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    });

    Route::prefix('admin/settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/', [SettingsController::class, 'update'])->name('admin.settings.update');
    });

    Route::prefix('admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
});
