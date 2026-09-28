<?php

namespace App\Modules\QuestionEngine\DTO;

use App\Modules\QuestionEngine\Enums\AssessmentFamily;

final class ToeicGenerationPlan
{
    /**
     * @param  string  $fingerprint  Deterministic hash of normalized request + standard + seed
     * @param  string  $mode  Plan mode ('full_test', 'part', 'custom')
     * @param  AssessmentFamily  $assessmentFamily  Assessment family (Toeic)
     * @param  string  $assessmentStandardId  Bound AssessmentStandard ID
     * @param  string  $standardVersion  Bound AssessmentStandard version
     * @param  int  $totalSlots  Total slot count
     * @param  array<string, int>  $sectionCounts  ['listening' => 100, 'reading' => 100]
     * @param  array<int, int>  $partCounts  [1 => 6, 2 => 25, 3 => 39, ...]
     * @param  array<string, int>  $difficultyCounts  ['easy' => 60, 'medium' => 100, 'hard' => 40]
     * @param  array<string, int>  $proficiencyCounts  ['b1_low' => 50, ...]
     * @param  array<int, array<string, int>>  $constructCounts  Per-part construct allocation counts
     * @param  list<ToeicGenerationSlot>  $slots  List of generation slots
     * @param  list<ToeicGenerationGroup>  $groups  List of generation groups
     * @param  ToeicBlueprintRequest  $request  Original blueprint request
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $mode,
        public readonly AssessmentFamily $assessmentFamily,
        public readonly string $assessmentStandardId,
        public readonly string $standardVersion,
        public readonly int $totalSlots,
        public readonly array $sectionCounts,
        public readonly array $partCounts,
        public readonly array $difficultyCounts,
        public readonly array $proficiencyCounts,
        public readonly array $constructCounts,
        public readonly array $slots,
        public readonly array $groups,
        public readonly ToeicBlueprintRequest $request,
        public readonly array $metadata = [],
    ) {}

    /**
     * Get a structured summary array of the generation plan.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $groupSummary = [];
        foreach ($this->groups as $group) {
            $type = $group->groupType;
            $groupSummary[$group->partNumber][$type] = ($groupSummary[$group->partNumber][$type] ?? 0) + 1;
        }

        return [
            'fingerprint' => $this->fingerprint,
            'standard' => "TOEIC-{$this->standardVersion}",
            'standard_id' => $this->assessmentStandardId,
            'mode' => $this->mode,
            'total_slots' => $this->totalSlots,
            'sections' => $this->sectionCounts,
            'parts' => $this->partCounts,
            'difficulty' => $this->difficultyCounts,
            'proficiency' => $this->proficiencyCounts,
            'groups' => $groupSummary,
            'group_count' => count($this->groups),
        ];
    }

    /**
     * Get a deterministic formatted textual summary of the generation plan.
     */
    public function getFormattedSummary(): string
    {
        $modeLabel = match ($this->mode) {
            'full_test' => 'Full Test',
            'part' => "Part {$this->request->partNumber}",
            'custom' => 'Custom',
            default => ucfirst($this->mode),
        };

        $lines = [];
        $lines[] = 'TOEIC Generation Plan';
        $lines[] = "Standard: TOEIC-{$this->standardVersion}";
        $lines[] = "Mode: {$modeLabel}";
        $lines[] = "Total: {$this->totalSlots}";
        $lines[] = '';

        if (isset($this->sectionCounts['listening']) && $this->sectionCounts['listening'] > 0) {
            $lines[] = 'Listening:';
            foreach ([1, 2, 3, 4] as $p) {
                if (isset($this->partCounts[$p])) {
                    $lines[] = "P{$p} {$this->partCounts[$p]}";
                }
            }
            $lines[] = '';
        }

        if (isset($this->sectionCounts['reading']) && $this->sectionCounts['reading'] > 0) {
            $lines[] = 'Reading:';
            foreach ([5, 6, 7] as $p) {
                if (isset($this->partCounts[$p])) {
                    $lines[] = "P{$p} {$this->partCounts[$p]}";
                }
            }
            $lines[] = '';
        }

        $lines[] = 'Difficulty:';
        foreach ($this->difficultyCounts as $diff => $count) {
            $lines[] = ucfirst($diff)." {$count}";
        }
        $lines[] = '';

        $lines[] = 'Proficiency:';
        foreach ($this->proficiencyCounts as $prof => $count) {
            $lines[] = strtoupper($prof)." {$count}";
        }
        $lines[] = '';

        if (!empty($this->groups)) {
            $lines[] = 'Groups:';
            $p3Groups = count(array_filter($this->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 3));
            $p4Groups = count(array_filter($this->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 4));
            $p6Groups = count(array_filter($this->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 6));
            $p7Single = count(array_filter($this->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 7 && $g->groupType === 'single'));
            $p7Double = count(array_filter($this->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 7 && $g->groupType === 'double'));
            $p7Triple = count(array_filter($this->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 7 && $g->groupType === 'triple'));
            $p7Total = $p7Single + $p7Double + $p7Triple;

            if ($p3Groups > 0) {
                $lines[] = "P3 conversations: {$p3Groups}";
            }
            if ($p4Groups > 0) {
                $lines[] = "P4 talks: {$p4Groups}";
            }
            if ($p6Groups > 0) {
                $lines[] = "P6 text completion: {$p6Groups}";
            }
            if ($p7Total > 0) {
                $lines[] = "P7 passage groups: {$p7Total} (Single: {$p7Single}, Double: {$p7Double}, Triple: {$p7Triple})";
            }
        }

        return trim(implode("\n", $lines));
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
            'total_slots' => $this->totalSlots,
            'section_counts' => $this->sectionCounts,
            'part_counts' => $this->partCounts,
            'difficulty_counts' => $this->difficultyCounts,
            'proficiency_counts' => $this->proficiencyCounts,
            'construct_counts' => $this->constructCounts,
            'slots' => array_map(fn (ToeicGenerationSlot $slot) => $slot->toArray(), $this->slots),
            'groups' => array_map(fn (ToeicGenerationGroup $group) => $group->toArray(), $this->groups),
            'request' => $this->request->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
