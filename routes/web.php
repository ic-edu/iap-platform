<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MonitoringDashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SuperAdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = Auth::user();

    if ($user->hasRole('super-admin')) {
        return redirect()->route('super-admin.dashboard');
    }

    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasRole('teacher')) {
        return redirect()->route('teacher.dashboard');
    }

    if ($user->hasRole('finance')) {
        return redirect()->route('finance.dashboard');
    }

    return redirect()->route('candidate.portal');
});

Route::get('/dashboard', function () {
    /** @var User|null $user */
    $user = Auth::user();

    if ($user?->hasRole('super-admin')) {
        return redirect()->route('super-admin.dashboard');
    }

    if ($user?->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user?->hasRole('teacher')) {
        return redirect()->route('teacher.dashboard');
    }

    if ($user?->hasRole('finance')) {
        return redirect()->route('finance.dashboard');
    }

    return redirect()->route('candidate.portal');
})->middleware(['web', 'auth'])->name('dashboard');

// Observability Probes
Route::get('/health', [HealthCheckController::class, 'health']);
Route::get('/ready', [HealthCheckController::class, 'ready']);
Route::get('/live', [HealthCheckController::class, 'live']);

// Super Admin Dedicated Landing Workspace
Route::middleware(['web', 'auth', 'role:super-admin'])->group(function () {
    Route::get('/super-admin/dashboard', [SuperAdminDashboardController::class, 'superAdminIndex'])
        ->name('super-admin.dashboard');
});

// Teacher Dedicated Landing Workspace
Route::middleware(['web', 'auth', 'role:teacher'])->group(function () {
    Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index'])
        ->name('teacher.dashboard');
});

// Finance Dedicated Landing Workspace
Route::middleware(['web', 'auth', 'role:finance'])->group(function () {
    Route::get('/finance/dashboard', [FinanceDashboardController::class, 'index'])
        ->name('finance.dashboard');
});

// Super Admin Only Governance & Audit Workspaces (Baseline v1.1 Rules)
Route::middleware(['web', 'auth', 'role:super-admin'])->group(function () {
    Route::get('/admin/monitoring', [MonitoringDashboardController::class, 'index'])
        ->name('admin.monitoring.index');

    Route::prefix('admin/approvals')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('admin.approvals.index');
        Route::post('/{test}/approve', [ApprovalController::class, 'approve'])->name('admin.approvals.approve');
        Route::post('/{test}/reject', [ApprovalController::class, 'reject'])->name('admin.approvals.reject');
        Route::post('/users/{deletionRequest}/approve', [ApprovalController::class, 'approveUserDeletion'])->name('admin.approvals.users.approve');
        Route::post('/users/{deletionRequest}/reject', [ApprovalController::class, 'rejectUserDeletion'])->name('admin.approvals.users.reject');
    });

    Route::prefix('admin/audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    });

    Route::prefix('admin/settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/', [SettingsController::class, 'update'])->name('admin.settings.update');
    });
});

// Shared Admin & Super Admin Workspaces
Route::middleware(['web', 'auth', 'role:admin|super-admin'])->group(function () {
    Route::get('/admin/dashboard', [SuperAdminDashboardController::class, 'adminIndex'])
        ->name('admin.dashboard');

    Route::prefix('admin/users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::post('/{user}/request-delete', [UserController::class, 'requestDelete'])->name('admin.users.request-delete');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
        Route::post('/{id}/restore', [UserController::class, 'restore'])->name('admin.users.restore');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});

// Shared Authoring Workspaces (Teacher, Admin, Super Admin)
Route::middleware(['web', 'auth', 'role:admin|super-admin|teacher'])->group(function () {
    Route::prefix('admin/media')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('admin.media.index');
        Route::get('/list', [MediaController::class, 'list'])->name('admin.media.list');
        Route::post('/', [MediaController::class, 'store'])->name('admin.media.store');
    });
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
});
