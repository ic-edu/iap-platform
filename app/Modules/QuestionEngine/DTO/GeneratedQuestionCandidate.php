<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionEngine\Enums\AssessmentFamily;

final class GeneratedQuestionCandidate
{
    /**
     * @param  string  $schemaVersion  e.g. 'generated_question_candidate_v1'
     * @param  string  $prompt  Question prompt / main stimulus text / instructions
     * @param  string|null  $passageText  Associated reading passage text
     * @param  string|null  $audioScript  Audio transcript/script
     * @param  list<array<string, mixed>>  $choices  List of options: ['label' => 'A', 'content' => '...', 'is_correct' => bool, 'explanation' => ?string]
     * @param  string|null  $correctAnswer  Direct label or identifier of the correct answer (e.g. 'A')
     * @param  string|null  $explanation  Pedagogical explanation of the question and answers
     * @param  string|null  $rubric  Evaluation rubric for constructed/open responses
     * @param  string|null  $sampleResponse  Exemplar / benchmark response
     * @param  AssessmentFamily|null  $assessmentFamily  Target assessment family identity
     * @param  string|null  $standardVersion  Target standard version
     * @param  string|null  $section  Target section
     * @param  int|null  $partNumber  Target part number (TOEIC)
     * @param  string|null  $taskType  Target task type (TOEFL)
     * @param  string|null  $claim  Target claim statement
     * @param  string|null  $skill  Target skill statement
     * @param  string|null  $construct  Target construct
     * @param  string|null  $proficiencyTarget  Target CEFR level/proficiency
     * @param  string|null  $difficulty  Target difficulty
     * @param  array<string, mixed>  $metadata  Taxonomy alignment, estimated timing, etc.
     */
    public function __construct(
        public readonly string $schemaVersion,
        public readonly string $prompt,
        public readonly ?string $passageText = null,
        public readonly ?string $audioScript = null,
        public readonly array $choices = [],
        public readonly ?string $correctAnswer = null,
        public readonly ?string $explanation = null,
        public readonly ?string $rubric = null,
        public readonly ?string $sampleResponse = null,
        public readonly ?AssessmentFamily $assessmentFamily = null,
        public readonly ?string $standardVersion = null,
        public readonly ?string $section = null,
        public readonly ?int $partNumber = null,
        public readonly ?string $taskType = null,
        public readonly ?string $claim = null,
        public readonly ?string $skill = null,
        public readonly ?string $construct = null,
        public readonly ?string $proficiencyTarget = null,
        public readonly ?string $difficulty = null,
        public readonly array $metadata = []
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $family = null;
        if (isset($data['assessment_family']) && $data['assessment_family'] !== null) {
            $family = $data['assessment_family'] instanceof AssessmentFamily
                ? $data['assessment_family']
                : AssessmentFamily::tryFrom((string) $data['assessment_family']);
        }

        return new self(
            schemaVersion: (string) ($data['schema_version'] ?? 'generated_question_candidate_v1'),
            prompt: (string) ($data['prompt'] ?? ($data['question_text'] ?? '')),
            passageText: isset($data['passage_text']) ? (string) $data['passage_text'] : null,
            audioScript: isset($data['audio_script']) ? (string) $data['audio_script'] : null,
            choices: (array) ($data['choices'] ?? []),
            correctAnswer: isset($data['correct_answer']) ? (string) $data['correct_answer'] : null,
            explanation: isset($data['explanation']) ? (string) $data['explanation'] : null,
            rubric: isset($data['rubric']) ? (string) $data['rubric'] : null,
            sampleResponse: isset($data['sample_response']) ? (string) $data['sample_response'] : null,
            assessmentFamily: $family,
            standardVersion: isset($data['standard_version']) ? (string) $data['standard_version'] : null,
            section: isset($data['section']) ? (string) $data['section'] : null,
            partNumber: isset($data['part_number']) && $data['part_number'] !== null ? (int) $data['part_number'] : null,
            taskType: isset($data['task_type']) ? (string) $data['task_type'] : null,
            claim: isset($data['claim']) ? (string) $data['claim'] : null,
            skill: isset($data['skill']) ? (string) $data['skill'] : null,
            construct: isset($data['construct']) ? (string) $data['construct'] : null,
            proficiencyTarget: isset($data['proficiency_target']) ? (string) $data['proficiency_target'] : null,
            difficulty: isset($data['difficulty']) ? (string) $data['difficulty'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'prompt' => $this->prompt,
            'passage_text' => $this->passageText,
            'audio_script' => $this->audioScript,
            'choices' => $this->choices,
            'correct_answer' => $this->correctAnswer,
            'explanation' => $this->explanation,
            'rubric' => $this->rubric,
            'sample_response' => $this->sampleResponse,
            'assessment_family' => $this->assessmentFamily?->value,
            'standard_version' => $this->standardVersion,
            'section' => $this->section,
            'part_number' => $this->partNumber,
            'task_type' => $this->taskType,
            'claim' => $this->claim,
            'skill' => $this->skill,
            'construct' => $this->construct,
            'proficiency_target' => $this->proficiencyTarget,
            'difficulty' => $this->difficulty,
            'metadata' => $this->metadata,
        ];
    }

    public function isMultipleChoice(): bool
    {
        return !empty($this->choices);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCorrectChoice(): ?array
    {
        foreach ($this->choices as $choice) {
            if (!empty($choice['is_correct'])) {
                return $choice;
            }
        }

        if ($this->correctAnswer !== null) {
            foreach ($this->choices as $choice) {
                if (isset($choice['label']) && strtoupper((string) $choice['label']) === strtoupper($this->correctAnswer)) {
                    return $choice;
                }
            }
        }

        return null;
    }
}
