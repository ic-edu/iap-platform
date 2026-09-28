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
        if (!isset($data['part_number']) && !isset($data['part'])) {
            throw new InvalidArgumentException('Missing part_number.');
        }

        $partNumber = (int) ($data['part_number'] ?? $data['part']);
        if ($partNumber < 1 || $partNumber > 7) {
            throw new InvalidArgumentException('The part_number must be an integer between 1 and 7 for TOEIC.');
        }

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

        $domainRaw = $data['domain'] ?? null;
        if ($domainRaw instanceof DomainTaxonomy) {
            $domain = $domainRaw;
        } elseif (is_string($domainRaw) && $domainRaw !== '') {
            $domain = DomainTaxonomy::tryFrom($domainRaw);
            if ($domain === null) {
                throw new InvalidArgumentException("Invalid domain '{$domainRaw}'.");
            }
        } else {
            $domain = null;
        }

        if ($contentMode === ContentMode::General) {
            if ($domain === null || $domain === DomainTaxonomy::GeneralWorkplace) {
                $domain = DomainTaxonomy::GeneralWorkplace;
            } else {
                throw new InvalidArgumentException('General content mode requires domain general_workplace.');
            }
        } elseif ($contentMode === ContentMode::DomainSpecific) {
            if ($domain === null) {
                throw new InvalidArgumentException('Domain-specific generation requires an explicit domain.');
            }
            if ($domain === DomainTaxonomy::GeneralWorkplace) {
                throw new InvalidArgumentException('Domain-specific content mode requires a specific domain other than general_workplace.');
            }
        }

        $sectionRaw = $data['section'] ?? null;
        if ($sectionRaw !== null) {
            $section = $sectionRaw instanceof SectionType
                ? $sectionRaw
                : (is_string($sectionRaw) ? SectionType::tryFrom($sectionRaw) : null);

            if ($section === null) {
                throw new InvalidArgumentException('Invalid section.');
            }
        } else {
            $section = null;
        }

        $contextRaw = $data['context'] ?? null;
        if ($contextRaw !== null && $contextRaw !== '') {
            $context = $contextRaw instanceof ContextTaxonomy
                ? $contextRaw
                : (is_string($contextRaw) ? ContextTaxonomy::tryFrom($contextRaw) : null);

            if ($context === null) {
                throw new InvalidArgumentException("Invalid context '{$contextRaw}'.");
            }
        } else {
            $context = null;
        }

        if (array_key_exists('test_type', $data) && $data['test_type'] !== null) {
            $testTypeRaw = $data['test_type'];
            $testType = $testTypeRaw instanceof TestType
                ? $testTypeRaw
                : (is_string($testTypeRaw) ? TestType::tryFrom($testTypeRaw) : null);

            if ($testType === null) {
                throw new InvalidArgumentException('Invalid test_type.');
            }
        } else {
            $testType = TestType::Toeic;
        }

        if (array_key_exists('item_count', $data) && $data['item_count'] !== null) {
            if (!is_numeric($data['item_count']) || (int) $data['item_count'] < 1) {
                throw new InvalidArgumentException('The item_count must be a positive integer greater than or equal to 1.');
            }
            $itemCount = (int) $data['item_count'];
        } else {
            $itemCount = 1;
        }

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
            testType: $testType,
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
