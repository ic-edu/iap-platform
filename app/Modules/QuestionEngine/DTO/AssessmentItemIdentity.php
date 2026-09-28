<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionEngine\Enums\AssessmentFamily;

class AssessmentItemIdentity
{
    public function __construct(
        public AssessmentFamily $assessmentFamily,
        public ?string $assessmentStandardId = null,
        public ?string $standardVersion = null,
        public ?string $section = null,
        public ?int $partNumber = null,
        public ?string $taskType = null,
        public ?string $claim = null,
        public ?string $skill = null,
        public ?string $construct = null,
        public ?string $proficiencyTarget = null,
        public ?string $domain = null,
        public ?string $context = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $familyRaw = $data['assessment_family'] ?? $data['family'] ?? null;
        $family = $familyRaw instanceof AssessmentFamily
            ? $familyRaw
            : (is_string($familyRaw) ? AssessmentFamily::tryFrom($familyRaw) : null);

        if ($family === null) {
            throw new \InvalidArgumentException('A valid assessment_family is required for AssessmentItemIdentity.');
        }

        return new self(
            assessmentFamily: $family,
            assessmentStandardId: isset($data['assessment_standard_id']) ? (string) $data['assessment_standard_id'] : null,
            standardVersion: isset($data['standard_version']) ? (string) $data['standard_version'] : null,
            section: isset($data['section']) ? (string) $data['section'] : null,
            partNumber: isset($data['part_number']) && is_numeric($data['part_number']) ? (int) $data['part_number'] : null,
            taskType: isset($data['task_type']) ? (string) $data['task_type'] : null,
            claim: isset($data['claim']) ? (string) $data['claim'] : null,
            skill: isset($data['skill']) ? (string) $data['skill'] : null,
            construct: isset($data['construct']) ? (string) $data['construct'] : null,
            proficiencyTarget: isset($data['proficiency_target']) ? (string) $data['proficiency_target'] : null,
            domain: isset($data['domain']) ? (string) $data['domain'] : null,
            context: isset($data['context']) ? (string) $data['context'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'assessment_family' => $this->assessmentFamily->value,
            'assessment_standard_id' => $this->assessmentStandardId,
            'standard_version' => $this->standardVersion,
            'section' => $this->section,
            'part_number' => $this->partNumber,
            'task_type' => $this->taskType,
            'claim' => $this->claim,
            'skill' => $this->skill,
            'construct' => $this->construct,
            'proficiency_target' => $this->proficiencyTarget,
            'domain' => $this->domain,
            'context' => $this->context,
        ];
    }
}
