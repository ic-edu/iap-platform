<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Assessment\Models\Answer;
use App\Modules\QuestionBank\Models\Question;

class ItemAnalysisService
{
    /**
     * Get item analysis metrics for a specific question.
     *
     * @return array<string, mixed>
     */
    public function analyzeQuestion(Question $question): array
    {
        $totalAnswers = Answer::where('question_id', $question->id)->count();

        if ($totalAnswers === 0) {
            return [
                'question_id' => $question->id,
                'total_responses' => 0,
                'correct_pct' => 0.0,
                'wrong_pct' => 0.0,
                'skipped_pct' => 0.0,
                'difficulty_index' => 0.0,
            ];
        }

        $correctCount = Answer::where('question_id', $question->id)->where('is_correct', true)->count();
        $wrongCount = Answer::where('question_id', $question->id)->where('is_correct', false)->whereNotNull('selected_choice_id')->count();
        $skippedCount = $totalAnswers - ($correctCount + $wrongCount);

        $correctPct = round(($correctCount / $totalAnswers) * 100, 2);
        $wrongPct = round(($wrongCount / $totalAnswers) * 100, 2);
        $skippedPct = round(($skippedCount / $totalAnswers) * 100, 2);
        $difficultyIndex = round($correctCount / $totalAnswers, 2);

        return [
            'question_id' => $question->id,
            'total_responses' => $totalAnswers,
            'correct_pct' => $correctPct,
            'wrong_pct' => $wrongPct,
            'skipped_pct' => $skippedPct,
            'difficulty_index' => $difficultyIndex,
        ];
    }
}
