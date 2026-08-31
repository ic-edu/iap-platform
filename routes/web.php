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
use App\Http\Controllers\Finance\FinancePaymentController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\MediaPreviewController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Central Media Preview Gateway (TASK 1 & TASK 2)
Route::get('/media/{media}/preview', [MediaPreviewController::class, 'preview'])->name('media.preview');

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = Auth::user();

    if ($user->hasRole('super-admin')) {
        return redirect()->route('super-admin.dashboard');
    }

    if ($user->hasRole('repository-manager')) {
        return redirect()->route('admin.repository-manager.dashboard');
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

    if ($user?->hasRole('repository-manager')) {
        return redirect()->route('admin.repository-manager.dashboard');
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
    Route::get('/teacher/revision-center', [TeacherDashboardController::class, 'revisionCenter'])
        ->name('teacher.revision-center');

    // Teacher Workspace Authoring Aliases (HOTFIX)
    Route::get('/teacher/archived-repositories', [\App\Http\Controllers\Teacher\TeacherArchivedRepositoryController::class, 'index'])
        ->name('teacher.archived-repositories.index');
    Route::get('/teacher/question-banks', [\App\Modules\QuestionBank\Controllers\QuestionBankController::class, 'index'])
        ->name('teacher.question-banks.index');
    Route::get('/teacher/question-banks/{questionBank}', [\App\Modules\QuestionBank\Controllers\QuestionBankController::class, 'show'])
        ->name('teacher.question-banks.show');
    Route::get('/teacher/assessments', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'index'])
        ->name('teacher.tests.index');
    Route::get('/teacher/assessments/{test}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'show'])
        ->name('teacher.tests.show');
    Route::get('/teacher/assessments/{test}/preview', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'previewAsCandidate'])
        ->name('teacher.tests.preview');
    Route::put('/teacher/assessments/{test}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'update'])
        ->name('teacher.tests.update');
    Route::get('/teacher/assessments/{test}/questions/{question}/edit', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'editQuestion'])
        ->name('teacher.tests.edit-question');
    Route::put('/teacher/assessments/{test}/questions/{question}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'updateQuestion'])
        ->name('teacher.tests.update-question');
    Route::post('/teacher/assessments/{test}/attach-master-question', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'attachMasterQuestion'])
        ->name('teacher.tests.attach-master-question');
    Route::post('/teacher/assessments/{test}/create-question', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'createAssessmentQuestion'])
        ->name('teacher.tests.create-question');
    Route::post('/teacher/assessments/{test}/create-audio-group', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'createAudioGroup'])
        ->name('teacher.tests.create-audio-group');
    Route::put('/teacher/assessments/{test}/audio-groups/{audioGroup}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'updateAudioGroup'])
        ->name('teacher.tests.update-audio-group');
    Route::delete('/teacher/assessments/{test}/audio-groups/{audioGroup}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'destroyAudioGroup'])
        ->name('teacher.tests.destroy-audio-group');
    Route::post('/teacher/assessments/{test}/create-passage-group', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'createPassageGroup'])
        ->name('teacher.tests.create-passage-group');
    Route::delete('/teacher/assessments/{test}/questions/{question}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'destroyQuestion'])
        ->name('teacher.tests.destroy-question');
    Route::post('/teacher/assessments/{test}/sections', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'addSection'])
        ->name('teacher.tests.add-section');
    Route::put('/teacher/assessments/{test}/sections/{section}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'updateSection'])
        ->name('teacher.tests.update-section');
    Route::delete('/teacher/assessments/{test}/sections/{section}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'destroySection'])
        ->name('teacher.tests.destroy-section');
    Route::post('/teacher/assessments/{test}/sections/{section}/media', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'attachSectionMedia'])
        ->name('teacher.tests.sections.media.attach');
    Route::delete('/teacher/assessments/{test}/sections/{section}/media/{media}', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'detachSectionMedia'])
        ->name('teacher.tests.sections.media.detach');
    Route::post('/teacher/assessments/{test}/resubmit', [\App\Modules\Assessment\Controllers\TestBuilderController::class, 'resubmit'])
        ->name('teacher.tests.resubmit');

    // Teacher My Media Workspace
    Route::get('/teacher/media', [MediaController::class, 'myMedia'])
        ->name('teacher.media.index');
    Route::post('/teacher/media/submit-selected', [MediaController::class, 'submitSelectedForReview'])
        ->name('teacher.media.submit-selected');
});

// Finance Dedicated Landing Workspace
Route::middleware(['web', 'auth', 'role:finance'])->prefix('finance')->name('finance.')->group(function () {
    Route::get('/dashboard', [FinanceDashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/payments/pending', [FinancePaymentController::class, 'pending'])
        ->name('payments.pending');
    Route::get('/payments/export/csv', [FinancePaymentController::class, 'exportCsv'])
        ->name('payments.export.csv');
    Route::get('/payments/export/xlsx', [FinancePaymentController::class, 'exportXlsx'])
        ->name('payments.export.xlsx');
    Route::get('/payments', [FinancePaymentController::class, 'index'])
        ->name('payments.index');
    Route::get('/payments/{payment}', [FinancePaymentController::class, 'show'])
        ->name('payments.show');
    Route::get('/payments/{payment}/proof', [FinancePaymentController::class, 'viewProof'])
        ->name('payments.proof');
    Route::post('/payments/{payment}/approve', [FinancePaymentController::class, 'approve'])
        ->name('payments.approve');
    Route::post('/payments/{payment}/reject', [FinancePaymentController::class, 'reject'])
        ->name('payments.reject');
});

// Super Admin Only Governance & Audit Workspaces (Baseline v1.1 Rules)
Route::middleware(['web', 'auth', 'role:super-admin'])->group(function () {
    Route::get('/admin/monitoring', [MonitoringDashboardController::class, 'index'])
        ->name('admin.monitoring.index');

    Route::prefix('admin/approvals')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->name('admin.approvals.index');
        Route::get('/assessments', [ApprovalController::class, 'assessmentsIndex'])->name('admin.approvals.assessments');
        Route::get('/question-banks', [ApprovalController::class, 'questionBanksIndex'])->name('admin.approvals.question-banks');
        Route::get('/question-bank-restorations', [ApprovalController::class, 'restorationsIndex'])->name('admin.approvals.question-bank-restorations');
        Route::get('/question-bank-archives', [ApprovalController::class, 'archivesIndex'])->name('admin.approvals.question-bank-archives');
        Route::get('/staff-creations', [ApprovalController::class, 'staffCreationsIndex'])->name('admin.approvals.staff-creations');
        Route::get('/user-deletions', [ApprovalController::class, 'userDeletionsIndex'])->name('admin.approvals.user-deletions');

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
        Route::post('/price-changes/{priceChangeRequest}/approve', [ApprovalController::class, 'approvePriceChange'])->name('admin.approvals.price-changes.approve');
        Route::post('/price-changes/{priceChangeRequest}/reject', [ApprovalController::class, 'rejectPriceChange'])->name('admin.approvals.price-changes.reject');
    });

    Route::prefix('admin/audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    });

    Route::prefix('admin/settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/', [SettingsController::class, 'update'])->name('admin.settings.update');
    });

    Route::prefix('admin/archived-repositories')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ArchivedRepositoryController::class, 'archivedIndex'])->name('admin.archived-repositories.index');
        Route::post('/{questionBank}/move-to-recycle-bin', [\App\Http\Controllers\Admin\ArchivedRepositoryController::class, 'moveToRecycleBin'])->name('admin.archived-repositories.move-to-recycle-bin');
    });

    Route::prefix('admin/recycle-bin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ArchivedRepositoryController::class, 'recycleBinIndex'])->name('admin.recycle-bin.index');
        Route::get('/{id}', [\App\Http\Controllers\Admin\ArchivedRepositoryController::class, 'recycleBinShow'])->name('admin.recycle-bin.show');
        Route::post('/{id}/restore', [\App\Http\Controllers\Admin\ArchivedRepositoryController::class, 'restoreFromRecycleBin'])->name('admin.recycle-bin.restore');
    });
});

// Shared Admin, Super Admin & Repository Manager Workspaces
Route::middleware(['web', 'auth', 'role:admin|super-admin|repository-manager'])->group(function () {
    Route::get('/admin/dashboard', [SuperAdminDashboardController::class, 'adminIndex'])
        ->name('admin.dashboard');

    // Candidate Management Workspace (Dedicated Candidate Operations)
    Route::prefix('admin/candidates')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [UserController::class, 'candidates'])->name('admin.candidates.index');
        Route::post('/', [UserController::class, 'storeCandidate'])->name('admin.candidates.store');
    });

    // Institutional Staff & Access Control Workspace
    Route::prefix('admin/users')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('/', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::post('/{user}/request-delete', [UserController::class, 'requestDelete'])->name('admin.users.request-delete');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggle-status');
        Route::post('/{id}/restore', [UserController::class, 'restore'])->name('admin.users.restore');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });

    Route::get('/admin/staff', [UserController::class, 'index'])->middleware('role:admin|super-admin')->name('admin.staff.index');

    // Publication Queues (Repository Manager & Super Admin Governance Only)
    Route::prefix('admin/publications')->middleware('role:repository-manager|super-admin')->group(function () {
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
        Route::get('/libraries', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'libraries'])
            ->middleware('role:repository-manager|super-admin')
            ->name('admin.academic-operations.libraries');
        Route::get('/monitoring', [\App\Http\Controllers\Admin\AdminAcademicOperationsController::class, 'monitoring'])->name('admin.academic-operations.monitoring');
    });

    // Content Refresh & Controlled Hard Reset Governance (Phase 5A)
    Route::prefix('admin/content-reset')->middleware('role:admin|super-admin|ceo')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ContentResetController::class, 'index'])->name('admin.content-reset.index');
        Route::get('/create', [\App\Http\Controllers\Admin\ContentResetController::class, 'create'])->name('admin.content-reset.create');
        Route::post('/', [\App\Http\Controllers\Admin\ContentResetController::class, 'store'])->name('admin.content-reset.store');
        Route::get('/{contentResetRequest}', [\App\Http\Controllers\Admin\ContentResetController::class, 'show'])->name('admin.content-reset.show');
        Route::post('/{contentResetRequest}/approve-ceo', [\App\Http\Controllers\Admin\ContentResetController::class, 'approveCeo'])->name('admin.content-reset.approve-ceo');
        Route::post('/{contentResetRequest}/approve-sa', [\App\Http\Controllers\Admin\ContentResetController::class, 'approveSa'])->name('admin.content-reset.approve-sa');
        Route::post('/{contentResetRequest}/execute', [\App\Http\Controllers\Admin\ContentResetController::class, 'execute'])->name('admin.content-reset.execute');
    });

});

// Shared Media Library & Academic Library (Teacher + Super Admin + Repository Manager Governance)
Route::middleware(['web', 'auth', 'role:super-admin|teacher|repository-manager'])->group(function () {
    // Academic Library Architecture (Sprint: Academic Library Architecture)
    Route::prefix('admin/academic-library')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AcademicLibraryController::class, 'index'])->name('admin.academic-library.index');
        Route::get('/quality', [\App\Http\Controllers\Admin\AcademicLibraryController::class, 'quality'])->name('admin.academic-library.quality');
        Route::get('/explorer', [\App\Http\Controllers\Admin\AcademicLibraryController::class, 'explorer'])->name('admin.academic-library.explorer');
        Route::get('/analytics', [\App\Http\Controllers\Admin\AcademicLibraryController::class, 'analytics'])->name('admin.academic-library.analytics');
        Route::get('/{slug}', [\App\Http\Controllers\Admin\AcademicLibraryController::class, 'show'])->name('admin.academic-library.show');
    });

    Route::prefix('admin/media')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('admin.media.index');
        Route::get('/list', [MediaController::class, 'list'])->name('admin.media.list');
        Route::post('/', [MediaController::class, 'store'])->name('admin.media.store');
        Route::get('/{media}/edit', [MediaController::class, 'edit'])->name('admin.media.edit');
        Route::put('/{media}', [MediaController::class, 'update'])->name('admin.media.update');
        Route::post('/{media}/submit-review', [MediaController::class, 'submitForReview'])->name('admin.media.submit-review');
        Route::get('/{media}/versions', [MediaController::class, 'versions'])->name('admin.media.versions');
        Route::get('/{media}', [MediaController::class, 'show'])->name('admin.media.show');
        Route::get('/{media}/stream', [MediaController::class, 'stream'])->name('admin.media.stream');
        Route::get('/{media}/download', [MediaController::class, 'download'])->name('admin.media.download');
        Route::post('/{media}/archive', [MediaController::class, 'archive'])->name('admin.media.archive');
        Route::post('/{media}/request-archive', [MediaController::class, 'requestArchive'])->name('admin.media.request-archive');
        Route::post('/{media}/request-download', [MediaController::class, 'requestDownload'])->name('admin.media.request-download');
        Route::patch('/{media}/metadata', [MediaController::class, 'updateMetadata'])->name('admin.media.metadata');
        Route::get('/{media}/usage', [MediaController::class, 'usage'])->name('admin.media.usage');
    });
});

// Super Admin & Repository Manager Media Governance
Route::middleware(['web', 'auth', 'role:super-admin|repository-manager'])->group(function () {
    Route::prefix('admin/media')->group(function () {
        Route::get('/archive', [MediaController::class, 'archiveIndex'])->name('admin.media.archive-index');
        Route::post('/{media}/restore', [MediaController::class, 'restore'])->name('admin.media.restore');
        Route::post('/{media}/request-restore', [MediaController::class, 'requestRestore'])->name('admin.media.request-restore');
        Route::post('/{media}/approve-archive', [MediaController::class, 'approveArchive'])->name('admin.media.approve-archive');
        Route::post('/{media}/approve-restore', [MediaController::class, 'approveRestore'])->name('admin.media.approve-restore');
        Route::get('/download-requests', [MediaController::class, 'downloadRequestsIndex'])->name('admin.media.download-requests');
        Route::post('/download-requests/{id}/approve', [MediaController::class, 'approveDownloadRequest'])->name('admin.media.approve-download');
        Route::post('/download-requests/{id}/reject', [MediaController::class, 'rejectDownloadRequest'])->name('admin.media.reject-download');
    });
});

// PART B: Repository Manager Dedicated Workspace & Approval Center
Route::middleware(['web', 'auth', 'role:repository-manager|super-admin'])->group(function () {
    Route::prefix('admin/repository-manager')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'dashboard'])->name('admin.repository-manager.dashboard');
        Route::get('/media', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'mediaApprovalCenter'])->name('admin.repository-manager.media-approval');
        Route::get('/media/{reviewRequest}', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'mediaReview'])->name('admin.repository-manager.media-review');
        Route::post('/media/{reviewRequest}/approve', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'approveMedia'])->name('admin.repository-manager.media-approve');
        Route::post('/media/{reviewRequest}/revision', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'requestRevisionMedia'])->name('admin.repository-manager.media-revision');
        Route::post('/media/{reviewRequest}/reject', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'rejectMedia'])->name('admin.repository-manager.media-reject');
        Route::get('/questions', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'questionsApproval'])->name('admin.repository-manager.questions-approval');
        Route::get('/questions/{questionBank}', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'validateQuestionBank'])->name('admin.repository-manager.question-bank-validate');
        Route::get('/questions/{questionBank}/review-complete', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'reviewComplete'])->name('admin.repository-manager.review-complete');
        Route::post('/questions/{questionBank}/approve', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'approveQuestionBank'])->name('admin.repository-manager.question-bank-approve');
        Route::post('/questions/{questionBank}/revision', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'requestQuestionBankRevision'])->name('admin.repository-manager.question-bank-revision');
        Route::post('/questions/{questionBank}/reject', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'rejectQuestionBank'])->name('admin.repository-manager.question-bank-reject');
        Route::get('/duplicates', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'duplicates'])->name('admin.repository-manager.duplicates');

        // SPRINT 10.2 & Sprint 11.5 Continuous Improvement: Assessment Approval & Review Routes
        Route::get('/assessments', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'assessmentApprovalCenter'])->name('admin.repository-manager.assessment-approval');
        Route::get('/assessments/{test}/review', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'assessmentReview'])
            ->name('admin.repository-manager.assessment-review');
        Route::post('/assessments/{test}/questions/{question}/review-ok', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'markQuestionReviewed'])
            ->name('admin.repository-manager.question-review-ok');
        Route::post('/assessments/{test}/questions/{question}/request-revision', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'requestQuestionRevision'])
            ->name('admin.repository-manager.question-request-revision');
        Route::post('/assessments/{test}/approve', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'approveAssessment'])
            ->name('admin.repository-manager.assessment-approve');
        Route::post('/assessments/{test}/revision', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'requestRevisionAssessment'])->name('admin.repository-manager.assessment-revision');
        Route::post('/assessments/{test}/archive', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'archiveAssessment'])->name('admin.repository-manager.assessment-archive');
        Route::post('/assessments/{test}/reject', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'rejectAssessment'])->name('admin.repository-manager.assessment-reject');

        // Assessment Requests Intake Queue
        Route::get('/assessment-requests', [\App\Http\Controllers\Admin\AssessmentRequestController::class, 'index'])->name('admin.repository-manager.assessment-requests.index');
        Route::post('/assessment-requests/{assessmentRequest}/create-draft', [\App\Http\Controllers\Admin\AssessmentRequestController::class, 'createDraft'])->name('admin.repository-manager.assessment-requests.create-draft');

        // Backward compatibility redirect for legacy double-prefixed URI
        Route::get('/repository-manager/assessments/{test}', function ($test) {
            return redirect()->route('admin.repository-manager.assessment-review', $test);
        });
    });
});

// Admin Assessment Requests
Route::middleware(['web', 'auth', 'role:admin|super-admin|repository-manager'])->group(function () {
    Route::get('/admin/assessment-requests', [\App\Http\Controllers\Admin\AssessmentRequestController::class, 'index'])->name('admin.assessment-requests.index');
    Route::post('/admin/assessment-requests', [\App\Http\Controllers\Admin\AssessmentRequestController::class, 'store'])->name('admin.assessment-requests.store');
});

// RRWE v1.0 & RRUXO-ENTERPRISE: Teacher Repository Revision Center Routes
Route::middleware(['web', 'auth', 'role:teacher|super-admin'])->prefix('teacher/repository-revisions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Teacher\TeacherRepositoryRevisionController::class, 'index'])->name('teacher.repository-revisions.index');
    Route::post('/request', [\App\Http\Controllers\Teacher\TeacherRepositoryRevisionController::class, 'requestRevision'])->name('teacher.repository-revisions.request');
    Route::get('/{revisionRequest}', [\App\Http\Controllers\Teacher\TeacherRepositoryRevisionController::class, 'show'])->name('teacher.repository-revisions.show');
    Route::get('/{revisionRequest}/item/{item}/edit', [\App\Http\Controllers\Teacher\TeacherRepositoryRevisionController::class, 'editQuestion'])->name('teacher.repository-revisions.edit-question');
    Route::post('/{revisionRequest}/item/{item}/update', [\App\Http\Controllers\Teacher\TeacherRepositoryRevisionController::class, 'updateQuestion'])->name('teacher.repository-revisions.update-question');
    Route::post('/{revisionRequest}/resubmit', [\App\Http\Controllers\Teacher\TeacherRepositoryRevisionController::class, 'resubmit'])->name('teacher.repository-revisions.resubmit');
});

Route::middleware(['web', 'auth', 'role:teacher|repository-manager|super-admin'])->group(function () {
    Route::post('/admin/repository-manager/assessments/{test}/submit', [\App\Http\Controllers\Admin\RepositoryManagerController::class, 'submitAssessmentForReview'])->name('admin.tests.submit');
});


Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');

    // Platform-Wide User Appearance & Theme Settings
    Route::get('/settings/appearance', [\App\Http\Controllers\AppearanceController::class, 'index'])->name('settings.appearance');
    Route::post('/settings/appearance', [\App\Http\Controllers\AppearanceController::class, 'update'])->name('settings.appearance.update');
});
