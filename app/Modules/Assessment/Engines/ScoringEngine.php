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
        $attempt->loadMissing(['test', 'answers.question.choices']);
        $test = $attempt->test;
        $isHuman = $test?->isHuman() ?? false;
        $isHybrid = $test?->isHybrid() ?? false;

        $totalScore = 0.0;

        foreach ($attempt->answers as $answer) {
            $question = $answer->question;
            if (!$question) {
                continue;
            }

            $questionType = $question->question_type?->value ?? (is_string($question->question_type) ? $question->question_type : 'multiple_choice');
            $isSubjective = in_array($questionType, ['essay', 'writing', 'speaking'], true);

            // For pure HUMAN tests on initial submission, do not auto-score subjective items
            if ($isHuman) {
                $answer->update([
                    'is_correct' => null,
                    'score_earned' => 0.0,
                ]);
                continue;
            }

            // For HYBRID tests on initial submission, only auto-score objective items; leave subjective items pending
            if ($isHybrid && $isSubjective) {
                $answer->update([
                    'is_correct' => null,
                    'score_earned' => 0.0,
                ]);
                continue;
            }

            // Objective scoring (Automatic items & Hybrid objective items)
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
