<?php

namespace App\Modules\QuestionEngine\DTO;

class AssessmentCapabilities
{
    /**
     * @param  list<string>  $supportedSections
     */
    public function __construct(
        public bool $supportsParts = false,
        public bool $supportsTaskTypes = false,
        public bool $supportsClaims = false,
        public bool $supportsAdaptiveBlueprint = false,
        public bool $supportsFixedFullTestBlueprint = false,
        public bool $supportsDomainSpecificity = false,
        public bool $supportsCefrTargeting = false,
        public array $supportedSections = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supports_parts' => $this->supportsParts,
            'supports_task_types' => $this->supportsTaskTypes,
            'supports_claims' => $this->supportsClaims,
            'supports_adaptive_blueprint' => $this->supportsAdaptiveBlueprint,
            'supports_fixed_full_test_blueprint' => $this->supportsFixedFullTestBlueprint,
            'supports_domain_specificity' => $this->supportsDomainSpecificity,
            'supports_cefr_targeting' => $this->supportsCefrTargeting,
            'supported_sections' => $this->supportedSections,
        ];
    }
}
