<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;

class TOEICScoringEngine
{
    /**
     * Authoritative Institutional TOEIC Conversion Table.
     * Mapping for Raw Correct (0..100) -> [listening, reading]
     *
     * Maximum: Listening = 495, Reading = 495, Total = 990.
     */
    public const CONVERSION_TABLE = [
        0 => ['listening' => 0, 'reading' => 0],
        1 => ['listening' => 5, 'reading' => 5],
        2 => ['listening' => 5, 'reading' => 5],
        3 => ['listening' => 5, 'reading' => 5],
        4 => ['listening' => 5, 'reading' => 5],
        5 => ['listening' => 5, 'reading' => 5],
        6 => ['listening' => 5, 'reading' => 5],
        7 => ['listening' => 5, 'reading' => 5],
        8 => ['listening' => 5, 'reading' => 5],
        9 => ['listening' => 5, 'reading' => 5],
        10 => ['listening' => 5, 'reading' => 5],
        11 => ['listening' => 5, 'reading' => 5],
        12 => ['listening' => 5, 'reading' => 5],
        13 => ['listening' => 5, 'reading' => 5],
        14 => ['listening' => 5, 'reading' => 5],
        15 => ['listening' => 5, 'reading' => 5],
        16 => ['listening' => 5, 'reading' => 5],
        17 => ['listening' => 10, 'reading' => 5],
        18 => ['listening' => 15, 'reading' => 5],
        19 => ['listening' => 20, 'reading' => 5],
        20 => ['listening' => 25, 'reading' => 5],
        21 => ['listening' => 30, 'reading' => 10],
        22 => ['listening' => 35, 'reading' => 15],
        23 => ['listening' => 40, 'reading' => 20],
        24 => ['listening' => 45, 'reading' => 25],
        25 => ['listening' => 50, 'reading' => 30],
        26 => ['listening' => 55, 'reading' => 35],
        27 => ['listening' => 60, 'reading' => 40],
        28 => ['listening' => 70, 'reading' => 45],
        29 => ['listening' => 80, 'reading' => 55],
        30 => ['listening' => 85, 'reading' => 60],
        31 => ['listening' => 90, 'reading' => 65],
        32 => ['listening' => 95, 'reading' => 70],
        33 => ['listening' => 100, 'reading' => 75],
        34 => ['listening' => 105, 'reading' => 80],
        35 => ['listening' => 115, 'reading' => 85],
        36 => ['listening' => 125, 'reading' => 90],
        37 => ['listening' => 135, 'reading' => 95],
        38 => ['listening' => 140, 'reading' => 105],
        39 => ['listening' => 150, 'reading' => 115],
        40 => ['listening' => 160, 'reading' => 120],
        41 => ['listening' => 170, 'reading' => 125],
        42 => ['listening' => 175, 'reading' => 130],
        43 => ['listening' => 180, 'reading' => 135],
        44 => ['listening' => 190, 'reading' => 140],
        45 => ['listening' => 200, 'reading' => 145],
        46 => ['listening' => 205, 'reading' => 155],
        47 => ['listening' => 215, 'reading' => 160],
        48 => ['listening' => 220, 'reading' => 170],
        49 => ['listening' => 225, 'reading' => 175],
        50 => ['listening' => 230, 'reading' => 185],
        51 => ['listening' => 235, 'reading' => 195],
        52 => ['listening' => 245, 'reading' => 205],
        53 => ['listening' => 255, 'reading' => 210],
        54 => ['listening' => 260, 'reading' => 215],
        55 => ['listening' => 265, 'reading' => 220],
        56 => ['listening' => 275, 'reading' => 230],
        57 => ['listening' => 285, 'reading' => 240],
        58 => ['listening' => 290, 'reading' => 245],
        59 => ['listening' => 295, 'reading' => 250],
        60 => ['listening' => 300, 'reading' => 255],
        61 => ['listening' => 310, 'reading' => 260],
        62 => ['listening' => 320, 'reading' => 270],
        63 => ['listening' => 325, 'reading' => 275],
        64 => ['listening' => 330, 'reading' => 280],
        65 => ['listening' => 335, 'reading' => 285],
        66 => ['listening' => 340, 'reading' => 290],
        67 => ['listening' => 345, 'reading' => 295],
        68 => ['listening' => 350, 'reading' => 295],
        69 => ['listening' => 355, 'reading' => 300],
        70 => ['listening' => 360, 'reading' => 310],
        71 => ['listening' => 365, 'reading' => 315],
        72 => ['listening' => 370, 'reading' => 320],
        73 => ['listening' => 375, 'reading' => 325],
        74 => ['listening' => 385, 'reading' => 330],
        75 => ['listening' => 395, 'reading' => 335],
        76 => ['listening' => 400, 'reading' => 340],
        77 => ['listening' => 405, 'reading' => 345],
        78 => ['listening' => 415, 'reading' => 355],
        79 => ['listening' => 420, 'reading' => 360],
        80 => ['listening' => 425, 'reading' => 370],
        81 => ['listening' => 430, 'reading' => 375],
        82 => ['listening' => 435, 'reading' => 385],
        83 => ['listening' => 440, 'reading' => 390],
        84 => ['listening' => 445, 'reading' => 395],
        85 => ['listening' => 450, 'reading' => 400],
        86 => ['listening' => 455, 'reading' => 405],
        87 => ['listening' => 460, 'reading' => 415],
        88 => ['listening' => 465, 'reading' => 420],
        89 => ['listening' => 475, 'reading' => 425],
        90 => ['listening' => 480, 'reading' => 435],
        91 => ['listening' => 485, 'reading' => 440],
        92 => ['listening' => 490, 'reading' => 450],
        93 => ['listening' => 495, 'reading' => 455],
        94 => ['listening' => 495, 'reading' => 460],
        95 => ['listening' => 495, 'reading' => 470],
        96 => ['listening' => 495, 'reading' => 475],
        97 => ['listening' => 495, 'reading' => 485],
        98 => ['listening' => 495, 'reading' => 485],
        99 => ['listening' => 495, 'reading' => 490],
        100 => ['listening' => 495, 'reading' => 495],
    ];

    /**
     * Convert Listening raw correct count (0..100) to scaled score.
     */
    public function convertListeningScore(int $correctCount): int
    {
        $clamped = max(0, min(100, $correctCount));
        return self::CONVERSION_TABLE[$clamped]['listening'] ?? 0;
    }

    /**
     * Convert Reading raw correct count (0..100) to scaled score.
     */
    public function convertReadingScore(int $correctCount): int
    {
        $clamped = max(0, min(100, $correctCount));
        return self::CONVERSION_TABLE[$clamped]['reading'] ?? 0;
    }

    /**
     * Determine if a question is part of the Listening section.
     */
    public function isListeningQuestion(?Question $question, ?TestSection $section = null): bool
    {
        if (!$question) {
            return false;
        }

        // Check question section enum
        if ($question->section === SectionType::Listening || (is_string($question->section) && strtolower($question->section) === 'listening')) {
            return true;
        }

        // Check section model
        if ($section) {
            if ($section->section_type === SectionType::Listening || (is_string($section->section_type) && strtolower($section->section_type) === 'listening')) {
                return true;
            }
            $title = strtolower($section->title ?? '');
            if (str_contains($title, 'listening') || str_contains($title, 'photograph') || str_contains($title, 'conversation') || str_contains($title, 'talk') || str_contains($title, 'part 1') || str_contains($title, 'part 2') || str_contains($title, 'part 3') || str_contains($title, 'part 4')) {
                return true;
            }
        }

        // Check audio presence
        if (!empty($question->audio_url)) {
            return true;
        }

        return false;
    }

    /**
     * Evaluate complete TOEIC attempt.
     *
     * @return array<string, mixed>
     */
    public function evaluateAttempt(Attempt $attempt): array
    {
        $attempt->loadMissing([
            'test.sections.testQuestions.question',
            'answers.question',
        ]);

        $test = $attempt->test;
        $sections = $test?->sections ?? collect();

        // Build question-to-section map
        $questionSectionMap = [];
        foreach ($sections as $section) {
            foreach ($section->testQuestions as $tq) {
                if ($tq->question_id) {
                    $questionSectionMap[$tq->question_id] = $section;
                }
            }
        }

        $listeningTotal = 0;
        $listeningCorrect = 0;
        $readingTotal = 0;
        $readingCorrect = 0;

        foreach ($attempt->answers as $answer) {
            $question = $answer->question;
            if (!$question) {
                continue;
            }

            $section = $questionSectionMap[$question->id] ?? null;
            $isListening = $this->isListeningQuestion($question, $section);

            $isCorrect = (bool) ($answer->is_correct ?? false);

            if ($isListening) {
                $listeningTotal++;
                if ($isCorrect) {
                    $listeningCorrect++;
                }
            } else {
                $readingTotal++;
                if ($isCorrect) {
                    $readingCorrect++;
                }
            }
        }

        // If all categorized into one side or untagged, handle gracefully:
        if ($listeningTotal === 0 && $readingTotal === 0) {
            $totalQuestions = $attempt->answers->count();
            $totalCorrect = $attempt->answers->where('is_correct', true)->count();
        } else {
            $totalQuestions = $listeningTotal + $readingTotal;
            $totalCorrect = $listeningCorrect + $readingCorrect;
        }

        $passScore = (float) ($test?->pass_score ?? 0.0);

        $assessmentMode = $test?->assessment_mode?->value ?? (is_string($test?->assessment_mode) ? $test->assessment_mode : 'simulator');
        $isMockTest = in_array($assessmentMode, ['mock_test', 'real_test'], true);
        $userFacingMode = $isMockTest ? 'mock_test' : 'simulator';

        // A full institutional mock test strictly requires Mock Test mode AND exactly 100 Listening + 100 Reading questions
        $isFullToeic = $isMockTest
            && $listeningTotal === 100
            && $readingTotal === 100;

        if ($isFullToeic) {
            $listeningScore = $this->convertListeningScore($listeningCorrect);
            $readingScore = $this->convertReadingScore($readingCorrect);
            $totalScore = (float) ($listeningScore + $readingScore);
            $isPractice = false;
            $scoreLabel = 'Institutional Scaled Score';
            $passed = ($totalScore >= $passScore);
        } else {
            // Simulator (40-50 questions) / Mini Mock / Practice / UAT / Partial test
            // Critical: DO NOT map short assessments (e.g. 40, 50, 3 questions) to 990!
            $listeningScore = $listeningCorrect;
            $readingScore = $readingCorrect;
            $percentage = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0.0;
            $totalScore = (float) $totalCorrect;
            $isPractice = true;
            $scoreLabel = $isMockTest ? 'Practice / Raw Score' : 'Practice Score';
            $passed = ($totalScore >= $passScore) || ($percentage >= $passScore);
        }

        return [
            'assessment_mode'   => $userFacingMode,
            'listening_total'   => $listeningTotal,
            'listening_correct' => $listeningCorrect,
            'listening_score'   => $listeningScore,
            'reading_total'     => $readingTotal,
            'reading_correct'   => $readingCorrect,
            'reading_score'     => $readingScore,
            'total_questions'   => $totalQuestions,
            'total_correct'     => $totalCorrect,
            'total_score'       => $totalScore,
            'is_full_toeic'     => $isFullToeic,
            'is_practice'       => $isPractice,
            'score_label'       => $scoreLabel,
            'passed'            => $passed,
        ];
    }
}
