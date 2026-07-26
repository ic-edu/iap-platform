<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;

class ScoringEngine
{
    /**
     * Evaluate answers and calculate total attempt score.
     */
    public function evaluateAttempt(Attempt $attempt): float
    {
        $attempt->loadMissing(['answers.question.choices']);
        $totalScore = 0.0;

        foreach ($attempt->answers as $answer) {
            $question = $answer->question;
            if (!$question) {
                continue;
            }

            $isCorrect = false;
            $earnedScore = 0.0;

            if ($answer->selected_choice_id) {
                $choice = $question->choices->firstWhere('id', $answer->selected_choice_id);
                if ($choice && $choice->is_correct) {
                    $isCorrect = true;
                    $earnedScore = (float) $question->points;
                }
            } elseif ($answer->text_response) {
                // Short answer exact/case-insensitive match check
                $correctChoice = $question->choices->firstWhere('is_correct', true);
                if ($correctChoice && strtolower(trim($answer->text_response)) === strtolower(trim($correctChoice->content))) {
                    $isCorrect = true;
                    $earnedScore = (float) $question->points;
                }
            }

            $answer->update([
                'is_correct' => $isCorrect,
                'score_earned' => $earnedScore,
            ]);

            $totalScore += $earnedScore;
        }

        $attempt->update(['total_score' => $totalScore]);

        return $totalScore;
    }
}
