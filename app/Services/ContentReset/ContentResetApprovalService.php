<?php

namespace App\Services\ContentReset;

use App\Models\ContentResetAuditLog;
use App\Models\ContentResetRequest;
use App\Models\User;
use InvalidArgumentException;

class ContentResetApprovalService
{
    /**
     * CEO Approval for Hard Reset.
     */
    public function approveCeo(ContentResetRequest $request, User $ceo, ?string $notes = null): ContentResetRequest
    {
        if ($request->status === 'blocked') {
            throw new InvalidArgumentException("Cannot approve a blocked reset request. Resolve forensic blockers first.");
        }

        if ($ceo->id === $request->requested_by) {
            throw new InvalidArgumentException("Self-approval prohibited: The requester cannot approve their own reset request.");
        }

        if (!$ceo->hasRole('ceo') && !$ceo->hasRole('super-admin')) {
            throw new InvalidArgumentException("User does not possess CEO executive approval authority.");
        }

        $request->update([
            'ceo_approver_id' => $ceo->id,
            'ceo_approved_at' => now(),
            'ceo_notes'       => $notes,
        ]);

        ContentResetAuditLog::create([
            'request_id' => $request->id,
            'action'     => 'CEO_APPROVED',
            'actor_id'   => $ceo->id,
            'payload'    => [
                'notes'      => $notes,
                'is_complete' => $request->isFullyApproved(),
            ],
        ]);

        if ($request->isSaApproved() && $request->sa_approver_id !== $ceo->id) {
            $request->update(['status' => 'approved']);
        } else {
            $request->update(['status' => 'awaiting_sa_approval']);
        }

        return $request->fresh();
    }

    /**
     * Super Admin Approval for Hard Reset.
     */
    public function approveSuperAdmin(ContentResetRequest $request, User $superAdmin, ?string $notes = null): ContentResetRequest
    {
        if ($request->status === 'blocked') {
            throw new InvalidArgumentException("Cannot approve a blocked reset request. Resolve forensic blockers first.");
        }

        if ($superAdmin->id === $request->requested_by) {
            throw new InvalidArgumentException("Self-approval prohibited: The requester cannot approve their own reset request.");
        }

        if (!$superAdmin->hasRole('super-admin')) {
            throw new InvalidArgumentException("User does not possess Super Admin approval authority.");
        }

        if ($request->ceo_approver_id && $superAdmin->id === $request->ceo_approver_id) {
            throw new InvalidArgumentException("Dual-approval violation: The same user cannot satisfy both CEO and Super Admin approvals.");
        }

        $request->update([
            'sa_approver_id' => $superAdmin->id,
            'sa_approved_at' => now(),
            'sa_notes'       => $notes,
        ]);

        ContentResetAuditLog::create([
            'request_id' => $request->id,
            'action'     => 'SUPER_ADMIN_APPROVED',
            'actor_id'   => $superAdmin->id,
            'payload'    => [
                'notes'      => $notes,
                'is_complete' => $request->isFullyApproved(),
            ],
        ]);

        if ($request->isCeoApproved() && $request->ceo_approver_id !== $superAdmin->id) {
            $request->update(['status' => 'approved']);
        } else {
            $request->update(['status' => 'awaiting_ceo_approval']);
        }

        return $request->fresh();
    }

    /**
     * Reject a reset request.
     */
    public function reject(ContentResetRequest $request, User $user, string $reason): ContentResetRequest
    {
        $request->update([
            'status' => 'rejected',
        ]);

        ContentResetAuditLog::create([
            'request_id' => $request->id,
            'action'     => 'RESET_REJECTED',
            'actor_id'   => $user->id,
            'payload'    => [
                'reason' => $reason,
            ],
        ]);

        return $request->fresh();
    }

    /**
     * Mutate scope of a request, invalidating any previous approvals.
     */
    public function mutateScope(ContentResetRequest $request, array $newScope, ?array $newTargets = null): ContentResetRequest
    {
        $hadApprovals = $request->isCeoApproved() || $request->isSaApproved() || $request->status === 'approved';

        $request->update([
            'scope'            => $newScope,
            'target_entities'  => $newTargets ?? $request->target_entities,
            'ceo_approver_id'  => null,
            'ceo_approved_at'  => null,
            'sa_approver_id'   => null,
            'sa_approved_at'   => null,
            'status'           => 'audit_pending',
            'audit_report'     => null,
        ]);

        ContentResetAuditLog::create([
            'request_id' => $request->id,
            'action'     => 'SCOPE_MUTATED_INVALIDATED',
            'actor_id'   => null,
            'payload'    => [
                'had_prior_approvals' => $hadApprovals,
                'new_scope'           => $newScope,
                'new_targets'         => $newTargets,
            ],
        ]);

        return $request->fresh();
    }
}
