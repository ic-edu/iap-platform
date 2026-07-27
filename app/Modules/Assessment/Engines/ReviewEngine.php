<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Modules\Certificate\Models\Certificate;

class ReviewEngine
{
    protected ResultEngine $resultEngine;

    public function __construct(
        ?ResultEngine $resultEngine = null
    ) {
        $this->resultEngine = $resultEngine ?? app(ResultEngine::class);
    }

    /**
     * Get review payload based on test settings and candidate attempt.
     *
     * @return array<string, mixed>
     */
    public function getReviewSummary(Attempt $attempt): array
    {
        $attempt->loadMissing(['test', 'answers.question.choices']);

        $resultPayload = $this->resultEngine->generateResult($attempt);
        $isPassed = $resultPayload['is_passed'];

        $certificate = Certificate::where('attempt_id', $attempt->id)->first();

        // Auto-issue if passed but certificate missing
        if ($isPassed && !$certificate) {
            $certificate = app(CertificateEngine::class)->issueCertificate($attempt);
        }

        return array_merge($resultPayload, [
            'attempt_id' => $attempt->id,
            'test_title' => $attempt->test?->title,
            'status' => $attempt->status->label(),
            'total_score' => $resultPayload['final_score'],
            'pass_score' => $resultPayload['pass_score'],
            'is_passed' => $isPassed,
            'certificate' => $certificate,
            'certificate_id' => $certificate?->id,
            'certificate_number' => $certificate?->certificate_number,
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'total_questions' => $resultPayload['total_questions'],
            'correct_answers' => $resultPayload['correct_count'],
        ]);
    }
}
