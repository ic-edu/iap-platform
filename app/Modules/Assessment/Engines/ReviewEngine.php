<?php

namespace App\Modules\Assessment\Engines;

use App\Modules\Assessment\Models\Attempt;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\Question;

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
        $attempt->loadMissing([
            'test.sections.testQuestions.question.passage',
            'test.sections.testQuestions.question.choices',
            'answers.question.passage',
            'answers.question.choices',
            'answers.selectedChoice',
        ]);

        $resultPayload = $this->resultEngine->generateResult($attempt);
        $isPassed = $resultPayload['is_passed'];

        $eligibilityService = app(\App\Modules\Certificate\Services\CertificateEligibilityService::class);
        $certificate = Certificate::where('attempt_id', $attempt->id)->first();

        if ($certificate) {
            // If certificate exists in database, ensure it is not from a simulator attempt
            if (!$eligibilityService->isCertificateEligible($certificate)) {
                $certificate = null;
            }
        } elseif ($eligibilityService->canIssueCertificate($attempt->test, $attempt)) {
            // Auto-issue for passed, non-simulator, evaluation-complete attempt if missing
            if ($isPassed && !$attempt->isPendingEvaluation()) {
                $certificate = app(CertificateEngine::class)->issueCertificate($attempt);
            }
        }

        $answersByQuestion = $attempt->answers->keyBy('question_id');
        $detailedQuestions = collect();
        $sectionStats = [];

        $testQuestions = collect();
        if ($attempt->test) {
            foreach ($attempt->test->sections as $section) {
                foreach ($section->testQuestions as $tq) {
                    if ($tq->question) {
                        $testQuestions->push([
                            'question' => $tq->question,
                            'section_title' => $section->title,
                            'section_type' => $section->section_type?->label() ?? 'General',
                            'points' => $tq->points ?? $tq->question->points,
                        ]);
                    }
                }
            }
        }

        // Fallback if testQuestions relation is empty (direct answers query)
        if ($testQuestions->isEmpty()) {
            foreach ($attempt->answers as $ans) {
                if ($ans->question) {
                    $testQuestions->push([
                        'question' => $ans->question,
                        'section_title' => $ans->question->section?->label() ?? 'General',
                        'section_type' => $ans->question->section?->value ?? 'general',
                        'points' => $ans->question->points,
                    ]);
                }
            }
        }

        foreach ($testQuestions as $index => $item) {
            /** @var Question $question */
            $question = $item['question'];
            $answer = $answersByQuestion->get($question->id);

            $correctChoice = $question->choices->firstWhere('is_correct', true);
            $selectedChoice = $answer?->selectedChoice ?? $question->choices->firstWhere('id', $answer?->selected_choice_id);

            $isCorrect = (bool) ($answer?->is_correct ?? false);
            $scoreEarned = (float) ($answer?->score_earned ?? ($isCorrect ? $item['points'] : 0));

            $candidateAnswerText = $selectedChoice?->content
                ?? $answer?->text_response
                ?? ($answer?->selected_choice_id ? 'Option '.$answer->selected_choice_id : 'Not Answered');

            $correctAnswerText = $correctChoice?->content ?? 'See Explanation';

            $choicesFormatted = $question->choices->map(function ($choice) use ($answer) {
                return [
                    'id' => $choice->id,
                    'label' => $choice->label,
                    'content' => $choice->content,
                    'is_correct' => (bool) $choice->is_correct,
                    'is_selected' => $answer?->selected_choice_id === $choice->id,
                ];
            })->toArray();

            $secType = $item['section_type'];
            if (!isset($sectionStats[$secType])) {
                $sectionStats[$secType] = ['total' => 0, 'correct' => 0];
            }
            $sectionStats[$secType]['total']++;
            if ($isCorrect) {
                $sectionStats[$secType]['correct']++;
            }

            $detailedQuestions->push([
                'index' => $index + 1,
                'question_id' => $question->id,
                'prompt' => $question->prompt,
                'passage_text' => $question->passage?->body ?? $question->passage_text,
                'audio_url' => $question->audio_url,
                'image_url' => $question->image_url,
                'question_type' => $question->question_type?->label() ?? 'Multiple Choice',
                'difficulty' => $question->difficulty?->label() ?? 'Medium',
                'section_title' => $item['section_title'],
                'section_type' => $secType,
                'points_possible' => $item['points'],
                'score_earned' => $scoreEarned,
                'is_correct' => $isCorrect,
                'candidate_answer' => $candidateAnswerText,
                'correct_answer' => $correctAnswerText,
                'choices' => $choicesFormatted,
                'explanation' => $question->explanation ?? 'No explanation provided.',
                'feedback' => $answer?->feedback,
            ]);
        }

        $suggestedAreas = collect($sectionStats)->filter(function ($stats) {
            return $stats['total'] > 0 && ($stats['correct'] / $stats['total']) < 0.7;
        })->keys()->map(fn ($sec) => "Focus on improving {$sec} skills")->values()->toArray();

        if (empty($suggestedAreas) && $resultPayload['percentage'] < 100) {
            $suggestedAreas[] = 'Review incorrect question rationales to boost total accuracy.';
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
            'incorrect_answers' => max(0, $resultPayload['total_questions'] - $resultPayload['correct_count']),
            'detailed_questions' => $detailedQuestions,
            'section_stats' => $sectionStats,
            'suggested_learning_areas' => $suggestedAreas,
        ]);
    }
}
