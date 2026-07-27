<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Modules\Certificate\Models\Certificate;

class ReviewEngine
{
    /**
     * Get review payload based on test settings and candidate attempt.
     *
     * @return array<string, mixed>
     */
    public function getReviewSummary(Attempt $attempt): array
    {
        $attempt->loadMissing(['test', 'answers.question.choices']);

        $passScore = $attempt->test?->pass_score ?? 0;
        $isPassed = ($attempt->total_score ?? 0.0) >= $passScore;

        $certificate = Certificate::where('attempt_id', $attempt->id)->first();

        // Auto-issue if passed but certificate missing
        if ($isPassed && !$certificate) {
            $certificate = app(CertificateEngine::class)->issueCertificate($attempt);
        }

        return [
            'attempt_id' => $attempt->id,
            'test_title' => $attempt->test?->title,
            'status' => $attempt->status->label(),
            'total_score' => $attempt->total_score ?? 0.0,
            'pass_score' => $passScore,
            'is_passed' => $isPassed,
            'certificate' => $certificate,
            'certificate_id' => $certificate?->id,
            'certificate_number' => $certificate?->certificate_number,
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'total_questions' => $attempt->answers->count(),
            'correct_answers' => $attempt->answers->where('is_correct', true)->count(),
        ];
    }
}
