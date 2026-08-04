<?php

namespace App\Services;

use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Support\Carbon;

/**
 * Service to calculate institutional Content Health Score using configurable weighted metrics.
 */
class AclHealthScoreService
{
    public function __construct(
        protected AclCoverageService $coverageService
    ) {}

    /**
     * Calculate comprehensive ACL Health Score (0 - 100).
     *
     * @return array{score: int, grade: string, breakdown: array<string, mixed>}
     */
    public function calculateHealthScore(): array
    {
        $totalBanks = QuestionBank::count();

        if ($totalBanks === 0) {
            return [
                'score'     => 100,
                'grade'     => 'A+',
                'breakdown' => [
                    'coverage'          => 100,
                    'approved_ratio'    => 100,
                    'draft_ratio'       => 100,
                    'revision_penalty'  => 0,
                    'recency_score'     => 100,
                ],
            ];
        }

        // 1. Coverage Score (Weight: 30%)
        $coveragePct = $this->coverageService->getOverallCoveragePercentage();
        $weightedCoverage = $coveragePct * 0.30;

        // 2. Approved Ratio Score (Weight: 25%)
        $approvedCount = QuestionBank::whereIn('status', ['approved', 'published'])->count();
        $approvedRatioPct = (int) round(($approvedCount / $totalBanks) * 100);
        $weightedApproved = $approvedRatioPct * 0.25;

        // 3. Controlled Draft Volume Score (Weight: 15%)
        $draftCount = QuestionBank::where('status', 'draft')->count();
        $draftRatio = $draftCount / $totalBanks;
        // Optimal draft ratio is around 10-30%. Excessive drafts lower score slightly.
        $draftScorePct = max(0, min(100, (int) round((1 - min(1.0, $draftRatio)) * 100)));
        $weightedDraft = $draftScorePct * 0.15;

        // 4. Low Revision Penalty Score (Weight: 10%)
        $revisionCount = QuestionBank::whereIn('status', ['rejected', 'revision_requested'])->count();
        $revisionRatio = $revisionCount / $totalBanks;
        $revisionScorePct = max(0, (int) round((1 - $revisionRatio) * 100));
        $weightedRevision = $revisionScorePct * 0.10;

        // 5. Maintenance Recency Score (Weight: 20%)
        $recentCount = QuestionBank::where('updated_at', '>=', Carbon::now()->subDays(30))->count();
        $recencyPct = (int) round(($recentCount / $totalBanks) * 100);
        $weightedRecency = $recencyPct * 0.20;

        // Total Composite Score
        $score = (int) round($weightedCoverage + $weightedApproved + $weightedDraft + $weightedRevision + $weightedRecency);
        $score = max(0, min(100, $score));

        $grade = match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            default      => 'Needs Maintenance',
        };

        return [
            'score'     => $score,
            'grade'     => $grade,
            'breakdown' => [
                'coverage'         => $coveragePct,
                'approved_ratio'   => $approvedRatioPct,
                'draft_ratio'      => $draftScorePct,
                'revision_score'   => $revisionScorePct,
                'recency_score'    => $recencyPct,
            ],
        ];
    }
}
