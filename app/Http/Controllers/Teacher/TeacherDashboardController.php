<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\View\View;

use App\Services\AssessmentWorkflowService;

class TeacherDashboardController extends Controller
{
    /**
     * Display Teacher Authoring Workspace Dashboard (TEACHER-UX-001 v2).
     *
     * All counts are scoped to the authenticated teacher's own question banks and assessments.
     */
    public function index(Request $request, AssessmentWorkflowService $workflowService): View
    {
        $user = $request->user();

        // ──────────────────────────────────────────────
        // KPI counts — scoped to THIS teacher's banks
        // ──────────────────────────────────────────────
        $myBanksQuery = QuestionBank::where('created_by', $user->id);

        $totalQuestionBanks       = (clone $myBanksQuery)->count();
        $draftQuestionBanks       = (clone $myBanksQuery)->where('status', 'draft')->count();
        $pendingApprovalQuestionBanks = (clone $myBanksQuery)->where('status', 'pending_approval')->count();
        $publishedQuestionBanks   = (clone $myBanksQuery)->where(function ($q) {
            $q->where('status', 'published')->orWhere('is_published', true);
        })->count();
        $approvedQuestionBanks    = (clone $myBanksQuery)->where('status', 'approved')->count();
        $archivedQuestionBanks    = (clone $myBanksQuery)->where('status', 'archived')->count();
        $rejectedQuestionBanks    = (clone $myBanksQuery)->where('status', 'rejected')->count();

        // PART E: Synchronized Assessment Test Metrics from AssessmentWorkflowService
        $testMetrics              = $workflowService->getTeacherMetrics($user);
        $draftAssessments         = $testMetrics['draft'];
        $pendingAssessments       = $testMetrics['pending'];
        $approvedAssessments      = $testMetrics['approved'];
        $needsRevisionAssessments = $testMetrics['needs_revision'];
        $archivedAssessments      = $testMetrics['archived'];

        // Combined Single Source of Truth Metrics for Teacher Dashboard (DEFECT 1 Workflow Inbox)
        $workflowInbox  = $workflowService->getWorkflowInboxMetrics($user);
        $pendingTotal   = $workflowInbox['total'];
        $draftTotal     = $draftQuestionBanks + $draftAssessments;
        $approvedTotal  = $approvedQuestionBanks + $approvedAssessments;
        $archivedTotal  = $archivedQuestionBanks + $archivedAssessments;

        // Actionable Repository Revision Tasks (RRWE v1.0)
        // Actionable ONLY when RevisionRequest is OPEN/IN_PROGRESS and QuestionBank is currently editable by Teacher
        $pendingRepoRevCount = \App\Models\RepositoryRevisionRequest::where(function ($q) use ($user) {
            $q->where('teacher_id', $user->id)
              ->orWhereHas('questionBank', fn($bq) => $bq->where('created_by', $user->id));
        })
        ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
        ->whereHas('questionBank', function ($bq) {
            $bq->whereIn('status', ['needs_revision', 'draft', 'rejected']);
        })
        ->count();

        // ──────────────────────────────────────────────
        // Recent banks for the activity widget (latest 8, with questions)
        // ──────────────────────────────────────────────
        $recentQuestionBanks = QuestionBank::with(['category', 'questions'])
            ->where('created_by', $user->id)
            ->latest()
            ->take(8)
            ->get();

        // ──────────────────────────────────────────────
        // Notifications (unread first, latest 8)
        // ──────────────────────────────────────────────
        $notifications = $user
            ? $user->notifications()
                ->orderBy('read_at', 'asc')
                ->latest()
                ->take(8)
                ->get()
            : collect();

        $unreadNotificationCount = $user
            ? $user->unreadNotifications()->count()
            : 0;

        // Latest unfinished draft for "Continue Working" section (PART 6)
        $latestDraftBank = QuestionBank::with(['questions'])
            ->where('created_by', $user->id)
            ->whereIn('status', ['draft', 'rejected', 'revision_requested'])
            ->latest('updated_at')
            ->first();

        $latestDraftTest = Test::with(['sections.testQuestions'])
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
            })
            ->whereIn('status', ['draft', 'rejected', 'revision_requested', 'needs_revision'])
            ->latest('updated_at')
            ->first();

        return view('teacher.dashboard', compact(
            'totalQuestionBanks',
            'draftQuestionBanks',
            'pendingApprovalQuestionBanks',
            'publishedQuestionBanks',
            'approvedQuestionBanks',
            'archivedQuestionBanks',
            'rejectedQuestionBanks',
            'draftAssessments',
            'pendingAssessments',
            'approvedAssessments',
            'needsRevisionAssessments',
            'archivedAssessments',
            'draftTotal',
            'pendingTotal',
            'approvedTotal',
            'archivedTotal',
            'workflowInbox',
            'pendingRepoRevCount',
            'recentQuestionBanks',
            'latestDraftBank',
            'latestDraftTest',
            'notifications',
            'unreadNotificationCount'
        ));
    }

    /**
     * Display Teacher Revision Center (TASK 2, 3, 4).
     */
    public function revisionCenter(Request $request): View
    {
        $user = $request->user();

        // Fetch Question Banks authored by teacher that require revision
        $revisionQuestionBanks = QuestionBank::with(['category', 'questions'])
            ->where('created_by', $user->id)
            ->whereIn('status', ['needs_revision', 'revision_requested', 'rejected'])
            ->latest('updated_at')
            ->get();

        // Attach latest repository feedback comments and reviewer details to each bank
        foreach ($revisionQuestionBanks as $bank) {
            $latestLog = \App\Models\RepositoryActivityLog::where('resource_type', 'QuestionBank')
                ->where('resource_id', (string) $bank->id)
                ->whereIn('action', ['revision_requested', 'rejected'])
                ->with(['reviewer'])
                ->latest()
                ->first();

            $bank->latest_feedback = $latestLog?->approval_note ?? 'Revision requested by Repository Manager. Please review questions and resubmit.';
            $bank->reviewer_name   = $latestLog?->reviewer?->name ?? 'Repository Manager';
            $bank->returned_at     = $latestLog?->created_at ?? $bank->updated_at;
        }

        // Fetch Assessments authored by teacher that require revision
        $revisionAssessments = Test::with(['sections'])
            ->where('created_by', $user->id)
            ->whereIn('status', ['needs_revision', 'revision_requested', 'rejected'])
            ->latest('updated_at')
            ->get();

        foreach ($revisionAssessments as $test) {
            $latestTestLog = \App\Models\RepositoryActivityLog::where('resource_type', 'Test')
                ->where('resource_id', (string) $test->id)
                ->whereIn('action', ['revision_requested', 'rejected'])
                ->with(['reviewer'])
                ->latest()
                ->first();

            $test->latest_feedback = $latestTestLog?->approval_note ?? 'Revision requested for assessment test.';
            $test->reviewer_name   = $latestTestLog?->reviewer?->name ?? 'Repository Manager';
            $test->returned_at     = $latestTestLog?->created_at ?? $test->updated_at;
        }

        return view('teacher.revision_center', compact('revisionQuestionBanks', 'revisionAssessments'));
    }
}
