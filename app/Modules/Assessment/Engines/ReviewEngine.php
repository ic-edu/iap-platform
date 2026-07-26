<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;

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

        $isPassed = ($attempt->total_score ?? 0.0) >= ($attempt->test ? $attempt->test->pass_score : 0);

        return [
            'attempt_id' => $attempt->id,
            'test_title' => $attempt->test?->title,
            'status' => $attempt->status->label(),
            'total_score' => $attempt->total_score,
            'pass_score' => $attempt->test?->pass_score,
            'is_passed' => $isPassed,
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'total_questions' => $attempt->answers->count(),
            'correct_answers' => $attempt->answers->where('is_correct', true)->count(),
        ];
    }
}
