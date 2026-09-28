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

        // 2. Standard Binding Consistency
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

        // 4. Per-Slot Invariants
        $seenSequences = [];
        $seenQNumbers = [];
        $actualPartCounts = [];
        $actualSectionCounts = ['listening' => 0, 'reading' => 0];

        foreach ($plan->slots as $idx => $slot) {
            $expectedSeq = $idx + 1;
            if ($slot->sequence !== $expectedSeq) {
                $errors[] = "Slot at index {$idx} has sequence {$slot->sequence}, expected {$expectedSeq}.";
            }
            if (isset($seenSequences[$slot->sequence])) {
                $errors[] = "Duplicate slot sequence {$slot->sequence} found.";
            }
            $seenSequences[$slot->sequence] = true;

            // Slot standard binding equals plan standard binding
            if ($slot->assessmentStandardId !== $plan->assessmentStandardId) {
                $errors[] = "Slot sequence {$slot->sequence} assessment_standard_id [{$slot->assessmentStandardId}] does not match plan standard [{$plan->assessmentStandardId}].";
            }
            if ($slot->standardVersion !== $plan->standardVersion) {
                $errors[] = "Slot sequence {$slot->sequence} standard_version [{$slot->standardVersion}] does not match plan version [{$plan->standardVersion}].";
            }

            // Part & Section validation from canonical validator
            $partBlueprint = ToeicQuestionValidator::getPartBlueprint($slot->partNumber);
            if ($partBlueprint === null) {
                $errors[] = "Slot sequence {$slot->sequence} has invalid part number [{$slot->partNumber}].";
            } else {
                if ($slot->section->value !== $partBlueprint['section']) {
                    $errors[] = "Slot sequence {$slot->sequence} in Part {$slot->partNumber} has section [{$slot->section->value}], expected [{$partBlueprint['section']}].";
                }
            }

            // Question Range validation from canonical validator
            $partRange = ToeicQuestionValidator::getPartQuestionRange($slot->partNumber);
            if ($slot->canonicalQuestionNumber !== null) {
                if (isset($seenQNumbers[$slot->canonicalQuestionNumber])) {
                    $errors[] = "Duplicate canonical question number {$slot->canonicalQuestionNumber} found.";
                }
                $seenQNumbers[$slot->canonicalQuestionNumber] = true;

                if ($partRange && ($slot->canonicalQuestionNumber < $partRange['start'] || $slot->canonicalQuestionNumber > $partRange['end'])) {
                    $errors[] = "Slot sequence {$slot->sequence} in Part {$slot->partNumber} has Q{$slot->canonicalQuestionNumber} outside canonical range Q{$partRange['start']}–Q{$partRange['end']}.";
                }
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

            // Accumulate counts
            $actualPartCounts[$slot->partNumber] = ($actualPartCounts[$slot->partNumber] ?? 0) + 1;
            $actualSectionCounts[$slot->section->value] = ($actualSectionCounts[$slot->section->value] ?? 0) + 1;
        }

        // Section & Part Counts Match
        foreach ($plan->partCounts as $pNum => $cnt) {
            $actual = $actualPartCounts[$pNum] ?? 0;
            if ($cnt !== $actual) {
                $errors[] = "Part {$pNum} declared count {$cnt} does not match actual slots {$actual}.";
            }
        }
        foreach ($plan->sectionCounts as $sName => $cnt) {
            $actual = $actualSectionCounts[$sName] ?? 0;
            if ($cnt !== $actual) {
                $errors[] = "Section {$sName} declared count {$cnt} does not match actual slots {$actual}.";
            }
        }

        // 5. Full Test Specific Rules (Derived from ToeicQuestionValidator)
        if ($plan->mode === 'full_test') {
            $fullTarget = ToeicQuestionValidator::getTotalCanonicalTargetCount();
            if ($plan->totalSlots !== $fullTarget) {
                $errors[] = "Full test mode must have exactly {$fullTarget} slots, got {$plan->totalSlots}.";
            }

            $expListening = ToeicQuestionValidator::getSectionTargetCount('listening');
            $expReading = ToeicQuestionValidator::getSectionTargetCount('reading');

            if (($plan->sectionCounts['listening'] ?? 0) !== $expListening) {
                $errors[] = "Listening section must contain exactly {$expListening} slots, got ".($plan->sectionCounts['listening'] ?? 0).'.';
            }
            if (($plan->sectionCounts['reading'] ?? 0) !== $expReading) {
                $errors[] = "Reading section must contain exactly {$expReading} slots, got ".($plan->sectionCounts['reading'] ?? 0).'.';
            }

            $allPartBps = ToeicQuestionValidator::getAllPartBlueprints();
            foreach ($allPartBps as $pNum => $bp) {
                $expCount = $bp['target_count'];
                $actCount = $plan->partCounts[$pNum] ?? 0;
                if ($actCount !== $expCount) {
                    $errors[] = "Part {$pNum} must have {$expCount} slots, got {$actCount}.";
                }
            }

            // Verify canonical question numbers Q1 to Q200
            for ($q = 1; $q <= $fullTarget; $q++) {
                if (!isset($seenQNumbers[$q])) {
                    $errors[] = "Missing canonical question number Q{$q} in full test.";
                }
            }

            // Verify Groups for Full Test
            $p3Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 3));
            $expP3Groups = ToeicQuestionValidator::getAudioGroupCountForPart(3);
            $expAudioQPerG = ToeicQuestionValidator::getAudioGroupQuestionCount();

            if (count($p3Groups) !== $expP3Groups) {
                $errors[] = "Part 3 must have exactly {$expP3Groups} audio groups, found ".count($p3Groups).'.';
            }
            foreach ($p3Groups as $g) {
                if ($g->questionCount !== $expAudioQPerG) {
                    $errors[] = "Part 3 Group {$g->groupIndex} must contain {$expAudioQPerG} questions, found {$g->questionCount}.";
                }
                if ($g->groupType !== 'conversation') {
                    $errors[] = "Part 3 Group {$g->groupIndex} group_type must be conversation, got [{$g->groupType}].";
                }
            }

            $p4Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 4));
            $expP4Groups = ToeicQuestionValidator::getAudioGroupCountForPart(4);

            if (count($p4Groups) !== $expP4Groups) {
                $errors[] = "Part 4 must have exactly {$expP4Groups} audio groups, found ".count($p4Groups).'.';
            }
            foreach ($p4Groups as $g) {
                if ($g->questionCount !== $expAudioQPerG) {
                    $errors[] = "Part 4 Group {$g->groupIndex} must contain {$expAudioQPerG} questions, found {$g->questionCount}.";
                }
                if ($g->groupType !== 'talk') {
                    $errors[] = "Part 4 Group {$g->groupIndex} group_type must be talk, got [{$g->groupType}].";
                }
            }

            $p6Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 6));
            $expP6Groups = ToeicQuestionValidator::getPart6PassageGroupCount();
            $expP6QPerG = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();

            if (count($p6Groups) !== $expP6Groups) {
                $errors[] = "Part 6 must have exactly {$expP6Groups} passage groups, found ".count($p6Groups).'.';
            }
            foreach ($p6Groups as $g) {
                if ($g->questionCount !== $expP6QPerG) {
                    $errors[] = "Part 6 Group {$g->groupIndex} must contain {$expP6QPerG} questions, found {$g->questionCount}.";
                }
                if ($g->documentCount !== 1) {
                    $errors[] = "Part 6 Group {$g->groupIndex} must have document_count 1, got {$g->documentCount}.";
                }
                if ($g->groupType !== 'passage') {
                    $errors[] = "Part 6 Group {$g->groupIndex} group_type must be passage, got [{$g->groupType}].";
                }
            }

            $p7Groups = array_values(array_filter($plan->groups, fn (ToeicGenerationGroup $g) => $g->partNumber === 7));
            $p7Bp = ToeicQuestionValidator::getPart7Blueprint();
            $expP7TotalGroups = $p7Bp['total_groups'];

            if (count($p7Groups) !== $expP7TotalGroups) {
                $errors[] = "Part 7 must have exactly {$expP7TotalGroups} passage groups, found ".count($p7Groups).'.';
            }

            $p7Single = array_values(array_filter($p7Groups, fn (ToeicGenerationGroup $g) => $g->groupType === 'single'));
            $p7Double = array_values(array_filter($p7Groups, fn (ToeicGenerationGroup $g) => $g->groupType === 'double'));
            $p7Triple = array_values(array_filter($p7Groups, fn (ToeicGenerationGroup $g) => $g->groupType === 'triple'));

            $expSingleGroups = $p7Bp['single']['group_count'];
            $expSingleQTotal = $p7Bp['single']['question_total'];
            $minSingleQ = $p7Bp['single']['questions_per_group_min'];
            $maxSingleQ = $p7Bp['single']['questions_per_group_max'];

            if (count($p7Single) !== $expSingleGroups) {
                $errors[] = "Part 7 must have {$expSingleGroups} single passage groups, found ".count($p7Single).'.';
            }
            $p7SingleQCount = array_sum(array_map(fn (ToeicGenerationGroup $g) => $g->questionCount, $p7Single));
            if ($p7SingleQCount !== $expSingleQTotal) {
                $errors[] = "Part 7 Single passage groups must have {$expSingleQTotal} total questions, got {$p7SingleQCount}.";
            }
            foreach ($p7Single as $g) {
                if ($g->documentCount !== $p7Bp['single']['document_count']) {
                    $errors[] = "Part 7 Single Group {$g->groupIndex} must have {$p7Bp['single']['document_count']} document, got {$g->documentCount}.";
                }
                if ($g->questionCount < $minSingleQ || $g->questionCount > $maxSingleQ) {
                    $errors[] = "Part 7 Single Group {$g->groupIndex} must have {$minSingleQ}..{$maxSingleQ} questions, got {$g->questionCount}.";
                }
            }

            $expDoubleGroups = $p7Bp['double']['group_count'];
            $expDoubleQTotal = $p7Bp['double']['question_total'];
            $expDoubleQPerG = $p7Bp['double']['questions_per_group'];
            if (count($p7Double) !== $expDoubleGroups) {
                $errors[] = "Part 7 must have {$expDoubleGroups} double passage groups, found ".count($p7Double).'.';
            }
            $p7DoubleQCount = array_sum(array_map(fn (ToeicGenerationGroup $g) => $g->questionCount, $p7Double));
            if ($p7DoubleQCount !== $expDoubleQTotal) {
                $errors[] = "Part 7 Double passage groups must have {$expDoubleQTotal} total questions, got {$p7DoubleQCount}.";
            }
            foreach ($p7Double as $g) {
                if ($g->documentCount !== $p7Bp['double']['document_count']) {
                    $errors[] = "Part 7 Double Group {$g->groupIndex} must have {$p7Bp['double']['document_count']} documents, got {$g->documentCount}.";
                }
                if ($g->questionCount !== $expDoubleQPerG) {
                    $errors[] = "Part 7 Double Group {$g->groupIndex} must have {$expDoubleQPerG} questions, got {$g->questionCount}.";
                }
            }

            $expTripleGroups = $p7Bp['triple']['group_count'];
            $expTripleQTotal = $p7Bp['triple']['question_total'];
            $expTripleQPerG = $p7Bp['triple']['questions_per_group'];
            if (count($p7Triple) !== $expTripleGroups) {
                $errors[] = "Part 7 must have {$expTripleGroups} triple passage groups, found ".count($p7Triple).'.';
            }
            $p7TripleQCount = array_sum(array_map(fn (ToeicGenerationGroup $g) => $g->questionCount, $p7Triple));
            if ($p7TripleQCount !== $expTripleQTotal) {
                $errors[] = "Part 7 Triple passage groups must have {$expTripleQTotal} total questions, got {$p7TripleQCount}.";
            }
            foreach ($p7Triple as $g) {
                if ($g->documentCount !== $p7Bp['triple']['document_count']) {
                    $errors[] = "Part 7 Triple Group {$g->groupIndex} must have {$p7Bp['triple']['document_count']} documents, got {$g->documentCount}.";
                }
                if ($g->questionCount !== $expTripleQPerG) {
                    $errors[] = "Part 7 Triple Group {$g->groupIndex} must have {$expTripleQPerG} questions, got {$g->questionCount}.";
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

        // 7. Group Cross-Referencing across ALL modes
        $groupIndexMap = [];
        foreach ($plan->groups as $group) {
            // Group type must never be generic 'custom_part'
            if ($group->groupType === 'custom_part') {
                $errors[] = "Group {$group->groupIndex} in Part {$group->partNumber} has invalid generic group_type 'custom_part'.";
            }

            $groupIndexMap[$group->partNumber][$group->groupIndex] = $group;

            if (count($group->slotSequences) !== $group->questionCount) {
                $errors[] = "Group {$group->groupIndex} in Part {$group->partNumber} declares questionCount {$group->questionCount} but has ".count($group->slotSequences).' slot sequences.';
            }
            if (count($group->questionNumbers) !== $group->questionCount) {
                $errors[] = "Group {$group->groupIndex} in Part {$group->partNumber} declares questionCount {$group->questionCount} but has ".count($group->questionNumbers).' question numbers.';
            }

            // Check group size integrity for specific parts
            if (in_array($group->partNumber, [3, 4], true)) {
                $expQ = ToeicQuestionValidator::getAudioGroupQuestionCount();
                if ($group->questionCount !== $expQ) {
                    $errors[] = "Audio Group {$group->groupIndex} in Part {$group->partNumber} must have exactly {$expQ} questions, found {$group->questionCount}.";
                }
            } elseif ($group->partNumber === 6) {
                $expQ = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();
                if ($group->questionCount !== $expQ) {
                    $errors[] = "Passage Group {$group->groupIndex} in Part 6 must have exactly {$expQ} questions, found {$group->questionCount}.";
                }
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
