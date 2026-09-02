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
     * Operational Admin Workspace: Candidate Operations, Assessment Assignments, Payment Eligibility.
     */
    public function adminIndex(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('repository-manager') && ! $user->hasRole(['admin', 'super-admin'])) {
            return redirect()->route('admin.repository-manager.dashboard');
        }

        return app(AdminOperationalDashboardController::class)->index($request);
    }

    /**
     * Default index method fallback.
     */
    public function index(): View
    {
        return $this->superAdminIndex();
    }
}
