<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankArchiveRequest;
use App\Models\User;
use App\Models\UserCreationRequest;
use App\Models\UserDeletionRequest;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Notifications\EnterpriseSystemNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    /**
     * Display Super Admin Approval Center.
     */
    public function index(): View
    {
        $pendingTests = Test::with(['creator', 'sections'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(10);

        $pendingQuestionBanks = QuestionBank::with(['creator', 'category'])
            ->where('status', 'pending_approval')
            ->latest()
            ->get();

        $pendingArchiveRequests = QuestionBankArchiveRequest::with(['questionBank', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $userCreationRequests = UserCreationRequest::with(['targetUser', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $userDeletionRequests = UserDeletionRequest::with(['targetUser', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $pendingRestorationRequests = QuestionBank::with(['creator', 'aclCategory', 'activityLogs'])
            ->where('status', 'pending_restore_approval')
            ->latest()
            ->get();

        $publishedCount = Test::where('is_published', true)->count();
        $pendingCount = Test::where('status', 'pending_approval')->count();
        $pendingQuestionBankCount = $pendingQuestionBanks->count();
        $pendingQuestionBankArchiveCount = $pendingArchiveRequests->count();
        $pendingRestorationCount = $pendingRestorationRequests->count();
        $pendingUserCreationCount = $userCreationRequests->count();
        $pendingUserDeletionCount = $userDeletionRequests->count();

        return view('admin.approvals.index', compact(
            'pendingTests',
            'publishedCount',
            'pendingCount',
            'pendingQuestionBanks',
            'pendingQuestionBankCount',
            'pendingArchiveRequests',
            'pendingQuestionBankArchiveCount',
            'pendingRestorationRequests',
            'pendingRestorationCount',
            'userCreationRequests',
            'pendingUserCreationCount',
            'userDeletionRequests',
            'pendingUserDeletionCount'
        ));
    }

    /**
     * Display Pending Assessments Queue.
     */
    public function assessmentsIndex(): View
    {
        $pendingTests = Test::with(['creator', 'sections'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('admin.approvals.assessments', compact('pendingTests'));
    }

    /**
     * Display Pending Question Banks Queue.
     */
    public function questionBanksIndex(): View
    {
        $pendingQuestionBanks = QuestionBank::with(['creator', 'category'])
            ->where('status', 'pending_approval')
            ->latest()
            ->paginate(15);

        return view('admin.approvals.question-banks', compact('pendingQuestionBanks'));
    }

    /**
     * Display Question Bank Restoration Approval Queue.
     */
    public function restorationsIndex(): View
    {
        $pendingRestorationRequests = QuestionBank::with(['creator', 'aclCategory', 'activityLogs'])
            ->where('status', 'pending_restore_approval')
            ->latest()
            ->paginate(15);

        return view('admin.approvals.question-bank-restorations', compact('pendingRestorationRequests'));
    }

    /**
     * Display Pending Question Bank Archives Queue.
     */
    public function archivesIndex(): View
    {
        $pendingArchiveRequests = QuestionBankArchiveRequest::with(['questionBank', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('admin.approvals.question-bank-archives', compact('pendingArchiveRequests'));
    }

    /**
     * Display Pending Staff Creations Queue.
     */
    public function staffCreationsIndex(): View
    {
        $userCreationRequests = UserCreationRequest::with(['targetUser', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('admin.approvals.staff-creations', compact('userCreationRequests'));
    }

    /**
     * Display Pending User Deletions Queue.
     */
    public function userDeletionsIndex(): View
    {
        $userDeletionRequests = UserDeletionRequest::with(['targetUser', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('admin.approvals.user-deletions', compact('userDeletionRequests'));
    }

    /**
     * Approve a Question Bank (Super Admin Only).
     * Notifies Teacher Author (approved) and all Admins (ready for publication).
     * ADMIN-OPS-001 Section 8: Admin receives "Question Bank Approved – Ready for Publication".
     */
    public function approveQuestionBank(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $questionBank->update([
            'status' => 'approved',
            'is_published' => false,
        ]);

        ActivityLogger::log(
            'QUESTION_BANK_APPROVED',
            "Approved Question Bank '{$questionBank->title}'",
            $questionBank
        );

        // Notify Teacher Author
        if ($questionBank->creator) {
            try {
                $questionBank->creator->notify(new EnterpriseSystemNotification(
                    title: 'Question Bank Approved',
                    message: "Your Question Bank '{$questionBank->title}' was approved by Super Admin {$actor->name}. It is now ready for Admin publication.",
                    type: 'QUESTION_BANK_APPROVED',
                    priority: 'HIGH',
                    entityType: 'question_bank',
                    entityId: (string) $questionBank->id,
                    targetUrl: route('admin.question-banks.show', $questionBank->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        // Notify all Repository Managers: Ready for Publication
        $repositoryManagers = User::role('repository-manager')->get();
        foreach ($repositoryManagers as $rm) {
            try {
                $rm->notify(new EnterpriseSystemNotification(
                    title: 'Question Bank Ready for Publication',
                    message: "Question Bank '{$questionBank->title}' was approved by Super Admin {$actor->name} and is ready for Repository Manager publication.",
                    type: 'QUESTION_BANK_APPROVED',
                    priority: 'HIGH',
                    entityType: 'question_bank',
                    entityId: (string) $questionBank->id,
                    targetUrl: route('admin.repository-manager.question-bank-validate', $questionBank->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "Question Bank '{$questionBank->title}' approved successfully. Repository Managers notified.");
    }

    /**
     * Reject a Question Bank (Super Admin Only).
     */
    public function rejectQuestionBank(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $reason = $request->input('reason', 'Requires revisions before approval.');

        $questionBank->update([
            'status' => 'rejected',
            'is_published' => false,
        ]);

        ActivityLogger::log(
            'QUESTION_BANK_REJECTED',
            "Rejected Question Bank '{$questionBank->title}'. Reason: {$reason}",
            $questionBank
        );

        if ($questionBank->creator) {
            try {
                $questionBank->creator->notify(new EnterpriseSystemNotification(
                    title: 'Question Bank Rejected',
                    message: "Your Question Bank '{$questionBank->title}' was rejected by Super Admin {$actor->name}. Reason: {$reason}",
                    type: 'QUESTION_BANK_REJECTED',
                    priority: 'HIGH',
                    entityType: 'question_bank',
                    entityId: (string) $questionBank->id,
                    targetUrl: route('admin.question-banks.show', $questionBank->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "Question Bank '{$questionBank->title}' rejected. Reason: {$reason}");
    }

    /**
     * Approve Question Bank Archive Request (Super Admin Only).
     * Notifies Teacher and requesting Admin (ADMIN-OPS-001 Section 8 & 12).
     */
    public function approveQuestionBankArchive(Request $request, QuestionBankArchiveRequest $archiveRequest): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $questionBank = $archiveRequest->questionBank;

        $archiveRequest->update([
            'status' => 'approved',
            'actioned_by' => $actor->id,
            'actioned_at' => now(),
        ]);

        if ($questionBank) {
            $questionBank->update([
                'status' => 'archived',
                'is_published' => false,
            ]);

            ActivityLogger::log(
                'QUESTION_BANK_ARCHIVED',
                "Archived Question Bank '{$questionBank->title}'",
                $questionBank
            );

            // Notify Teacher Author
            if ($questionBank->creator) {
                try {
                    $questionBank->creator->notify(new EnterpriseSystemNotification(
                        title: 'Question Bank Archived',
                        message: "Your Question Bank '{$questionBank->title}' has been archived by Super Admin {$actor->name}.",
                        type: 'ARCHIVE_COMPLETED',
                        priority: 'NORMAL',
                        entityType: 'question_bank',
                        entityId: (string) $questionBank->id,
                        targetUrl: route('admin.question-banks.show', $questionBank->id)
                    ));
                } catch (\Throwable $e) {
                    // Silently handle in dev
                }
            }
        }

        // Notify Requesting Admin: Archive Approved (ADMIN-OPS-001 Section 8)
        if ($archiveRequest->requester) {
            try {
                $archiveRequest->requester->notify(new EnterpriseSystemNotification(
                    title: 'Question Bank Archive Approved',
                    message: "Your archive request for Question Bank '{$questionBank?->title}' was approved by Super Admin {$actor->name}.",
                    type: 'ARCHIVE_APPROVED',
                    priority: 'NORMAL',
                    entityType: 'archive_request',
                    entityId: (string) $archiveRequest->id,
                    targetUrl: route('admin.publications.archive-requests')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "Question Bank '{$questionBank?->title}' archived successfully.");
    }

    /**
     * Reject Question Bank Archive Request (Super Admin Only).
     */
    public function rejectQuestionBankArchive(Request $request, QuestionBankArchiveRequest $archiveRequest): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $reason = $request->input('reason', 'Archive request rejected by Super Admin.');
        $questionBank = $archiveRequest->questionBank;

        $archiveRequest->update([
            'status' => 'rejected',
            'actioned_by' => $actor->id,
            'actioned_at' => now(),
        ]);

        if ($questionBank) {
            $questionBank->update(['status' => 'approved']);

            ActivityLogger::log(
                'ARCHIVE_REQUEST_REJECTED',
                "Rejected archive request for Question Bank '{$questionBank->title}'. Reason: {$reason}",
                $questionBank
            );
        }

        if ($archiveRequest->requester) {
            try {
                $archiveRequest->requester->notify(new EnterpriseSystemNotification(
                    title: 'Archive Request Rejected',
                    message: "Your archive request for Question Bank '{$questionBank?->title}' was rejected by Super Admin {$actor->name}. Reason: {$reason}",
                    type: 'ARCHIVE_REJECTED',
                    priority: 'NORMAL',
                    entityType: 'archive_request',
                    entityId: (string) $archiveRequest->id,
                    targetUrl: route('admin.publications.archive-requests')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "Archive request for Question Bank '{$questionBank?->title}' rejected. Status reverted to Approved.");
    }

    /**
     * Approve an assessment test (Super Admin Only).
     * Notifies Admins: ready for publication (ADMIN-OPS-001 Section 11).
     */
    public function approve(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $test->update([
            'status' => 'approved',
            'is_published' => false,
        ]);

        ActivityLogger::log('ASSESSMENT_APPROVED', "Approved Assessment Test '{$test->title}'", $test);

        // Notify Teacher Author
        if ($test->creator) {
            try {
                $test->creator->notify(new EnterpriseSystemNotification(
                    title: 'Assessment Approved',
                    message: "Your Assessment Test '{$test->title}' was approved by Super Admin {$user->name} and is ready for Admin publication.",
                    type: 'ASSESSMENT_APPROVED',
                    priority: 'HIGH',
                    entityType: 'assessment',
                    entityId: (string) $test->id,
                    targetUrl: route('admin.tests.show', $test->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        // Notify all Admins: ready for publication
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new EnterpriseSystemNotification(
                    title: 'Assessment Ready for Publication',
                    message: "Assessment Test '{$test->title}' was approved by Super Admin {$user->name} and is ready for publication.",
                    type: 'ASSESSMENT_APPROVED',
                    priority: 'HIGH',
                    entityType: 'assessment',
                    entityId: (string) $test->id,
                    targetUrl: route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id])
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' approved. Admins notified.");
    }

    /**
     * Reject an assessment test (Super Admin Only).
     */
    public function reject(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $reason = $request->input('reason', 'Requires revisions before publication.');

        $test->update([
            'status' => 'rejected',
            'is_published' => false,
        ]);

        ActivityLogger::log('ASSESSMENT_REJECTED', "Rejected Assessment '{$test->title}'. Reason: {$reason}", $test);

        if ($test->creator) {
            try {
                $test->creator->notify(new EnterpriseSystemNotification(
                    title: 'Assessment Rejected',
                    message: "Your Assessment Test '{$test->title}' was rejected by Super Admin {$user->name}. Reason: {$reason}",
                    type: 'ASSESSMENT_REJECTED',
                    priority: 'HIGH',
                    entityType: 'assessment',
                    entityId: (string) $test->id,
                    targetUrl: route('admin.tests.show', $test->id)
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' rejected. Reason: {$reason}");
    }

    /**
     * Approve user creation request (Super Admin Only - UAC-003).
     */
    public function approveUserCreation(Request $request, UserCreationRequest $creationRequest): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $targetUser = $creationRequest->targetUser;

        $creationRequest->update([
            'status' => 'approved',
            'actioned_by' => $actor->id,
            'actioned_at' => now(),
        ]);

        if ($targetUser) {
            $targetUser->update(['status' => 'active']);

            ActivityLogger::log('APPROVAL_APPROVED', "Approved creation request for user account {$targetUser->name} ({$targetUser->email})", $targetUser);
            ActivityLogger::log('ACCOUNT_ACTIVATED', "Activated staff account for {$targetUser->name} ({$targetUser->email})", $targetUser);
        }

        if ($creationRequest->requester) {
            try {
                $creationRequest->requester->notify(new EnterpriseSystemNotification(
                    title: 'User Creation Approved',
                    message: "Your creation request for staff account '{$targetUser?->name}' ({$targetUser?->email}) was approved by Super Admin {$actor->name}. The account is now active.",
                    type: 'STAFF_ACCOUNT_APPROVED',
                    priority: 'HIGH',
                    entityType: 'user',
                    entityId: (string) $targetUser?->id,
                    targetUrl: route('admin.users.index')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "User creation request for '{$targetUser?->name}' approved and activated successfully.");
    }

    /**
     * Reject user creation request (Super Admin Only - UAC-003).
     */
    public function rejectUserCreation(Request $request, UserCreationRequest $creationRequest): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $reason = $request->input('reason', 'Request rejected by Super Admin.');
        $targetUser = $creationRequest->targetUser;

        $creationRequest->update([
            'status' => 'rejected',
            'actioned_by' => $actor->id,
            'actioned_at' => now(),
        ]);

        if ($targetUser) {
            $targetUser->update(['status' => 'inactive']);
            ActivityLogger::log('APPROVAL_REJECTED', "Rejected creation request for user account {$targetUser->name} ({$targetUser->email}). Reason: {$reason}", $targetUser);
        }

        if ($creationRequest->requester) {
            try {
                $creationRequest->requester->notify(new EnterpriseSystemNotification(
                    title: 'User Creation Rejected',
                    message: "Your creation request for staff account '{$targetUser?->name}' ({$targetUser?->email}) was rejected. Reason: {$reason}",
                    type: 'STAFF_ACCOUNT_REJECTED',
                    priority: 'NORMAL',
                    entityType: 'user',
                    entityId: (string) $targetUser?->id,
                    targetUrl: route('admin.users.index')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "User creation request for '{$targetUser?->name}' rejected.");
    }

    /**
     * Approve user deletion request (Super Admin Only).
     */
    public function approveUserDeletion(Request $request, UserDeletionRequest $deletionRequest): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $targetUser = $deletionRequest->targetUser;

        $deletionRequest->update([
            'status' => 'approved',
            'actioned_by' => $actor->id,
            'actioned_at' => now(),
        ]);

        if ($targetUser) {
            $targetUser->update(['status' => 'deleted']);
            $targetUser->delete();
            ActivityLogger::log('DELETE_REQUEST_APPROVED', "Approved deletion request for user account {$targetUser->name} ({$targetUser->email})", $targetUser);
            ActivityLogger::log('USER_SOFT_DELETED', "Soft deleted user account {$targetUser->name} ({$targetUser->email})", $targetUser);
        }

        if ($deletionRequest->requester) {
            try {
                $deletionRequest->requester->notify(new EnterpriseSystemNotification(
                    title: 'User Deletion Request Approved',
                    message: "Your deletion request for user account '{$targetUser?->name}' ({$targetUser?->email}) was approved by Super Admin {$actor->name}.",
                    type: 'USER_DELETION_APPROVED',
                    priority: 'NORMAL',
                    entityType: 'user',
                    entityId: (string) $targetUser?->id,
                    targetUrl: route('admin.users.index')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "User deletion request for '{$targetUser?->name}' approved and soft-deleted successfully.");
    }

    /**
     * Reject user deletion request (Super Admin Only).
     */
    public function rejectUserDeletion(Request $request, UserDeletionRequest $deletionRequest): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $reason = $request->input('reason', 'Request rejected by Super Admin.');
        $targetUser = $deletionRequest->targetUser;

        $deletionRequest->update([
            'status' => 'rejected',
            'actioned_by' => $actor->id,
            'actioned_at' => now(),
        ]);

        if ($targetUser) {
            $targetUser->update(['status' => 'active']);
            ActivityLogger::log('DELETE_REQUEST_REJECTED', "Rejected deletion request for user account {$targetUser->name} ({$targetUser->email}). Reason: {$reason}", $targetUser);
        }

        if ($deletionRequest->requester) {
            try {
                $deletionRequest->requester->notify(new EnterpriseSystemNotification(
                    title: 'User Deletion Request Rejected',
                    message: "Your deletion request for user account '{$targetUser?->name}' ({$targetUser?->email}) was rejected. Reason: {$reason}",
                    type: 'USER_DELETION_REJECTED',
                    priority: 'NORMAL',
                    entityType: 'user',
                    entityId: (string) $targetUser?->id,
                    targetUrl: route('admin.users.index')
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "User deletion request for '{$targetUser?->name}' rejected. Status reverted to Active.");
    }
}
