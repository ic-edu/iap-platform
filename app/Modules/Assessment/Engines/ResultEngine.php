<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;

class ResultEngine
{
    /**
     * Generate complete result evaluation payload for an attempt.
     *
     * @return array<string, mixed>
     */
    public function generateResult(Attempt $attempt): array
    {
        $attempt->loadMissing(['test', 'answers.question']);

        $totalScore = $attempt->total_score ?? 0.0;
        $passScore = $attempt->test ? $attempt->test->pass_score : 0;
        $isPassed = $totalScore >= $passScore;

        $totalQuestions = $attempt->answers->count();
        $correctCount = $attempt->answers->where('is_correct', true)->count();
        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0.0;

        $grade = match (true) {
            $percentage >= 90 => 'A+',
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            default => 'D',
        };

        return [
            'attempt_id' => $attempt->id,
            'test_id' => $attempt->test_id,
            'test_title' => $attempt->test?->title,
            'final_score' => $totalScore,
            'pass_score' => $passScore,
            'is_passed' => $isPassed,
            'percentage' => $percentage,
            'grade' => $grade,
            'completion_status' => $attempt->status->label(),
            'total_questions' => $totalQuestions,
            'correct_count' => $correctCount,
        ];
    }
}
