<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankArchiveRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\Reporting\Services\SystemHealthService;
use App\Services\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService
    ) {}

    /**
     * Display Super Admin Executive Platform Overview Landing Dashboard (/super-admin/dashboard).
     * Synchronized with Approval Engine (SA-006).
     */
    public function superAdminIndex(): View
    {
        $totalUsers = User::count();
        $superAdminsCount = User::role('super-admin')->count();
        $adminsCount = User::role('admin')
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'super-admin');
            })->count();

        $teachersCount = User::role('teacher')->count();
        $financeCount = User::role('finance')->count();
        $studentsCount = User::role('student')->count();

        // SA-006: Display Published Question Banks only (excluding Draft, Pending, Rejected, Archived)
        $publishedQuestionBanksCount = QuestionBank::where('status', 'published')
            ->orWhere('is_published', true)
            ->count();

        $publishedTestsCount = AssessmentTest::where('is_published', true)->count();

        // SA-006: Synchronized Approval Engine total pending approvals
        $pendingApprovalsCount = ApprovalEngine::getTotalPendingCount();
        $certificatesCount = Certificate::count();

        $systemHealth = $this->healthService->checkHealth();
        $recentUsers = User::latest()->take(5)->get();
        $recentCertificates = Certificate::with('user')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'superAdminsCount',
            'adminsCount',
            'teachersCount',
            'financeCount',
            'studentsCount',
            'publishedQuestionBanksCount',
            'publishedTestsCount',
            'pendingApprovalsCount',
            'certificatesCount',
            'systemHealth',
            'recentUsers',
            'recentCertificates'
        ));
    }

    /**
     * Display Admin Operational Landing Dashboard (/admin/dashboard).
     * ADMIN-OPS-001 Section 5: Operational counters from live workflow state.
     */
    public function adminIndex(Request $request): View
    {
        $user = $request->user();

        // Section 5 Operational Counters
        $readyToPublishQuestionBanks = QuestionBank::where('status', 'approved')->count();
        $readyToPublishAssessments = AssessmentTest::where('status', 'approved')
            ->where('is_published', false)->count();
        $pendingArchiveRequests = QuestionBankArchiveRequest::where('status', 'pending')->count();

        $publishedTodayBanks = QuestionBank::where('status', 'published')
            ->whereDate('updated_at', today())->count();
        $publishedTodayAssessments = AssessmentTest::where('is_published', true)
            ->whereDate('updated_at', today())->count();
        $publishedToday = $publishedTodayBanks + $publishedTodayAssessments;

        $publishedThisWeekBanks = QuestionBank::where('status', 'published')
            ->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $publishedThisWeekAssessments = AssessmentTest::where('is_published', true)
            ->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $publishedThisWeek = $publishedThisWeekBanks + $publishedThisWeekAssessments;

        $certificatesGeneratedToday = Certificate::whereDate('created_at', today())->count();

        // Section 6 Task Center: Action items
        $tasks = [];
        $readyBanksList = QuestionBank::where('status', 'approved')
            ->latest('updated_at')->take(5)->get();
        foreach ($readyBanksList as $bank) {
            $tasks[] = [
                'icon' => '📂',
                'title' => 'Question Bank ready for publication',
                'entity' => $bank->title,
                'url' => route('admin.publications.question-banks', ['status' => 'approved', 'highlight' => $bank->id]),
                'priority' => 'HIGH',
                'type' => 'QUESTION_BANK_READY',
            ];
        }
        $readyAssessmentsList = AssessmentTest::where('status', 'approved')
            ->where('is_published', false)->latest('updated_at')->take(5)->get();
        foreach ($readyAssessmentsList as $assessment) {
            $tasks[] = [
                'icon' => '📋',
                'title' => 'Assessment ready for publication',
                'entity' => $assessment->title,
                'url' => route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $assessment->id]),
                'priority' => 'HIGH',
                'type' => 'ASSESSMENT_READY',
            ];
        }

        // Recent Publications Feed
        $recentBanks = QuestionBank::where('status', 'published')
            ->latest('updated_at')->take(5)->get()
            ->map(fn ($b) => [
                'icon' => '📂', 'title' => $b->title, 'type' => 'Question Bank',
                'date' => $b->updated_at?->diffForHumans(),
                'url' => route('admin.publications.question-banks'),
            ]);
        $recentAssessments = AssessmentTest::where('is_published', true)
            ->latest('updated_at')->take(5)->get()
            ->map(fn ($t) => [
                'icon' => '📋', 'title' => $t->title, 'type' => 'Assessment',
                'date' => $t->updated_at?->diffForHumans(),
                'url' => route('admin.publications.assessments'),
            ]);
        $recentPublications = $recentBanks->concat($recentAssessments)
            ->sortByDesc('date')->take(8)->values();

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

    /**
     * Default index method fallback.
     */
    public function index(): View
    {
        return $this->superAdminIndex();
    }
}
