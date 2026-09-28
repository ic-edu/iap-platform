<?php

namespace App\Modules\QuestionEngine\DTO;

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
        public readonly array $metadata = []
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
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
