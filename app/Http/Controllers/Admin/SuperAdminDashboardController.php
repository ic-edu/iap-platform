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
     * Display Super Admin Platform Overview Landing Dashboard.
     */
    public function index(): View
    {
        $totalUsers = User::count();
        $teachersCount = User::role('teacher')->count();
        $studentsCount = User::role('student')->count();
        $adminsCount = User::role(['admin', 'super-admin'])->count();
        $financeCount = User::role('finance')->count();

        $questionBanksCount = QuestionBank::count();
        $publishedTestsCount = AssessmentTest::where('is_published', true)->count();
        $pendingApprovalsCount = AssessmentTest::where('is_published', false)->count();
        $certificatesCount = Certificate::count();

        $systemHealth = $this->healthService->checkHealth();
        $recentUsers = User::latest()->take(5)->get();
        $recentCertificates = Certificate::with('user')->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'teachersCount',
            'studentsCount',
            'adminsCount',
            'financeCount',
            'questionBanksCount',
            'publishedTestsCount',
            'pendingApprovalsCount',
            'certificatesCount',
            'systemHealth',
            'recentUsers',
            'recentCertificates'
        ));
    }
}
