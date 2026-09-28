<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflResponseMode;
use App\Modules\QuestionEngine\Enums\ToeflScoringMode;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use InvalidArgumentException;

class ToeflGenerationSlot
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $sequence,
        public AssessmentFamily $assessmentFamily,
        public string $assessmentStandardId,
        public string $standardVersion,
        public string $section,
        public ToeflTaskType $taskType,
        public ToeflClaim $claim,
        public ToeflSkill $skill,
        public ?ProficiencyTarget $proficiencyTarget,
        public ?DifficultyLevel $difficulty,
        public ToeflLanguageUseContext $languageUseContext,
        public ToeflResponseMode $responseMode,
        public ToeflScoringMode $scoringMode,
        public bool $adaptiveSection,
        public ?int $adaptiveStage = null,
        public ?string $moduleRole = null,
        public string $itemScoringCategory = 'unspecified',
        public ?string $stimulusType = null,
        public ?string $stimulusTypeProvenance = 'iap_derived',
        public ?int $seed = null,
        public ?int $partNumber = null,
        public array $metadata = []
    ) {
        if ($this->partNumber !== null) {
            throw new InvalidArgumentException('TOEFL generation slot part_number must always be null.');
        }

        if ($this->sequence <= 0) {
            throw new InvalidArgumentException("Slot sequence must be a positive integer, got {$this->sequence}.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'assessment_family' => $this->assessmentFamily->value,
            'assessment_standard_id' => $this->assessmentStandardId,
            'standard_version' => $this->standardVersion,
            'section' => $this->section,
            'task_type' => $this->taskType->value,
            'claim' => $this->claim->value,
            'skill' => $this->skill->value,
            'proficiency_target' => $this->proficiencyTarget?->value,
            'difficulty' => $this->difficulty?->value,
            'language_use_context' => $this->languageUseContext->value,
            'response_mode' => $this->responseMode->value,
            'scoring_mode' => $this->scoringMode->value,
            'adaptive_section' => $this->adaptiveSection,
            'adaptive_stage' => $this->adaptiveStage,
            'module_role' => $this->moduleRole,
            'item_scoring_category' => $this->itemScoringCategory,
            'stimulus_type' => $this->stimulusType,
            'stimulus_type_provenance' => $this->stimulusTypeProvenance,
            'part_number' => null,
            'seed' => $this->seed,
            'metadata' => $this->metadata,
        ];
    }
}
