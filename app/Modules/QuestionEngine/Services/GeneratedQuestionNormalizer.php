<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\DTO\GeneratedQuestionCandidate;
use App\Modules\QuestionEngine\DTO\GenerationProviderResponse;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Models\QuestionGenerationItem;
use InvalidArgumentException;

class GeneratedQuestionNormalizer
{
    /**
     * Normalize raw provider response or payload into a GeneratedQuestionCandidate.
     * Populates missing structural identity from trusted item or metadata context.
     *
     * @param  GenerationProviderResponse|string|array<string, mixed>  $raw
     * @param  array<string, mixed>|null  $trustedMetadata
     *
     * @throws InvalidArgumentException
     */
    public function normalize(
        GenerationProviderResponse|string|array $raw,
        ?QuestionGenerationItem $item = null,
        ?array $trustedMetadata = null
    ): GeneratedQuestionCandidate {
        $payload = null;

        if ($raw instanceof GenerationProviderResponse) {
            if ($raw->parsedPayload !== null) {
                $payload = $raw->parsedPayload;
            } elseif (!empty($raw->rawContent)) {
                $payload = $this->parseJsonString($raw->rawContent);
            } else {
                throw new InvalidArgumentException('GenerationProviderResponse contains neither parsed payload nor raw content.');
            }
        } elseif (is_string($raw)) {
            $payload = $this->parseJsonString($raw);
        } elseif (is_array($raw)) {
            $payload = $raw;
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Normalized payload must be an associative array.');
        }

        return $this->buildCandidateFromPayload($payload, $item, $trustedMetadata);
    }

    /**
     * Extract JSON from raw string (stripping markdown fences if present).
     *
     * @return array<string, mixed>
     */
    protected function parseJsonString(string $raw): array
    {
        $clean = trim($raw);

        // Strip markdown fences ```json ... ```
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $clean, $matches)) {
            $clean = trim($matches[1]);
        } elseif (preg_match('/```(?:json)?\s*(.*)/s', $clean, $matches)) {
            $clean = trim($matches[1]);
            $clean = preg_replace('/```$/s', '', $clean);
            $clean = trim($clean);
        }

        // Strip leading/trailing non-JSON artifacts
        $firstBrace = strpos($clean, '{');
        $lastBrace = strrpos($clean, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $clean = substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new InvalidArgumentException('Failed to parse valid JSON from provider output: '.json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $trustedMetadata
     */
    protected function buildCandidateFromPayload(
        array $payload,
        ?QuestionGenerationItem $item = null,
        ?array $trustedMetadata = null
    ): GeneratedQuestionCandidate {
        $prompt = (string) ($payload['prompt'] ?? ($payload['question_text'] ?? ($payload['question'] ?? '')));
        $passageText = isset($payload['passage_text']) && !empty($payload['passage_text']) ? (string) $payload['passage_text'] : null;
        $audioScript = isset($payload['audio_script']) && !empty($payload['audio_script']) ? (string) $payload['audio_script'] : null;
        $explanation = isset($payload['explanation']) ? (string) $payload['explanation'] : null;
        $rubric = isset($payload['rubric']) ? (string) $payload['rubric'] : null;
        $sampleResponse = isset($payload['sample_response']) ? (string) $payload['sample_response'] : null;
        $correctAnswer = isset($payload['correct_answer']) ? (string) $payload['correct_answer'] : null;
        $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : [];

        // Extract structural identity: provider-supplied first, fallback to trusted slot context
        $rawFamily = $payload['assessment_family'] ?? ($metadata['assessment_family'] ?? null);
        $assessmentFamily = null;
        if ($rawFamily !== null) {
            $assessmentFamily = $rawFamily instanceof AssessmentFamily
                ? $rawFamily
                : AssessmentFamily::tryFrom((string) $rawFamily);
        } elseif ($item !== null) {
            $assessmentFamily = $item->assessment_family;
        } elseif (isset($trustedMetadata['assessment_family'])) {
            $assessmentFamily = $trustedMetadata['assessment_family'] instanceof AssessmentFamily
                ? $trustedMetadata['assessment_family']
                : AssessmentFamily::tryFrom((string) $trustedMetadata['assessment_family']);
        }

        $standardVersion = isset($payload['standard_version']) && $payload['standard_version'] !== ''
            ? (string) $payload['standard_version']
            : ($metadata['standard_version'] ?? ($item?->standard_version ?? ($trustedMetadata['standard_version'] ?? null)));

        $section = isset($payload['section']) && $payload['section'] !== ''
            ? (string) $payload['section']
            : ($metadata['section'] ?? ($item?->section ?? ($trustedMetadata['section'] ?? null)));

        $partNumber = isset($payload['part_number']) && $payload['part_number'] !== null && $payload['part_number'] !== ''
            ? (int) $payload['part_number']
            : (isset($metadata['part_number']) && $metadata['part_number'] !== null && $metadata['part_number'] !== ''
                ? (int) $metadata['part_number']
                : ($item?->part_number ?? (isset($trustedMetadata['part_number']) && $trustedMetadata['part_number'] !== null ? (int) $trustedMetadata['part_number'] : null)));

        $taskType = isset($payload['task_type']) && $payload['task_type'] !== ''
            ? (string) $payload['task_type']
            : ($metadata['task_type'] ?? ($item?->task_type ?? ($trustedMetadata['task_type'] ?? null)));

        $claim = isset($payload['claim']) && $payload['claim'] !== ''
            ? (string) $payload['claim']
            : ($metadata['claim'] ?? ($item?->claim ?? ($trustedMetadata['claim'] ?? null)));

        $skill = isset($payload['skill']) && $payload['skill'] !== ''
            ? (string) $payload['skill']
            : ($metadata['skill'] ?? ($item?->skill ?? ($trustedMetadata['skill'] ?? null)));

        $construct = isset($payload['construct']) && $payload['construct'] !== ''
            ? (string) $payload['construct']
            : ($metadata['construct'] ?? ($item?->construct ?? ($trustedMetadata['construct'] ?? null)));

        $proficiencyTarget = isset($payload['proficiency_target']) && $payload['proficiency_target'] !== ''
            ? (string) $payload['proficiency_target']
            : ($metadata['proficiency_target'] ?? ($item?->proficiency_target ?? ($trustedMetadata['proficiency_target'] ?? null)));

        $difficulty = isset($payload['difficulty']) && $payload['difficulty'] !== ''
            ? (string) $payload['difficulty']
            : ($metadata['difficulty'] ?? ($item?->difficulty ?? ($trustedMetadata['difficulty'] ?? null)));

        $rawChoices = (array) ($payload['choices'] ?? ($payload['options'] ?? []));
        $normalizedChoices = $this->normalizeChoices($rawChoices, $correctAnswer);

        return new GeneratedQuestionCandidate(
            schemaVersion: (string) ($payload['schema_version'] ?? 'generated_question_candidate_v1'),
            prompt: $prompt,
            passageText: $passageText,
            audioScript: $audioScript,
            choices: $normalizedChoices,
            correctAnswer: $correctAnswer,
            explanation: $explanation,
            rubric: $rubric,
            sampleResponse: $sampleResponse,
            assessmentFamily: $assessmentFamily,
            standardVersion: $standardVersion ? (string) $standardVersion : null,
            section: $section ? (string) $section : null,
            partNumber: $partNumber,
            taskType: $taskType ? (string) $taskType : null,
            claim: $claim ? (string) $claim : null,
            skill: $skill ? (string) $skill : null,
            construct: $construct ? (string) $construct : null,
            proficiencyTarget: $proficiencyTarget ? (string) $proficiencyTarget : null,
            difficulty: $difficulty ? (string) $difficulty : null,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<int, mixed>  $rawChoices
     * @return list<array<string, mixed>>
     */
    protected function normalizeChoices(array $rawChoices, ?string &$correctAnswer): array
    {
        $normalized = [];
        $defaultLabels = ['A', 'B', 'C', 'D', 'E'];
        $hasMarkedCorrect = false;

        foreach ($rawChoices as $idx => $rawChoice) {
            $label = $defaultLabels[$idx] ?? chr(65 + $idx);
            $content = '';
            $isCorrect = false;
            $explanation = null;

            if (is_string($rawChoice)) {
                $content = trim($rawChoice);
                // Check if string is formatted like "(A) Content"
                if (preg_match('/^\(([A-E])\)\s*(.*)$/i', $content, $m)) {
                    $label = strtoupper($m[1]);
                    $content = trim($m[2]);
                }
            } elseif (is_array($rawChoice)) {
                $label = strtoupper((string) ($rawChoice['label'] ?? ($rawChoice['key'] ?? $label)));
                $content = trim((string) ($rawChoice['content'] ?? ($rawChoice['text'] ?? ($rawChoice['choice_text'] ?? ''))));
                $isCorrect = !empty($rawChoice['is_correct']) || !empty($rawChoice['correct']);
                $explanation = isset($rawChoice['explanation']) ? (string) $rawChoice['explanation'] : null;
            }

            if ($correctAnswer !== null && strtoupper($label) === strtoupper($correctAnswer)) {
                $isCorrect = true;
            }

            if ($isCorrect) {
                $hasMarkedCorrect = true;
                if ($correctAnswer === null) {
                    $correctAnswer = $label;
                }
            }

            $normalized[] = [
                'label' => $label,
                'content' => $content,
                'is_correct' => $isCorrect,
                'explanation' => $explanation,
            ];
        }

        // If no choice was marked correct but correctAnswer is specified
        if (!$hasMarkedCorrect && $correctAnswer !== null) {
            foreach ($normalized as &$c) {
                if (strtoupper($c['label']) === strtoupper($correctAnswer)) {
                    $c['is_correct'] = true;
                    $hasMarkedCorrect = true;
                    break;
                }
            }
        }

        return $normalized;
    }
}
