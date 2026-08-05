<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use Illuminate\Support\Carbon;

class AssessmentWorkflowService
{
    /**
     * Get synchronized assessment metrics for Teacher Workspace (PART E).
     * Allowed statuses: draft, pending, approved, needs_revision, archived.
     */
    public function getTeacherMetrics(User $user): array
    {
        $baseQuery = Test::where('created_by', $user->id);

        $draft         = (clone $baseQuery)->where('status', 'draft')->count();
        $pending       = (clone $baseQuery)->whereIn('status', ['pending', 'pending_approval'])->count();
        $approved      = (clone $baseQuery)->where('status', 'approved')->count();
        $needsRevision = (clone $baseQuery)->whereIn('status', ['needs_revision', 'revision_requested'])->count();
        $archived      = (clone $baseQuery)->where('status', 'archived')->count();

        return [
            'draft'          => $draft,
            'pending'        => $pending,
            'approved'       => $approved,
            'needs_revision' => $needsRevision,
            'archived'       => $archived,
        ];
    }

    /**
     * Get synchronized assessment metrics for Repository Manager Command Center (PART E).
     */
    public function getRepositoryManagerMetrics(): array
    {
        $pendingAssessmentsCount  = Test::whereIn('status', ['pending', 'pending_approval'])->count();
        $approvedAssessmentsToday = Test::where('status', 'approved')
            ->whereDate('updated_at', Carbon::today())
            ->count();
        $needsRevisionCount       = Test::whereIn('status', ['needs_revision', 'revision_requested'])->count();
        $totalApproved            = Test::where('status', 'approved')->count();

        return [
            'pendingAssessmentsCount'  => $pendingAssessmentsCount,
            'approvedAssessmentsToday' => $approvedAssessmentsToday,
            'needsRevisionCount'       => $needsRevisionCount,
            'totalApproved'            => $totalApproved,
        ];
    }

    /**
     * Get synchronized assessment metrics for Super Admin Governance Monitoring (PART E).
     */
    public function getSuperAdminMetrics(): array
    {
        $totalAssessments         = Test::count();
        $pendingAssessments       = Test::where('status', 'pending')->count();
        $approvedAssessments      = Test::where('status', 'approved')->count();
        $needsRevisionAssessments = Test::where('status', 'needs_revision')->count();
        $archivedAssessments      = Test::where('status', 'archived')->count();

        return [
            'totalAssessments'         => $totalAssessments,
            'pendingAssessments'       => $pendingAssessments,
            'approvedAssessments'      => $approvedAssessments,
            'needsRevisionAssessments' => $needsRevisionAssessments,
            'archivedAssessments'      => $archivedAssessments,
        ];
    }

    /**
     * Transition assessment status safely enforcing status and is_published logic (PART D).
     */
    public function transition(Test $test, string $targetStatus, ?string $reason = null): Test
    {
        $targetStatus = match ($targetStatus) {
            'pending_approval', 'pending_review' => 'pending',
            'revision_requested', 'rejected'     => 'needs_revision',
            default                              => $targetStatus,
        };

        $isPublished = ($targetStatus === 'approved');

        $test->update([
            'status'       => $targetStatus,
            'is_published' => $isPublished,
        ]);

        return $test;
    }
}
