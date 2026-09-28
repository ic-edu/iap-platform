<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Services\PartConstructCompatibility;
use App\Services\ToeicQuestionValidator;
use InvalidArgumentException;

final class ToeicBlueprintRequest
{
    public const MODE_FULL_TEST = 'full_test';

    public const MODE_PART = 'part';

    public const MODE_CUSTOM = 'custom';

    public const ALLOWED_MODES = [
        self::MODE_FULL_TEST,
        self::MODE_PART,
        self::MODE_CUSTOM,
    ];

    /**
     * @param  string  $mode  'full_test' | 'part' | 'custom'
     * @param  int|null  $partNumber  Required for 'part' mode (1..7)
     * @param  int|null  $itemCount  Target slot count
     * @param  ContentMode  $contentMode  ContentMode enum
     * @param  DomainTaxonomy  $domain  DomainTaxonomy enum
     * @param  array<string, int|float>  $proficiencyDistribution  Proficiency target distribution (sum = 100)
     * @param  array<string, int|float>  $difficultyDistribution  Difficulty level distribution (sum = 100)
     * @param  array<int, array<string, int|float>>|null  $constructDistribution  Per-part or global construct distribution
     * @param  array<string, int|float>|null  $contextDistribution  Optional context distribution
     * @param  int|null  $seed  Deterministic seed
     * @param  string|null  $standardId  Optional explicit standard ID
     * @param  string|null  $standardVersion  Optional explicit standard version
     * @param  array<int, int>  $customParts  Normalized parts specification for custom mode [partNumber => count]
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $mode = self::MODE_FULL_TEST,
        public readonly ?int $partNumber = null,
        public readonly ?int $itemCount = null,
        public readonly ContentMode $contentMode = ContentMode::General,
        public readonly DomainTaxonomy $domain = DomainTaxonomy::GeneralWorkplace,
        public readonly array $proficiencyDistribution = [
            'b1_low' => 25,
            'b1_standard' => 50,
            'b2_low' => 25,
        ],
        public readonly array $difficultyDistribution = [
            'easy' => 30,
            'medium' => 50,
            'hard' => 20,
        ],
        public readonly ?array $constructDistribution = null,
        public readonly ?array $contextDistribution = null,
        public readonly ?int $seed = null,
        public readonly ?string $standardId = null,
        public readonly ?string $standardVersion = null,
        public readonly array $customParts = [],
        public readonly array $metadata = [],
    ) {
        $this->validate();
    }

    /**
     * Create instance from array payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $mode = (string) ($data['mode'] ?? self::MODE_FULL_TEST);

        // Normalize Part Number (Strict Integer Parsing)
        $partNumber = null;
        if (isset($data['part_number']) && $data['part_number'] !== null && $data['part_number'] !== '') {
            $partNumber = self::parseStrictPartNumber($data['part_number'], 'part_number');
        }

        // Normalize Item Count (Strict Integer Parsing)
        $itemCount = null;
        if (isset($data['item_count']) && $data['item_count'] !== null && $data['item_count'] !== '') {
            $itemCount = self::parseStrictPositiveInteger($data['item_count'], 'item_count');
        }

        // Normalize Content Mode
        $rawContentMode = $data['content_mode'] ?? ContentMode::General;
        $contentMode = $rawContentMode instanceof ContentMode
            ? $rawContentMode
            : ContentMode::tryFrom((string) $rawContentMode);

        if ($contentMode === null) {
            throw new InvalidArgumentException("Invalid content mode: [{$data['content_mode']}].");
        }

        // Normalize Domain
        $domainSpecified = isset($data['domain']) && $data['domain'] !== null && $data['domain'] !== '';
        if ($contentMode === ContentMode::General) {
            if ($domainSpecified) {
                $domainVal = $data['domain'] instanceof DomainTaxonomy ? $data['domain']->value : (string) $data['domain'];
                if ($domainVal !== DomainTaxonomy::GeneralWorkplace->value) {
                    throw new InvalidArgumentException('General content mode requires domain general_workplace.');
                }
            }
            $domain = DomainTaxonomy::GeneralWorkplace;
        } else {
            if (!$domainSpecified) {
                throw new InvalidArgumentException('Domain-specific content mode requires an explicit domain other than general_workplace.');
            }
            $domain = $data['domain'] instanceof DomainTaxonomy
                ? $data['domain']
                : DomainTaxonomy::tryFrom((string) $data['domain']);

            if ($domain === null) {
                throw new InvalidArgumentException("Invalid domain: [{$data['domain']}].");
            }

            if ($domain === DomainTaxonomy::GeneralWorkplace) {
                throw new InvalidArgumentException('Domain-specific content mode requires an explicit domain other than general_workplace.');
            }
        }

        // Normalize Proficiency Distribution
        $rawProf = $data['proficiency_distribution'] ?? [
            'b1_low' => 25,
            'b1_standard' => 50,
            'b2_low' => 25,
        ];
        $proficiencyDistribution = [];
        foreach ($rawProf as $pKey => $pWeight) {
            $keyStr = $pKey instanceof ProficiencyTarget ? $pKey->value : (string) $pKey;
            $target = ProficiencyTarget::tryFrom($keyStr);
            if ($target === null) {
                throw new InvalidArgumentException("Invalid proficiency target in distribution: [{$keyStr}].");
            }
            $proficiencyDistribution[$target->value] = is_numeric($pWeight) ? (float) $pWeight : $pWeight;
        }

        // Normalize Difficulty Distribution
        $rawDiff = $data['difficulty_distribution'] ?? [
            'easy' => 30,
            'medium' => 50,
            'hard' => 20,
        ];
        $difficultyDistribution = [];
        foreach ($rawDiff as $dKey => $dWeight) {
            $keyStr = $dKey instanceof DifficultyLevel ? $dKey->value : (string) $dKey;
            $level = DifficultyLevel::tryFrom($keyStr);
            if ($level === null) {
                throw new InvalidArgumentException("Invalid difficulty level in distribution: [{$keyStr}].");
            }
            $difficultyDistribution[$level->value] = is_numeric($dWeight) ? (float) $dWeight : $dWeight;
        }

        // Normalize Construct Distribution (Strict Part Keys)
        $constructDistribution = null;
        if (!empty($data['construct_distribution']) && is_array($data['construct_distribution'])) {
            $constructDistribution = [];
            $firstKey = array_key_first($data['construct_distribution']);
            $isPerPart = is_array($data['construct_distribution'][$firstKey] ?? null);

            if ($isPerPart) {
                foreach ($data['construct_distribution'] as $partKey => $partConstructs) {
                    $pNum = self::parseStrictPartNumber($partKey, 'construct_distribution part key');
                    if (!is_array($partConstructs)) {
                        throw new InvalidArgumentException("Construct distribution for Part {$pNum} must be an array.");
                    }
                    $constructDistribution[$pNum] = [];
                    foreach ($partConstructs as $cKey => $cWeight) {
                        $cKeyStr = $cKey instanceof ConstructTaxonomy ? $cKey->value : (string) $cKey;
                        $construct = ConstructTaxonomy::tryFrom($cKeyStr);
                        if ($construct === null) {
                            throw new InvalidArgumentException("Invalid construct [{$cKeyStr}] for Part {$pNum}.");
                        }
                        if (!PartConstructCompatibility::isCompatible($pNum, $construct)) {
                            throw new InvalidArgumentException("Construct [{$construct->value}] is incompatible with Part {$pNum}.");
                        }
                        $constructDistribution[$pNum][$construct->value] = is_numeric($cWeight) ? (float) $cWeight : $cWeight;
                    }
                }
            } else {
                $tempMap = [];
                foreach ($data['construct_distribution'] as $cKey => $cWeight) {
                    $cKeyStr = $cKey instanceof ConstructTaxonomy ? $cKey->value : (string) $cKey;
                    $construct = ConstructTaxonomy::tryFrom($cKeyStr);
                    if ($construct === null) {
                        throw new InvalidArgumentException("Invalid construct in distribution: [{$cKeyStr}].");
                    }
                    if ($partNumber !== null && !PartConstructCompatibility::isCompatible($partNumber, $construct)) {
                        throw new InvalidArgumentException("Construct [{$construct->value}] is incompatible with Part {$partNumber}.");
                    }
                    $tempMap[$construct->value] = is_numeric($cWeight) ? (float) $cWeight : $cWeight;
                }
                if ($partNumber !== null) {
                    $constructDistribution[$partNumber] = $tempMap;
                } else {
                    $constructDistribution = $tempMap;
                }
            }
        }

        // Normalize Context Distribution
        $contextDistribution = null;
        if (!empty($data['context_distribution']) && is_array($data['context_distribution'])) {
            $contextDistribution = [];
            foreach ($data['context_distribution'] as $ctxKey => $ctxWeight) {
                $ctxKeyStr = $ctxKey instanceof ContextTaxonomy ? $ctxKey->value : (string) $ctxKey;
                $context = ContextTaxonomy::tryFrom($ctxKeyStr);
                if ($context === null) {
                    throw new InvalidArgumentException("Invalid context in distribution: [{$ctxKeyStr}].");
                }
                $contextDistribution[$context->value] = is_numeric($ctxWeight) ? (float) $ctxWeight : $ctxWeight;
            }
        }

        // Normalize Seed (Strict Integer Parsing)
        $seed = null;
        if (isset($data['seed']) && $data['seed'] !== null && $data['seed'] !== '') {
            if (is_int($data['seed'])) {
                $seed = $data['seed'];
            } elseif (is_string($data['seed']) && preg_match('/^-?\d+$/', trim($data['seed']))) {
                $seed = (int) trim($data['seed']);
            } else {
                throw new InvalidArgumentException("Seed must be a valid integer, got [{$data['seed']}].");
            }
        }

        $standardId = isset($data['standard_id']) ? (string) $data['standard_id'] : (isset($data['assessment_standard_id']) ? (string) $data['assessment_standard_id'] : null);
        $standardVersion = isset($data['standard_version']) ? (string) $data['standard_version'] : null;

        // Normalize custom_parts ONCE strictly
        $rawCustomParts = isset($data['custom_parts']) && is_array($data['custom_parts']) ? $data['custom_parts'] : [];
        $customParts = self::normalizeCustomParts($rawCustomParts);

        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

        return new self(
            mode: $mode,
            partNumber: $partNumber,
            itemCount: $itemCount,
            contentMode: $contentMode,
            domain: $domain,
            proficiencyDistribution: $proficiencyDistribution,
            difficultyDistribution: $difficultyDistribution,
            constructDistribution: $constructDistribution,
            contextDistribution: $contextDistribution,
            seed: $seed,
            standardId: $standardId,
            standardVersion: $standardVersion,
            customParts: $customParts,
            metadata: $metadata,
        );
    }

    /**
     * Parse strict positive integer.
     */
    private static function parseStrictPositiveInteger(mixed $value, string $context): int
    {
        if (is_int($value)) {
            if ($value <= 0) {
                throw new InvalidArgumentException("{$context} must be positive, got [{$value}].");
            }

            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9]\d*$/', trim($value))) {
            return (int) trim($value);
        }

        throw new InvalidArgumentException("{$context} must be a valid positive integer, got [".(is_scalar($value) ? (string) $value : gettype($value)).'].');
    }

    /**
     * Parse strict TOEIC part number (1..7).
     */
    private static function parseStrictPartNumber(mixed $value, string $context): int
    {
        if (is_int($value)) {
            if ($value < 1 || $value > 7) {
                throw new InvalidArgumentException("Invalid TOEIC part number [{$value}] in {$context}. Must be between 1 and 7.");
            }

            return $value;
        }

        if (is_string($value) && preg_match('/^[1-7]$/', trim($value))) {
            return (int) trim($value);
        }

        throw new InvalidArgumentException('Invalid TOEIC part number ['.(is_scalar($value) ? (string) $value : gettype($value))."] in {$context}. Must be integer between 1 and 7.");
    }

    /**
     * Normalize custom_parts into canonical [part_number => item_count] map.
     *
     * @param  array<mixed, mixed>  $customParts
     * @return array<int, int>
     */
    private static function normalizeCustomParts(array $customParts): array
    {
        if (empty($customParts)) {
            return [];
        }

        $normalized = [];
        $isList = array_is_list($customParts);

        if ($isList) {
            foreach ($customParts as $idx => $partVal) {
                $part = self::parseStrictPartNumber($partVal, "custom_parts index {$idx}");
                $canonicalCount = ToeicQuestionValidator::getPartTargetQuestionCount($part);
                $normalized[$part] = $canonicalCount;
            }
        } else {
            foreach ($customParts as $partKey => $countVal) {
                $part = self::parseStrictPartNumber($partKey, 'custom_parts key');
                $canonicalMax = ToeicQuestionValidator::getPartTargetQuestionCount($part);
                $count = self::parseStrictPositiveInteger($countVal, "custom_parts count for Part {$part}");

                if ($count > $canonicalMax) {
                    throw new InvalidArgumentException("Custom count [{$count}] exceeds canonical Part {$part} limit of {$canonicalMax} questions.");
                }

                // Group-safe checks for Part 3 & 4
                if (in_array($part, [3, 4], true)) {
                    $qPerG = ToeicQuestionValidator::getAudioGroupQuestionCount();
                    if ($count % $qPerG !== 0) {
                        throw new InvalidArgumentException("Custom Part {$part} item count [{$count}] must be a multiple of {$qPerG} (complete audio groups).");
                    }
                } elseif ($part === 6) {
                    $qPerG = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();
                    if ($count % $qPerG !== 0) {
                        throw new InvalidArgumentException("Custom Part 6 item count [{$count}] must be a multiple of {$qPerG} (complete passage groups).");
                    }
                }

                $normalized[$part] = $count;
            }
        }

        return $normalized;
    }

    /**
     * Validate the request invariants.
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (!in_array($this->mode, self::ALLOWED_MODES, true)) {
            throw new InvalidArgumentException("Invalid mode [{$this->mode}]. Allowed modes: ".implode(', ', self::ALLOWED_MODES));
        }

        // Validate Part Mode
        if ($this->mode === self::MODE_PART) {
            if ($this->partNumber === null) {
                throw new InvalidArgumentException('Part mode requires an explicit part_number.');
            }
            if ($this->partNumber < 1 || $this->partNumber > 7) {
                throw new InvalidArgumentException("Invalid TOEIC part number [{$this->partNumber}]. Must be between 1 and 7.");
            }

            $canonicalPartMax = ToeicQuestionValidator::getPartTargetQuestionCount($this->partNumber);
            if ($this->itemCount !== null) {
                if ($this->itemCount <= 0) {
                    throw new InvalidArgumentException("item_count must be positive, got [{$this->itemCount}].");
                }
                if ($this->itemCount > $canonicalPartMax) {
                    throw new InvalidArgumentException("item_count [{$this->itemCount}] exceeds canonical Part {$this->partNumber} limit of {$canonicalPartMax} questions.");
                }

                // Group-safe check for Part 3 & 4
                if (in_array($this->partNumber, [3, 4], true)) {
                    $audioGroupQCount = ToeicQuestionValidator::getAudioGroupQuestionCount();
                    if ($this->itemCount % $audioGroupQCount !== 0) {
                        throw new InvalidArgumentException("Part {$this->partNumber} item_count [{$this->itemCount}] must be a multiple of {$audioGroupQCount} (complete audio groups).");
                    }
                }

                // Group-safe check for Part 6
                if ($this->partNumber === 6) {
                    $p6GroupQCount = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();
                    if ($this->itemCount % $p6GroupQCount !== 0) {
                        throw new InvalidArgumentException("Part 6 item_count [{$this->itemCount}] must be a multiple of {$p6GroupQCount} (complete passage groups).");
                    }
                }
            }
        }

        // Validate Full Test Mode
        if ($this->mode === self::MODE_FULL_TEST) {
            if ($this->partNumber !== null) {
                throw new InvalidArgumentException('Full test mode must not specify a part_number.');
            }
            $fullTarget = ToeicQuestionValidator::getTotalCanonicalTargetCount();
            if ($this->itemCount !== null && $this->itemCount !== $fullTarget) {
                throw new InvalidArgumentException("Full test mode item_count must be exactly {$fullTarget}, got [{$this->itemCount}].");
            }
        }

        // Validate Custom Mode
        if ($this->mode === self::MODE_CUSTOM) {
            $maxCanonical = ToeicQuestionValidator::getTotalCanonicalTargetCount();
            if (!empty($this->customParts)) {
                $totalCustom = array_sum($this->customParts);
                if ($totalCustom > $maxCanonical) {
                    throw new InvalidArgumentException("Custom mode total item count [{$totalCustom}] cannot exceed {$maxCanonical}.");
                }
            } elseif ($this->itemCount !== null && $this->itemCount > $maxCanonical) {
                throw new InvalidArgumentException("Custom mode item_count [{$this->itemCount}] cannot exceed {$maxCanonical}.");
            }
        }

        // Content Mode & Domain Validation
        if ($this->contentMode === ContentMode::General && $this->domain !== DomainTaxonomy::GeneralWorkplace) {
            throw new InvalidArgumentException('General content mode requires domain general_workplace.');
        }

        if ($this->contentMode === ContentMode::DomainSpecific && $this->domain === DomainTaxonomy::GeneralWorkplace) {
            throw new InvalidArgumentException('Domain-specific content mode requires an explicit domain other than general_workplace.');
        }

        // Proficiency Distribution Validation
        $profSum = array_sum($this->proficiencyDistribution);
        if (abs($profSum - 100.0) > 0.0001) {
            throw new InvalidArgumentException("Proficiency distribution percentages must sum to exactly 100. Sum: {$profSum}.");
        }

        // Difficulty Distribution Validation
        $diffSum = array_sum($this->difficultyDistribution);
        if (abs($diffSum - 100.0) > 0.0001) {
            throw new InvalidArgumentException("Difficulty distribution percentages must sum to exactly 100. Sum: {$diffSum}.");
        }

        // Construct Distribution Validation
        if ($this->constructDistribution !== null) {
            foreach ($this->constructDistribution as $pKey => $constructs) {
                if (is_numeric($pKey) && is_array($constructs)) {
                    $pNum = (int) $pKey;
                    if ($pNum < 1 || $pNum > 7) {
                        throw new InvalidArgumentException("Invalid part number in construct distribution: [{$pNum}].");
                    }
                    $cSum = array_sum($constructs);
                    if (abs($cSum - 100.0) > 0.0001) {
                        throw new InvalidArgumentException("Construct distribution for Part {$pNum} must sum to 100. Sum: {$cSum}.");
                    }
                    foreach ($constructs as $cKey => $cWeight) {
                        $construct = ConstructTaxonomy::tryFrom((string) $cKey);
                        if ($construct === null || !PartConstructCompatibility::isCompatible($pNum, $construct)) {
                            throw new InvalidArgumentException("Construct [{$cKey}] is incompatible with Part {$pNum}.");
                        }
                    }
                }
            }
        }
    }

    /**
     * Normalized array representation used for deterministic hashing.
     *
     * @return array<string, mixed>
     */
    public function normalizedArray(): array
    {
        $data = [
            'mode' => $this->mode,
            'part_number' => $this->partNumber,
            'item_count' => $this->itemCount,
            'content_mode' => $this->contentMode->value,
            'domain' => $this->domain->value,
            'proficiency_distribution' => $this->proficiencyDistribution,
            'difficulty_distribution' => $this->difficultyDistribution,
            'construct_distribution' => $this->constructDistribution,
            'context_distribution' => $this->contextDistribution,
            'seed' => $this->seed,
            'custom_parts' => $this->customParts,
        ];

        ksort($data);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'part_number' => $this->partNumber,
            'item_count' => $this->itemCount,
            'content_mode' => $this->contentMode->value,
            'domain' => $this->domain->value,
            'proficiency_distribution' => $this->proficiencyDistribution,
            'difficulty_distribution' => $this->difficultyDistribution,
            'construct_distribution' => $this->constructDistribution,
            'context_distribution' => $this->contextDistribution,
            'seed' => $this->seed,
            'standard_id' => $this->standardId,
            'standard_version' => $this->standardVersion,
            'custom_parts' => $this->customParts,
            'metadata' => $this->metadata,
        ];
    }
}
