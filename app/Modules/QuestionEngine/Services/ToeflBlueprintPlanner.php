<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionEngine\DTO\ToeflAdaptiveModulePlan;
use App\Modules\QuestionEngine\DTO\ToeflBlueprintRequest;
use App\Modules\QuestionEngine\DTO\ToeflGenerationPlan;
use App\Modules\QuestionEngine\DTO\ToeflGenerationSlot;
use App\Modules\QuestionEngine\DTO\ToeflTaskSpecification;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ProficiencyTarget;
use App\Modules\QuestionEngine\Enums\ToeflLanguageUseContext;
use App\Modules\QuestionEngine\Enums\ToeflSkill;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use InvalidArgumentException;
use RuntimeException;

class ToeflBlueprintPlanner
{
    public const STRATEGY_VERSION = 'toefl_blueprint_v1';

    public function __construct(
        protected AssessmentStandardRegistry $registry,
        protected ?WeightedSlotAllocator $allocator = null,
        protected ?ToeflGenerationPlanValidator $validator = null,
    ) {
        $this->allocator ??= new WeightedSlotAllocator;
        $this->validator ??= new ToeflGenerationPlanValidator;
    }

    /**
     * Plan a canonical TOEFL iBT generation blueprint.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function plan(ToeflBlueprintRequest $request): ToeflGenerationPlan
    {
        // 1. Resolve Active TOEFL iBT AssessmentStandard (Atomically bound once)
        $activeStandard = $this->registry->findActiveStandard(AssessmentFamily::ToeflIbt);

        if ($activeStandard === null) {
            throw new RuntimeException('No active assessment standard found for TOEFL iBT family. An active standard is required to generate a blueprint plan.');
        }

        // Validate explicit assertions against active standard
        if ($request->standardId !== null && $request->standardId !== $activeStandard->id) {
            throw new InvalidArgumentException("Requested standard_id [{$request->standardId}] does not match active TOEFL iBT standard [{$activeStandard->id}].");
        }

        if ($request->standardVersion !== null && $request->standardVersion !== $activeStandard->version) {
            throw new InvalidArgumentException("Requested standard_version [{$request->standardVersion}] does not match active TOEFL iBT standard version [{$activeStandard->version}].");
        }

        $standardId = $activeStandard->id;
        $standardVersion = $activeStandard->version;

        // 2. Generate Plan according to Mode
        return match ($request->mode) {
            ToeflBlueprintRequest::MODE_FULL_TEST => $this->planFullTest($request, $standardId, $standardVersion),
            ToeflBlueprintRequest::MODE_SECTION => $this->planSection($request, $standardId, $standardVersion),
            ToeflBlueprintRequest::MODE_TASK => $this->planTask($request, $standardId, $standardVersion),
            ToeflBlueprintRequest::MODE_CUSTOM => $this->planCustom($request, $standardId, $standardVersion),
            default => throw new InvalidArgumentException("Unsupported planning mode: [{$request->mode}]."),
        };
    }

    /**
     * Plan a full 4-section canonical TOEFL iBT test (Reading, Listening, Writing, Speaking).
     */
    protected function planFullTest(ToeflBlueprintRequest $request, string $standardId, string $standardVersion): ToeflGenerationPlan
    {
        // Canonical section task allocations (IAP-derived default strategy for ranged adaptive sections)
        $plannedTaskCounts = [
            // Reading (50 max adaptive pool)
            ToeflTaskType::CompleteTheWords->value => 30,
            ToeflTaskType::ReadInDailyLife->value => 10,
            ToeflTaskType::ReadAnAcademicPassage->value => 10,

            // Listening (47 max adaptive pool)
            ToeflTaskType::ListenAndChooseAResponse->value => 15,
            ToeflTaskType::ListenToAConversation->value => 10,
            ToeflTaskType::ListenToAnAnnouncement->value => 8,
            ToeflTaskType::ListenToAnAcademicTalk->value => 14,

            // Writing (12 canonical fixed)
            ToeflTaskType::BuildASentence->value => 10,
            ToeflTaskType::WriteAnEmail->value => 1,
            ToeflTaskType::WriteForAnAcademicDiscussion->value => 1,

            // Speaking (11 canonical fixed)
            ToeflTaskType::ListenAndRepeat->value => 7,
            ToeflTaskType::TakeAnInterview->value => 4,
        ];

        return $this->buildPlanFromTaskCounts(
            taskCounts: $plannedTaskCounts,
            mode: ToeflBlueprintRequest::MODE_FULL_TEST,
            request: $request,
            standardId: $standardId,
            standardVersion: $standardVersion,
        );
    }

    /**
     * Plan a single TOEFL iBT section.
     */
    protected function planSection(ToeflBlueprintRequest $request, string $standardId, string $standardVersion): ToeflGenerationPlan
    {
        $section = $request->section;
        if ($section === null) {
            throw new InvalidArgumentException('Section mode requires a section parameter.');
        }

        $taskCounts = match ($section) {
            'reading' => [
                ToeflTaskType::CompleteTheWords->value => 30,
                ToeflTaskType::ReadInDailyLife->value => 10,
                ToeflTaskType::ReadAnAcademicPassage->value => 10,
            ],
            'listening' => [
                ToeflTaskType::ListenAndChooseAResponse->value => 15,
                ToeflTaskType::ListenToAConversation->value => 10,
                ToeflTaskType::ListenToAnAnnouncement->value => 8,
                ToeflTaskType::ListenToAnAcademicTalk->value => 14,
            ],
            'writing' => [
                ToeflTaskType::BuildASentence->value => 10,
                ToeflTaskType::WriteAnEmail->value => 1,
                ToeflTaskType::WriteForAnAcademicDiscussion->value => 1,
            ],
            'speaking' => [
                ToeflTaskType::ListenAndRepeat->value => 7,
                ToeflTaskType::TakeAnInterview->value => 4,
            ],
            default => throw new InvalidArgumentException("Unknown section '{$section}'."),
        };

        return $this->buildPlanFromTaskCounts(
            taskCounts: $taskCounts,
            mode: ToeflBlueprintRequest::MODE_SECTION,
            request: $request,
            standardId: $standardId,
            standardVersion: $standardVersion,
        );
    }

    /**
     * Plan a single task type.
     */
    protected function planTask(ToeflBlueprintRequest $request, string $standardId, string $standardVersion): ToeflGenerationPlan
    {
        $task = $request->taskType;
        if ($task === null) {
            throw new InvalidArgumentException('Task mode requires a task_type parameter.');
        }

        $fixed = $task->itemCountFixed();
        $range = $task->itemCountRange();

        $count = $request->taskCount;
        if ($count === null) {
            if ($fixed !== null) {
                $count = $fixed;
            } elseif ($range !== null) {
                // Default midpoint / representative count
                $count = (int) round(($range['min'] + $range['max']) / 2);
            } else {
                $count = 10;
            }
        }

        $taskCounts = [$task->value => $count];

        return $this->buildPlanFromTaskCounts(
            taskCounts: $taskCounts,
            mode: ToeflBlueprintRequest::MODE_TASK,
            request: $request,
            standardId: $standardId,
            standardVersion: $standardVersion,
        );
    }

    /**
     * Plan a custom selection of tasks and item counts.
     */
    protected function planCustom(ToeflBlueprintRequest $request, string $standardId, string $standardVersion): ToeflGenerationPlan
    {
        $customTasks = $request->customTasks;
        if (empty($customTasks)) {
            throw new InvalidArgumentException('Custom mode requires custom_tasks to be specified.');
        }

        return $this->buildPlanFromTaskCounts(
            taskCounts: $customTasks,
            mode: ToeflBlueprintRequest::MODE_CUSTOM,
            request: $request,
            standardId: $standardId,
            standardVersion: $standardVersion,
        );
    }

    /**
     * Build full generation plan and slots from normalized task counts.
     *
     * @param  array<string, int>  $taskCounts
     */
    protected function buildPlanFromTaskCounts(
        array $taskCounts,
        string $mode,
        ToeflBlueprintRequest $request,
        string $standardId,
        string $standardVersion
    ): ToeflGenerationPlan {
        $slots = [];
        $globalSeq = 1;

        $sectionCounts = [];
        $actualTaskCounts = [];
        $claimCounts = [];
        $skillCounts = [];
        $responseModeCounts = [];
        $scoringModeCounts = [];
        $difficultyCounts = [];
        $proficiencyCounts = [];

        // Order tasks canonically: Reading -> Listening -> Writing -> Speaking
        $orderedTaskKeys = [];
        foreach (ToeflTaskType::cases() as $tType) {
            if (isset($taskCounts[$tType->value]) && $taskCounts[$tType->value] > 0) {
                $orderedTaskKeys[] = $tType->value;
            }
        }

        // Section sequence trackers for adaptive module representation
        $sectionSlots = ['reading' => [], 'listening' => [], 'writing' => [], 'speaking' => []];

        foreach ($orderedTaskKeys as $taskKey) {
            $task = ToeflTaskType::from($taskKey);
            $taskCount = $taskCounts[$taskKey];
            $taskSpec = ToeflTaskSpecification::forTaskType($task);
            $section = $task->section();
            $claim = $task->claim();
            $isAdaptive = in_array($section, ['reading', 'listening'], true);

            // 1. Skill Sequence Allocation
            $skillDist = $this->resolveSkillDistribution($task, $request);
            $skillSeq = $this->allocator->allocateSequence(
                $taskCount,
                $skillDist,
                $request->seed !== null ? $request->seed + ($globalSeq * 13) : null
            );

            // 2. Language Context Sequence Allocation
            $contextDist = $this->resolveContextDistribution($task, $request);
            $contextSeq = $this->allocator->allocateSequence(
                $taskCount,
                $contextDist,
                $request->seed !== null ? $request->seed + ($globalSeq * 17) : null
            );

            // 3. Difficulty Sequence Allocation
            $diffSeq = $this->allocator->allocateSequence(
                $taskCount,
                $request->difficultyDistribution,
                $request->seed !== null ? $request->seed + ($globalSeq * 23) : null
            );

            // 4. Proficiency Sequence Allocation (Fail-closed on explicit incompatible targets)
            $profDist = $this->resolveProficiencyDistribution($task, $request);
            $profSeq = $this->allocator->allocateSequence(
                $taskCount,
                $profDist,
                $request->seed !== null ? $request->seed + ($globalSeq * 29) : null
            );

            for ($i = 0; $i < $taskCount; $i++) {
                $slotSkill = ToeflSkill::from($skillSeq[$i]);
                $slotContext = ToeflLanguageUseContext::from($contextSeq[$i]);
                $slotDiff = DifficultyLevel::from($diffSeq[$i]);
                $slotProf = ProficiencyTarget::from($profSeq[$i]);
                $slotSeed = $request->seed !== null ? $request->seed + $globalSeq : null;

                // Adaptive role placeholder
                $adaptiveStage = null;
                $moduleRole = null;
                if ($isAdaptive) {
                    $isRouter = ($i < (int) ceil($taskCount / 2));
                    $adaptiveStage = $isRouter ? 1 : 2;
                    $moduleRole = $isRouter ? 'stage_1_router_candidate' : 'stage_2_candidate';
                }

                $slot = new ToeflGenerationSlot(
                    sequence: $globalSeq,
                    assessmentFamily: AssessmentFamily::ToeflIbt,
                    assessmentStandardId: $standardId,
                    standardVersion: $standardVersion,
                    section: $section,
                    taskType: $task,
                    claim: $claim,
                    skill: $slotSkill,
                    proficiencyTarget: $slotProf,
                    difficulty: $slotDiff,
                    languageUseContext: $slotContext,
                    responseMode: $task->responseMode(),
                    scoringMode: $task->scoringMode(),
                    adaptiveSection: $isAdaptive,
                    adaptiveStage: $adaptiveStage,
                    moduleRole: $moduleRole,
                    itemScoringCategory: $request->itemScoringCategory,
                    stimulusType: $taskSpec->stimulusType,
                    stimulusTypeProvenance: 'iap_derived',
                    seed: $slotSeed,
                    partNumber: null,
                    metadata: [
                        'task_label' => $task->label(),
                        'claim_label' => $claim->shortLabel(),
                        'skill_label' => $slotSkill->shortLabel(),
                        'cefr_envelope' => "{$task->cefrMin()}-{$task->cefrMax()}",
                        'provenance' => [
                            'official' => ['section', 'task_type', 'claim', 'skill', 'response_mode', 'scoring_mode'],
                            'iap_derived' => ['difficulty', 'proficiency_target', 'stimulus_type', 'adaptive_module_role'],
                        ],
                    ]
                );

                $slots[] = $slot;
                $sectionSlots[$section][] = $slot;

                // Accumulate counts
                $sectionCounts[$section] = ($sectionCounts[$section] ?? 0) + 1;
                $actualTaskCounts[$task->value] = ($actualTaskCounts[$task->value] ?? 0) + 1;
                $claimCounts[$claim->value] = ($claimCounts[$claim->value] ?? 0) + 1;
                $skillCounts[$slotSkill->value] = ($skillCounts[$slotSkill->value] ?? 0) + 1;
                $responseModeCounts[$task->responseMode()->value] = ($responseModeCounts[$task->responseMode()->value] ?? 0) + 1;
                $scoringModeCounts[$task->scoringMode()->value] = ($scoringModeCounts[$task->scoringMode()->value] ?? 0) + 1;
                $difficultyCounts[$slotDiff->value] = ($difficultyCounts[$slotDiff->value] ?? 0) + 1;
                $proficiencyCounts[$slotProf->value] = ($proficiencyCounts[$slotProf->value] ?? 0) + 1;

                $globalSeq++;
            }
        }

        // Build Adaptive Module Plans for Reading and Listening
        $adaptiveModules = [];
        if (!empty($sectionSlots['reading'])) {
            $adaptiveModules = array_merge($adaptiveModules, $this->buildAdaptiveModules('reading', $sectionSlots['reading']));
        }

        if (!empty($sectionSlots['listening'])) {
            $adaptiveModules = array_merge($adaptiveModules, $this->buildAdaptiveModules('listening', $sectionSlots['listening']));
        }

        $totalSlots = count($slots);
        $fingerprint = $this->generateFingerprint($request, $standardId, $standardVersion);

        $plan = new ToeflGenerationPlan(
            fingerprint: $fingerprint,
            mode: $mode,
            assessmentFamily: AssessmentFamily::ToeflIbt,
            assessmentStandardId: $standardId,
            standardVersion: $standardVersion,
            plannerStrategyVersion: self::STRATEGY_VERSION,
            totalSlots: $totalSlots,
            sectionCounts: $sectionCounts,
            taskCounts: $actualTaskCounts,
            claimCounts: $claimCounts,
            skillCounts: $skillCounts,
            responseModeCounts: $responseModeCounts,
            scoringModeCounts: $scoringModeCounts,
            difficultyCounts: $difficultyCounts,
            proficiencyCounts: $proficiencyCounts,
            slots: $slots,
            adaptiveModules: $adaptiveModules,
            request: $request,
            metadata: [
                'planner_strategy' => self::STRATEGY_VERSION,
                'canonical_standard' => 'TOEFL iBT 2026.1 (ETS Official)',
                'notes' => 'Deterministic IAP TOEFL blueprint plan respecting ETS 2026 task boundaries and CEFR envelopes.',
            ]
        );

        // Validate plan invariants
        $this->validator->validate($plan);

        return $plan;
    }

    /**
     * Resolve skill distribution for a task.
     *
     * @return array<string, int|float>
     */
    protected function resolveSkillDistribution(ToeflTaskType $task, ToeflBlueprintRequest $request): array
    {
        if ($request->skillDistribution !== null && isset($request->skillDistribution[$task->value])) {
            return $request->skillDistribution[$task->value];
        }

        $skills = $task->skills();
        $count = count($skills);
        if ($count === 1) {
            return [$skills[0]->value => 100];
        }

        $equalWeight = round(100.0 / $count, 4);
        $dist = [];
        $sum = 0.0;
        foreach ($skills as $idx => $s) {
            if ($idx === $count - 1) {
                $dist[$s->value] = round(100.0 - $sum, 4);
            } else {
                $dist[$s->value] = $equalWeight;
                $sum += $equalWeight;
            }
        }

        return $dist;
    }

    /**
     * Resolve context distribution for a task.
     *
     * @return array<string, int|float>
     */
    protected function resolveContextDistribution(ToeflTaskType $task, ToeflBlueprintRequest $request): array
    {
        if ($request->languageContextDistribution !== null && isset($request->languageContextDistribution[$task->value])) {
            return $request->languageContextDistribution[$task->value];
        }

        $contexts = $task->allowedLanguageUseContexts();
        $count = count($contexts);
        if ($count === 1) {
            return [$contexts[0]->value => 100];
        }

        $equalWeight = round(100.0 / $count, 4);
        $dist = [];
        $sum = 0.0;
        foreach ($contexts as $idx => $c) {
            if ($idx === $count - 1) {
                $dist[$c->value] = round(100.0 - $sum, 4);
            } else {
                $dist[$c->value] = $equalWeight;
                $sum += $equalWeight;
            }
        }

        return $dist;
    }

    /**
     * Resolve proficiency target distribution for a task.
     * Fail closed on explicit caller distribution if any target is incompatible with task envelope.
     *
     * @return array<string, int|float>
     */
    protected function resolveProficiencyDistribution(ToeflTaskType $task, ToeflBlueprintRequest $request): array
    {
        if ($request->hasExplicitProficiencyDistribution && $request->proficiencyDistribution !== null) {
            foreach ($request->proficiencyDistribution as $targetKey => $weight) {
                if ($weight > 0) {
                    if (!ToeflProficiencyCompatibility::isTargetCompatible($task, $targetKey)) {
                        throw new InvalidArgumentException("Explicit proficiency target '{$targetKey}' is outside official CEFR envelope [{$task->cefrMin()}-{$task->cefrMax()}] for task '{$task->value}'.");
                    }
                }
            }

            return $request->proficiencyDistribution;
        }

        // Fallback to task default distribution (derived directly from task allowed envelope)
        return ToeflProficiencyCompatibility::getDefaultDistributionForTask($task);
    }

    /**
     * Build adaptive module representations for Reading and Listening.
     * Router module references actual candidate slots with accurate matching taskCounts.
     * Stage 2 Lower and Upper are structural placeholders with empty slots/counts.
     *
     * @param  list<ToeflGenerationSlot>  $sectionSlotObjects
     * @return list<ToeflAdaptiveModulePlan>
     */
    protected function buildAdaptiveModules(string $section, array $sectionSlotObjects): array
    {
        $routerSeqs = [];
        $routerTaskCounts = [];

        foreach ($sectionSlotObjects as $slot) {
            if ($slot->adaptiveStage === 1) {
                $routerSeqs[] = $slot->sequence;
                $tVal = $slot->taskType->value;
                $routerTaskCounts[$tVal] = ($routerTaskCounts[$tVal] ?? 0) + 1;
            }
        }

        return [
            new ToeflAdaptiveModulePlan(
                section: $section,
                stage: 1,
                moduleRole: 'stage_1_router',
                taskCounts: $routerTaskCounts,
                slotSequences: $routerSeqs,
                routingRule: 'unspecified',
                routingThreshold: null,
                provenance: 'official_structure_plus_iap_planning',
                metadata: [
                    'note' => 'IAP generation pool for Stage 1 router items.',
                ]
            ),
            new ToeflAdaptiveModulePlan(
                section: $section,
                stage: 2,
                moduleRole: 'stage_2_lower',
                taskCounts: [],
                slotSequences: [],
                routingRule: 'unspecified',
                routingThreshold: null,
                provenance: 'official_structure_plus_iap_planning',
                metadata: [
                    'note' => 'Structural placeholder for Stage 2 lower-routed items (unresolved operational routing threshold).',
                ]
            ),
            new ToeflAdaptiveModulePlan(
                section: $section,
                stage: 2,
                moduleRole: 'stage_2_upper',
                taskCounts: [],
                slotSequences: [],
                routingRule: 'unspecified',
                routingThreshold: null,
                provenance: 'official_structure_plus_iap_planning',
                metadata: [
                    'note' => 'Structural placeholder for Stage 2 upper-routed items (unresolved operational routing threshold).',
                ]
            ),
        ];
    }

    /**
     * Generate deterministic plan fingerprint hash.
     */
    protected function generateFingerprint(ToeflBlueprintRequest $request, string $standardId, string $standardVersion): string
    {
        $data = $request->normalizedArray();
        $data['assessment_standard_id'] = $standardId;
        $data['standard_version'] = $standardVersion;
        $data['assessment_family'] = AssessmentFamily::ToeflIbt->value;
        $data['planner_strategy_version'] = self::STRATEGY_VERSION;

        return hash('sha256', (string) json_encode($data));
    }
}
