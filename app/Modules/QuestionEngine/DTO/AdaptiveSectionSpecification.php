<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionEngine\Enums\AdaptiveType;

class AdaptiveSectionSpecification
{
    /**
     * @param  array<string, mixed>|null  $routerDefinition
     * @param  array<string, mixed>|null  $lowerModuleDefinition
     * @param  array<string, mixed>|null  $upperModuleDefinition
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $section,
        public bool $isAdaptive = true,
        public AdaptiveType $adaptiveType = AdaptiveType::TwoStage,
        public ?array $routerDefinition = null,
        public ?array $lowerModuleDefinition = null,
        public ?array $upperModuleDefinition = null,
        public bool $routingThresholdsKnown = false,
        public array $metadata = []
    ) {}

    /**
     * Build canonical representation for adaptive sections (Reading & Listening).
     */
    public static function forSection(string $section): self
    {
        $normalized = strtolower(trim($section));

        if (!in_array($normalized, ['reading', 'listening'], true)) {
            return new self(
                section: $normalized,
                isAdaptive: false,
                adaptiveType: AdaptiveType::Linear,
                routerDefinition: null,
                lowerModuleDefinition: null,
                upperModuleDefinition: null,
                routingThresholdsKnown: false,
                metadata: ['stage_model' => 'linear']
            );
        }

        return new self(
            section: $normalized,
            isAdaptive: true,
            adaptiveType: AdaptiveType::TwoStage,
            routerDefinition: [
                'stage' => 1,
                'role' => 'router',
                'description' => 'Stage 1 routing module across broad proficiency spectrum',
                'threshold_rule' => 'unspecified',
            ],
            lowerModuleDefinition: [
                'stage' => 2,
                'role' => 'lower_module',
                'description' => 'Stage 2 targeted module for foundational/intermediate proficiency',
            ],
            upperModuleDefinition: [
                'stage' => 2,
                'role' => 'upper_module',
                'description' => 'Stage 2 targeted module for advanced/high proficiency',
            ],
            routingThresholdsKnown: false,
            metadata: [
                'stage_model' => 'two_stage_mst',
                'operational_routing_rules' => 'proprietary_ets_unspecified',
                'routing_thresholds_note' => 'Operational cut scores and equating parameters are proprietary to ETS and preserved as unspecified.',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'section' => $this->section,
            'is_adaptive' => $this->isAdaptive,
            'adaptive_type' => $this->adaptiveType->value,
            'router_definition' => $this->routerDefinition,
            'lower_module_definition' => $this->lowerModuleDefinition,
            'upper_module_definition' => $this->upperModuleDefinition,
            'routing_thresholds_known' => $this->routingThresholdsKnown,
            'metadata' => $this->metadata,
        ];
    }
}
