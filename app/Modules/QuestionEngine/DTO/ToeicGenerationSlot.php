<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use InvalidArgumentException;

final class ToeicGenerationSlot
{
    /**
     * @param  int  $sequence  1-indexed sequence in generation plan
     * @param  int|null  $canonicalQuestionNumber  Canonical 1..200 question number (if applicable)
     * @param  AssessmentFamily  $assessmentFamily  Assessment family (Toeic)
     * @param  string  $assessmentStandardId  Bound AssessmentStandard ULID/ID
     * @param  string  $standardVersion  Bound AssessmentStandard version
     * @param  SectionType  $section  SectionType (Listening / Reading)
     * @param  int  $partNumber  TOEIC Part number (1..7)
     * @param  ProficiencyTarget  $proficiencyTarget  Target proficiency
     * @param  DifficultyLevel  $difficulty  Target difficulty
     * @param  ConstructTaxonomy  $construct  Assigned construct
     * @param  ContentMode  $contentMode  ContentMode (General / DomainSpecific)
     * @param  DomainTaxonomy  $domain  Domain
     * @param  ContextTaxonomy|null  $context  Optional context
     * @param  string|null  $groupType  Optional group type (standalone, conversation, talk, single, double, triple, passage)
     * @param  int|null  $groupIndex  1-indexed group index in part
     * @param  int|null  $positionInGroup  1-indexed question position in group
     * @param  int|null  $seed  Slot-specific deterministic seed
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly int $sequence,
        public readonly ?int $canonicalQuestionNumber,
        public readonly AssessmentFamily $assessmentFamily,
        public readonly string $assessmentStandardId,
        public readonly string $standardVersion,
        public readonly SectionType $section,
        public readonly int $partNumber,
        public readonly ProficiencyTarget $proficiencyTarget,
        public readonly DifficultyLevel $difficulty,
        public readonly ConstructTaxonomy $construct,
        public readonly ContentMode $contentMode,
        public readonly DomainTaxonomy $domain,
        public readonly ?ContextTaxonomy $context = null,
        public readonly ?string $groupType = null,
        public readonly ?int $groupIndex = null,
        public readonly ?int $positionInGroup = null,
        public readonly ?int $seed = null,
        public readonly array $metadata = [],
    ) {
        if ($this->sequence < 1) {
            throw new InvalidArgumentException("Slot sequence must be positive, got {$this->sequence}.");
        }
        if ($this->partNumber < 1 || $this->partNumber > 7) {
            throw new InvalidArgumentException("Invalid TOEIC part number [{$this->partNumber}]. Must be between 1 and 7.");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sequence: (int) $data['sequence'],
            canonicalQuestionNumber: isset($data['canonical_question_number']) && $data['canonical_question_number'] !== null ? (int) $data['canonical_question_number'] : null,
            assessmentFamily: $data['assessment_family'] instanceof AssessmentFamily ? $data['assessment_family'] : AssessmentFamily::from((string) $data['assessment_family']),
            assessmentStandardId: (string) $data['assessment_standard_id'],
            standardVersion: (string) $data['standard_version'],
            section: $data['section'] instanceof SectionType ? $data['section'] : SectionType::from((string) $data['section']),
            partNumber: (int) $data['part_number'],
            proficiencyTarget: $data['proficiency_target'] instanceof ProficiencyTarget ? $data['proficiency_target'] : ProficiencyTarget::from((string) $data['proficiency_target']),
            difficulty: $data['difficulty'] instanceof DifficultyLevel ? $data['difficulty'] : DifficultyLevel::from((string) $data['difficulty']),
            construct: $data['construct'] instanceof ConstructTaxonomy ? $data['construct'] : ConstructTaxonomy::from((string) $data['construct']),
            contentMode: $data['content_mode'] instanceof ContentMode ? $data['content_mode'] : ContentMode::from((string) $data['content_mode']),
            domain: $data['domain'] instanceof DomainTaxonomy ? $data['domain'] : DomainTaxonomy::from((string) $data['domain']),
            context: isset($data['context']) && $data['context'] !== null ? ($data['context'] instanceof ContextTaxonomy ? $data['context'] : ContextTaxonomy::from((string) $data['context'])) : null,
            groupType: isset($data['group_type']) ? (string) $data['group_type'] : null,
            groupIndex: isset($data['group_index']) && $data['group_index'] !== null ? (int) $data['group_index'] : null,
            positionInGroup: isset($data['position_in_group']) && $data['position_in_group'] !== null ? (int) $data['position_in_group'] : null,
            seed: isset($data['seed']) && $data['seed'] !== null ? (int) $data['seed'] : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'canonical_question_number' => $this->canonicalQuestionNumber,
            'assessment_family' => $this->assessmentFamily->value,
            'assessment_standard_id' => $this->assessmentStandardId,
            'standard_version' => $this->standardVersion,
            'section' => $this->section->value,
            'part_number' => $this->partNumber,
            'proficiency_target' => $this->proficiencyTarget->value,
            'difficulty' => $this->difficulty->value,
            'construct' => $this->construct->value,
            'content_mode' => $this->contentMode->value,
            'domain' => $this->domain->value,
            'context' => $this->context?->value,
            'group_type' => $this->groupType,
            'group_index' => $this->groupIndex,
            'position_in_group' => $this->positionInGroup,
            'seed' => $this->seed,
            'metadata' => $this->metadata,
        ];
    }
}
