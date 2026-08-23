<?php

namespace App\Services\ContentReset;

use App\Models\ContentResetAuditLog;
use App\Models\ContentResetRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ContentResetExecutor
{
    public function __construct(
        protected ContentResetAuditService $auditService,
        protected ContentResetBackupService $backupService,
        protected ContentResetIntegrityService $integrityService
    ) {}

    /**
     * Execute or Dry-Run a content reset request.
     *
     * @return array<string, mixed>
     */
    public function execute(ContentResetRequest $request, User $executor, bool $dryRun = false): array
    {
        // 1. Run fresh forensic audit
        $auditReport = $this->auditService->performForensicAudit($request);

        if ($auditReport['is_blocked']) {
            throw new InvalidArgumentException("Execution blocked: Forensic audit detected " . count($auditReport['blockers']) . " blocker(s):\n" . implode("\n", $auditReport['blockers']));
        }

        // 2. Handle Dry-Run Mode
        if ($dryRun) {
            ContentResetAuditLog::create([
                'request_id' => $request->id,
                'action'     => 'DRY_RUN_EXECUTED',
                'actor_id'   => $executor->id,
                'payload'    => [
                    'mode'         => $request->mode,
                    'audit_report' => $auditReport,
                    'status'       => 'dry_run_success',
                ],
            ]);

            return [
                'dry_run'                      => true,
                'request_number'               => $request->request_number,
                'mode'                         => $request->mode,
                'risk_classification'          => $request->risk_classification,
                'target_assessments_count'     => $auditReport['target_assessments_count'],
                'target_sections_count'        => $auditReport['target_sections_count'],
                'test_question_bindings_count' => $auditReport['test_question_bindings_count'],
                'bound_questions_total'        => $auditReport['bound_questions_total'],
                'shared_questions_preserved'   => $auditReport['shared_questions_preserved'],
                'deletable_questions_count'    => $auditReport['deletable_questions_count'],
                'transient_attempts_count'     => $auditReport['transient_attempts_count'],
                'database_mutated'             => false,
                'files_deleted'                => false,
                'excluded_protected_domains'   => $auditReport['excluded_protected_domains'],
            ];
        }

        // 3. Authorization & Approval Enforcement for Real Execution
        if ($request->isHardReset()) {
            if (!$request->isFullyApproved()) {
                throw new InvalidArgumentException("Execution rejected: Content Hard Reset requires dual approvals from both CEO and Super Admin.");
            }
        } elseif (!$request->isRefresh()) {
            throw new InvalidArgumentException("Unknown reset mode '{$request->mode}'.");
        }

        // 4. Backup Snapshot Requirement
        if (empty($request->backup_reference)) {
            $this->backupService->createSnapshot($request);
        }

        // 5. Pre-Reset Protected Domain Baseline Capture
        $preResetCounts = $this->integrityService->capturePreResetCounts();

        // 6. Atomic Transactional Execution
        $targetAssessmentIds = $auditReport['target_assessment_ids'] ?? [];
        $scope = $request->scope ?? [];

        $deletedCounts = [
            'assessments' => 0,
            'sections'    => 0,
            'bindings'    => 0,
            'questions'   => 0,
            'choices'     => 0,
            'attempts'    => 0,
            'answers'     => 0,
        ];
        $preservedSharedCount = 0;

        try {
            DB::transaction(function () use ($request, $executor, $targetAssessmentIds, $scope, $auditReport, $preResetCounts, &$deletedCounts, &$preservedSharedCount) {
                $request->update([
                    'status'      => 'executing',
                    'executed_by' => $executor->id,
                    'executed_at' => now(),
                ]);

                ContentResetAuditLog::create([
                    'request_id' => $request->id,
                    'action'     => 'EXECUTION_STARTED',
                    'actor_id'   => $executor->id,
                    'payload'    => ['mode' => $request->mode],
                ]);

                if (!empty($targetAssessmentIds)) {
                    // A. Delete transient attempts & answers for targeted tests
                    $targetAttempts = Attempt::whereIn('test_id', $targetAssessmentIds)->get();
                    $targetAttemptIds = $targetAttempts->pluck('id')->toArray();

                    if (!empty($targetAttemptIds)) {
                        $answersCount = Answer::whereIn('attempt_id', $targetAttemptIds)->delete();
                        $deletedCounts['answers'] = $answersCount;

                        $attemptsCount = Attempt::whereIn('id', $targetAttemptIds)->delete();
                        $deletedCounts['attempts'] = $attemptsCount;
                    }

                    // B. Delete Test Sections and Test Questions bindings
                    $targetSections = TestSection::whereIn('test_id', $targetAssessmentIds)->get();
                    $targetSectionIds = $targetSections->pluck('id')->toArray();

                    $bindingsCount = TestQuestion::whereIn('test_section_id', $targetSectionIds)->delete();
                    $deletedCounts['bindings'] = $bindingsCount;

                    $sectionsCount = TestSection::whereIn('id', $targetSectionIds)->delete();
                    $deletedCounts['sections'] = $sectionsCount;


                    // C. Handle bound questions (preserve shared questions)
                    if (in_array(ContentResetDomains::QUESTION_CONTENT, $scope, true)) {
                        // Derive bound question IDs from the now-deleted bindings by re-reading
                        // the audit report detail which has full question_id entries
                        $sharedQIds   = collect($auditReport['shared_questions_detail'] ?? [])->where('status', 'PRESERVED')->pluck('question_id')->toArray();
                        $allBoundQIds = collect($auditReport['shared_questions_detail'] ?? [])->pluck('question_id')->toArray();

                        // Questions not in shared list are safe to delete
                        foreach ($allBoundQIds as $qId) {
                            if (in_array($qId, $sharedQIds, true)) {
                                $preservedSharedCount++;
                                continue;
                            }
                            // Double-check no remaining references after bindings were deleted above
                            $isUsedElsewhere = TestQuestion::where('question_id', $qId)->exists();
                            if ($isUsedElsewhere) {
                                $preservedSharedCount++;
                            } else {
                                QuestionChoice::where('question_id', $qId)->delete();
                                Question::where('id', $qId)->delete();
                                $deletedCounts['questions']++;
                            }
                        }
                    }

                    // D. Delete target assessment records (hard purge)
                    foreach ($targetAssessmentIds as $tId) {
                        $testRecord = Test::withTrashed()->find($tId);
                        if ($testRecord) {
                            $testRecord->forceDelete();
                            $deletedCounts['assessments']++;
                        }
                    }
                }

                // 7. Post-Reset Integrity Validation inside transaction
                $this->integrityService->verifyPostResetIntegrity($preResetCounts);
            });
        } catch (Throwable $e) {
            ContentResetAuditLog::create([
                'request_id' => $request->id,
                'action'     => 'EXECUTION_ROLLED_BACK',
                'actor_id'   => $executor->id,
                'payload'    => [
                    'error' => $e->getMessage(),
                ],
            ]);

            $request->update(['status' => 'blocked']);

            throw $e;
        }

        // 8. Finalize Successful Execution
        $executionLog = [
            'mode'                    => $request->mode,
            'executed_by'             => $executor->id,
            'executor_name'           => $executor->name,
            'deleted_counts'          => $deletedCounts,
            'preserved_shared_count'  => $auditReport['shared_questions_preserved'],
            'integrity_verified'      => true,
            'completed_at'            => now()->toIso8601String(),
        ];

        $request->update([
            'status'        => 'completed',
            'completed_at'  => now(),
            'execution_log' => $executionLog,
        ]);

        ContentResetAuditLog::create([
            'request_id' => $request->id,
            'action'     => 'EXECUTION_COMMITTED',
            'actor_id'   => $executor->id,
            'payload'    => $executionLog,
        ]);

        ActivityLogger::log(
            'CONTENT_RESET_EXECUTED',
            "Executed {$request->mode} for request {$request->request_number}. Deleted {$deletedCounts['assessments']} assessment(s).",
            $request
        );

        return [
            'success'                      => true,
            'request_number'               => $request->request_number,
            'mode'                         => $request->mode,
            'deleted_counts'               => $deletedCounts,
            'shared_questions_preserved'   => $auditReport['shared_questions_preserved'],
            'integrity_verified'           => true,
            'completed_at'                 => now()->toIso8601String(),
        ];
    }
}
