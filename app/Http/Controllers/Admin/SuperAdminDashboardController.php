<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\Reporting\Services\SystemHealthService;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService
    ) {}

    /**
     * Display Super Admin Platform Overview Landing Dashboard (/super-admin/dashboard).
     * Strictly computes non-overlapping role counters for Baseline v1.1 compliance.
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

        $questionBanksCount = QuestionBank::count();
        $publishedTestsCount = AssessmentTest::where('is_published', true)->count();
        $pendingApprovalsCount = AssessmentTest::whereIn('status', ['pending_approval', 'draft'])
            ->orWhereNull('status')
            ->count();
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
            'questionBanksCount',
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

        $questionBanksCount = QuestionBank::count();
        $publishedTestsCount = AssessmentTest::where('is_published', true)->count();
        $pendingApprovalsCount = AssessmentTest::where('status', 'pending_approval')->count();

        $recentUsers = User::latest()->take(5)->get();

        return view('admin.admin_dashboard', compact(
            'totalUsers',
            'teachersCount',
            'studentsCount',
            'questionBanksCount',
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
