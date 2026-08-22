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

        $testType = $test?->test_type;
        $isToeic = $testType === \App\Modules\QuestionBank\Enums\TestType::Toeic || (is_string($testType) && strtolower($testType) === 'toeic');

        if ($isToeic) {
            $toeicEngine = app(TOEICScoringEngine::class);
            $toeicEval = $toeicEngine->evaluateAttempt($attempt);

            $attempt->update([
                'total_score' => $toeicEval['total_score'],
                'section_scores' => [
                    'listening' => [
                        'correct' => $toeicEval['listening_correct'],
                        'total'   => $toeicEval['listening_total'],
                        'score'   => $toeicEval['listening_score'],
                    ],
                    'reading' => [
                        'correct' => $toeicEval['reading_correct'],
                        'total'   => $toeicEval['reading_total'],
                        'score'   => $toeicEval['reading_score'],
                    ],
                    'is_full_toeic' => $toeicEval['is_full_toeic'],
                    'is_practice'   => $toeicEval['is_practice'],
                    'score_label'   => $toeicEval['score_label'],
                ],
            ]);

            return (float) $toeicEval['total_score'];
        }

        $attempt->update(['total_score' => $totalScore]);

        return $totalScore;
    }
}
