<?php

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Models\Certificate;
use Illuminate\Support\Facades\Cache;

class AnalyticsEngine
{
    /**
     * Get platform analytics summary with 5-minute caching.
     *
     * @return array<string, mixed>
     */
    public function getAnalyticsSummary(): array
    {
        return Cache::remember('platform.analytics.summary', 300, function () {
            $totalAttempts = Attempt::count();
            $completedAttempts = Attempt::whereIn('status', ['submitted', 'completed'])->count();
            $activeAttempts = Attempt::where('status', 'in_progress')->count();

            $avgScore = round(Attempt::whereNotNull('total_score')->avg('total_score') ?? 0, 2);

            $passedCount = Attempt::whereHas('test', function ($q) {
                $q->whereColumn('attempts.total_score', '>=', 'tests.pass_score');
            })->count();

            $passRate = $completedAttempts > 0 ? round(($passedCount / $completedAttempts) * 100, 2) : 0.0;
            $completionRate = $totalAttempts > 0 ? round(($completedAttempts / $totalAttempts) * 100, 2) : 0.0;

            $activeCandidates = User::role('student')->count();
            $certsIssued = Certificate::where('status', 'valid')->count();
            $certsRevoked = Certificate::where('status', 'revoked')->count();

            return [
                'total_attempts' => $totalAttempts,
                'completed_attempts' => $completedAttempts,
                'active_attempts' => $activeAttempts,
                'avg_score' => $avgScore,
                'pass_rate' => $passRate,
                'completion_rate' => $completionRate,
                'active_candidates' => $activeCandidates,
                'certificates_issued' => $certsIssued,
                'certificates_revoked' => $certsRevoked,
            ];
        });
    }
}
