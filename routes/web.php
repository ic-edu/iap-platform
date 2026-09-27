<?php

use App\Http\Controllers\Admin\AcademicLibraryController;
use App\Http\Controllers\Admin\AdminAcademicOperationsController;
use App\Http\Controllers\Admin\AdminOperationalDashboardController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ArchivedRepositoryController;
use App\Http\Controllers\Admin\AssessmentRequestController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ContentResetController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MonitoringDashboardController;
use App\Http\Controllers\Admin\PublicationOperationController;
use App\Http\Controllers\Admin\RepositoryManagerController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SuperAdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinancePaymentController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MediaPreviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Teacher\TeacherArchivedRepositoryController;
use App\Http\Controllers\Teacher\TeacherDashboardController;
use App\Http\Controllers\Teacher\TeacherRepositoryRevisionController;
use App\Models\User;
use App\Modules\Assessment\Controllers\TestBuilderController;
use App\Modules\QuestionBank\Controllers\QuestionBankController;
use App\Services\NavigationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Central Media Preview Gateway (TASK 1 & TASK 2)
Route::get('/media/{media}/preview', [MediaPreviewController::class, 'preview'])->name('media.preview');

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    return redirect(NavigationService::getDashboardRouteForUser(Auth::user()));
});

Route::get('/dashboard', function () {
    return redirect(NavigationService::getDashboardRouteForUser(Auth::user()));
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
    Route::get('/teacher/archived-repositories', [TeacherArchivedRepositoryController::class, 'index'])
        ->name('teacher.archived-repositories.index');
    Route::get('/teacher/question-banks', [QuestionBankController::class, 'index'])
        ->name('teacher.question-banks.index');
    Route::get('/teacher/question-banks/{questionBank}', [QuestionBankController::class, 'show'])
        ->name('teacher.question-banks.show');
    Route::get('/teacher/assessments', [TestBuilderController::class, 'index'])
        ->name('teacher.tests.index');
    Route::get('/teacher/assessments/{test}', [TestBuilderController::class, 'show'])
        ->name('teacher.tests.show');
    Route::get('/teacher/assessments/{test}/preview', [TestBuilderController::class, 'previewAsCandidate'])
        ->name('teacher.tests.preview');
    Route::put('/teacher/assessments/{test}', [TestBuilderController::class, 'update'])
        ->name('teacher.tests.update');
    Route::get('/teacher/assessments/{test}/questions/{question}/edit', [TestBuilderController::class, 'editQuestion'])
        ->name('teacher.tests.edit-question');
    Route::put('/teacher/assessments/{test}/questions/{question}', [TestBuilderController::class, 'updateQuestion'])
        ->name('teacher.tests.update-question');
    Route::post('/teacher/assessments/{test}/attach-master-question', [TestBuilderController::class, 'attachMasterQuestion'])
        ->name('teacher.tests.attach-master-question');
    Route::post('/teacher/assessments/{test}/create-question', [TestBuilderController::class, 'createAssessmentQuestion'])
        ->name('teacher.tests.create-question');
    Route::post('/teacher/assessments/{test}/create-audio-group', [TestBuilderController::class, 'createAudioGroup'])
        ->name('teacher.tests.create-audio-group');
    Route::put('/teacher/assessments/{test}/audio-groups/{audioGroup}', [TestBuilderController::class, 'updateAudioGroup'])
        ->name('teacher.tests.update-audio-group');
    Route::delete('/teacher/assessments/{test}/audio-groups/{audioGroup}', [TestBuilderController::class, 'destroyAudioGroup'])
        ->name('teacher.tests.destroy-audio-group');
    Route::post('/teacher/assessments/{test}/create-passage-group', [TestBuilderController::class, 'createPassageGroup'])
        ->name('teacher.tests.create-passage-group');
    Route::put('/teacher/assessments/{test}/passage-groups/{passageGroup}', [TestBuilderController::class, 'updatePassageGroup'])
        ->name('teacher.tests.update-passage-group');
    Route::delete('/teacher/assessments/{test}/passage-groups/{passageGroup}', [TestBuilderController::class, 'destroyPassageGroup'])
        ->name('teacher.tests.destroy-passage-group');
    Route::delete('/teacher/assessments/{test}/questions/{question}', [TestBuilderController::class, 'destroyQuestion'])
        ->name('teacher.tests.destroy-question');
    Route::post('/teacher/assessments/{test}/sections', [TestBuilderController::class, 'addSection'])
        ->name('teacher.tests.add-section');
    Route::put('/teacher/assessments/{test}/sections/{section}', [TestBuilderController::class, 'updateSection'])
        ->name('teacher.tests.update-section');
    Route::delete('/teacher/assessments/{test}/sections/{section}', [TestBuilderController::class, 'destroySection'])
        ->name('teacher.tests.destroy-section');
    Route::post('/teacher/assessments/{test}/sections/{section}/media', [TestBuilderController::class, 'attachSectionMedia'])
        ->name('teacher.tests.sections.media.attach');
    Route::delete('/teacher/assessments/{test}/sections/{section}/media/{media}', [TestBuilderController::class, 'detachSectionMedia'])
        ->name('teacher.tests.sections.media.detach');
    Route::post('/teacher/assessments/{test}/resubmit', [TestBuilderController::class, 'resubmit'])
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
        Route::get('/', [ArchivedRepositoryController::class, 'archivedIndex'])->name('admin.archived-repositories.index');
        Route::post('/{questionBank}/move-to-recycle-bin', [ArchivedRepositoryController::class, 'moveToRecycleBin'])->name('admin.archived-repositories.move-to-recycle-bin');
    });

    Route::prefix('admin/recycle-bin')->group(function () {
        Route::get('/', [ArchivedRepositoryController::class, 'recycleBinIndex'])->name('admin.recycle-bin.index');
        Route::get('/{id}', [ArchivedRepositoryController::class, 'recycleBinShow'])->name('admin.recycle-bin.show');
        Route::post('/{id}/restore', [ArchivedRepositoryController::class, 'restoreFromRecycleBin'])->name('admin.recycle-bin.restore');
    });
});

// Shared Admin, Super Admin & Repository Manager Workspaces
Route::middleware(['web', 'auth', 'role:admin|super-admin|repository-manager'])->group(function () {
    Route::get('/admin/dashboard', [SuperAdminDashboardController::class, 'adminIndex'])
        ->name('admin.dashboard');

    // Institutional Assessment Assignments (O3 RA Operational Assignment)
    Route::post('/admin/institutional-seat-allocations/{allocation}/assign-assessment', [AdminOperationalDashboardController::class, 'assignInstitutionalSeat'])
        ->middleware('role:admin|super-admin')
        ->name('admin.institutional-seats.assign');

    // Result Release Operations (Sprint 3 RA Operational Result Release)
    Route::post('/admin/assessment-attempts/{attempt}/release-result', [AdminOperationalDashboardController::class, 'releaseResult'])
        ->middleware('role:admin|super-admin')
        ->name('admin.assessment-attempts.release-result');

    // Candidate Management Workspace (Dedicated Candidate Operations)
    Route::prefix('admin/candidates')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [UserController::class, 'candidates'])->name('admin.candidates.index');
        Route::post('/', [UserController::class, 'storeCandidate'])->name('admin.candidates.store');
    });

    // Universal All Users Directory (Super Admin & Admin Governance)
    Route::get('/admin/all-users', [UserController::class, 'allUsers'])
        ->middleware('role:admin|super-admin')
        ->name('admin.all-users.index');

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
        Route::get('/applications', [AdminAcademicOperationsController::class, 'applications'])->name('admin.academic-operations.applications');
        Route::patch('/applications/{application}/status', [AdminAcademicOperationsController::class, 'updateApplicationStatus'])->name('admin.academic-operations.applications.status');
        Route::get('/courses', [AdminAcademicOperationsController::class, 'courses'])->name('admin.academic-operations.courses');
        Route::post('/courses', [AdminAcademicOperationsController::class, 'storeMasterCourse'])->name('admin.academic-operations.courses.store');
        Route::post('/courses/{course}/approve', [AdminAcademicOperationsController::class, 'approveCourse'])->name('admin.academic-operations.courses.approve');
        Route::post('/courses/{course}/reject', [AdminAcademicOperationsController::class, 'rejectCourse'])->name('admin.academic-operations.courses.reject');
        Route::get('/teacher-assignments', [AdminAcademicOperationsController::class, 'teacherAssignments'])->name('admin.academic-operations.teacher-assignments');
        Route::post('/teacher-assignments', [AdminAcademicOperationsController::class, 'storeTeacherAssignment'])->name('admin.academic-operations.teacher-assignments.store');
        Route::get('/enrollments', [AdminAcademicOperationsController::class, 'enrollments'])->name('admin.academic-operations.enrollments');
        Route::post('/enrollments', [AdminAcademicOperationsController::class, 'storeEnrollment'])->name('admin.academic-operations.enrollments.store');
        Route::get('/libraries', [AdminAcademicOperationsController::class, 'libraries'])
            ->middleware('role:repository-manager|super-admin')
            ->name('admin.academic-operations.libraries');
        Route::get('/monitoring', [AdminAcademicOperationsController::class, 'monitoring'])->name('admin.academic-operations.monitoring');
    });

    // Content Refresh & Controlled Hard Reset Governance (Phase 5A)
    Route::prefix('admin/content-reset')->middleware('role:admin|super-admin|ceo')->group(function () {
        Route::get('/', [ContentResetController::class, 'index'])->name('admin.content-reset.index');
        Route::get('/create', [ContentResetController::class, 'create'])->name('admin.content-reset.create');
        Route::post('/', [ContentResetController::class, 'store'])->name('admin.content-reset.store');
        Route::get('/{contentResetRequest}', [ContentResetController::class, 'show'])->name('admin.content-reset.show');
        Route::post('/{contentResetRequest}/approve-ceo', [ContentResetController::class, 'approveCeo'])->name('admin.content-reset.approve-ceo');
        Route::post('/{contentResetRequest}/approve-sa', [ContentResetController::class, 'approveSa'])->name('admin.content-reset.approve-sa');
        Route::post('/{contentResetRequest}/execute', [ContentResetController::class, 'execute'])->name('admin.content-reset.execute');
    });

});

// Shared Media Library & Academic Library (Teacher + Super Admin + Repository Manager Governance)
Route::middleware(['web', 'auth', 'role:super-admin|teacher|repository-manager'])->group(function () {
    // Academic Library Architecture (Sprint: Academic Library Architecture)
    Route::prefix('admin/academic-library')->group(function () {
        Route::get('/', [AcademicLibraryController::class, 'index'])->name('admin.academic-library.index');
        Route::get('/quality', [AcademicLibraryController::class, 'quality'])->name('admin.academic-library.quality');
        Route::get('/explorer', [AcademicLibraryController::class, 'explorer'])->name('admin.academic-library.explorer');
        Route::get('/analytics', [AcademicLibraryController::class, 'analytics'])->name('admin.academic-library.analytics');
        Route::get('/{slug}', [AcademicLibraryController::class, 'show'])->name('admin.academic-library.show');
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
        Route::get('/dashboard', [RepositoryManagerController::class, 'dashboard'])->name('admin.repository-manager.dashboard');
        Route::get('/media', [RepositoryManagerController::class, 'mediaApprovalCenter'])->name('admin.repository-manager.media-approval');
        Route::get('/media/{reviewRequest}', [RepositoryManagerController::class, 'mediaReview'])->name('admin.repository-manager.media-review');
        Route::post('/media/{reviewRequest}/approve', [RepositoryManagerController::class, 'approveMedia'])->name('admin.repository-manager.media-approve');
        Route::post('/media/{reviewRequest}/revision', [RepositoryManagerController::class, 'requestRevisionMedia'])->name('admin.repository-manager.media-revision');
        Route::post('/media/{reviewRequest}/reject', [RepositoryManagerController::class, 'rejectMedia'])->name('admin.repository-manager.media-reject');
        Route::get('/questions', [RepositoryManagerController::class, 'questionsApproval'])->name('admin.repository-manager.questions-approval');
        Route::get('/questions/{questionBank}', [RepositoryManagerController::class, 'validateQuestionBank'])->name('admin.repository-manager.question-bank-validate');
        Route::get('/questions/{questionBank}/review-complete', [RepositoryManagerController::class, 'reviewComplete'])->name('admin.repository-manager.review-complete');
        Route::post('/questions/{questionBank}/approve', [RepositoryManagerController::class, 'approveQuestionBank'])->name('admin.repository-manager.question-bank-approve');
        Route::post('/questions/{questionBank}/revision', [RepositoryManagerController::class, 'requestQuestionBankRevision'])->name('admin.repository-manager.question-bank-revision');
        Route::post('/questions/{questionBank}/reject', [RepositoryManagerController::class, 'rejectQuestionBank'])->name('admin.repository-manager.question-bank-reject');
        Route::get('/duplicates', [RepositoryManagerController::class, 'duplicates'])->name('admin.repository-manager.duplicates');

        // Dedicated Repository Revision Governance Routes
        Route::get('/revisions', [RepositoryManagerController::class, 'revisionsQueue'])->name('admin.repository-manager.revisions.index');
        Route::get('/revisions/{revisionRequest}', [RepositoryManagerController::class, 'reviewRevision'])->name('admin.repository-manager.revisions.review');
        Route::post('/revisions/{revisionRequest}/approve', [RepositoryManagerController::class, 'approveRevision'])->name('admin.repository-manager.revisions.approve');
        Route::post('/revisions/{revisionRequest}/request-changes', [RepositoryManagerController::class, 'requestRevisionChanges'])->name('admin.repository-manager.revisions.request-changes');
        Route::post('/revisions/{revisionRequest}/reject', [RepositoryManagerController::class, 'rejectRevision'])->name('admin.repository-manager.revisions.reject');

        // SPRINT 10.2 & Sprint 11.5 Continuous Improvement: Assessment Governance Workspace & Review Routes
        Route::get('/assessment-governance', [RepositoryManagerController::class, 'assessmentGovernance'])->name('admin.repository-manager.assessment-governance');
        Route::get('/assessments', [RepositoryManagerController::class, 'assessmentApprovalCenter'])->name('admin.repository-manager.assessment-approval');
        Route::get('/assessments/{test}/review', [RepositoryManagerController::class, 'assessmentReview'])
            ->name('admin.repository-manager.assessment-review');
        Route::post('/assessments/{test}/questions/{question}/review-ok', [RepositoryManagerController::class, 'markQuestionReviewed'])
            ->name('admin.repository-manager.question-review-ok');
        Route::post('/assessments/{test}/questions/{question}/request-revision', [RepositoryManagerController::class, 'requestQuestionRevision'])
            ->name('admin.repository-manager.question-request-revision');
        Route::post('/assessments/{test}/approve', [RepositoryManagerController::class, 'approveAssessment'])
            ->name('admin.repository-manager.assessment-approve');
        Route::post('/assessments/{test}/revision', [RepositoryManagerController::class, 'requestRevisionAssessment'])->name('admin.repository-manager.assessment-revision');
        Route::post('/assessments/{test}/archive', [RepositoryManagerController::class, 'archiveAssessment'])->name('admin.repository-manager.assessment-archive');
        Route::post('/assessments/{test}/reject', [RepositoryManagerController::class, 'rejectAssessment'])->name('admin.repository-manager.assessment-reject');

        // Assessment Requests Intake Queue
        Route::get('/assessment-requests', [AssessmentRequestController::class, 'index'])->name('admin.repository-manager.assessment-requests.index');
        Route::post('/assessment-requests/{assessmentRequest}/create-draft', [AssessmentRequestController::class, 'createDraft'])->name('admin.repository-manager.assessment-requests.create-draft');
        Route::get('/assessment-requests/{assessmentRequest}/assessment', [AssessmentRequestController::class, 'showDraftAssessment'])->name('admin.repository-manager.assessment-requests.assessment-show');

        // Backward compatibility redirect for legacy double-prefixed URI
        Route::get('/repository-manager/assessments/{test}', function ($test) {
            return redirect()->route('admin.repository-manager.assessment-review', $test);
        });
    });
});

// Admin Assessment Requests
Route::middleware(['web', 'auth', 'role:admin|super-admin|repository-manager'])->group(function () {
    Route::get('/admin/assessment-requests', [AssessmentRequestController::class, 'index'])->name('admin.assessment-requests.index');
    Route::post('/admin/assessment-requests', [AssessmentRequestController::class, 'store'])->name('admin.assessment-requests.store');
});

// RRWE v1.0 & RRUXO-ENTERPRISE: Teacher Repository Revision Center Routes
Route::middleware(['web', 'auth', 'role:teacher|super-admin'])->prefix('teacher/repository-revisions')->group(function () {
    Route::get('/', [TeacherRepositoryRevisionController::class, 'index'])->name('teacher.repository-revisions.index');
    Route::post('/request', [TeacherRepositoryRevisionController::class, 'requestRevision'])->name('teacher.repository-revisions.request');
    Route::get('/{revisionRequest}', [TeacherRepositoryRevisionController::class, 'show'])->name('teacher.repository-revisions.show');
    Route::get('/{revisionRequest}/item/{item}/edit', [TeacherRepositoryRevisionController::class, 'editQuestion'])->name('teacher.repository-revisions.edit-question');
    Route::post('/{revisionRequest}/item/{item}/update', [TeacherRepositoryRevisionController::class, 'updateQuestion'])->name('teacher.repository-revisions.update-question');
    Route::post('/{revisionRequest}/resubmit', [TeacherRepositoryRevisionController::class, 'resubmit'])->name('teacher.repository-revisions.resubmit');
});

Route::middleware(['web', 'auth', 'role:teacher|repository-manager|super-admin'])->group(function () {
    Route::post('/admin/repository-manager/assessments/{test}/submit', [RepositoryManagerController::class, 'submitAssessmentForReview'])->name('admin.tests.submit');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');

    // Platform-Wide User Appearance & Theme Settings
    Route::get('/settings/appearance', [AppearanceController::class, 'index'])->name('settings.appearance');
    Route::post('/settings/appearance', [AppearanceController::class, 'update'])->name('settings.appearance.update');
});
