<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\Enums\AdaptiveType;
use App\Modules\QuestionEngine\Enums\ToeflClaim;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use InvalidArgumentException;

class ToeflSectionSpecification
{
    /**
     * @param  list<ToeflLanguageUseContext>  $supportedLanguageUseContexts
     * @param  array<string, ToeflTaskSpecification>  $taskSpecifications
     * @param  array<string, mixed>  $scoringScale
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $section,
        public ToeflClaim $claim,
        public bool $isAdaptive,
        public AdaptiveType $adaptiveType,
        public ?int $totalItemsMin = null,
        public ?int $totalItemsMax = null,
        public ?int $fixedTotalItems = null,
        public array $supportedLanguageUseContexts = [],
        public array $taskSpecifications = [],
        public ?AdaptiveSectionSpecification $adaptiveSpecification = null,
        public array $scoringScale = [],
        public array $metadata = []
    ) {}

    /**
     * Generate canonical specification for a TOEFL section according to official ETS 2026 specs.
     */
    public static function forSection(string|SectionType $section): self
    {
        $normalized = $section instanceof SectionType
            ? strtolower($section->value)
            : strtolower(trim((string) $section));

        $tasks = ToeflTaskType::forSection($normalized);
        if (empty($tasks)) {
            throw new InvalidArgumentException("Unknown or unsupported TOEFL section '{$section}'.");
        }

        $taskSpecs = [];
        foreach ($tasks as $task) {
            $taskSpecs[$task->value] = ToeflTaskSpecification::forTaskType($task);
        }

        $claim = ToeflClaim::forSection($normalized);

        $commonScoring = [
            'score_range' => ['min' => 1.0, 'max' => 6.0],
            'increment' => 0.5,
            'scale_type' => 'ets_half_band',
            'transition_scale' => ['min' => 0, 'max' => 120, 'comparable' => true],
        ];

        return match ($normalized) {
            'reading' => new self(
                section: 'reading',
                claim: $claim,
                isAdaptive: true,
                adaptiveType: AdaptiveType::TwoStage,
                totalItemsMin: null,
                totalItemsMax: 50,
                fixedTotalItems: null,
                supportedLanguageUseContexts: [
                    ToeflLanguageUseContext::Academic,
                    ToeflLanguageUseContext::SocialInterpersonal,
                ],
                taskSpecifications: $taskSpecs,
                adaptiveSpecification: AdaptiveSectionSpecification::forSection('reading'),
                scoringScale: $commonScoring,
                metadata: [
                    'structure_mode' => 'two_stage_adaptive',
                    'includes_pretest' => true,
                    'official_maximum_target' => 50,
                ]
            ),
            'listening' => new self(
                section: 'listening',
                claim: $claim,
                isAdaptive: true,
                adaptiveType: AdaptiveType::TwoStage,
                totalItemsMin: null,
                totalItemsMax: 47,
                fixedTotalItems: null,
                supportedLanguageUseContexts: [
                    ToeflLanguageUseContext::Academic,
                    ToeflLanguageUseContext::AcademicNavigational,
                    ToeflLanguageUseContext::SocialInterpersonal,
                ],
                taskSpecifications: $taskSpecs,
                adaptiveSpecification: AdaptiveSectionSpecification::forSection('listening'),
                scoringScale: $commonScoring,
                metadata: [
                    'structure_mode' => 'two_stage_adaptive',
                    'includes_pretest' => true,
                    'official_maximum_target' => 47,
                ]
            ),
            'writing' => new self(
                section: 'writing',
                claim: $claim,
                isAdaptive: false,
                adaptiveType: AdaptiveType::Linear,
                totalItemsMin: 12,
                totalItemsMax: 12,
                fixedTotalItems: 12,
                supportedLanguageUseContexts: [
                    ToeflLanguageUseContext::Academic,
                    ToeflLanguageUseContext::AcademicNavigational,
                    ToeflLanguageUseContext::SocialInterpersonal,
                ],
                taskSpecifications: $taskSpecs,
                adaptiveSpecification: AdaptiveSectionSpecification::forSection('writing'),
                scoringScale: $commonScoring,
                metadata: [
                    'structure_mode' => 'linear',
                    'includes_pretest' => false,
                    'official_fixed_total' => 12,
                ]
            ),
            'speaking' => new self(
                section: 'speaking',
                claim: $claim,
                isAdaptive: false,
                adaptiveType: AdaptiveType::Linear,
                totalItemsMin: 11,
                totalItemsMax: 11,
                fixedTotalItems: 11,
                supportedLanguageUseContexts: [
                    ToeflLanguageUseContext::AcademicNavigational,
                ],
                taskSpecifications: $taskSpecs,
                adaptiveSpecification: AdaptiveSectionSpecification::forSection('speaking'),
                scoringScale: $commonScoring,
                metadata: [
                    'structure_mode' => 'linear',
                    'includes_pretest' => false,
                    'official_fixed_total' => 11,
                ]
            ),
            default => throw new InvalidArgumentException("Unknown or unsupported TOEFL section '{$section}'."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'section' => $this->section,
            'claim' => $this->claim->value,
            'is_adaptive' => $this->isAdaptive,
            'adaptive_type' => $this->adaptiveType->value,
            'total_items_min' => $this->totalItemsMin,
            'total_items_max' => $this->totalItemsMax,
            'fixed_total_items' => $this->fixedTotalItems,
            'supported_language_use_contexts' => array_map(fn (ToeflLanguageUseContext $ctx) => $ctx->value, $this->supportedLanguageUseContexts),
            'task_specifications' => array_map(fn (ToeflTaskSpecification $s) => $s->toArray(), $this->taskSpecifications),
            'adaptive_specification' => $this->adaptiveSpecification?->toArray(),
            'scoring_scale' => $this->scoringScale,
            'metadata' => $this->metadata,
        ];
    }
}
