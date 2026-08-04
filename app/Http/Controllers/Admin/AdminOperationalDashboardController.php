<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankArchiveRequest;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\Reporting\Services\SystemHealthService;
use App\Services\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminOperationalDashboardController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService
    ) {}

    /**
     * Display Admin Operational Dashboard (ADMIN-OPS-001 Section 5).
     * Redesigned around operational workload, live workflow counters, task center.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Section 5 Required Cards (live workflow data)
        $readyToPublishQuestionBanks = QuestionBank::where('status', 'approved')
            ->whereNotIn('status', ['published'])
            ->count();

        $readyToPublishAssessments = Test::where('status', 'approved')
            ->where('is_published', false)
            ->count();

        $pendingArchiveRequests = QuestionBankArchiveRequest::where('status', 'pending')->count();

        $publishedTodayBanks = QuestionBank::where('status', 'published')
            ->whereDate('updated_at', today())
            ->count();
        $publishedTodayAssessments = Test::where('is_published', true)
            ->whereDate('updated_at', today())
            ->count();
        $publishedToday = $publishedTodayBanks + $publishedTodayAssessments;

        $publishedThisWeekBanks = QuestionBank::where('status', 'published')
            ->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
        $publishedThisWeekAssessments = Test::where('is_published', true)
            ->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
        $publishedThisWeek = $publishedThisWeekBanks + $publishedThisWeekAssessments;

        $certificatesGeneratedToday = Certificate::whereDate('created_at', today())->count();

        // Section 6 Task Center: My Tasks (clickable)
        $tasks = [];

        $readyBanksList = QuestionBank::where('status', 'approved')
            ->latest('updated_at')
            ->take(5)
            ->get();

        foreach ($readyBanksList as $bank) {
            $tasks[] = [
                'icon' => '📂',
                'title' => "Question Bank ready for publication",
                'entity' => $bank->title,
                'url' => route('admin.publications.question-banks', ['status' => 'approved', 'highlight' => $bank->id]),
                'priority' => 'HIGH',
                'type' => 'QUESTION_BANK_READY',
            ];
        }

        $readyAssessmentsList = Test::where('status', 'approved')
            ->where('is_published', false)
            ->latest('updated_at')
            ->take(5)
            ->get();

        foreach ($readyAssessmentsList as $assessment) {
            $tasks[] = [
                'icon' => '📋',
                'title' => "Assessment ready for publication",
                'entity' => $assessment->title,
                'url' => route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $assessment->id]),
                'priority' => 'HIGH',
                'type' => 'ASSESSMENT_READY',
            ];
        }

        $approvedArchives = QuestionBankArchiveRequest::with('questionBank')
            ->where('status', 'approved')
            ->latest()
            ->take(3)
            ->get();

        foreach ($approvedArchives as $archReq) {
            $tasks[] = [
                'icon' => '📦',
                'title' => "Archive request approved — action complete",
                'entity' => $archReq->questionBank?->title ?? 'Question Bank',
                'url' => route('admin.publications.archive-requests'),
                'priority' => 'NORMAL',
                'type' => 'ARCHIVE_COMPLETE',
            ];
        }

        // Recent publications feed
        $recentPublications = collect();
        $recentBanks = QuestionBank::where('status', 'published')
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn ($b) => [
                'icon' => '📂',
                'title' => $b->title,
                'type' => 'Question Bank',
                'date' => $b->updated_at?->diffForHumans(),
                'url' => route('admin.publications.question-banks'),
            ]);
        $recentAssessments = Test::where('is_published', true)
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'icon' => '📋',
                'title' => $t->title,
                'type' => 'Assessment',
                'date' => $t->updated_at?->diffForHumans(),
                'url' => route('admin.publications.assessments'),
            ]);

        $recentPublications = $recentBanks->concat($recentAssessments)
            ->sortByDesc('date')
            ->take(8)
            ->values();

        // Unread notifications for task center alert badge
        $unreadNotificationsCount = $user ? $user->unreadNotifications->count() : 0;

        return view('admin.operational_dashboard', compact(
            'readyToPublishQuestionBanks',
            'readyToPublishAssessments',
            'pendingArchiveRequests',
            'publishedToday',
            'publishedThisWeek',
            'certificatesGeneratedToday',
            'tasks',
            'recentPublications',
            'unreadNotificationsCount'
        ));
    }
}
