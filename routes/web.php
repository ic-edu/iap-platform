<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MonitoringDashboardController;
use App\Http\Controllers\Admin\PublicationOperationController;
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
        Route::post('/question-banks/{questionBank}/approve', [ApprovalController::class, 'approveQuestionBank'])->name('admin.approvals.question-banks.approve');
        Route::post('/question-banks/{questionBank}/reject', [ApprovalController::class, 'rejectQuestionBank'])->name('admin.approvals.question-banks.reject');
        Route::post('/question-banks/archives/{archiveRequest}/approve', [ApprovalController::class, 'approveQuestionBankArchive'])->name('admin.approvals.question-banks.archives.approve');
        Route::post('/question-banks/archives/{archiveRequest}/reject', [ApprovalController::class, 'rejectQuestionBankArchive'])->name('admin.approvals.question-banks.archives.reject');
        Route::post('/users/creation/{creationRequest}/approve', [ApprovalController::class, 'approveUserCreation'])->name('admin.approvals.users.creation.approve');
        Route::post('/users/creation/{creationRequest}/reject', [ApprovalController::class, 'rejectUserCreation'])->name('admin.approvals.users.creation.reject');
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

    // Admin Operations Publication Queues (ADMIN-OPS-001)
    Route::prefix('admin/publications')->group(function () {
        Route::get('/question-banks', [PublicationOperationController::class, 'questionBanksQueue'])->name('admin.publications.question-banks');
        Route::get('/assessments', [PublicationOperationController::class, 'assessmentsQueue'])->name('admin.publications.assessments');
        Route::get('/published', [PublicationOperationController::class, 'publishedContents'])->name('admin.publications.published');
        Route::get('/archive-requests', [PublicationOperationController::class, 'archiveRequests'])->name('admin.publications.archive-requests');
        Route::post('/question-banks/{questionBank}/publish', [PublicationOperationController::class, 'publishQuestionBank'])->name('admin.publications.question-banks.publish');
        Route::post('/question-banks/{questionBank}/unpublish', [PublicationOperationController::class, 'unpublishQuestionBank'])->name('admin.publications.question-banks.unpublish');
        Route::post('/assessments/{test}/publish', [PublicationOperationController::class, 'publishAssessment'])->name('admin.publications.assessments.publish');
        Route::post('/assessments/{test}/unpublish', [PublicationOperationController::class, 'unpublishAssessment'])->name('admin.publications.assessments.unpublish');
    });

    // Admin Academic Operations Foundation
    Route::prefix('admin/academic-operations')->group(function () {
        Route::get('/applications', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'applications'])->name('admin.academic-operations.applications');
        Route::patch('/applications/{application}/status', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'updateApplicationStatus'])->name('admin.academic-operations.applications.status');
        Route::get('/courses', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'courses'])->name('admin.academic-operations.courses');
        Route::post('/courses', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'storeMasterCourse'])->name('admin.academic-operations.courses.store');
        Route::post('/courses/{course}/approve', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'approveCourse'])->name('admin.academic-operations.courses.approve');
        Route::post('/courses/{course}/reject', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'rejectCourse'])->name('admin.academic-operations.courses.reject');
        Route::get('/teacher-assignments', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'teacherAssignments'])->name('admin.academic-operations.teacher-assignments');
        Route::post('/teacher-assignments', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'storeTeacherAssignment'])->name('admin.academic-operations.teacher-assignments.store');
        Route::get('/enrollments', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'enrollments'])->name('admin.academic-operations.enrollments');
        Route::post('/enrollments', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'storeEnrollment'])->name('admin.academic-operations.enrollments.store');
        Route::get('/libraries', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'libraries'])->name('admin.academic-operations.libraries');
        Route::get('/monitoring', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'monitoring'])->name('admin.academic-operations.monitoring');
    });
});

// Shared Media Library (Teacher + Admin + Super Admin)
Route::middleware(['web', 'auth', 'role:admin|super-admin|teacher'])->group(function () {
    Route::prefix('admin/media')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('admin.media.index');
        Route::get('/list', [MediaController::class, 'list'])->name('admin.media.list');
        Route::post('/', [MediaController::class, 'store'])->name('admin.media.store');
        Route::post('/{media}/archive', [MediaController::class, 'archive'])->name('admin.media.archive');
        Route::post('/{media}/request-archive', [MediaController::class, 'requestArchive'])->name('admin.media.request-archive');
        Route::patch('/{media}/metadata', [MediaController::class, 'updateMetadata'])->name('admin.media.metadata');
        Route::get('/{media}/usage', [MediaController::class, 'usage'])->name('admin.media.usage');
    });
});

// Admin & Super Admin Media Governance
Route::middleware(['web', 'auth', 'role:admin|super-admin'])->group(function () {
    Route::prefix('admin/media')->group(function () {
        Route::get('/archive', [MediaController::class, 'archiveIndex'])->name('admin.media.archive-index');
        Route::post('/{media}/restore', [MediaController::class, 'restore'])->name('admin.media.restore');
        Route::post('/{media}/request-restore', [MediaController::class, 'requestRestore'])->name('admin.media.request-restore');
        Route::post('/{media}/approve-archive', [MediaController::class, 'approveArchive'])->name('admin.media.approve-archive');
        Route::post('/{media}/approve-restore', [MediaController::class, 'approveRestore'])->name('admin.media.approve-restore');
        Route::post('/{media}/request-delete', [MediaController::class, 'requestDelete'])->name('admin.media.request-delete');
    });
});


Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
});
