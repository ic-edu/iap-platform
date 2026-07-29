<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\Reporting\Services\SystemHealthService;
use App\Services\ApprovalEngine;
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
     */
    public function adminIndex(): View
    {
        $totalUsers = User::count();
        $teachersCount = User::role('teacher')->count();
        $studentsCount = User::role('student')->count();

        $publishedQuestionBanksCount = QuestionBank::where('status', 'published')
            ->orWhere('is_published', true)
            ->count();
        $publishedTestsCount = AssessmentTest::where('is_published', true)->count();
        $pendingApprovalsCount = ApprovalEngine::getTotalPendingCount();

        $recentUsers = User::latest()->take(5)->get();

        return view('admin.admin_dashboard', compact(
            'totalUsers',
            'teachersCount',
            'studentsCount',
            'publishedQuestionBanksCount',
            'publishedTestsCount',
            'pendingApprovalsCount',
            'recentUsers'
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
