<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use InvalidArgumentException;

final class ToeflBlueprintRequest
{
    public const MODE_FULL_TEST = 'full_test';

    public const MODE_SECTION = 'section';

    public const MODE_TASK = 'task';

    public const MODE_CUSTOM = 'custom';

    public const ALLOWED_MODES = [
        self::MODE_FULL_TEST,
        self::MODE_SECTION,
        self::MODE_TASK,
        self::MODE_CUSTOM,
    ];

    public const ALLOWED_SECTIONS = [
        'reading',
        'listening',
        'writing',
        'speaking',
    ];

    /**
     * @param  string  $mode  'full_test' | 'section' | 'task' | 'custom'
     * @param  list<string>|null  $selectedSections  Optional list of sections for multi-section planning
     * @param  string|null  $section  Required for 'section' mode ('reading', 'listening', 'writing', 'speaking')
     * @param  ToeflTaskType|null  $taskType  Required for 'task' mode
     * @param  int|null  $taskCount  Item count for 'task' mode
     * @param  array<string, int>  $customTasks  Normalized tasks specification for custom mode [task_type_value => count]
     * @param  array<string, int|float>  $proficiencyDistribution  Proficiency target distribution (sum = 100)
     * @param  array<string, int|float>  $difficultyDistribution  Difficulty level distribution (sum = 100)
     * @param  array<string, array<string, int|float>>|null  $skillDistribution  Per-task skill distribution [task_type => [skill => weight]]
     * @param  array<string, array<string, int|float>>|null  $languageContextDistribution  Per-task context distribution [task_type => [context => weight]]
     * @param  string  $itemScoringCategory  'unspecified' | 'scored' | 'pretest'
     * @param  int|null  $seed  Deterministic seed
     * @param  string|null  $standardId  Optional explicit standard ID assertion
     * @param  string|null  $standardVersion  Optional explicit standard version assertion
     * @param  bool  $allowPracticeCounts  Whether practice-mode counts below official ranges are permitted
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $mode = self::MODE_FULL_TEST,
        public readonly ?array $selectedSections = null,
        public readonly ?string $section = null,
        public readonly ?ToeflTaskType $taskType = null,
        public readonly ?int $taskCount = null,
        public readonly array $customTasks = [],
        public readonly array $proficiencyDistribution = [
            'b1_low' => 20,
            'b1_standard' => 30,
            'b2_low' => 25,
            'b2_standard' => 15,
            'c1' => 10,
        ],
        public readonly array $difficultyDistribution = [
            'easy' => 30,
            'medium' => 50,
            'hard' => 20,
        ],
        public readonly ?array $skillDistribution = null,
        public readonly ?array $languageContextDistribution = null,
        public readonly string $itemScoringCategory = 'unspecified',
        public readonly ?int $seed = null,
        public readonly ?string $standardId = null,
        public readonly ?string $standardVersion = null,
        public readonly bool $allowPracticeCounts = false,
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
        // Disallow part_number on TOEFL requests
        if (isset($data['part_number']) && $data['part_number'] !== null) {
            throw new InvalidArgumentException('TOEFL iBT does not use part_number. Use section or task_type.');
        }

        if (isset($data['custom_parts']) && !empty($data['custom_parts'])) {
            throw new InvalidArgumentException('TOEFL iBT does not use custom_parts. Use custom_tasks.');
        }

        $mode = strtolower(trim((string) ($data['mode'] ?? self::MODE_FULL_TEST)));

        // Normalize section
        $section = null;
        if (isset($data['section']) && $data['section'] !== null && $data['section'] !== '') {
            $section = strtolower(trim((string) $data['section']));
            if (!in_array($section, self::ALLOWED_SECTIONS, true)) {
                throw new InvalidArgumentException("Invalid TOEFL section '{$data['section']}'. Allowed sections: ".implode(', ', self::ALLOWED_SECTIONS));
            }
        }

        // Normalize selected_sections
        $selectedSections = null;
        if (isset($data['selected_sections']) && is_array($data['selected_sections'])) {
            $selectedSections = [];
            foreach ($data['selected_sections'] as $sec) {
                $secNorm = strtolower(trim((string) $sec));
                if (!in_array($secNorm, self::ALLOWED_SECTIONS, true)) {
                    throw new InvalidArgumentException("Invalid TOEFL section '{$sec}' in selected_sections.");
                }
                $selectedSections[] = $secNorm;
            }
        }

        // Normalize task_type
        $taskType = null;
        if (isset($data['task_type']) && $data['task_type'] !== null && $data['task_type'] !== '') {
            $taskType = $data['task_type'] instanceof ToeflTaskType
                ? $data['task_type']
                : ToeflTaskType::tryFrom(trim((string) $data['task_type']));

            if ($taskType === null) {
                throw new InvalidArgumentException("Invalid TOEFL task type '{$data['task_type']}'.");
            }
        }

        // Normalize task_count / item_count (Strict positive integer)
        $rawCount = $data['task_count'] ?? ($data['item_count'] ?? null);
        $taskCount = null;
        if ($rawCount !== null && $rawCount !== '') {
            $taskCount = self::parseStrictPositiveInteger($rawCount, 'task_count');
        }

        // Normalize custom_tasks
        $rawCustomTasks = $data['custom_tasks'] ?? ($data['tasks'] ?? []);
        $customTasks = self::normalizeCustomTasks($rawCustomTasks);

        // Normalize proficiency distribution
        $rawProf = $data['proficiency_distribution'] ?? [
            'b1_low' => 20,
            'b1_standard' => 30,
            'b2_low' => 25,
            'b2_standard' => 15,
            'c1' => 10,
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

        // Normalize difficulty distribution
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

        // Normalize skill distribution
        $skillDistribution = null;
        if (!empty($data['skill_distribution']) && is_array($data['skill_distribution'])) {
            $skillDistribution = [];
            foreach ($data['skill_distribution'] as $taskKey => $skillMap) {
                $tType = $taskKey instanceof ToeflTaskType ? $taskKey : ToeflTaskType::tryFrom((string) $taskKey);
                if ($tType === null) {
                    throw new InvalidArgumentException("Invalid task type '{$taskKey}' in skill_distribution.");
                }
                if (!is_array($skillMap)) {
                    throw new InvalidArgumentException("Skill distribution for task '{$tType->value}' must be an array.");
                }

                $skillDistribution[$tType->value] = [];
                foreach ($skillMap as $sKey => $sWeight) {
                    $sEnum = $sKey instanceof ToeflSkill ? $sKey : ToeflSkill::tryFrom((string) $sKey);
                    if ($sEnum === null) {
                        throw new InvalidArgumentException("Invalid skill '{$sKey}' in skill_distribution for task '{$tType->value}'.");
                    }
                    if (!in_array($sEnum, $tType->skills(), true)) {
                        throw new InvalidArgumentException("Skill '{$sEnum->value}' is incompatible with task '{$tType->value}'.");
                    }
                    $skillDistribution[$tType->value][$sEnum->value] = is_numeric($sWeight) ? (float) $sWeight : $sWeight;
                }
            }
        }

        // Normalize language context distribution
        $languageContextDistribution = null;
        if (!empty($data['language_context_distribution']) && is_array($data['language_context_distribution'])) {
            $languageContextDistribution = [];
            foreach ($data['language_context_distribution'] as $taskKey => $ctxMap) {
                $tType = $taskKey instanceof ToeflTaskType ? $taskKey : ToeflTaskType::tryFrom((string) $taskKey);
                if ($tType === null) {
                    throw new InvalidArgumentException("Invalid task type '{$taskKey}' in language_context_distribution.");
                }
                if (!is_array($ctxMap)) {
                    throw new InvalidArgumentException("Context distribution for task '{$tType->value}' must be an array.");
                }

                $languageContextDistribution[$tType->value] = [];
                foreach ($ctxMap as $cKey => $cWeight) {
                    $cEnum = $cKey instanceof ToeflLanguageUseContext ? $cKey : ToeflLanguageUseContext::tryFrom((string) $cKey);
                    if ($cEnum === null) {
                        throw new InvalidArgumentException("Invalid context '{$cKey}' in language_context_distribution for task '{$tType->value}'.");
                    }
                    if (!in_array($cEnum, $tType->allowedLanguageUseContexts(), true)) {
                        throw new InvalidArgumentException("Context '{$cEnum->value}' is not allowed for task '{$tType->value}'.");
                    }
                    $languageContextDistribution[$tType->value][$cEnum->value] = is_numeric($cWeight) ? (float) $cWeight : $cWeight;
                }
            }
        }

        // Normalize item_scoring_category
        $itemScoringCategory = 'unspecified';
        if (isset($data['item_scoring_category']) && $data['item_scoring_category'] !== null && $data['item_scoring_category'] !== '') {
            $cat = strtolower(trim((string) $data['item_scoring_category']));
            if (!in_array($cat, ['unspecified', 'scored', 'pretest'], true)) {
                throw new InvalidArgumentException("Invalid item_scoring_category '{$data['item_scoring_category']}'. Allowed: unspecified, scored, pretest.");
            }
            $itemScoringCategory = $cat;
        }

        // Normalize seed
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
        $allowPracticeCounts = (bool) ($data['allow_practice_counts'] ?? false);
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

        return new self(
            mode: $mode,
            selectedSections: $selectedSections,
            section: $section,
            taskType: $taskType,
            taskCount: $taskCount,
            customTasks: $customTasks,
            proficiencyDistribution: $proficiencyDistribution,
            difficultyDistribution: $difficultyDistribution,
            skillDistribution: $skillDistribution,
            languageContextDistribution: $languageContextDistribution,
            itemScoringCategory: $itemScoringCategory,
            seed: $seed,
            standardId: $standardId,
            standardVersion: $standardVersion,
            allowPracticeCounts: $allowPracticeCounts,
            metadata: $metadata,
        );
    }

    /**
     * Parse strict positive integer without silent truncation or lossy casts.
     */
    public static function parseStrictPositiveInteger(mixed $value, string $context): int
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
     * Normalize custom tasks map.
     *
     * @param  array<mixed, mixed>  $customTasks
     * @return array<string, int>
     */
    private static function normalizeCustomTasks(array $customTasks): array
    {
        if (empty($customTasks)) {
            return [];
        }

        $normalized = [];
        foreach ($customTasks as $taskKey => $countVal) {
            $tType = $taskKey instanceof ToeflTaskType ? $taskKey : ToeflTaskType::tryFrom(trim((string) $taskKey));
            if ($tType === null) {
                throw new InvalidArgumentException("Invalid TOEFL task type '{$taskKey}' in custom tasks.");
            }

            $count = self::parseStrictPositiveInteger($countVal, "custom task count for '{$tType->value}'");
            $normalized[$tType->value] = $count;
        }

        return $normalized;
    }

    /**
     * Validate request invariants.
     */
    private function validate(): void
    {
        if (!in_array($this->mode, self::ALLOWED_MODES, true)) {
            throw new InvalidArgumentException("Invalid mode [{$this->mode}]. Allowed modes: ".implode(', ', self::ALLOWED_MODES));
        }

        // Validate Section Mode
        if ($this->mode === self::MODE_SECTION) {
            if ($this->section === null) {
                throw new InvalidArgumentException('Section mode requires an explicit section parameter.');
            }
            if (!in_array($this->section, self::ALLOWED_SECTIONS, true)) {
                throw new InvalidArgumentException("Invalid section '{$this->section}'. Allowed: ".implode(', ', self::ALLOWED_SECTIONS));
            }
        }

        // Validate Task Mode
        if ($this->mode === self::MODE_TASK) {
            if ($this->taskType === null) {
                throw new InvalidArgumentException('Task mode requires an explicit task_type parameter.');
            }

            $fixed = $this->taskType->itemCountFixed();
            $range = $this->taskType->itemCountRange();

            if ($this->taskCount !== null) {
                if ($this->taskCount <= 0) {
                    throw new InvalidArgumentException("task_count must be positive, got [{$this->taskCount}].");
                }

                if (!$this->allowPracticeCounts) {
                    if ($fixed !== null && $this->taskCount > $fixed) {
                        throw new InvalidArgumentException("Task count [{$this->taskCount}] exceeds canonical fixed count of {$fixed} for task '{$this->taskType->value}'.");
                    }
                    if ($range !== null) {
                        if ($this->taskCount < $range['min'] || $this->taskCount > $range['max']) {
                            throw new InvalidArgumentException("Task count [{$this->taskCount}] is outside canonical official range of [{$range['min']}-{$range['max']}] for task '{$this->taskType->value}'.");
                        }
                    }
                }
            }
        }

        // Validate Custom Mode
        if ($this->mode === self::MODE_CUSTOM) {
            if (empty($this->customTasks)) {
                throw new InvalidArgumentException('Custom mode requires custom_tasks to be non-empty.');
            }

            if (!$this->allowPracticeCounts) {
                foreach ($this->customTasks as $taskVal => $cnt) {
                    $tType = ToeflTaskType::from($taskVal);
                    $range = $tType->itemCountRange();
                    if ($range !== null) {
                        if ($cnt < $range['min'] || $cnt > $range['max']) {
                            throw new InvalidArgumentException("Custom count [{$cnt}] is outside canonical official range of [{$range['min']}-{$range['max']}] for task '{$taskVal}'.");
                        }
                    }
                }
            }
        }

        // Validate Proficiency Distribution
        $profSum = array_sum($this->proficiencyDistribution);
        if (abs($profSum - 100.0) > 0.0001) {
            throw new InvalidArgumentException("Proficiency distribution percentages must sum to exactly 100. Sum: {$profSum}.");
        }

        // Validate Difficulty Distribution
        $diffSum = array_sum($this->difficultyDistribution);
        if (abs($diffSum - 100.0) > 0.0001) {
            throw new InvalidArgumentException("Difficulty distribution percentages must sum to exactly 100. Sum: {$diffSum}.");
        }

        // Validate Skill Distribution
        if ($this->skillDistribution !== null) {
            foreach ($this->skillDistribution as $taskKey => $skillMap) {
                $tType = ToeflTaskType::tryFrom($taskKey);
                if ($tType === null) {
                    throw new InvalidArgumentException("Invalid task type '{$taskKey}' in skill distribution.");
                }
                $sSum = array_sum($skillMap);
                if (abs($sSum - 100.0) > 0.0001) {
                    throw new InvalidArgumentException("Skill distribution for task '{$taskKey}' must sum to 100. Sum: {$sSum}.");
                }
                foreach ($skillMap as $sKey => $sWeight) {
                    $sEnum = ToeflSkill::tryFrom($sKey);
                    if ($sEnum === null || !in_array($sEnum, $tType->skills(), true)) {
                        throw new InvalidArgumentException("Skill '{$sKey}' is incompatible with task '{$taskKey}'.");
                    }
                }
            }
        }

        // Validate Context Distribution
        if ($this->languageContextDistribution !== null) {
            foreach ($this->languageContextDistribution as $taskKey => $ctxMap) {
                $tType = ToeflTaskType::tryFrom($taskKey);
                if ($tType === null) {
                    throw new InvalidArgumentException("Invalid task type '{$taskKey}' in context distribution.");
                }
                $cSum = array_sum($ctxMap);
                if (abs($cSum - 100.0) > 0.0001) {
                    throw new InvalidArgumentException("Language context distribution for task '{$taskKey}' must sum to 100. Sum: {$cSum}.");
                }
                foreach ($ctxMap as $cKey => $cWeight) {
                    $cEnum = ToeflLanguageUseContext::tryFrom($cKey);
                    if ($cEnum === null || !in_array($cEnum, $tType->allowedLanguageUseContexts(), true)) {
                        throw new InvalidArgumentException("Context '{$cKey}' is not allowed for task '{$taskKey}'.");
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
            'selected_sections' => $this->selectedSections,
            'section' => $this->section,
            'task_type' => $this->taskType?->value,
            'task_count' => $this->taskCount,
            'custom_tasks' => $this->customTasks,
            'proficiency_distribution' => $this->proficiencyDistribution,
            'difficulty_distribution' => $this->difficultyDistribution,
            'skill_distribution' => $this->skillDistribution,
            'language_context_distribution' => $this->languageContextDistribution,
            'item_scoring_category' => $this->itemScoringCategory,
            'allow_practice_counts' => $this->allowPracticeCounts,
            'seed' => $this->seed,
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
            'selected_sections' => $this->selectedSections,
            'section' => $this->section,
            'task_type' => $this->taskType?->value,
            'task_count' => $this->taskCount,
            'custom_tasks' => $this->customTasks,
            'proficiency_distribution' => $this->proficiencyDistribution,
            'difficulty_distribution' => $this->difficultyDistribution,
            'skill_distribution' => $this->skillDistribution,
            'language_context_distribution' => $this->languageContextDistribution,
            'item_scoring_category' => $this->itemScoringCategory,
            'seed' => $this->seed,
            'standard_id' => $this->standardId,
            'standard_version' => $this->standardVersion,
            'allow_practice_counts' => $this->allowPracticeCounts,
            'metadata' => $this->metadata,
        ];
    }
}
