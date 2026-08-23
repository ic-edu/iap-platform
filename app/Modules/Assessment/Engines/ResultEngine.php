<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Enums\EvaluationStatus;
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

        $isPendingEvaluation = $attempt->isPendingEvaluation() || ($attempt->evaluation_status === EvaluationStatus::InProgress);

        $totalQuestions = $attempt->answers->count();
        $correctCount = $attempt->answers->where('is_correct', true)->count();
        $percentage = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0.0;
        $rawScore = (float) ($attempt->total_score ?? 0.0);
        $passScore = (float) ($attempt->test ? $attempt->test->pass_score : 0);

        $testType = $attempt->test?->test_type;
        $isToeic = $testType === TestType::Toeic || (is_string($testType) && strtolower($testType) === 'toeic');

        if ($isToeic) {
            $toeicEval = app(TOEICScoringEngine::class)->evaluateAttempt($attempt);
            $finalScore = (float) $toeicEval['total_score'];
            $isPassed = !$isPendingEvaluation && $toeicEval['passed'];
            $toeicData = $toeicEval;
        } else {
            // General or standard test scoring
            $finalScore = $rawScore;
            // Check both raw score and accuracy percentage against pass_score threshold
            $effectiveScore = max($rawScore, $percentage);
            $isPassed = !$isPendingEvaluation && ($effectiveScore >= $passScore);
            $toeicData = null;
        }

        $grade = match (true) {
            $isPendingEvaluation => 'Pending Evaluation',
            $percentage >= 90 => 'A+',
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            default => 'D',
        };

        $completionStatus = $isPendingEvaluation
            ? 'Awaiting Examiner Evaluation'
            : $attempt->status->label();

        $certificate = $attempt->certificate;

        return [
            'attempt_id' => $attempt->id,
            'test_id' => $attempt->test_id,
            'test_title' => $attempt->test?->title,
            'scoring_method' => $attempt->test?->scoring_method?->value ?? 'automatic',
            'evaluation_status' => $attempt->evaluation_status?->value ?? 'not_required',
            'is_pending_evaluation' => $isPendingEvaluation,
            'raw_score' => $rawScore,
            'final_score' => $finalScore,
            'pass_score' => $passScore,
            'is_passed' => $isPassed,
            'percentage' => $percentage,
            'grade' => $grade,
            'completion_status' => $completionStatus,
            'total_questions' => $totalQuestions,
            'correct_count' => $correctCount,
            'toeic_breakdown' => $toeicData,
            'is_full_toeic' => $toeicData['is_full_toeic'] ?? false,
            'is_practice' => $toeicData['is_practice'] ?? false,
            'score_label' => $toeicData['score_label'] ?? null,
            'certificate_id' => $certificate?->id,
            'certificate_number' => $certificate?->certificate_number,
        ];
    }
}
