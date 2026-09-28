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
            'text_cohesion => 20',
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

        // Fix clean keys for default construct distributions
        $this->defaultConstructDistributions[6] = [
            'grammar' => 35,
            'vocabulary' => 35,
            'text_cohesion' => 20,
            'detail' => 10,
        ];
    }

    /**
     * Plan a canonical TOEIC generation blueprint.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function plan(ToeicBlueprintRequest $request): ToeicGenerationPlan
    {
        // 1. Resolve Active TOEIC AssessmentStandard
        $activeStandard = $this->registry->findActiveStandard(AssessmentFamily::Toeic);

        if ($activeStandard === null) {
            throw new RuntimeException('No active assessment standard found for TOEIC family. An active standard is required to generate a blueprint plan.');
        }

        $standardId = $request->standardId ?? $activeStandard->id;
        $standardVersion = $request->standardVersion ?? $activeStandard->version;

        if ($request->standardId !== null && $request->standardId !== $activeStandard->id) {
            throw new InvalidArgumentException("Requested standard_id [{$request->standardId}] does not match active TOEIC standard [{$activeStandard->id}].");
        }

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
        $totalSlots = 200;

        // 1. Allocate Global Distributions
        $diffSequence = $this->allocator->allocateSequence($totalSlots, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $profSequence = $this->allocator->allocateSequence($totalSlots, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);
        $ctxSequence = $request->contextDistribution !== null
            ? $this->allocator->allocateSequence($totalSlots, $request->contextDistribution, $request->seed !== null ? $request->seed + 300 : null)
            : null;

        $slots = [];
        $groups = [];
        $globalSeq = 1;

        // Per-part construct distributions
        $partBlueprints = ToeicQuestionValidator::getAllPartBlueprints();

        foreach ($partBlueprints as $partNum => $bp) {
            $partTargetCount = $bp['target_count'];
            $partStartNum = $bp['start_number'];
            $section = SectionType::from($bp['section']);

            // Resolve constructs for this part
            $constructDist = $request->constructDistribution[$partNum] ?? $this->defaultConstructDistributions[$partNum];
            $partConstructSeq = $this->allocator->allocateSequence($partTargetCount, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null);

            if ($partNum === 1 || $partNum === 2 || $partNum === 5) {
                // Standalone Questions
                for ($pIdx = 0; $pIdx < $partTargetCount; $pIdx++) {
                    $qNum = $partStartNum + $pIdx;
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
                        construct: ConstructTaxonomy::from($partConstructSeq[$pIdx]),
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
            } elseif ($partNum === 3) {
                // Part 3: 13 AudioGroups × 3 questions
                $numGroups = 13;
                $qPerGroup = ToeicQuestionValidator::getAudioGroupQuestionCount();
                $partLocalQIdx = 0;

                for ($g = 1; $g <= $numGroups; $g++) {
                    $groupQNumbers = [];
                    $groupSlotSeqs = [];

                    for ($pos = 1; $pos <= $qPerGroup; $pos++) {
                        $qNum = $partStartNum + $partLocalQIdx;
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
                            construct: ConstructTaxonomy::from($partConstructSeq[$partLocalQIdx]),
                            contentMode: $request->contentMode,
                            domain: $request->domain,
                            context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                            groupType: 'conversation',
                            groupIndex: $g,
                            positionInGroup: $pos,
                            seed: $slotSeed,
                        );

                        $groupQNumbers[] = $qNum;
                        $groupSlotSeqs[] = $globalSeq;
                        $globalSeq++;
                        $partLocalQIdx++;
                    }

                    $groups[] = new ToeicGenerationGroup(
                        groupType: 'conversation',
                        partNumber: $partNum,
                        groupIndex: $g,
                        documentCount: null,
                        questionCount: $qPerGroup,
                        questionNumbers: $groupQNumbers,
                        slotSequences: $groupSlotSeqs,
                    );
                }
            } elseif ($partNum === 4) {
                // Part 4: 10 AudioGroups × 3 questions
                $numGroups = 10;
                $qPerGroup = ToeicQuestionValidator::getAudioGroupQuestionCount();
                $partLocalQIdx = 0;

                for ($g = 1; $g <= $numGroups; $g++) {
                    $groupQNumbers = [];
                    $groupSlotSeqs = [];

                    for ($pos = 1; $pos <= $qPerGroup; $pos++) {
                        $qNum = $partStartNum + $partLocalQIdx;
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
                            construct: ConstructTaxonomy::from($partConstructSeq[$partLocalQIdx]),
                            contentMode: $request->contentMode,
                            domain: $request->domain,
                            context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                            groupType: 'talk',
                            groupIndex: $g,
                            positionInGroup: $pos,
                            seed: $slotSeed,
                        );

                        $groupQNumbers[] = $qNum;
                        $groupSlotSeqs[] = $globalSeq;
                        $globalSeq++;
                        $partLocalQIdx++;
                    }

                    $groups[] = new ToeicGenerationGroup(
                        groupType: 'talk',
                        partNumber: $partNum,
                        groupIndex: $g,
                        documentCount: null,
                        questionCount: $qPerGroup,
                        questionNumbers: $groupQNumbers,
                        slotSequences: $groupSlotSeqs,
                    );
                }
            } elseif ($partNum === 6) {
                // Part 6: 4 PassageGroups × 4 questions
                $numGroups = ToeicQuestionValidator::getPart6PassageGroupCount();
                $qPerGroup = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();
                $partLocalQIdx = 0;

                for ($g = 1; $g <= $numGroups; $g++) {
                    $groupQNumbers = [];
                    $groupSlotSeqs = [];

                    for ($pos = 1; $pos <= $qPerGroup; $pos++) {
                        $qNum = $partStartNum + $partLocalQIdx;
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
                            construct: ConstructTaxonomy::from($partConstructSeq[$partLocalQIdx]),
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
                        $partLocalQIdx++;
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
                // Part 7: 15 PassageGroups (Single: 10 groups / 29 Q, Double: 2 groups / 10 Q, Triple: 3 groups / 15 Q)
                // Single: 4 groups of 2, 3 groups of 3, 3 groups of 4 = 8 + 9 + 12 = 29
                $singleGroupQCounts = [2, 2, 2, 2, 3, 3, 3, 4, 4, 4];
                $doubleGroupQCounts = [5, 5];
                $tripleGroupQCounts = [5, 5, 5];

                $partLocalQIdx = 0;
                $gIndex = 1;

                // 1. Single Passages
                foreach ($singleGroupQCounts as $qCount) {
                    $groupQNumbers = [];
                    $groupSlotSeqs = [];

                    for ($pos = 1; $pos <= $qCount; $pos++) {
                        $qNum = $partStartNum + $partLocalQIdx;
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
                            construct: ConstructTaxonomy::from($partConstructSeq[$partLocalQIdx]),
                            contentMode: $request->contentMode,
                            domain: $request->domain,
                            context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                            groupType: 'single',
                            groupIndex: $gIndex,
                            positionInGroup: $pos,
                            seed: $slotSeed,
                        );

                        $groupQNumbers[] = $qNum;
                        $groupSlotSeqs[] = $globalSeq;
                        $globalSeq++;
                        $partLocalQIdx++;
                    }

                    $groups[] = new ToeicGenerationGroup(
                        groupType: 'single',
                        partNumber: $partNum,
                        groupIndex: $gIndex,
                        documentCount: 1,
                        questionCount: $qCount,
                        questionNumbers: $groupQNumbers,
                        slotSequences: $groupSlotSeqs,
                    );
                    $gIndex++;
                }

                // 2. Double Passages
                foreach ($doubleGroupQCounts as $qCount) {
                    $groupQNumbers = [];
                    $groupSlotSeqs = [];

                    for ($pos = 1; $pos <= $qCount; $pos++) {
                        $qNum = $partStartNum + $partLocalQIdx;
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
                            construct: ConstructTaxonomy::from($partConstructSeq[$partLocalQIdx]),
                            contentMode: $request->contentMode,
                            domain: $request->domain,
                            context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                            groupType: 'double',
                            groupIndex: $gIndex,
                            positionInGroup: $pos,
                            seed: $slotSeed,
                        );

                        $groupQNumbers[] = $qNum;
                        $groupSlotSeqs[] = $globalSeq;
                        $globalSeq++;
                        $partLocalQIdx++;
                    }

                    $groups[] = new ToeicGenerationGroup(
                        groupType: 'double',
                        partNumber: $partNum,
                        groupIndex: $gIndex,
                        documentCount: 2,
                        questionCount: $qCount,
                        questionNumbers: $groupQNumbers,
                        slotSequences: $groupSlotSeqs,
                    );
                    $gIndex++;
                }

                // 3. Triple Passages
                foreach ($tripleGroupQCounts as $qCount) {
                    $groupQNumbers = [];
                    $groupSlotSeqs = [];

                    for ($pos = 1; $pos <= $qCount; $pos++) {
                        $qNum = $partStartNum + $partLocalQIdx;
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
                            construct: ConstructTaxonomy::from($partConstructSeq[$partLocalQIdx]),
                            contentMode: $request->contentMode,
                            domain: $request->domain,
                            context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$globalSeq - 1]) : null,
                            groupType: 'triple',
                            groupIndex: $gIndex,
                            positionInGroup: $pos,
                            seed: $slotSeed,
                        );

                        $groupQNumbers[] = $qNum;
                        $groupSlotSeqs[] = $globalSeq;
                        $globalSeq++;
                        $partLocalQIdx++;
                    }

                    $groups[] = new ToeicGenerationGroup(
                        groupType: 'triple',
                        partNumber: $partNum,
                        groupIndex: $gIndex,
                        documentCount: 3,
                        questionCount: $qCount,
                        questionNumbers: $groupQNumbers,
                        slotSequences: $groupSlotSeqs,
                    );
                    $gIndex++;
                }
            }
        }

        // Compute Counts and Fingerprint
        $sectionCounts = ['listening' => 100, 'reading' => 100];
        $partCounts = [1 => 6, 2 => 25, 3 => 39, 4 => 30, 5 => 30, 6 => 16, 7 => 54];

        $difficultyCounts = $this->allocator->allocate($totalSlots, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $proficiencyCounts = $this->allocator->allocate($totalSlots, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);

        $constructCounts = [];
        foreach ($partBlueprints as $pNum => $bp) {
            $dist = $request->constructDistribution[$pNum] ?? $this->defaultConstructDistributions[$pNum];
            $constructCounts[$pNum] = $this->allocator->allocate($bp['target_count'], $dist, $request->seed !== null ? $request->seed + ($pNum * 17) : null);
        }

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
        $startNum = $blueprint['start_number'];

        // Distributions
        $diffSequence = $this->allocator->allocateSequence($itemCount, $request->difficultyDistribution, $request->seed !== null ? $request->seed + 100 : null);
        $profSequence = $this->allocator->allocateSequence($itemCount, $request->proficiencyDistribution, $request->seed !== null ? $request->seed + 200 : null);
        $ctxSequence = $request->contextDistribution !== null
            ? $this->allocator->allocateSequence($itemCount, $request->contextDistribution, $request->seed !== null ? $request->seed + 300 : null)
            : null;

        $constructDist = $request->constructDistribution[$partNum] ?? ($request->constructDistribution ?? $this->defaultConstructDistributions[$partNum]);
        $constructSeq = $this->allocator->allocateSequence($itemCount, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null);

        $slots = [];
        $groups = [];
        $globalSeq = 1;

        if ($partNum === 1 || $partNum === 2 || $partNum === 5) {
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
                    proficiencyTarget: ProficiencyTarget::from($profSequence[$i]),
                    difficulty: DifficultyLevel::from($diffSequence[$i]),
                    construct: ConstructTaxonomy::from($constructSeq[$i]),
                    contentMode: $request->contentMode,
                    domain: $request->domain,
                    context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$i]) : null,
                    groupType: 'standalone',
                    groupIndex: null,
                    positionInGroup: null,
                    seed: $slotSeed,
                );
                $globalSeq++;
            }
        } elseif ($partNum === 3 || $partNum === 4) {
            $qPerGroup = ToeicQuestionValidator::getAudioGroupQuestionCount();
            $groupType = $partNum === 3 ? 'conversation' : 'talk';
            $numGroups = (int) ceil($itemCount / $qPerGroup);
            $idx = 0;

            for ($g = 1; $g <= $numGroups; $g++) {
                $groupQNumbers = [];
                $groupSlotSeqs = [];
                $qInThisGroup = min($qPerGroup, $itemCount - $idx);

                for ($pos = 1; $pos <= $qInThisGroup; $pos++) {
                    $qNum = $startNum + $idx;
                    $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                    $slots[] = new ToeicGenerationSlot(
                        sequence: $globalSeq,
                        canonicalQuestionNumber: $qNum,
                        assessmentFamily: AssessmentFamily::Toeic,
                        assessmentStandardId: $standardId,
                        standardVersion: $standardVersion,
                        section: $section,
                        partNumber: $partNum,
                        proficiencyTarget: ProficiencyTarget::from($profSequence[$idx]),
                        difficulty: DifficultyLevel::from($diffSequence[$idx]),
                        construct: ConstructTaxonomy::from($constructSeq[$idx]),
                        contentMode: $request->contentMode,
                        domain: $request->domain,
                        context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$idx]) : null,
                        groupType: $groupType,
                        groupIndex: $g,
                        positionInGroup: $pos,
                        seed: $slotSeed,
                    );

                    $groupQNumbers[] = $qNum;
                    $groupSlotSeqs[] = $globalSeq;
                    $globalSeq++;
                    $idx++;
                }

                $groups[] = new ToeicGenerationGroup(
                    groupType: $groupType,
                    partNumber: $partNum,
                    groupIndex: $g,
                    documentCount: null,
                    questionCount: $qInThisGroup,
                    questionNumbers: $groupQNumbers,
                    slotSequences: $groupSlotSeqs,
                );
            }
        } elseif ($partNum === 6) {
            $qPerGroup = ToeicQuestionValidator::getPart6PassageGroupQuestionCount();
            $numGroups = (int) ceil($itemCount / $qPerGroup);
            $idx = 0;

            for ($g = 1; $g <= $numGroups; $g++) {
                $groupQNumbers = [];
                $groupSlotSeqs = [];
                $qInThisGroup = min($qPerGroup, $itemCount - $idx);

                for ($pos = 1; $pos <= $qInThisGroup; $pos++) {
                    $qNum = $startNum + $idx;
                    $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                    $slots[] = new ToeicGenerationSlot(
                        sequence: $globalSeq,
                        canonicalQuestionNumber: $qNum,
                        assessmentFamily: AssessmentFamily::Toeic,
                        assessmentStandardId: $standardId,
                        standardVersion: $standardVersion,
                        section: $section,
                        partNumber: $partNum,
                        proficiencyTarget: ProficiencyTarget::from($profSequence[$idx]),
                        difficulty: DifficultyLevel::from($diffSequence[$idx]),
                        construct: ConstructTaxonomy::from($constructSeq[$idx]),
                        contentMode: $request->contentMode,
                        domain: $request->domain,
                        context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$idx]) : null,
                        groupType: 'passage',
                        groupIndex: $g,
                        positionInGroup: $pos,
                        seed: $slotSeed,
                    );

                    $groupQNumbers[] = $qNum;
                    $groupSlotSeqs[] = $globalSeq;
                    $globalSeq++;
                    $idx++;
                }

                $groups[] = new ToeicGenerationGroup(
                    groupType: 'passage',
                    partNumber: $partNum,
                    groupIndex: $g,
                    documentCount: 1,
                    questionCount: $qInThisGroup,
                    questionNumbers: $groupQNumbers,
                    slotSequences: $groupSlotSeqs,
                );
            }
        } elseif ($partNum === 7) {
            // Part 7 single/double/triple group definitions
            $groupDefs = [
                ['type' => 'single', 'docs' => 1, 'count' => 2],
                ['type' => 'single', 'docs' => 1, 'count' => 2],
                ['type' => 'single', 'docs' => 1, 'count' => 2],
                ['type' => 'single', 'docs' => 1, 'count' => 2],
                ['type' => 'single', 'docs' => 1, 'count' => 3],
                ['type' => 'single', 'docs' => 1, 'count' => 3],
                ['type' => 'single', 'docs' => 1, 'count' => 3],
                ['type' => 'single', 'docs' => 1, 'count' => 4],
                ['type' => 'single', 'docs' => 1, 'count' => 4],
                ['type' => 'single', 'docs' => 1, 'count' => 4],
                ['type' => 'double', 'docs' => 2, 'count' => 5],
                ['type' => 'double', 'docs' => 2, 'count' => 5],
                ['type' => 'triple', 'docs' => 3, 'count' => 5],
                ['type' => 'triple', 'docs' => 3, 'count' => 5],
                ['type' => 'triple', 'docs' => 3, 'count' => 5],
            ];

            $idx = 0;
            $gIndex = 1;

            foreach ($groupDefs as $gDef) {
                if ($idx >= $itemCount) {
                    break;
                }
                $qInThisGroup = min($gDef['count'], $itemCount - $idx);
                $groupQNumbers = [];
                $groupSlotSeqs = [];

                for ($pos = 1; $pos <= $qInThisGroup; $pos++) {
                    $qNum = $startNum + $idx;
                    $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                    $slots[] = new ToeicGenerationSlot(
                        sequence: $globalSeq,
                        canonicalQuestionNumber: $qNum,
                        assessmentFamily: AssessmentFamily::Toeic,
                        assessmentStandardId: $standardId,
                        standardVersion: $standardVersion,
                        section: $section,
                        partNumber: $partNum,
                        proficiencyTarget: ProficiencyTarget::from($profSequence[$idx]),
                        difficulty: DifficultyLevel::from($diffSequence[$idx]),
                        construct: ConstructTaxonomy::from($constructSeq[$idx]),
                        contentMode: $request->contentMode,
                        domain: $request->domain,
                        context: $ctxSequence !== null ? ContextTaxonomy::from($ctxSequence[$idx]) : null,
                        groupType: $gDef['type'],
                        groupIndex: $gIndex,
                        positionInGroup: $pos,
                        seed: $slotSeed,
                    );

                    $groupQNumbers[] = $qNum;
                    $groupSlotSeqs[] = $globalSeq;
                    $globalSeq++;
                    $idx++;
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
            slots: $slots,
            groups: $groups,
            request: $request,
        );

        $this->validator->validate($plan);

        return $plan;
    }

    /**
     * Plan a custom batch of selected parts.
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

            $partAllocations[$partNum] = $count;
            $totalSlots += $count;
        }

        if ($totalSlots > 200) {
            throw new InvalidArgumentException("Custom mode total slots {$totalSlots} cannot exceed 200.");
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
            $startNum = $blueprint['start_number'];

            $constructDist = $request->constructDistribution[$partNum] ?? $this->defaultConstructDistributions[$partNum];
            $constructSeq = $this->allocator->allocateSequence($count, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null);
            $constructCounts[$partNum] = $this->allocator->allocate($count, $constructDist, $request->seed !== null ? $request->seed + ($partNum * 17) : null);

            for ($i = 0; $i < $count; $i++) {
                $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;
                $slots[] = new ToeicGenerationSlot(
                    sequence: $globalSeq,
                    canonicalQuestionNumber: $startNum + $i,
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
                    groupType: 'custom_part',
                    groupIndex: 1,
                    positionInGroup: $i + 1,
                    seed: $slotSeed,
                );
                $globalSeq++;
            }

            $groups[] = new ToeicGenerationGroup(
                groupType: 'custom_part',
                partNumber: $partNum,
                groupIndex: 1,
                documentCount: null,
                questionCount: $count,
                questionNumbers: array_map(fn ($idx) => $startNum + $idx, range(0, $count - 1)),
                slotSequences: array_map(fn ($idx) => ($globalSeq - $count) + $idx, range(0, $count - 1)),
            );
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
