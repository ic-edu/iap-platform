<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\QuestionBank\Enums\TestType;

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

        $totalQuestions = $attempt->answers->count();
        $correctCount = $attempt->answers->where('is_correct', true)->count();
        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0.0;
        $rawScore = (float) ($attempt->total_score ?? 0.0);
        $passScore = (float) ($attempt->test ? $attempt->test->pass_score : 0);

        $testType = $attempt->test?->test_type;
        $isToeic = $testType === TestType::Toeic || (is_string($testType) && strtolower($testType) === 'toeic');

        if ($isToeic) {
            // TOEIC Scaled Score Range: 10 to 990 points
            // Scales accuracy percentage (0-100%) to TOEIC scale (10-990)
            $finalScore = (float) round(10 + ($percentage / 100) * 980);
            $isPassed = $finalScore >= $passScore;
        } else {
            // General or standard test scoring
            $finalScore = $rawScore;
            // Check both raw score and accuracy percentage against pass_score threshold
            $effectiveScore = max($rawScore, $percentage);
            $isPassed = $effectiveScore >= $passScore;
        }

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
            'raw_score' => $rawScore,
            'final_score' => $finalScore,
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
