<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDashboardController extends Controller
{
    /**
     * Display Teacher Authoring Workspace Dashboard (TEACHER-UX-001 v2).
     *
     * All counts are scoped to the authenticated teacher's own question banks and assessments.
     */
    public function index(Request $request): View
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

        // SPRINT 10.2: Synchronized Assessment Test Metrics
        $myTestsQuery = Test::where('created_by', $user->id);

        $draftAssessments       = (clone $myTestsQuery)->whereIn('status', ['draft'])->count();
        $pendingAssessments     = (clone $myTestsQuery)->whereIn('status', ['pending', 'pending_approval'])->count();
        $approvedAssessments    = (clone $myTestsQuery)->where('status', 'approved')->count();
        $needsRevisionAssessments = (clone $myTestsQuery)->whereIn('status', ['needs_revision', 'revision_requested', 'rejected'])->count();
        $archivedAssessments    = (clone $myTestsQuery)->where('status', 'archived')->count();

        // Combined Single Source of Truth Metrics for Teacher Dashboard
        $draftTotal     = $draftQuestionBanks + $draftAssessments;
        $pendingTotal   = $pendingApprovalQuestionBanks + $pendingAssessments;
        $approvedTotal  = $approvedQuestionBanks + $approvedAssessments;
        $archivedTotal  = $archivedQuestionBanks + $archivedAssessments;

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
            'recentQuestionBanks',
            'latestDraftBank',
            'notifications',
            'unreadNotificationCount'
        ));
    }
}
