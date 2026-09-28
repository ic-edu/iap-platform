<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\DTO\GenerationValidationResult;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
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
     * Validate candidate against slot constraints, structural identity, and quality rules.
     */
    public function validate(GeneratedQuestionCandidate $candidate, QuestionGenerationItem $item): GenerationValidationResult
    {
        $violations = [];
        $warnings = [];
        $metrics = [];

        // 1. Validate Structural Identity Match between Candidate and Generation Item (Non-optional)
        $this->validateStructuralIdentityMatch($candidate, $item, $violations);

        // 2. Stem / Prompt checks
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

        // 3. Family & Part specific structural checks and Canonical Rules
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
     */
    protected function validateStructuralIdentityMatch(
        GeneratedQuestionCandidate $candidate,
        QuestionGenerationItem $item,
        array &$violations
    ): void {
        // Family
        if ($candidate->assessmentFamily === null) {
            $violations[] = [
                'code' => 'MISSING_STRUCTURAL_IDENTITY',
                'field' => 'assessment_family',
                'message' => 'Candidate is missing required assessment family identity.',
            ];
        } elseif ($candidate->assessmentFamily !== $item->assessment_family) {
            $violations[] = [
                'code' => 'FAMILY_MISMATCH',
                'field' => 'assessment_family',
                'message' => "Candidate family [{$candidate->assessmentFamily->value}] does not match slot [{$item->assessment_family->value}].",
            ];
        }

        // Standard Version
        if ($candidate->standardVersion === null) {
            $violations[] = [
                'code' => 'MISSING_STRUCTURAL_IDENTITY',
                'field' => 'standard_version',
                'message' => 'Candidate is missing required standard version identity.',
            ];
        } elseif (!empty($item->standard_version) && $candidate->standardVersion !== $item->standard_version) {
            $violations[] = [
                'code' => 'STANDARD_MISMATCH',
                'field' => 'standard_version',
                'message' => "Candidate standard version [{$candidate->standardVersion}] does not match slot [{$item->standard_version}].",
            ];
        }

        // Section
        if ($candidate->section === null) {
            $violations[] = [
                'code' => 'MISSING_STRUCTURAL_IDENTITY',
                'field' => 'section',
                'message' => 'Candidate is missing required section identity.',
            ];
        } elseif (!empty($item->section) && strtolower($candidate->section) !== strtolower((string) $item->section)) {
            $violations[] = [
                'code' => 'SECTION_MISMATCH',
                'field' => 'section',
                'message' => "Candidate section [{$candidate->section}] does not match slot [{$item->section}].",
            ];
        }

        // TOEIC specific required identity
        if ($item->assessment_family === AssessmentFamily::Toeic) {
            if ($candidate->partNumber === null) {
                $violations[] = [
                    'code' => 'MISSING_STRUCTURAL_IDENTITY',
                    'field' => 'part_number',
                    'message' => 'TOEIC candidate is missing required part number.',
                ];
            } elseif ($item->part_number !== null && $candidate->partNumber !== (int) $item->part_number) {
                $violations[] = [
                    'code' => 'PART_MISMATCH',
                    'field' => 'part_number',
                    'message' => "Candidate part [{$candidate->partNumber}] does not match slot [{$item->part_number}].",
                ];
            }

            if ($candidate->construct === null) {
                $violations[] = [
                    'code' => 'MISSING_STRUCTURAL_IDENTITY',
                    'field' => 'construct',
                    'message' => 'TOEIC candidate is missing required construct.',
                ];
            } elseif (!empty($item->construct) && strtolower($candidate->construct) !== strtolower((string) $item->construct)) {
                $violations[] = [
                    'code' => 'CONSTRUCT_MISMATCH',
                    'field' => 'construct',
                    'message' => "Candidate construct [{$candidate->construct}] does not match slot [{$item->construct}].",
                ];
            }
        }

        // TOEFL specific required identity
        if ($item->assessment_family === AssessmentFamily::ToeflIbt) {
            if ($candidate->taskType === null) {
                $violations[] = [
                    'code' => 'MISSING_STRUCTURAL_IDENTITY',
                    'field' => 'task_type',
                    'message' => 'TOEFL candidate is missing required task type.',
                ];
            } elseif (!empty($item->task_type) && strtolower($candidate->taskType) !== strtolower((string) $item->task_type)) {
                $violations[] = [
                    'code' => 'TASK_MISMATCH',
                    'field' => 'task_type',
                    'message' => "Candidate task type [{$candidate->taskType}] does not match slot [{$item->task_type}].",
                ];
            }

            if ($candidate->claim === null) {
                $violations[] = [
                    'code' => 'MISSING_STRUCTURAL_IDENTITY',
                    'field' => 'claim',
                    'message' => 'TOEFL candidate is missing required claim.',
                ];
            } elseif (!empty($item->claim) && strtolower($candidate->claim) !== strtolower((string) $item->claim)) {
                $violations[] = [
                    'code' => 'CLAIM_MISMATCH',
                    'field' => 'claim',
                    'message' => "Candidate claim [{$candidate->claim}] does not match slot [{$item->claim}].",
                ];
            }

            if ($candidate->skill === null) {
                $violations[] = [
                    'code' => 'MISSING_STRUCTURAL_IDENTITY',
                    'field' => 'skill',
                    'message' => 'TOEFL candidate is missing required skill.',
                ];
            } elseif (!empty($item->skill) && strtolower($candidate->skill) !== strtolower((string) $item->skill)) {
                $violations[] = [
                    'code' => 'SKILL_MISMATCH',
                    'field' => 'skill',
                    'message' => "Candidate skill [{$candidate->skill}] does not match slot [{$item->skill}].",
                ];
            }
        }

        // Proficiency Target
        if ($candidate->proficiencyTarget === null) {
            $violations[] = [
                'code' => 'MISSING_STRUCTURAL_IDENTITY',
                'field' => 'proficiency_target',
                'message' => 'Candidate is missing required proficiency target.',
            ];
        } elseif (!empty($item->proficiency_target) && strtolower($candidate->proficiencyTarget) !== strtolower((string) $item->proficiency_target)) {
            $violations[] = [
                'code' => 'PROFICIENCY_MISMATCH',
                'field' => 'proficiency_target',
                'message' => "Candidate proficiency [{$candidate->proficiencyTarget}] does not match slot [{$item->proficiency_target}].",
            ];
        }

        // Difficulty
        if ($candidate->difficulty === null) {
            $violations[] = [
                'code' => 'MISSING_STRUCTURAL_IDENTITY',
                'field' => 'difficulty',
                'message' => 'Candidate is missing required difficulty.',
            ];
        } elseif (!empty($item->difficulty) && strtolower($candidate->difficulty) !== strtolower((string) $item->difficulty)) {
            $violations[] = [
                'code' => 'DIFFICULTY_MISMATCH',
                'field' => 'difficulty',
                'message' => "Candidate difficulty [{$candidate->difficulty}] does not match slot [{$item->difficulty}].",
            ];
        }
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

        // Validate Part-Construct compatibility using canonical PartConstructCompatibility
        $construct = $candidate->construct ?? $item->construct;
        if ($construct !== null && !PartConstructCompatibility::isCompatible($part, $construct)) {
            $violations[] = [
                'code' => 'INCOMPATIBLE_CONSTRUCT_FOR_PART',
                'field' => 'construct',
                'message' => "Construct [{$construct}] is incompatible with TOEIC Part {$part}.",
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
        $taskTypeEnum = ToeflTaskType::tryFrom($task);

        $isConstructed = in_array($task, [
            'build_a_sentence',
            'write_an_email',
            'write_for_an_academic_discussion',
            'listen_and_repeat',
            'take_an_interview',
        ], true);

        // Validate CEFR envelope using canonical ToeflProficiencyCompatibility
        $proficiency = $candidate->proficiencyTarget ?? $item->proficiency_target;
        if ($taskTypeEnum !== null && $proficiency !== null) {
            $targetEnum = ProficiencyTarget::tryFrom($proficiency);
            if ($targetEnum !== null && !ToeflProficiencyCompatibility::isTargetCompatible($taskTypeEnum, $targetEnum)) {
                $violations[] = [
                    'code' => 'INCOMPATIBLE_PROFICIENCY_FOR_TASK',
                    'field' => 'proficiency_target',
                    'message' => "Proficiency [{$proficiency}] is outside the official CEFR envelope for TOEFL task [{$task}].",
                ];
            }
        }

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
