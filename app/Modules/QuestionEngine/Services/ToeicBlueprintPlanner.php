<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionEngine\DTO\ToeicBlueprintRequest;
use App\Modules\QuestionEngine\DTO\ToeicGenerationGroup;
use App\Modules\QuestionEngine\DTO\ToeicGenerationPlan;
use App\Modules\QuestionEngine\DTO\ToeicGenerationSlot;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ConstructTaxonomy;
use App\Modules\QuestionEngine\Enums\ContextTaxonomy;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Services\ToeicQuestionValidator;
use InvalidArgumentException;
use RuntimeException;

class ToeicBlueprintPlanner
{
    /**
     * Canonical default construct distributions per TOEIC part.
     *
     * @var array<int, array<string, int>>
     */
    protected array $defaultConstructDistributions = [
        1 => [
            'visual_description' => 50,
            'detail' => 30,
            'vocabulary' => 20,
        ],
        2 => [
            'intent' => 40,
            'pragmatic_response' => 30,
            'detail' => 20,
            'vocabulary' => 10,
        ],
        3 => [
            'detail' => 30,
            'purpose' => 20,
            'inference' => 20,
            'intent' => 15,
            'graphic_interpretation' => 15,
        ],
        4 => [
            'detail' => 30,
            'purpose' => 20,
            'inference' => 20,
            'main_idea' => 15,
            'graphic_interpretation' => 15,
        ],
        5 => [
            'grammar' => 60,
            'vocabulary' => 40,
        ],
        6 => [
            'grammar' => 35,
            'vocabulary' => 35,
            'text_cohesion' => 20,
            'detail' => 10,
        ],
        7 => [
            'detail' => 30,
            'inference' => 20,
            'purpose' => 15,
            'main_idea' => 15,
            'text_cohesion' => 10,
            'graphic_interpretation' => 10,
        ],
    ];

    public function __construct(
        protected AssessmentStandardRegistry $registry,
        protected ?WeightedSlotAllocator $allocator = null,
        protected ?ToeicGenerationPlanValidator $validator = null,
    ) {
        $this->allocator ??= new WeightedSlotAllocator;
        $this->validator ??= new ToeicGenerationPlanValidator;
    }

    /**
     * Plan a canonical TOEIC generation blueprint.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function plan(ToeicBlueprintRequest $request): ToeicGenerationPlan
    {
        // 1. Resolve Active TOEIC AssessmentStandard (Atomically bound once)
        $activeStandard = $this->registry->findActiveStandard(AssessmentFamily::Toeic);

        if ($activeStandard === null) {
            throw new RuntimeException('No active assessment standard found for TOEIC family. An active standard is required to generate a blueprint plan.');
        }

        // Validate explicit assertions against active standard
        if ($request->standardId !== null && $request->standardId !== $activeStandard->id) {
            throw new InvalidArgumentException("Requested standard_id [{$request->standardId}] does not match active TOEIC standard [{$activeStandard->id}].");
        }

        if ($request->standardVersion !== null && $request->standardVersion !== $activeStandard->version) {
            throw new InvalidArgumentException("Requested standard_version [{$request->standardVersion}] does not match active TOEIC standard version [{$activeStandard->version}].");
        }

        $standardId = $activeStandard->id;
        $standardVersion = $activeStandard->version;

        // 2. Generate Plan according to Mode
        return match ($request->mode) {
            ToeicBlueprintRequest::MODE_FULL_TEST => $this->planFullTest($request, $standardId, $standardVersion),
            ToeicBlueprintRequest::MODE_PART => $this->planPart($request, $standardId, $standardVersion),
            ToeicBlueprintRequest::MODE_CUSTOM => $this->planCustom($request, $standardId, $standardVersion),
            default => throw new InvalidArgumentException("Unsupported planning mode: [{$request->mode}]."),
        };
    }

    /**
     * Plan a full 200-item canonical TOEIC test.
     */
    protected function planFullTest(ToeicBlueprintRequest $request, string $standardId, string $standardVersion): ToeicGenerationPlan
    {
        $totalSlots = ToeicQuestionValidator::getTotalCanonicalTargetCount();

        // Global Sequences
        $diffSequence = $this->allocator->allocateSequence($totalSlots, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $profSequence = $this->allocator->allocateSequence($totalSlots, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);
        $ctxSequence = $request->contextDistribution !== null
            ? $this->allocator->allocateSequence($totalSlots, $request->contextDistribution, $request->seed !== null ? $request->seed + 300 : null)
            : null;

        $slots = [];
        $groups = [];
        $globalSeq = 1;

        $partBlueprints = ToeicQuestionValidator::getAllPartBlueprints();
        $partCounts = [];
        $constructCounts = [];
        $sectionCounts = [
            'listening' => ToeicQuestionValidator::getSectionTargetCount('listening'),
            'reading' => ToeicQuestionValidator::getSectionTargetCount('reading'),
        ];

        foreach ($partBlueprints as $partNum => $bp) {
            $partTargetCount = $bp['target_count'];
            $partCounts[$partNum] = $partTargetCount;

            $constructDist = $request->constructDistribution[$partNum] ?? $this->defaultConstructDistributions[$partNum];
            $constructCounts[$partNum] = $this->allocator->allocate($partTargetCount, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null);

            $partResult = $this->planPartSlotsAndGroups(
                partNum: $partNum,
                itemCount: $partTargetCount,
                globalSeq: $globalSeq,
                diffSequence: $diffSequence,
                profSequence: $profSequence,
                ctxSequence: $ctxSequence,
                constructDist: $constructDist,
                standardId: $standardId,
                standardVersion: $standardVersion,
                request: $request,
            );

            foreach ($partResult['slots'] as $slot) {
                $slots[] = $slot;
            }
            foreach ($partResult['groups'] as $group) {
                $groups[] = $group;
            }
        }

        $difficultyCounts = $this->allocator->allocate($totalSlots, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $proficiencyCounts = $this->allocator->allocate($totalSlots, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);
        $fingerprint = $this->generateFingerprint($request, $standardId, $standardVersion);

        $plan = new ToeicGenerationPlan(
            fingerprint: $fingerprint,
            mode: ToeicBlueprintRequest::MODE_FULL_TEST,
            assessmentFamily: AssessmentFamily::Toeic,
            assessmentStandardId: $standardId,
            standardVersion: $standardVersion,
            totalSlots: $totalSlots,
            sectionCounts: $sectionCounts,
            partCounts: $partCounts,
            difficultyCounts: $difficultyCounts,
            proficiencyCounts: $proficiencyCounts,
            constructCounts: $constructCounts,
            slots: $slots,
            groups: $groups,
            request: $request,
        );

        $this->validator->validate($plan);

        return $plan;
    }

    /**
     * Plan a single-part TOEIC generation plan.
     */
    protected function planPart(ToeicBlueprintRequest $request, string $standardId, string $standardVersion): ToeicGenerationPlan
    {
        $partNum = $request->partNumber;
        $canonicalCount = ToeicQuestionValidator::getPartTargetQuestionCount($partNum);
        $itemCount = $request->itemCount ?? $canonicalCount;

        if ($itemCount > $canonicalCount) {
            throw new InvalidArgumentException("Item count [{$itemCount}] exceeds canonical Part {$partNum} limit of {$canonicalCount}.");
        }

        $blueprint = ToeicQuestionValidator::getPartBlueprint($partNum);
        $section = SectionType::from($blueprint['section']);

        // Distributions
        $diffSequence = $this->allocator->allocateSequence($itemCount, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $profSequence = $this->allocator->allocateSequence($itemCount, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);
        $ctxSequence = $request->contextDistribution !== null
            ? $this->allocator->allocateSequence($itemCount, $request->contextDistribution, $request->seed !== null ? $request->seed + 300 : null)
            : null;

        $constructDist = $request->constructDistribution[$partNum] ?? ($request->constructDistribution ?? $this->defaultConstructDistributions[$partNum]);
        $globalSeq = 1;

        $partResult = $this->planPartSlotsAndGroups(
            partNum: $partNum,
            itemCount: $itemCount,
            globalSeq: $globalSeq,
            diffSequence: $diffSequence,
            profSequence: $profSequence,
            ctxSequence: $ctxSequence,
            constructDist: $constructDist,
            standardId: $standardId,
            standardVersion: $standardVersion,
            request: $request,
        );

        $sectionCounts = [$section->value => $itemCount];
        $partCounts = [$partNum => $itemCount];
        $difficultyCounts = $this->allocator->allocate($itemCount, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $proficiencyCounts = $this->allocator->allocate($itemCount, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);
        $constructCounts = [$partNum => $this->allocator->allocate($itemCount, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null)];

        $fingerprint = $this->generateFingerprint($request, $standardId, $standardVersion);

        $plan = new ToeicGenerationPlan(
            fingerprint: $fingerprint,
            mode: ToeicBlueprintRequest::MODE_PART,
            assessmentFamily: AssessmentFamily::Toeic,
            assessmentStandardId: $standardId,
            standardVersion: $standardVersion,
            totalSlots: $itemCount,
            sectionCounts: $sectionCounts,
            partCounts: $partCounts,
            difficultyCounts: $difficultyCounts,
            proficiencyCounts: $proficiencyCounts,
            constructCounts: $constructCounts,
            slots: $partResult['slots'],
            groups: $partResult['groups'],
            request: $request,
        );

        $this->validator->validate($plan);

        return $plan;
    }

    /**
     * Plan a custom batch of selected parts preserving canonical group awareness.
     */
    protected function planCustom(ToeicBlueprintRequest $request, string $standardId, string $standardVersion): ToeicGenerationPlan
    {
        $customParts = $request->customParts;
        if (empty($customParts)) {
            throw new InvalidArgumentException('Custom mode requires custom_parts to be defined.');
        }

        $partAllocations = [];
        $totalSlots = 0;

        foreach ($customParts as $k => $v) {
            $partNum = is_int($k) && $k >= 1 && $k <= 7 ? $k : (int) $v;
            $count = is_int($k) && $k >= 1 && $k <= 7 ? (int) $v : ToeicQuestionValidator::getPartTargetQuestionCount($partNum);

            $maxPart = ToeicQuestionValidator::getPartTargetQuestionCount($partNum);
            if ($count > $maxPart) {
                throw new InvalidArgumentException("Custom part {$partNum} count {$count} exceeds canonical part limit of {$maxPart}.");
            }

            // Group safe checks
            if (in_array($partNum, [3, 4], true)) {
                $qPerG = ToeicQuestionValidator::getAudioGroupQuestionCount();
                if ($count % $qPerG !== 0) {
                    throw new InvalidArgumentException("Custom Part {$partNum} item count [{$count}] must be a multiple of {$qPerG} (complete audio groups).");
                }
            } elseif ($partNum === 6) {
                $qPerG = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();
                if ($count % $qPerG !== 0) {
                    throw new InvalidArgumentException("Custom Part 6 item count [{$count}] must be a multiple of {$qPerG} (complete passage groups).");
                }
            }

            $partAllocations[$partNum] = $count;
            $totalSlots += $count;
        }

        if ($totalSlots > ToeicQuestionValidator::getTotalCanonicalTargetCount()) {
            throw new InvalidArgumentException("Custom mode total slots {$totalSlots} cannot exceed ".ToeicQuestionValidator::getTotalCanonicalTargetCount().'.');
        }

        // Global Distributions
        $diffSequence = $this->allocator->allocateSequence($totalSlots, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $profSequence = $this->allocator->allocateSequence($totalSlots, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);
        $ctxSequence = $request->contextDistribution !== null
            ? $this->allocator->allocateSequence($totalSlots, $request->contextDistribution, $request->seed !== null ? $request->seed + 300 : null)
            : null;

        $slots = [];
        $groups = [];
        $globalSeq = 1;
        $sectionCounts = ['listening' => 0, 'reading' => 0];
        $constructCounts = [];

        foreach ($partAllocations as $partNum => $count) {
            $blueprint = ToeicQuestionValidator::getPartBlueprint($partNum);
            $section = SectionType::from($blueprint['section']);
            $sectionCounts[$section->value] += $count;

            $constructDist = $request->constructDistribution[$partNum] ?? $this->defaultConstructDistributions[$partNum];
            $constructCounts[$partNum] = $this->allocator->allocate($count, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null);

            // Plan using the exact same canonical group-aware logic
            $partResult = $this->planPartSlotsAndGroups(
                partNum: $partNum,
                itemCount: $count,
                globalSeq: $globalSeq,
                diffSequence: $diffSequence,
                profSequence: $profSequence,
                ctxSequence: $ctxSequence,
                constructDist: $constructDist,
                standardId: $standardId,
                standardVersion: $standardVersion,
                request: $request,
            );

            foreach ($partResult['slots'] as $slot) {
                $slots[] = $slot;
            }
            foreach ($partResult['groups'] as $group) {
                $groups[] = $group;
            }
        }

        $difficultyCounts = $this->allocator->allocate($totalSlots, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $proficiencyCounts = $this->allocator->allocate($totalSlots, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);

        $fingerprint = $this->generateFingerprint($request, $standardId, $standardVersion);

        $plan = new ToeicGenerationPlan(
            fingerprint: $fingerprint,
            mode: ToeicBlueprintRequest::MODE_CUSTOM,
            assessmentFamily: AssessmentFamily::Toeic,
            assessmentStandardId: $standardId,
            standardVersion: $standardVersion,
            totalSlots: $totalSlots,
            sectionCounts: $sectionCounts,
            partCounts: $partAllocations,
            difficultyCounts: $difficultyCounts,
            proficiencyCounts: $proficiencyCounts,
            constructCounts: $constructCounts,
            slots: $slots,
            groups: $groups,
            request: $request,
        );

        $this->validator->validate($plan);

        return $plan;
    }

    /**
     * Unified group-aware slot and group generation helper for all modes.
     *
     * @param  array<string|int, int|float>  $diffSequence
     * @param  array<string|int, int|float>  $profSequence
     * @param  array<string|int, int|float>|null  $ctxSequence
     * @param  array<string, int|float>  $constructDist
     * @return array{slots: list<ToeicGenerationSlot>, groups: list<ToeicGenerationGroup>}
     */
    protected function planPartSlotsAndGroups(
        int $partNum,
        int $itemCount,
        int &$globalSeq,
        array $diffSequence,
        array $profSequence,
        ?array $ctxSequence,
        array $constructDist,
        string $standardId,
        string $standardVersion,
        ToeicBlueprintRequest $request,
    ): array {
        $blueprint = ToeicQuestionValidator::getPartBlueprint($partNum);
        $section = SectionType::from($blueprint['section']);
        $startNum = $blueprint['start_number'];

        $constructSeq = $this->allocator->allocateSequence($itemCount, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null);

        $slots = [];
        $groups = [];

        if ($partNum === 1 || $partNum === 2 || $partNum === 5) {
            // Standalone Questions
            for ($i = 0; $i < $itemCount; $i++) {
                $qNum = $startNum + $i;
                $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                $slots[] = new ToeicGenerationSlot(
                    sequence: $globalSeq,
                    canonicalQuestionNumber: $qNum,
                    assessmentFamily: AssessmentFamily::Toeic,
                    assessmentStandardId: $standardId,
                    standardVersion: $standardVersion,
                    section: $section,
                    partNumber: $partNum,
                    proficiencyTarget: ProficiencyTarget::from($profSequence[$globalSeq - 1]),
                    difficulty: DifficultyLevel::from($diffSequence[$globalSeq - 1]),
                    construct: ConstructTaxonomy::from($constructSeq[$i]),
                    contentMode: $request->contentMode,
                    domain: $request->domain,
                    context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                    groupType: 'standalone',
                    groupIndex: null,
                    positionInGroup: null,
                    seed: $slotSeed,
                );
                $globalSeq++;
            }
        } elseif ($partNum === 3 || $partNum === 4) {
            // Audio Groups (3 questions per dialogue/talk)
            $qPerGroup = ToeicQuestionValidator::getAudioGroupQuestionCount();
            $groupType = $partNum === 3 ? 'conversation' : 'talk';

            if ($itemCount % $qPerGroup !== 0) {
                throw new InvalidArgumentException("Part {$partNum} item count [{$itemCount}] must be a multiple of {$qPerGroup} (complete audio groups).");
            }

            $numGroups = (int) ($itemCount / $qPerGroup);
            $localQIdx = 0;

            for ($g = 1; $g <= $numGroups; $g++) {
                $groupQNumbers = [];
                $groupSlotSeqs = [];

                for ($pos = 1; $pos <= $qPerGroup; $pos++) {
                    $qNum = $startNum + $localQIdx;
                    $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                    $slots[] = new ToeicGenerationSlot(
                        sequence: $globalSeq,
                        canonicalQuestionNumber: $qNum,
                        assessmentFamily: AssessmentFamily::Toeic,
                        assessmentStandardId: $standardId,
                        standardVersion: $standardVersion,
                        section: $section,
                        partNumber: $partNum,
                        proficiencyTarget: ProficiencyTarget::from($profSequence[$globalSeq - 1]),
                        difficulty: DifficultyLevel::from($diffSequence[$globalSeq - 1]),
                        construct: ConstructTaxonomy::from($constructSeq[$localQIdx]),
                        contentMode: $request->contentMode,
                        domain: $request->domain,
                        context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                        groupType: $groupType,
                        groupIndex: $g,
                        positionInGroup: $pos,
                        seed: $slotSeed,
                    );

                    $groupQNumbers[] = $qNum;
                    $groupSlotSeqs[] = $globalSeq;
                    $globalSeq++;
                    $localQIdx++;
                }

                $groups[] = new ToeicGenerationGroup(
                    groupType: $groupType,
                    partNumber: $partNum,
                    groupIndex: $g,
                    documentCount: null,
                    questionCount: $qPerGroup,
                    questionNumbers: $groupQNumbers,
                    slotSequences: $groupSlotSeqs,
                );
            }
        } elseif ($partNum === 6) {
            // Part 6 Passage Groups (4 questions per passage)
            $qPerGroup = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();

            if ($itemCount % $qPerGroup !== 0) {
                throw new InvalidArgumentException("Part 6 item count [{$itemCount}] must be a multiple of {$qPerGroup} (complete passage groups).");
            }

            $numGroups = (int) ($itemCount / $qPerGroup);
            $localQIdx = 0;

            for ($g = 1; $g <= $numGroups; $g++) {
                $groupQNumbers = [];
                $groupSlotSeqs = [];

                for ($pos = 1; $pos <= $qPerGroup; $pos++) {
                    $qNum = $startNum + $localQIdx;
                    $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                    $slots[] = new ToeicGenerationSlot(
                        sequence: $globalSeq,
                        canonicalQuestionNumber: $qNum,
                        assessmentFamily: AssessmentFamily::Toeic,
                        assessmentStandardId: $standardId,
                        standardVersion: $standardVersion,
                        section: $section,
                        partNumber: $partNum,
                        proficiencyTarget: ProficiencyTarget::from($profSequence[$globalSeq - 1]),
                        difficulty: DifficultyLevel::from($diffSequence[$globalSeq - 1]),
                        construct: ConstructTaxonomy::from($constructSeq[$localQIdx]),
                        contentMode: $request->contentMode,
                        domain: $request->domain,
                        context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                        groupType: 'passage',
                        groupIndex: $g,
                        positionInGroup: $pos,
                        seed: $slotSeed,
                    );

                    $groupQNumbers[] = $qNum;
                    $groupSlotSeqs[] = $globalSeq;
                    $globalSeq++;
                    $localQIdx++;
                }

                $groups[] = new ToeicGenerationGroup(
                    groupType: 'passage',
                    partNumber: $partNum,
                    groupIndex: $g,
                    documentCount: 1,
                    questionCount: $qPerGroup,
                    questionNumbers: $groupQNumbers,
                    slotSequences: $groupSlotSeqs,
                );
            }
        } elseif ($partNum === 7) {
            // Part 7: Single (10 groups / 29 Q), Double (2 groups / 10 Q), Triple (3 groups / 15 Q)
            $singleGroupCounts = $this->derivePart7SingleGroupAllocations($request->seed);
            $groupDefs = [];

            // Single passages (10 groups, 1 doc)
            foreach ($singleGroupCounts as $cnt) {
                $groupDefs[] = ['type' => 'single', 'docs' => 1, 'count' => $cnt];
            }

            // Double passages (2 groups, 2 docs, 5 questions each)
            $p7Bp = ToeicQuestionValidator::getPart7Blueprint();
            $doubleGroupCount = $p7Bp['double']['group_count'];
            $doubleQPerGroup = $p7Bp['double']['questions_per_group'];
            for ($d = 1; $d <= $doubleGroupCount; $d++) {
                $groupDefs[] = ['type' => 'double', 'docs' => 2, 'count' => $doubleQPerGroup];
            }

            // Triple passages (3 groups, 3 docs, 5 questions each)
            $tripleGroupCount = $p7Bp['triple']['group_count'];
            $tripleQPerGroup = $p7Bp['triple']['questions_per_group'];
            for ($t = 1; $t <= $tripleGroupCount; $t++) {
                $groupDefs[] = ['type' => 'triple', 'docs' => 3, 'count' => $tripleQPerGroup];
            }

            // Validate that partial itemCount can be cleanly represented as complete passage groups
            $runningTotal = 0;
            $validGroupCounts = [];
            foreach ($groupDefs as $gDef) {
                $runningTotal += $gDef['count'];
                $validGroupCounts[] = $runningTotal;
            }

            if (!in_array($itemCount, $validGroupCounts, true)) {
                throw new InvalidArgumentException("Part 7 item_count [{$itemCount}] does not align with complete canonical passage groups. Valid complete group counts: ".implode(', ', $validGroupCounts).'.');
            }

            $localQIdx = 0;
            $gIndex = 1;

            foreach ($groupDefs as $gDef) {
                if ($localQIdx >= $itemCount) {
                    break;
                }

                $qInThisGroup = $gDef['count'];
                $groupQNumbers = [];
                $groupSlotSeqs = [];

                for ($pos = 1; $pos <= $qInThisGroup; $pos++) {
                    $qNum = $startNum + $localQIdx;
                    $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                    $slots[] = new ToeicGenerationSlot(
                        sequence: $globalSeq,
                        canonicalQuestionNumber: $qNum,
                        assessmentFamily: AssessmentFamily::Toeic,
                        assessmentStandardId: $standardId,
                        standardVersion: $standardVersion,
                        section: $section,
                        partNumber: $partNum,
                        proficiencyTarget: ProficiencyTarget::from($profSequence[$globalSeq - 1]),
                        difficulty: DifficultyLevel::from($diffSequence[$globalSeq - 1]),
                        construct: ConstructTaxonomy::from($constructSeq[$localQIdx]),
                        contentMode: $request->contentMode,
                        domain: $request->domain,
                        context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                        groupType: $gDef['type'],
                        groupIndex: $gIndex,
                        positionInGroup: $pos,
                        seed: $slotSeed,
                    );

                    $groupQNumbers[] = $qNum;
                    $groupSlotSeqs[] = $globalSeq;
                    $globalSeq++;
                    $localQIdx++;
                }

                $groups[] = new ToeicGenerationGroup(
                    groupType: $gDef['type'],
                    partNumber: $partNum,
                    groupIndex: $gIndex,
                    documentCount: $gDef['docs'],
                    questionCount: $qInThisGroup,
                    questionNumbers: $groupQNumbers,
                    slotSequences: $groupSlotSeqs,
                );
                $gIndex++;
            }
        }

        return [
            'slots' => $slots,
            'groups' => $groups,
        ];
    }

    /**
     * Deterministic Planner Strategy: derives any valid 29-questions-across-10-groups allocation
     * satisfying canonical Part 7 Single Passage constraints (min 2, max 4 questions per group).
     *
     * @return list<int>
     */
    public function derivePart7SingleGroupAllocations(?int $seed = null): array
    {
        $p7Bp = ToeicQuestionValidator::getPart7Blueprint();
        $targetGroups = $p7Bp['single']['group_count']; // 10
        $targetQuestions = $p7Bp['single']['question_total']; // 29
        $minQ = $p7Bp['single']['questions_per_group_min']; // 2
        $maxQ = $p7Bp['single']['questions_per_group_max']; // 4

        // Base allocation: each group starts with minQ (2)
        $allocations = array_fill(0, $targetGroups, $minQ);
        $baseTotal = $targetGroups * $minQ; // 20
        $remainder = $targetQuestions - $baseTotal; // 9

        // Distribute remainder deterministically across groups with max capacity of (maxQ - minQ) = 2 each
        // Standard deterministic distribution: 4 groups of 2, 3 groups of 3, 3 groups of 4 (4*2 + 3*3 + 3*4 = 8 + 9 + 12 = 29)
        $extraAllocations = [0, 0, 0, 0, 1, 1, 1, 2, 2, 2];

        // If seed is provided, deterministic permutation of extra allocations using local LCG
        if ($seed !== null) {
            $state = ($seed + 777) & 0x7FFFFFFF;
            for ($i = count($extraAllocations) - 1; $i > 0; $i--) {
                $state = (1103515245 * $state + 12345) & 0x7FFFFFFF;
                $j = $state % ($i + 1);
                $temp = $extraAllocations[$i];
                $extraAllocations[$i] = $extraAllocations[$j];
                $extraAllocations[$j] = $temp;
            }
        }

        for ($i = 0; $i < $targetGroups; $i++) {
            $allocations[$i] += $extraAllocations[$i];
        }

        return $allocations;
    }

    /**
     * Generate deterministic plan fingerprint hash.
     */
    protected function generateFingerprint(ToeicBlueprintRequest $request, string $standardId, string $standardVersion): string
    {
        $data = $request->normalizedArray();
        $data['assessment_standard_id'] = $standardId;
        $data['standard_version'] = $standardVersion;
        $data['assessment_family'] = AssessmentFamily::Toeic->value;

        return hash('sha256', (string) json_encode($data));
    }
}
