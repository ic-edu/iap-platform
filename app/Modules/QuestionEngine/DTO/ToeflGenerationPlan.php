<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionEngine\Enums\AssessmentFamily;

class ToeflGenerationPlan
{
    /**
     * @param  array<string, int>  $sectionCounts
     * @param  array<string, int>  $taskCounts
     * @param  array<string, int>  $claimCounts
     * @param  array<string, int>  $skillCounts
     * @param  array<string, int>  $responseModeCounts
     * @param  array<string, int>  $scoringModeCounts
     * @param  array<string, int>  $difficultyCounts
     * @param  array<string, int>  $proficiencyCounts
     * @param  list<ToeflGenerationSlot>  $slots
     * @param  list<ToeflAdaptiveModulePlan>  $adaptiveModules
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $fingerprint,
        public string $mode,
        public AssessmentFamily $assessmentFamily,
        public string $assessmentStandardId,
        public string $standardVersion,
        public string $plannerStrategyVersion,
        public int $totalSlots,
        public array $sectionCounts,
        public array $taskCounts,
        public array $claimCounts,
        public array $skillCounts,
        public array $responseModeCounts,
        public array $scoringModeCounts,
        public array $difficultyCounts,
        public array $proficiencyCounts,
        public array $slots,
        public array $adaptiveModules,
        public ToeflBlueprintRequest $request,
        public array $metadata = []
    ) {}

    /**
     * Get structured array summary of the generation plan.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'mode' => $this->mode,
            'assessment_family' => $this->assessmentFamily->value,
            'assessment_standard_id' => $this->assessmentStandardId,
            'standard_version' => $this->standardVersion,
            'planner_strategy_version' => $this->plannerStrategyVersion,
            'total_slots' => $this->totalSlots,
            'section_counts' => $this->sectionCounts,
            'task_counts' => $this->taskCounts,
            'claim_counts' => $this->claimCounts,
            'skill_counts' => $this->skillCounts,
            'response_mode_counts' => $this->responseModeCounts,
            'scoring_mode_counts' => $this->scoringModeCounts,
            'difficulty_counts' => $this->difficultyCounts,
            'proficiency_counts' => $this->proficiencyCounts,
            'adaptive_module_count' => count($this->adaptiveModules),
        ];
    }

    /**
     * Get clean, formatted human-readable summary.
     */
    public function getFormattedSummary(): string
    {
        $lines = [];
        $lines[] = '========================================';
        $lines[] = 'TOEFL iBT GENERATION BLUEPRINT PLAN';
        $lines[] = '========================================';
        $lines[] = "Standard Version: {$this->standardVersion} (ID: {$this->assessmentStandardId})";
        $lines[] = "Planning Mode: {$this->mode}";
        $lines[] = "Planner Strategy: {$this->plannerStrategyVersion}";
        $lines[] = "Total Generation Slots: {$this->totalSlots}";
        $lines[] = "Plan Fingerprint: {$this->fingerprint}";
        $lines[] = '----------------------------------------';
        $lines[] = 'SECTIONS & TASK ALLOCATIONS:';

        foreach ($this->sectionCounts as $sec => $cnt) {
            $isAdaptive = in_array($sec, ['reading', 'listening'], true);
            $modeLabel = $isAdaptive ? 'Two-Stage Adaptive (IAP pool)' : 'Linear Fixed';
            $lines[] = '  * Section: '.strtoupper($sec)." [{$cnt} slots - {$modeLabel}]";

            foreach ($this->taskCounts as $task => $tCnt) {
                if ($this->getSectionForTask($task) === $sec) {
                    $lines[] = "      - {$task}: {$tCnt}";
                }
            }
        }

        if (!empty($this->adaptiveModules)) {
            $lines[] = '----------------------------------------';
            $lines[] = 'ADAPTIVE MODULE PLACEHOLDERS:';
            foreach ($this->adaptiveModules as $mod) {
                $lines[] = "  * [{$mod->section}] Stage {$mod->stage} ({$mod->moduleRole}): ".count($mod->slotSequences)." slots (Rule: {$mod->routingRule})";
            }
        }

        $lines[] = '----------------------------------------';
        $lines[] = 'PROVENANCE DISTINCTION:';
        $lines[] = '  * OFFICIAL ETS: Section, Task Type, Claim, Skills, CEFR Bounds, Context, Response/Scoring Mode';
        $lines[] = '  * IAP DERIVED: Default Ranged-Task Allocations, Adaptive Module Routing Placeholders, Target Distributions';
        $lines[] = '========================================';

        return implode("\n", $lines);
    }

    private function getSectionForTask(string $taskType): ?string
    {
        return match ($taskType) {
            'complete_the_words', 'read_in_daily_life', 'read_an_academic_passage' => 'reading',
            'listen_and_choose_a_response', 'listen_to_a_conversation', 'listen_to_an_announcement', 'listen_to_an_academic_talk' => 'listening',
            'build_a_sentence', 'write_an_email', 'write_for_an_academic_discussion' => 'writing',
            'listen_and_repeat', 'take_an_interview' => 'speaking',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'mode' => $this->mode,
            'assessment_family' => $this->assessmentFamily->value,
            'assessment_standard_id' => $this->assessmentStandardId,
            'standard_version' => $this->standardVersion,
            'planner_strategy_version' => $this->plannerStrategyVersion,
            'total_slots' => $this->totalSlots,
            'section_counts' => $this->sectionCounts,
            'task_counts' => $this->taskCounts,
            'claim_counts' => $this->claimCounts,
            'skill_counts' => $this->skillCounts,
            'response_mode_counts' => $this->responseModeCounts,
            'scoring_mode_counts' => $this->scoringModeCounts,
            'difficulty_counts' => $this->difficultyCounts,
            'proficiency_counts' => $this->proficiencyCounts,
            'slots' => array_map(fn (ToeflGenerationSlot $s) => $s->toArray(), $this->slots),
            'adaptive_modules' => array_map(fn (ToeflAdaptiveModulePlan $m) => $m->toArray(), $this->adaptiveModules),
            'request' => $this->request->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
