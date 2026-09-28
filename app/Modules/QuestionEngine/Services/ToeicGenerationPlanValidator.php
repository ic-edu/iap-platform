<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\DTO\ToeicGenerationGroup;
use App\Modules\QuestionEngine\DTO\ToeicGenerationPlan;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ContentMode;
use App\Modules\QuestionEngine\Enums\DomainTaxonomy;
use App\Services\ToeicQuestionValidator;
use InvalidArgumentException;

class ToeicGenerationPlanValidator
{
    /**
     * Validate a ToeicGenerationPlan and throw InvalidArgumentException if invalid.
     *
     * @throws InvalidArgumentException
     */
    public function validate(ToeicGenerationPlan $plan): void
    {
        $result = $this->check($plan);

        if (!$result['is_valid']) {
            $errorMessage = implode('; ', $result['errors']);
            throw new InvalidArgumentException("TOEIC Generation Plan validation failed: {$errorMessage}");
        }
    }

    /**
     * Check a ToeicGenerationPlan and return structured errors.
     *
     * @return array{is_valid: bool, errors: list<string>}
     */
    public function check(ToeicGenerationPlan $plan): array
    {
        $errors = [];

        // 1. Assessment Family
        if ($plan->assessmentFamily !== AssessmentFamily::Toeic) {
            $errors[] = "Expected assessment family TOEIC, got [{$plan->assessmentFamily->value}].";
        }

        // 2. Standard Binding
        if (empty($plan->assessmentStandardId)) {
            $errors[] = 'Assessment standard ID must not be empty.';
        }
        if (empty($plan->standardVersion)) {
            $errors[] = 'Standard version must not be empty.';
        }

        // 3. Slot Counts Integrity
        $actualSlotCount = count($plan->slots);
        if ($actualSlotCount !== $plan->totalSlots) {
            $errors[] = "Slot count mismatch: plan declares {$plan->totalSlots} slots, but contains {$actualSlotCount} slots.";
        }

        // 4. Sequence Integrity
        $seenSequences = [];
        $seenQNumbers = [];
        foreach ($plan->slots as $idx => $slot) {
            $expectedSeq = $idx + 1;
            if ($slot->sequence !== $expectedSeq) {
                $errors[] = "Slot at index {$idx} has sequence {$slot->sequence}, expected {$expectedSeq}.";
            }
            if (isset($seenSequences[$slot->sequence])) {
                $errors[] = "Duplicate slot sequence {$slot->sequence} found.";
            }
            $seenSequences[$slot->sequence] = true;

            if ($slot->canonicalQuestionNumber !== null) {
                if (isset($seenQNumbers[$slot->canonicalQuestionNumber])) {
                    $errors[] = "Duplicate canonical question number {$slot->canonicalQuestionNumber} found.";
                }
                $seenQNumbers[$slot->canonicalQuestionNumber] = true;
            }

            // Construct Compatibility Check
            if (!PartConstructCompatibility::isCompatible($slot->partNumber, $slot->construct)) {
                $errors[] = "Slot sequence {$slot->sequence} in Part {$slot->partNumber} has incompatible construct [{$slot->construct->value}].";
            }

            // Domain / Content Mode Check
            if ($slot->contentMode === ContentMode::General && $slot->domain !== DomainTaxonomy::GeneralWorkplace) {
                $errors[] = "Slot sequence {$slot->sequence} has general content mode but domain is [{$slot->domain->value}].";
            }
            if ($slot->contentMode === ContentMode::DomainSpecific && $slot->domain === DomainTaxonomy::GeneralWorkplace) {
                $errors[] = "Slot sequence {$slot->sequence} has domain-specific mode but domain is general_workplace.";
            }
        }

        // 5. Full Test Specific Rules
        if ($plan->mode === 'full_test') {
            if ($plan->totalSlots !== 200) {
                $errors[] = "Full test mode must have exactly 200 slots, got {$plan->totalSlots}.";
            }

            $listeningCount = $plan->sectionCounts['listening'] ?? 0;
            $readingCount = $plan->sectionCounts['reading'] ?? 0;
            if ($listeningCount !== 100) {
                $errors[] = "Listening section must contain exactly 100 slots, got {$listeningCount}.";
            }
            if ($readingCount !== 100) {
                $errors[] = "Reading section must contain exactly 100 slots, got {$readingCount}.";
            }

            $expectedPartCounts = [
                1 => 6,
                2 => 25,
                3 => 39,
                4 => 30,
                5 => 30,
                6 => 16,
                7 => 54,
            ];

            foreach ($expectedPartCounts as $pNum => $expCount) {
                $actCount = $plan->partCounts[$pNum] ?? 0;
                if ($actCount !== $expCount) {
                    $errors[] = "Part {$pNum} must have {$expCount} slots, got {$actCount}.";
                }
            }

            // Verify canonical question numbers Q1 to Q200
            for ($q = 1; $q <= 200; $q++) {
                if (!isset($seenQNumbers[$q])) {
                    $errors[] = "Missing canonical question number Q{$q} in full test.";
                }
            }

            // Part Question Number Ranges
            $partRanges = [
                1 => ['start' => 1, 'end' => 6],
                2 => ['start' => 7, 'end' => 31],
                3 => ['start' => 32, 'end' => 70],
                4 => ['start' => 71, 'end' => 100],
                5 => ['start' => 101, 'end' => 130],
                6 => ['start' => 131, 'end' => 146],
                7 => ['start' => 147, 'end' => 200],
            ];

            foreach ($plan->slots as $slot) {
                $range = $partRanges[$slot->partNumber] ?? null;
                if ($range && ($slot->canonicalQuestionNumber < $range['start'] || $slot->canonicalQuestionNumber > $range['end'])) {
                    $errors[] = "Slot sequence {$slot->sequence} in Part {$slot->partNumber} has Q{$slot->canonicalQuestionNumber} outside range Q{$range['start']}–Q{$range['end']}.";
                }
            }

            // Verify Groups for Full Test
            $p3Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 3));
            if (count($p3Groups) !== 13) {
                $errors[] = 'Part 3 must have exactly 13 audio groups, found '.count($p3Groups).'.';
            }
            foreach ($p3Groups as $g) {
                if ($g->questionCount !== 3) {
                    $errors[] = "Part 3 Group {$g->groupIndex} must contain 3 questions, found {$g->questionCount}.";
                }
                if ($g->groupType !== 'conversation') {
                    $errors[] = "Part 3 Group {$g->groupIndex} group_type must be conversation, got [{$g->groupType}].";
                }
            }

            $p4Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 4));
            if (count($p4Groups) !== 10) {
                $errors[] = 'Part 4 must have exactly 10 audio groups, found '.count($p4Groups).'.';
            }
            foreach ($p4Groups as $g) {
                if ($g->questionCount !== 3) {
                    $errors[] = "Part 4 Group {$g->groupIndex} must contain 3 questions, found {$g->questionCount}.";
                }
                if ($g->groupType !== 'talk') {
                    $errors[] = "Part 4 Group {$g->groupIndex} group_type must be talk, got [{$g->groupType}].";
                }
            }

            $p6Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 6));
            if (count($p6Groups) !== 4) {
                $errors[] = 'Part 6 must have exactly 4 passage groups, found '.count($p6Groups).'.';
            }
            foreach ($p6Groups as $g) {
                if ($g->questionCount !== 4) {
                    $errors[] = "Part 6 Group {$g->groupIndex} must contain 4 questions, found {$g->questionCount}.";
                }
                if ($g->documentCount !== 1) {
                    $errors[] = "Part 6 Group {$g->groupIndex} must have document_count 1, got {$g->documentCount}.";
                }
            }

            $p7Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 7));
            if (count($p7Groups) !== 15) {
                $errors[] = 'Part 7 must have exactly 15 passage groups, found '.count($p7Groups).'.';
            }

            $p7Single = array_values(array_filter($p7Groups, fn (ToeicGenerationGroup $g) => $g->groupType === 'single'));
            $p7Double = array_values(array_filter($p7Groups, fn (ToeicGenerationGroup $g) => $g->groupType === 'double'));
            $p7Triple = array_values(array_filter($p7Groups, fn (ToeicGenerationGroup $g) => $g->groupType === 'triple'));

            if (count($p7Single) !== 10) {
                $errors[] = 'Part 7 must have 10 single passage groups, found '.count($p7Single).'.';
            }
            $p7SingleQCount = array_sum(array_map(fn (ToeicGenerationGroup $g) => $g->questionCount, $p7Single));
            if ($p7SingleQCount !== 29) {
                $errors[] = "Part 7 Single passage groups must have 29 total questions, got {$p7SingleQCount}.";
            }
            foreach ($p7Single as $g) {
                if ($g->documentCount !== 1) {
                    $errors[] = "Part 7 Single Group {$g->groupIndex} must have 1 document, got {$g->documentCount}.";
                }
                if ($g->questionCount < 2 || $g->questionCount > 4) {
                    $errors[] = "Part 7 Single Group {$g->groupIndex} must have 2..4 questions, got {$g->questionCount}.";
                }
            }

            if (count($p7Double) !== 2) {
                $errors[] = 'Part 7 must have 2 double passage groups, found '.count($p7Double).'.';
            }
            $p7DoubleQCount = array_sum(array_map(fn (ToeicGenerationGroup $g) => $g->questionCount, $p7Double));
            if ($p7DoubleQCount !== 10) {
                $errors[] = "Part 7 Double passage groups must have 10 total questions, got {$p7DoubleQCount}.";
            }
            foreach ($p7Double as $g) {
                if ($g->documentCount !== 2) {
                    $errors[] = "Part 7 Double Group {$g->groupIndex} must have 2 documents, got {$g->documentCount}.";
                }
                if ($g->questionCount !== 5) {
                    $errors[] = "Part 7 Double Group {$g->groupIndex} must have 5 questions, got {$g->questionCount}.";
                }
            }

            if (count($p7Triple) !== 3) {
                $errors[] = 'Part 7 must have 3 triple passage groups, found '.count($p7Triple).'.';
            }
            $p7TripleQCount = array_sum(array_map(fn (ToeicGenerationGroup $g) => $g->questionCount, $p7Triple));
            if ($p7TripleQCount !== 15) {
                $errors[] = "Part 7 Triple passage groups must have 15 total questions, got {$p7TripleQCount}.";
            }
            foreach ($p7Triple as $g) {
                if ($g->documentCount !== 3) {
                    $errors[] = "Part 7 Triple Group {$g->groupIndex} must have 3 documents, got {$g->documentCount}.";
                }
                if ($g->questionCount !== 5) {
                    $errors[] = "Part 7 Triple Group {$g->groupIndex} must have 5 questions, got {$g->questionCount}.";
                }
            }
        }

        // 6. Part Mode Specific Rules
        if ($plan->mode === 'part') {
            $part = $plan->request->partNumber;
            $maxCount = ToeicQuestionValidator::getPartTargetQuestionCount($part);
            if ($plan->totalSlots > $maxCount) {
                $errors[] = "Part mode total slots {$plan->totalSlots} exceeds canonical Part {$part} limit of {$maxCount}.";
            }
            foreach ($plan->slots as $slot) {
                if ($slot->partNumber !== $part) {
                    $errors[] = "Slot in part mode has part {$slot->partNumber}, expected {$part}.";
                }
            }
        }

        // 7. Group Cross-Referencing
        $groupIndexMap = [];
        foreach ($plan->groups as $group) {
            $groupIndexMap[$group->partNumber][$group->groupIndex] = $group;
            if (count($group->slotSequences) !== $group->questionCount) {
                $errors[] = "Group {$group->groupIndex} in Part {$group->partNumber} declares questionCount {$group->questionCount} but has ".count($group->slotSequences).' slot sequences.';
            }
        }

        foreach ($plan->slots as $slot) {
            if (in_array($slot->partNumber, [3, 4, 6, 7], true)) {
                if ($slot->groupIndex === null) {
                    $errors[] = "Slot sequence {$slot->sequence} in Part {$slot->partNumber} is missing group_index.";
                } else {
                    $group = $groupIndexMap[$slot->partNumber][$slot->groupIndex] ?? null;
                    if ($group === null) {
                        $errors[] = "Slot sequence {$slot->sequence} references nonexistent group {$slot->groupIndex} in Part {$slot->partNumber}.";
                    } elseif (!in_array($slot->sequence, $group->slotSequences, true)) {
                        $errors[] = "Slot sequence {$slot->sequence} not listed in group {$slot->groupIndex} slotSequences.";
                    }
                }
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
