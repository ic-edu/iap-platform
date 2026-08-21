<?php

namespace App\Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    /**
     * Display operational reporting dashboard analytics.
     */
    public function index(Request $request): View
    {
        if ($request->user()?->hasRole('finance')) {
            abort(403, 'Finance users are restricted to Financial & Commerce Reports.');
        }

        // 1. People Metrics
        $totalStudents = User::role('student')->count();
        $totalTeachers = User::role('teacher')->count();
        $totalRMs = User::role('repository-manager')->count();
        $totalAdmins = User::role('admin')->count();
        $totalSuperAdmins = User::role('super-admin')->count();
        $totalStaff = $totalTeachers + $totalRMs + $totalAdmins + $totalSuperAdmins;

        // 2. Assessment Metrics
        $totalTests = Test::count();
        $publishedTests = Test::where('is_published', true)->count();
        $simulatorTests = Test::where('assessment_mode', AssessmentMode::Simulator)->count();
        $realTests = Test::where('assessment_mode', AssessmentMode::RealTest)->count();

        // 3. Candidate Activity & Results
        $activeAssignments = CandidateTestAssignment::where('status', 'active')->count();
        $inProgressAttempts = Attempt::where('status', 'in_progress')->count();
        $finishedAttempts = Attempt::with(['test', 'user'])->whereIn('status', ['submitted', 'completed'])->get();
        $totalAttempts = $finishedAttempts->count();

        $totalPassed = $finishedAttempts->filter(fn ($att) => ($att->result_summary['is_passed'] ?? false))->count();
        $totalFailed = $totalAttempts - $totalPassed;
        $passRate = $totalAttempts > 0 ? round(($totalPassed / $totalAttempts) * 100, 1) : 0;

        // 4. Certification & Commerce Eligibility
        $totalCertificates = Certificate::count();
        $paidEligibleCandidates = User::role('student')
            ->whereHas('orders.invoice.payments', fn ($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
            ->count();

        // 5. Visual Chart Data Aggregations (Live DB)
        // A. Activity Trend: attempts over the last 7 days
        $activityTrend = ['labels' => [], 'data' => []];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $label = Carbon::now()->subDays($i)->format('d M');
            $count = Attempt::whereDate('created_at', $date)->count();
            $activityTrend['labels'][] = $label;
            $activityTrend['data'][] = $count;
        }

        // B. Simulator vs Real Test Breakdown
        $simulatorAttemptsCount = Attempt::whereHas('test', fn ($t) => $t->where('assessment_mode', AssessmentMode::Simulator))->count();
        $realTestAttemptsCount = Attempt::whereHas('test', fn ($t) => $t->where('assessment_mode', AssessmentMode::RealTest))->count();

        // C. Candidate Status Distribution
        $statusDistribution = [
            'Active Assignments' => $activeAssignments,
            'In Progress' => $inProgressAttempts,
            'Passed' => $totalPassed,
            'Failed' => $totalFailed,
        ];

        // D. Assessment Popularity: Top 5 Assessments by attempt count
        $popularAssessments = Test::withCount('attempts')
            ->orderByDesc('attempts_count')
            ->take(5)
            ->get();

        /** @var view-string $viewName */
        $viewName = 'reporting::index';

        return view($viewName, compact(
            'totalStudents',
            'totalTeachers',
            'totalRMs',
            'totalAdmins',
            'totalSuperAdmins',
            'totalStaff',
            'totalTests',
            'publishedTests',
            'simulatorTests',
            'realTests',
            'activeAssignments',
            'inProgressAttempts',
            'totalAttempts',
            'totalPassed',
            'totalFailed',
            'passRate',
            'totalCertificates',
            'paidEligibleCandidates',
            'activityTrend',
            'simulatorAttemptsCount',
            'realTestAttemptsCount',
            'statusDistribution',
            'popularAssessments'
        ));
    }

    /**
     * Export attempts performance data as CSV file.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        if ($request->user()?->hasRole('finance')) {
            abort(403, 'Finance users are restricted to Financial & Commerce Reports.');
        }

        $attempts = Attempt::with(['user', 'test', 'certificate'])
            ->whereIn('status', ['submitted', 'completed'])
            ->latest('updated_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="assessment_report_'.date('Ymd_His').'.csv"',
        ];

        $callback = function () use ($attempts) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Attempt ID',
                'Candidate Name',
                'Candidate Email',
                'Assessment Title',
                'Assessment Mode',
                'Final Score',
                'Pass Score',
                'Result',
                'Certificate Number',
                'Submission Date',
            ]);

            foreach ($attempts as $att) {
                $res = $att->result_summary;
                $passed = $res['is_passed'] ?? false;
                $modeLabel = $att->test?->assessment_mode?->label() ?? 'Assessment';

                fputcsv($file, [
                    $att->id,
                    $att->user?->name ?? 'Candidate',
                    $att->user?->email ?? 'N/A',
                    $att->test?->title ?? 'Test Session',
                    $modeLabel,
                    $res['final_score'] ?? 0,
                    $res['pass_score'] ?? 0,
                    $passed ? 'PASSED' : 'FAILED',
                    $att->certificate?->certificate_number ?? 'N/A',
                    $att->submitted_at?->toIso8601String() ?? $att->updated_at?->toIso8601String(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
