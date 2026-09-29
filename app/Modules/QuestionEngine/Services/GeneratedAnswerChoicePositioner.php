<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;

class GeneratedAnswerChoicePositioner
{
    /**
     * Canonical uppercase labels for multiple choice options.
     *
     * @var list<string>
     */
    protected array $canonicalLabels = ['A', 'B', 'C', 'D', 'E', 'F'];

    /**
     * Deterministically reposition answer choices so final correct answer position
     * is system-controlled, distributed across batches, and not biased by provider output.
     */
    public function reposition(
        GeneratedQuestionCandidate $candidate,
        ?QuestionGenerationItem $item = null
    ): GeneratedQuestionCandidate {
        // Only apply policy to choice-based candidates
        if (!$candidate->isMultipleChoice() || empty($candidate->choices)) {
            return $candidate;
        }

        $choices = $candidate->choices;
        $choiceCount = count($choices);

        if ($choiceCount < 2) {
            return $candidate;
        }

        // Identify the single correct choice and distractors
        $correctChoice = null;
        $originalCorrectLabel = null;
        $distractors = [];

        foreach ($choices as $choice) {
            $isCorrect = !empty($choice['is_correct']);
            if ($isCorrect) {
                if ($correctChoice === null) {
                    $correctChoice = $choice;
                    $originalCorrectLabel = $choice['label'] ?? null;
                } else {
                    // More than 1 correct choice - let Quality Gate catch this violation
                    return $candidate;
                }
            } else {
                $distractors[] = $choice;
            }
        }

        // If no choice was explicitly marked is_correct, fallback to matching candidate correctAnswer
        if ($correctChoice === null && $candidate->correctAnswer !== null) {
            $distractors = [];
            foreach ($choices as $choice) {
                $label = (string) ($choice['label'] ?? '');
                if (strtoupper($label) === strtoupper((string) $candidate->correctAnswer)) {
                    $correctChoice = array_merge($choice, ['is_correct' => true]);
                    $originalCorrectLabel = $label;
                } else {
                    $distractors[] = $choice;
                }
            }
        }

        if ($correctChoice === null) {
            return $candidate;
        }

        // Compute deterministic target index for the correct choice
        $targetIndex = $this->determineTargetIndex($item, $candidate, $choiceCount);

        // Build new reordered choices array
        $newChoices = array_fill(0, $choiceCount, null);
        $newChoices[$targetIndex] = $correctChoice;

        $distractorIdx = 0;
        for ($i = 0; $i < $choiceCount; $i++) {
            if ($i !== $targetIndex && isset($distractors[$distractorIdx])) {
                $newChoices[$i] = $distractors[$distractorIdx];
                $distractorIdx++;
            }
        }

        // Relabel choices canonically with A, B, C, D...
        $canonicalChoices = [];
        $newCorrectAnswer = null;

        foreach ($newChoices as $idx => $choice) {
            if ($choice === null) {
                continue;
            }

            $canonicalLabel = $this->canonicalLabels[$idx] ?? chr(65 + $idx);
            $isCorrect = !empty($choice['is_correct']);

            if ($isCorrect) {
                $newCorrectAnswer = $canonicalLabel;
            }

            $canonicalChoices[] = [
                'label' => $canonicalLabel,
                'content' => $choice['content'] ?? '',
                'is_correct' => $isCorrect,
                'explanation' => $choice['explanation'] ?? null,
            ];
        }

        // Attach positioning telemetry to metadata
        $metadata = $candidate->metadata;
        $metadata['answer_positioning'] = [
            'strategy' => 'deterministic_batch_cycle_v1',
            'original_correct_label' => $originalCorrectLabel,
            'final_correct_label' => $newCorrectAnswer,
            'target_choice_index' => $targetIndex,
            'choice_count' => $choiceCount,
        ];

        return new GeneratedQuestionCandidate(
            schemaVersion: $candidate->schemaVersion,
            prompt: $candidate->prompt,
            passageText: $candidate->passageText,
            audioScript: $candidate->audioScript,
            choices: $canonicalChoices,
            correctAnswer: $newCorrectAnswer,
            explanation: $candidate->explanation,
            rubric: $candidate->rubric,
            sampleResponse: $candidate->sampleResponse,
            assessmentFamily: $candidate->assessmentFamily,
            standardVersion: $candidate->standardVersion,
            section: $candidate->section,
            partNumber: $candidate->partNumber,
            taskType: $candidate->taskType,
            claim: $candidate->claim,
            skill: $candidate->skill,
            construct: $candidate->construct,
            proficiencyTarget: $candidate->proficiencyTarget,
            difficulty: $candidate->difficulty,
            metadata: $metadata,
        );
    }

    /**
     * Compute a deterministic target index for a multiple choice question.
     */
    public function determineTargetIndex(
        ?QuestionGenerationItem $item,
        GeneratedQuestionCandidate $candidate,
        int $choiceCount
    ): int {
        if ($choiceCount <= 1) {
            return 0;
        }

        // 1. Derive stable batch seed
        $seedSource = '';
        if ($item !== null) {
            $seedSource = (string) ($item->generation_batch_id ?: ($item->slot_fingerprint ?: $item->id));
        }

        if (empty($seedSource)) {
            $seedSource = md5($candidate->prompt ?: 'iap_default_generation_seed');
        }

        $batchOffset = abs(crc32($seedSource)) % $choiceCount;

        // 2. Cycle by slot sequence (1-indexed)
        $slotSequence = $item !== null ? max(1, (int) $item->slot_sequence) : 1;

        return ($batchOffset + ($slotSequence - 1)) % $choiceCount;
    }
}
