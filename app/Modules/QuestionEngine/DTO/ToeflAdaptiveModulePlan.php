<?php

namespace App\Modules\QuestionEngine\DTO;

class ToeflAdaptiveModulePlan
{
    /**
     * @param  array<string, int>  $taskCounts
     * @param  list<int>  $slotSequences
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $section,
        public int $stage,
        public string $moduleRole,
        public array $taskCounts,
        public array $slotSequences,
        public string $routingRule = 'unspecified',
        public ?string $routingThreshold = null,
        public string $provenance = 'official_structure_plus_iap_planning',
        public array $metadata = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'section' => $this->section,
            'stage' => $this->stage,
            'module_role' => $this->moduleRole,
            'task_counts' => $this->taskCounts,
            'slot_sequences' => $this->slotSequences,
            'routing_rule' => $this->routingRule,
            'routing_threshold' => $this->routingThreshold,
            'provenance' => $this->provenance,
            'metadata' => $this->metadata,
        ];
    }
}
