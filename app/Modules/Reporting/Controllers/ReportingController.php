<?php

namespace App\Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    /**
     * Display reporting dashboard analytics.
     */
    public function index(Request $request): View
    {
        $totalAttempts = Attempt::where('status', 'submitted')->count();
        $totalPassed = Attempt::where('status', 'submitted')->get()->filter(fn ($att) => ($att->result_summary['is_passed'] ?? false))->count();
        $passRate = $totalAttempts > 0 ? round(($totalPassed / $totalAttempts) * 100, 1) : 0;
        $totalCertificates = Certificate::count();
        $totalTests = Test::count();

        $recentAttempts = Attempt::with(['test', 'user'])
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->limit(10)
            ->get();

        /** @var view-string $viewName */
        $viewName = 'reporting::index';

        return view($viewName, compact('totalAttempts', 'totalPassed', 'passRate', 'totalCertificates', 'totalTests', 'recentAttempts'));
    }

    /**
     * Export attempts performance data as CSV file.
     */
    public function exportCsv(): StreamedResponse
    {
        $attempts = Attempt::with(['user', 'test', 'certificate'])->where('status', 'submitted')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="assessment_report_'.date('Ymd_His').'.csv"',
        ];

        $callback = function () use ($attempts) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Attempt ID', 'Candidate Name', 'Candidate Email', 'Assessment Title', 'Final Score', 'Pass Threshold', 'Status', 'Certificate Ref', 'Submitted Date']);

            foreach ($attempts as $att) {
                $res = $att->result_summary;
                fputcsv($file, [
                    $att->id,
                    $att->user?->name ?? 'N/A',
                    $att->user?->email ?? 'N/A',
                    $att->test?->title ?? 'N/A',
                    $res['final_score'] ?? 0,
                    $res['pass_score'] ?? 0,
                    ($res['is_passed'] ?? false) ? 'PASSED' : 'FAILED',
                    $att->certificate?->certificate_number ?? 'None',
                    $att->submitted_at?->format('Y-m-d H:i:s') ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
