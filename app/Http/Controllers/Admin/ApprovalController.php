<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserCreationRequest;
use App\Models\UserDeletionRequest;
use App\Modules\Assessment\Models\Test;
use App\Notifications\SystemAlertNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    /**
     * Display Super Admin Approval Center for tests, user creation & deletion requests.
     */
    public function index(): View
    {
        $pendingTests = Test::with(['creator', 'sections'])
            ->whereIn('status', ['pending_approval', 'draft'])
            ->orWhereNull('status')
            ->latest()
            ->paginate(10);

        $userCreationRequests = UserCreationRequest::with(['targetUser', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $userDeletionRequests = UserDeletionRequest::with(['targetUser', 'requester'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $publishedCount = Test::where('is_published', true)->count();
        $pendingCount = Test::where('status', 'pending_approval')->count();
        $pendingUserCreationCount = $userCreationRequests->count();
        $pendingUserDeletionCount = $userDeletionRequests->count();

        return view('admin.approvals.index', compact(
            'pendingTests',
            'publishedCount',
            'pendingCount',
            'userCreationRequests',
            'pendingUserCreationCount',
            'userDeletionRequests',
            'pendingUserDeletionCount'
        ));
    }

    /**
     * Approve an assessment test (Super Admin Only).
     */
    public function approve(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $test->update([
            'status' => 'approved',
            'is_published' => false,
        ]);

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' approved successfully. It is now ready for Admin publication.");
    }

    /**
     * Reject an assessment test back to draft (Super Admin Only).
     */
    public function reject(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Approval Center operations are strictly reserved for Super Admin.');
        }

        $reason = $request->input('reason', 'Requires revisions before publication.');

        $test->update([
            'status' => 'rejected',
            'is_published' => false,
        ]);

        return redirect()->route('admin.approvals.index')->with('status', "Assessment '{$test->title}' rejected. Reason: {$reason}");
    }

    /**
     * Approve user creation request (Super Admin Only - UAC-003).
     */
    public function approveUserCreation(Request $request, UserCreationRequest $creationRequest): RedirectResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->hasRole('super-admin')) {
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

            ActivityLogger::log(
                'APPROVAL_APPROVED',
                "Approved creation request for user account {$targetUser->name} ({$targetUser->email})",
                $targetUser
            );

            ActivityLogger::log(
                'ACCOUNT_ACTIVATED',
                "Activated staff account for {$targetUser->name} ({$targetUser->email})",
                $targetUser
            );
        }

        // Notify requesting Admin
        if ($creationRequest->requester) {
            try {
                $creationRequest->requester->notify(new SystemAlertNotification(
                    'User Creation Approved',
                    "Your creation request for staff account '{$targetUser?->name}' ({$targetUser?->email}) was approved by Super Admin {$actor->name}. The account is now active."
                ));
            } catch (\Throwable $e) {
                // Silently handle notification in dev
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
        if (!$actor || !$actor->hasRole('super-admin')) {
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

            ActivityLogger::log(
                'APPROVAL_REJECTED',
                "Rejected creation request for user account {$targetUser->name} ({$targetUser->email}). Reason: {$reason}",
                $targetUser
            );
        }

        // Notify requesting Admin
        if ($creationRequest->requester) {
            try {
                $creationRequest->requester->notify(new SystemAlertNotification(
                    'User Creation Rejected',
                    "Your creation request for staff account '{$targetUser?->name}' ({$targetUser?->email}) was rejected by Super Admin {$actor->name}. Reason: {$reason}"
                ));
            } catch (\Throwable $e) {
                // Silently handle notification in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "User creation request for '{$targetUser?->name}' rejected. Account set to Inactive.");
    }

    /**
     * Approve user deletion request (Super Admin Only / Soft Delete).
     */
    public function approveUserDeletion(Request $request, UserDeletionRequest $deletionRequest): RedirectResponse
    {
        $actor = $request->user();
        if (!$actor || !$actor->hasRole('super-admin')) {
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
            $targetUser->delete(); // Soft delete user

            ActivityLogger::log(
                'DELETE_REQUEST_APPROVED',
                "Approved deletion request for user account {$targetUser->name} ({$targetUser->email})",
                $targetUser
            );

            ActivityLogger::log(
                'USER_SOFT_DELETED',
                "Soft deleted user account {$targetUser->name} ({$targetUser->email})",
                $targetUser
            );
        }

        // Notify requesting Admin
        if ($deletionRequest->requester) {
            try {
                $deletionRequest->requester->notify(new SystemAlertNotification(
                    'User Deletion Request Approved',
                    "Your deletion request for user account '{$targetUser?->name}' ({$targetUser?->email}) was approved by Super Admin {$actor->name}."
                ));
            } catch (\Throwable $e) {
                // Silently handle notification in dev
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
        if (!$actor || !$actor->hasRole('super-admin')) {
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

            ActivityLogger::log(
                'DELETE_REQUEST_REJECTED',
                "Rejected deletion request for user account {$targetUser->name} ({$targetUser->email}). Reason: {$reason}",
                $targetUser
            );
        }

        // Notify requesting Admin
        if ($deletionRequest->requester) {
            try {
                $deletionRequest->requester->notify(new SystemAlertNotification(
                    'User Deletion Request Rejected',
                    "Your deletion request for user account '{$targetUser?->name}' ({$targetUser?->email}) was rejected by Super Admin {$actor->name}. Reason: {$reason}"
                ));
            } catch (\Throwable $e) {
                // Silently handle notification in dev
            }
        }

        return redirect()->route('admin.approvals.index')->with('status', "User deletion request for '{$targetUser?->name}' rejected. Status reverted to Active.");
    }
}
