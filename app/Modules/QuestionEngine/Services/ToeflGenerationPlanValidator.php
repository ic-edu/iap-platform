<?php

namespace App\Modules\QuestionEngine\Services;

use App\Modules\QuestionEngine\DTO\ToeflGenerationPlan;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\ToeflTaskType;
use InvalidArgumentException;

class ToeflGenerationPlanValidator
{
    /**
     * Validate a TOEFL Generation Plan fail-closed.
     *
     * @throws InvalidArgumentException
     */
    public function validate(ToeflGenerationPlan $plan): void
    {
        // 1. Validate Family & Basic Header
        if ($plan->assessmentFamily !== AssessmentFamily::ToeflIbt) {
            throw new InvalidArgumentException("Invalid assessment family '{$plan->assessmentFamily->value}'. Expected toefl_ibt.");
        }

        if (empty($plan->assessmentStandardId)) {
            throw new InvalidArgumentException('Plan is missing assessment_standard_id.');
        }

        if (empty($plan->standardVersion)) {
            throw new InvalidArgumentException('Plan is missing standard_version.');
        }

        if (empty($plan->fingerprint) || strlen($plan->fingerprint) !== 64) {
            throw new InvalidArgumentException('Plan fingerprint must be a 64-character SHA-256 hash.');
        }

        // 2. Validate Slot Counts
        $slots = $plan->slots;
        $actualSlotCount = count($slots);
        if ($plan->totalSlots !== $actualSlotCount) {
            throw new InvalidArgumentException("Plan declared total_slots ({$plan->totalSlots}) does not match actual slot count ({$actualSlotCount}).");
        }

        $expectedSeq = 1;
        $seenSequences = [];
        $actualSectionCounts = [];
        $actualTaskCounts = [];
        $actualClaimCounts = [];
        $actualSkillCounts = [];
        $actualResponseModeCounts = [];
        $actualScoringModeCounts = [];

        foreach ($slots as $slot) {
            // Check sequence monotonicity & uniqueness
            if ($slot->sequence !== $expectedSeq) {
                throw new InvalidArgumentException("Slot sequence out of order. Expected {$expectedSeq}, got {$slot->sequence}.");
            }
            if (isset($seenSequences[$slot->sequence])) {
                throw new InvalidArgumentException("Duplicate slot sequence {$slot->sequence}.");
            }
            $seenSequences[$slot->sequence] = true;
            $expectedSeq++;

            // Check standard binding consistency
            if ($slot->assessmentStandardId !== $plan->assessmentStandardId) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} standard ID [{$slot->assessmentStandardId}] does not match plan standard ID [{$plan->assessmentStandardId}].");
            }
            if ($slot->standardVersion !== $plan->standardVersion) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} standard version [{$slot->standardVersion}] does not match plan standard version [{$plan->standardVersion}].");
            }

            // Check part_number is null
            if ($slot->partNumber !== null) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} has non-null part_number [{$slot->partNumber}]. TOEFL does not use part_number.");
            }

            $task = $slot->taskType;

            // Check section consistency
            if ($slot->section !== $task->section()) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} section [{$slot->section}] does not match task section [{$task->section()}].");
            }

            // Check claim consistency
            if ($slot->claim !== $task->claim()) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} claim [{$slot->claim->value}] does not match task claim [{$task->claim()->value}].");
            }

            // Check skill compatibility
            if (!in_array($slot->skill, $task->skills(), true)) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} skill [{$slot->skill->value}] is incompatible with task [{$task->value}].");
            }

            // Check language use context compatibility
            if (!in_array($slot->languageUseContext, $task->allowedLanguageUseContexts(), true)) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} context [{$slot->languageUseContext->value}] is not allowed for task [{$task->value}].");
            }

            // Check response mode canonical inheritance
            if ($slot->responseMode !== $task->responseMode()) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} response mode [{$slot->responseMode->value}] does not match canonical [{$task->responseMode()->value}].");
            }

            // Check scoring mode canonical inheritance
            if ($slot->scoringMode !== $task->scoringMode()) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} scoring mode [{$slot->scoringMode->value}] does not match canonical [{$task->scoringMode()->value}].");
            }

            // Check adaptive section flag
            $isSectionAdaptive = in_array($slot->section, ['reading', 'listening'], true);
            if ($slot->adaptiveSection !== $isSectionAdaptive) {
                throw new InvalidArgumentException("Slot sequence {$slot->sequence} adaptive_section flag mismatch. Expected ".($isSectionAdaptive ? 'true' : 'false').'.');
            }

            // Check proficiency target compatibility with CEFR envelope
            if ($slot->proficiencyTarget !== null) {
                if (!ToeflProficiencyCompatibility::isTargetCompatible($task, $slot->proficiencyTarget)) {
                    throw new InvalidArgumentException("Slot sequence {$slot->sequence} proficiency target [{$slot->proficiencyTarget->value}] is outside CEFR envelope [{$task->cefrMin()}-{$task->cefrMax()}] for task [{$task->value}].");
                }
            }

            // Accumulate counts
            $sec = $slot->section;
            $actualSectionCounts[$sec] = ($actualSectionCounts[$sec] ?? 0) + 1;

            $tVal = $task->value;
            $actualTaskCounts[$tVal] = ($actualTaskCounts[$tVal] ?? 0) + 1;

            $cVal = $slot->claim->value;
            $actualClaimCounts[$cVal] = ($actualClaimCounts[$cVal] ?? 0) + 1;

            $sVal = $slot->skill->value;
            $actualSkillCounts[$sVal] = ($actualSkillCounts[$sVal] ?? 0) + 1;

            $rVal = $slot->responseMode->value;
            $actualResponseModeCounts[$rVal] = ($actualResponseModeCounts[$rVal] ?? 0) + 1;

            $scVal = $slot->scoringMode->value;
            $actualScoringModeCounts[$scVal] = ($actualScoringModeCounts[$scVal] ?? 0) + 1;
        }

        // 3. Verify Aggregated Counts
        if ($plan->sectionCounts !== $actualSectionCounts) {
            throw new InvalidArgumentException('Plan section_counts does not match computed slot counts.');
        }

        if ($plan->taskCounts !== $actualTaskCounts) {
            throw new InvalidArgumentException('Plan task_counts does not match computed slot counts.');
        }

        if ($plan->claimCounts !== $actualClaimCounts) {
            throw new InvalidArgumentException('Plan claim_counts does not match computed slot counts.');
        }

        if ($plan->skillCounts !== $actualSkillCounts) {
            throw new InvalidArgumentException('Plan skill_counts does not match computed slot counts.');
        }

        if ($plan->responseModeCounts !== $actualResponseModeCounts) {
            throw new InvalidArgumentException('Plan response_mode_counts does not match computed slot counts.');
        }

        if ($plan->scoringModeCounts !== $actualScoringModeCounts) {
            throw new InvalidArgumentException('Plan scoring_mode_counts does not match computed slot counts.');
        }

        // 4. Section & Task Bounds Validation
        $readingTotal = $actualSectionCounts['reading'] ?? 0;
        if ($readingTotal > 50) {
            throw new InvalidArgumentException("Reading section total ({$readingTotal}) exceeds official maximum target of 50.");
        }

        $listeningTotal = $actualSectionCounts['listening'] ?? 0;
        if ($listeningTotal > 47) {
            throw new InvalidArgumentException("Listening section total ({$listeningTotal}) exceeds official maximum target of 47.");
        }

        // Mode-specific canonical constraints
        $isFullTest = $plan->mode === 'full_test';
        $allowPractice = $plan->request->allowPracticeCounts;

        if ($isFullTest) {
            // In full test mode, Writing must be exactly 12 and Speaking exactly 11
            $writingTotal = $actualSectionCounts['writing'] ?? 0;
            if ($writingTotal !== 12) {
                throw new InvalidArgumentException("Full test mode Writing total must be exactly 12, got {$writingTotal}.");
            }

            $speakingTotal = $actualSectionCounts['speaking'] ?? 0;
            if ($speakingTotal !== 11) {
                throw new InvalidArgumentException("Full test mode Speaking total must be exactly 11, got {$speakingTotal}.");
            }

            // Fixed task counts in full test
            $this->assertTaskCount(ToeflTaskType::CompleteTheWords, 30, $actualTaskCounts);
            $this->assertTaskCount(ToeflTaskType::ListenToAConversation, 10, $actualTaskCounts);
            $this->assertTaskCount(ToeflTaskType::BuildASentence, 10, $actualTaskCounts);
            $this->assertTaskCount(ToeflTaskType::WriteAnEmail, 1, $actualTaskCounts);
            $this->assertTaskCount(ToeflTaskType::WriteForAnAcademicDiscussion, 1, $actualTaskCounts);
            $this->assertTaskCount(ToeflTaskType::ListenAndRepeat, 7, $actualTaskCounts);
            $this->assertTaskCount(ToeflTaskType::TakeAnInterview, 4, $actualTaskCounts);
        }

        // Check task bounds (fixed and ranged)
        foreach (ToeflTaskType::cases() as $tType) {
            $count = $actualTaskCounts[$tType->value] ?? 0;
            if ($count > 0) {
                $fixed = $tType->itemCountFixed();
                $range = $tType->itemCountRange();

                if ($fixed !== null) {
                    if (!$allowPractice && $plan->mode !== 'custom') {
                        if ($count !== $fixed) {
                            throw new InvalidArgumentException("Task '{$tType->value}' count ({$count}) must equal canonical fixed count of {$fixed}.");
                        }
                    } else {
                        if ($count > $fixed) {
                            throw new InvalidArgumentException("Task '{$tType->value}' count ({$count}) exceeds canonical fixed count of {$fixed}.");
                        }
                    }
                }

                if ($range !== null) {
                    if (!$allowPractice) {
                        if ($count < $range['min'] || $count > $range['max']) {
                            throw new InvalidArgumentException("Task '{$tType->value}' count ({$count}) is outside official bounds [{$range['min']}-{$range['max']}].");
                        }
                    } else {
                        if ($count > $range['max']) {
                            throw new InvalidArgumentException("Task '{$tType->value}' practice count ({$count}) exceeds official maximum of {$range['max']}.");
                        }
                    }
                }
            }
        }

        // 5. Adaptive Modules Integrity Validation
        foreach ($plan->adaptiveModules as $mod) {
            $seqCount = count($mod->slotSequences);
            $taskSum = array_sum($mod->taskCounts);

            if ($seqCount !== $taskSum) {
                throw new InvalidArgumentException("Adaptive module [{$mod->section}:{$mod->moduleRole}] slot sequence count ({$seqCount}) does not match task counts sum ({$taskSum}).");
            }

            if ($mod->moduleRole === 'stage_1_router') {
                if ($seqCount === 0) {
                    throw new InvalidArgumentException("Router module for section [{$mod->section}] must contain planned candidate slot references.");
                }

                $computedTasks = [];
                foreach ($mod->slotSequences as $seq) {
                    if (!isset($slots[$seq - 1])) {
                        throw new InvalidArgumentException("Router module referenced invalid slot sequence [{$seq}].");
                    }
                    $slot = $slots[$seq - 1];
                    if ($slot->section !== $mod->section) {
                        throw new InvalidArgumentException("Router module section [{$mod->section}] does not match slot section [{$slot->section}].");
                    }
                    $tVal = $slot->taskType->value;
                    $computedTasks[$tVal] = ($computedTasks[$tVal] ?? 0) + 1;
                }

                if ($mod->taskCounts !== $computedTasks) {
                    throw new InvalidArgumentException('Router module task_counts does not match actual referenced slot task counts.');
                }
            } elseif (in_array($mod->moduleRole, ['stage_2_lower', 'stage_2_upper'], true)) {
                // Lower and Upper must remain unassigned placeholders unless explicitly modeled
                if (!empty($mod->slotSequences)) {
                    throw new InvalidArgumentException("Stage 2 placeholder module [{$mod->moduleRole}] must not contain fake shared operational slot references.");
                }
                if ($mod->routingThreshold !== null) {
                    throw new InvalidArgumentException("Stage 2 placeholder module [{$mod->moduleRole}] must not invent proprietary ETS routing thresholds.");
                }
            }
        }
    }

    private function assertTaskCount(ToeflTaskType $task, int $expected, array $actualCounts): void
    {
        $actual = $actualCounts[$task->value] ?? 0;
        if ($actual !== $expected) {
            throw new InvalidArgumentException("Task '{$task->value}' must have exactly {$expected} items in full canonical plan, got {$actual}.");
        }
    }
}
