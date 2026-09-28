<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Services\PartConstructCompatibility;
use InvalidArgumentException;

class QuestionGenerationRequest
{
    public function __construct(
        public int $partNumber,
        public ProficiencyTarget $proficiencyTarget,
        public DifficultyLevel $difficulty,
        public ConstructTaxonomy $construct,
        public ContentMode $contentMode = ContentMode::General,
        public DomainTaxonomy $domain = DomainTaxonomy::GeneralWorkplace,
        public ?SectionType $section = null,
        public ?ContextTaxonomy $context = null,
        public int $itemCount = 1,
        public TestType $testType = TestType::Toeic,
        public string $locale = 'en',
        public ?string $seed = null,
        public array $metadata = []
    ) {
        $canonicalSection = PartConstructCompatibility::getCanonicalSectionForPart($this->partNumber);
        if ($this->section === null) {
            $this->section = $canonicalSection;
        }

        if ($this->contentMode === ContentMode::General && $this->domain === DomainTaxonomy::GeneralWorkplace) {
            $this->domain = DomainTaxonomy::GeneralWorkplace;
        }
    }

    /**
     * Create a QuestionGenerationRequest instance from raw array data.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $partNumber = (int) ($data['part_number'] ?? $data['part'] ?? 0);

        $proficiencyRaw = $data['proficiency_target'] ?? $data['proficiency'] ?? null;
        $proficiencyTarget = $proficiencyRaw instanceof ProficiencyTarget
            ? $proficiencyRaw
            : (is_string($proficiencyRaw) ? ProficiencyTarget::tryFrom($proficiencyRaw) : null);

        if ($proficiencyTarget === null) {
            throw new InvalidArgumentException('Invalid or missing proficiency_target.');
        }

        $difficultyRaw = $data['difficulty'] ?? null;
        $difficulty = $difficultyRaw instanceof DifficultyLevel
            ? $difficultyRaw
            : (is_string($difficultyRaw) ? DifficultyLevel::tryFrom($difficultyRaw) : null);

        if ($difficulty === null) {
            throw new InvalidArgumentException('Invalid or missing difficulty level.');
        }

        $constructRaw = $data['construct'] ?? null;
        $construct = $constructRaw instanceof ConstructTaxonomy
            ? $constructRaw
            : (is_string($constructRaw) ? ConstructTaxonomy::tryFrom($constructRaw) : null);

        if ($construct === null) {
            throw new InvalidArgumentException('Invalid or missing construct.');
        }

        $contentModeRaw = $data['content_mode'] ?? ContentMode::General->value;
        $contentMode = $contentModeRaw instanceof ContentMode
            ? $contentModeRaw
            : (is_string($contentModeRaw) ? ContentMode::tryFrom($contentModeRaw) : null);

        if ($contentMode === null) {
            throw new InvalidArgumentException('Invalid content_mode.');
        }

        $domainRaw = $data['domain'] ?? ($contentMode === ContentMode::General ? DomainTaxonomy::GeneralWorkplace->value : null);
        $domain = $domainRaw instanceof DomainTaxonomy
            ? $domainRaw
            : (is_string($domainRaw) ? DomainTaxonomy::tryFrom($domainRaw) : null);

        if ($domain === null) {
            if ($contentMode === ContentMode::DomainSpecific) {
                throw new InvalidArgumentException('Domain-specific generation requires a valid domain.');
            }
            $domain = DomainTaxonomy::GeneralWorkplace;
        }

        $sectionRaw = $data['section'] ?? null;
        $section = $sectionRaw instanceof SectionType
            ? $sectionRaw
            : (is_string($sectionRaw) ? SectionType::tryFrom($sectionRaw) : null);

        $contextRaw = $data['context'] ?? null;
        $context = $contextRaw instanceof ContextTaxonomy
            ? $contextRaw
            : (is_string($contextRaw) ? ContextTaxonomy::tryFrom($contextRaw) : null);

        $testTypeRaw = $data['test_type'] ?? TestType::Toeic->value;
        $testType = $testTypeRaw instanceof TestType
            ? $testTypeRaw
            : (is_string($testTypeRaw) ? TestType::tryFrom($testTypeRaw) : TestType::Toeic);

        $itemCount = max(1, (int) ($data['item_count'] ?? 1));
        $locale = (string) ($data['locale'] ?? 'en');
        $seed = isset($data['seed']) ? (string) $data['seed'] : null;
        $metadata = (array) ($data['metadata'] ?? []);

        return new self(
            partNumber: $partNumber,
            proficiencyTarget: $proficiencyTarget,
            difficulty: $difficulty,
            construct: $construct,
            contentMode: $contentMode,
            domain: $domain,
            section: $section,
            context: $context,
            itemCount: $itemCount,
            testType: $testType ?? TestType::Toeic,
            locale: $locale,
            seed: $seed,
            metadata: $metadata
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'test_type' => $this->testType->value,
            'content_mode' => $this->contentMode->value,
            'domain' => $this->domain->value,
            'section' => $this->section?->value,
            'part_number' => $this->partNumber,
            'proficiency_target' => $this->proficiencyTarget->value,
            'difficulty' => $this->difficulty->value,
            'construct' => $this->construct->value,
            'context' => $this->context?->value,
            'item_count' => $this->itemCount,
            'locale' => $this->locale,
            'seed' => $this->seed,
            'metadata' => $this->metadata,
        ];
    }

    public function isDomainSpecific(): bool
    {
        return $this->contentMode === ContentMode::DomainSpecific;
    }

    public function isListening(): bool
    {
        return $this->section === SectionType::Listening;
    }

    public function isReading(): bool
    {
        return $this->section === SectionType::Reading;
    }
}
