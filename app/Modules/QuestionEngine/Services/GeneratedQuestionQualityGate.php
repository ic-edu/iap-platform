<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\DTO\GenerationValidationResult;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;

class GeneratedQuestionQualityGate
{
    /**
     * @var list<string>
     */
    protected array $forbiddenPlaceholders = [
        'lorem ipsum',
        'todo',
        '[insert',
        '[placeholder',
        '<insert',
        '<placeholder',
        'tbd',
        'replace me',
        'sample text goes here',
    ];

    /**
     * Validate candidate against slot constraints and quality rules.
     */
    public function validate(GeneratedQuestionCandidate $candidate, QuestionGenerationItem $item): GenerationValidationResult
    {
        $violations = [];
        $warnings = [];
        $metrics = [];

        // 1. Stem / Prompt checks
        $prompt = trim($candidate->prompt);
        if (empty($prompt)) {
            $violations[] = [
                'code' => 'EMPTY_PROMPT',
                'field' => 'prompt',
                'message' => 'The question prompt cannot be empty.',
            ];
        } elseif (mb_strlen($prompt) < 5) {
            $violations[] = [
                'code' => 'PROMPT_TOO_SHORT',
                'field' => 'prompt',
                'message' => "The question prompt is too short ({$prompt}).",
            ];
        }

        $this->checkForPlaceholders($prompt, 'prompt', $violations);

        // 2. Family & Part specific structural checks
        $family = $item->assessment_family;

        if ($family === AssessmentFamily::Toeic) {
            $this->validateToeicStructure($candidate, $item, $violations, $warnings, $metrics);
        } elseif ($family === AssessmentFamily::ToeflIbt) {
            $this->validateToeflStructure($candidate, $item, $violations, $warnings, $metrics);
        } else {
            $this->validateGenericStructure($candidate, $item, $violations, $warnings, $metrics);
        }

        // Compute general metrics
        $metrics['prompt_length'] = mb_strlen($prompt);
        $metrics['choice_count'] = count($candidate->choices);
        $metrics['has_passage'] = !empty($candidate->passageText);
        $metrics['has_audio_script'] = !empty($candidate->audioScript);
        $metrics['has_explanation'] = !empty($candidate->explanation);

        if (!empty($violations)) {
            return GenerationValidationResult::invalid($violations, $metrics, $warnings);
        }

        return GenerationValidationResult::valid($metrics, $warnings);
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $metrics
     */
    protected function validateToeicStructure(
        GeneratedQuestionCandidate $candidate,
        QuestionGenerationItem $item,
        array &$violations,
        array &$warnings,
        array &$metrics
    ): void {
        $part = (int) ($item->part_number ?? 0);
        $expectedChoiceCount = ($part === 2) ? 3 : 4;

        if ($candidate->isMultipleChoice()) {
            $this->validateMultipleChoices($candidate, $expectedChoiceCount, $violations);
        } else {
            $violations[] = [
                'code' => 'MISSING_CHOICES',
                'field' => 'choices',
                'message' => "TOEIC Part {$part} questions require multiple choice options.",
            ];
        }

        // Passage requirement for Reading Part 6 and 7
        if (in_array($part, [6, 7], true)) {
            if (empty(trim((string) $candidate->passageText))) {
                $violations[] = [
                    'code' => 'MISSING_PASSAGE',
                    'field' => 'passage_text',
                    'message' => "TOEIC Part {$part} requires a stimulus reading passage.",
                ];
            } else {
                $this->checkForPlaceholders($candidate->passageText, 'passage_text', $violations);
            }
        }

        // Audio script for Listening Parts 3 and 4
        if (in_array($part, [3, 4], true)) {
            if (empty(trim((string) $candidate->audioScript))) {
                $warnings[] = "TOEIC Part {$part} item generated without explicit audio transcript script.";
            } else {
                $this->checkForPlaceholders($candidate->audioScript, 'audio_script', $violations);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $metrics
     */
    protected function validateToeflStructure(
        GeneratedQuestionCandidate $candidate,
        QuestionGenerationItem $item,
        array &$violations,
        array &$warnings,
        array &$metrics
    ): void {
        $task = (string) ($item->task_type ?? '');
        $isConstructed = in_array($task, [
            'build_a_sentence',
            'write_an_email',
            'write_for_an_academic_discussion',
            'listen_and_repeat',
            'take_an_interview',
        ], true);

        if ($isConstructed) {
            if (empty(trim((string) $candidate->rubric)) && empty(trim((string) $candidate->sampleResponse))) {
                $warnings[] = "Constructed response task {$task} does not supply rubric or sample response.";
            }
        } else {
            if ($candidate->isMultipleChoice()) {
                $this->validateMultipleChoices($candidate, 4, $violations);
            }
        }

        if (in_array($task, ['read_an_academic_passage', 'read_in_daily_life'], true)) {
            if (empty(trim((string) $candidate->passageText))) {
                $violations[] = [
                    'code' => 'MISSING_PASSAGE',
                    'field' => 'passage_text',
                    'message' => "TOEFL task {$task} requires a reading stimulus passage.",
                ];
            } else {
                $this->checkForPlaceholders($candidate->passageText, 'passage_text', $violations);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $metrics
     */
    protected function validateGenericStructure(
        GeneratedQuestionCandidate $candidate,
        QuestionGenerationItem $item,
        array &$violations,
        array &$warnings,
        array &$metrics
    ): void {
        if ($candidate->isMultipleChoice()) {
            $this->validateMultipleChoices($candidate, count($candidate->choices), $violations);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     */
    protected function validateMultipleChoices(GeneratedQuestionCandidate $candidate, int $expectedCount, array &$violations): void
    {
        $choices = $candidate->choices;
        $count = count($choices);

        if ($count !== $expectedCount) {
            $violations[] = [
                'code' => 'INVALID_CHOICE_COUNT',
                'field' => 'choices',
                'message' => "Expected {$expectedCount} choice options, got {$count}.",
            ];
        }

        $correctCount = 0;
        $seenContents = [];

        foreach ($choices as $idx => $choice) {
            $content = trim((string) ($choice['content'] ?? ''));
            $label = $choice['label'] ?? chr(65 + $idx);

            if (empty($content)) {
                $violations[] = [
                    'code' => 'EMPTY_CHOICE_CONTENT',
                    'field' => "choices.{$idx}",
                    'message' => "Choice {$label} content cannot be empty.",
                ];
            }

            $this->checkForPlaceholders($content, "choices.{$idx}", $violations);

            // Duplicate choices check
            $lower = mb_strtolower($content);
            if (isset($seenContents[$lower]) && !empty($content)) {
                $violations[] = [
                    'code' => 'DUPLICATE_CHOICE_CONTENT',
                    'field' => "choices.{$idx}",
                    'message' => "Choice {$label} has identical content to choice {$seenContents[$lower]}.",
                ];
            }
            $seenContents[$lower] = $label;

            if (!empty($choice['is_correct'])) {
                $correctCount++;
            }
        }

        if ($correctCount !== 1) {
            $violations[] = [
                'code' => 'INVALID_CORRECT_ANSWER_COUNT',
                'field' => 'choices',
                'message' => "Multiple choice questions must have exactly 1 correct answer marked, found {$correctCount}.",
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     */
    protected function checkForPlaceholders(string $text, string $field, array &$violations): void
    {
        $lower = mb_strtolower($text);
        foreach ($this->forbiddenPlaceholders as $ph) {
            if (str_contains($lower, $ph)) {
                $violations[] = [
                    'code' => 'PLACEHOLDER_DETECTED',
                    'field' => $field,
                    'message' => "Forbidden placeholder text [{$ph}] detected in {$field}.",
                ];
                break;
            }
        }
    }
}
