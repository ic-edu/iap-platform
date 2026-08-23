<?php

namespace App\Services\ContentReset;

use App\Models\ContentResetAuditLog;
use App\Models\ContentResetRequest;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use InvalidArgumentException;

class ContentResetAuditService
{
    /**
     * Perform deep forensic audit on a content reset request.
     *
     * @return array<string, mixed>
     */
    public function performForensicAudit(ContentResetRequest $request): array
    {
        $scope = $request->scope ?? [];
        $targetEntities = $request->target_entities ?? [];
        $mode = $request->mode;

        $blockers = [];
        $warnings = [];

        // 1. Validate Scope & Protected Domains
        foreach ($scope as $domain) {
            if (ContentResetDomains::isProtected($domain)) {
                $blockers[] = "Domain '{$domain}' is absolutely protected and cannot be included in content reset.";
            } elseif (!ContentResetDomains::isResettable($domain)) {
                $blockers[] = "Domain '{$domain}' is not a valid resettable content domain.";
            }
        }

        // 2. Identify Target Assessments
        $targetAssessmentIds = $targetEntities['assessment_ids'] ?? [];

        if (empty($targetAssessmentIds)) {
            if (in_array(ContentResetDomains::DRAFT_ASSESSMENTS, $scope, true)) {
                $draftIds = Test::where('status', 'draft')->pluck('id')->toArray();
                $targetAssessmentIds = array_merge($targetAssessmentIds, $draftIds);
            }
            if (in_array(ContentResetDomains::ARCHIVED_ASSESSMENTS, $scope, true)) {
                $archivedIds = Test::where('status', 'archived')->pluck('id')->toArray();
                $targetAssessmentIds = array_merge($targetAssessmentIds, $archivedIds);
            }
        }

        $targetAssessmentIds = array_unique($targetAssessmentIds);
        $targetAssessments = Test::whereIn('id', $targetAssessmentIds)->get();

        // 3. Mode Rules: Published assessment in routine refresh is blocked
        if ($mode === ContentResetDomains::MODE_REFRESH) {
            foreach ($targetAssessments as $test) {
                if ($test->is_published || $test->status === 'published') {
                    $blockers[] = "Published assessment '{$test->title}' ({$test->id}) cannot be reset in routine Content Refresh mode. Explicit Content Hard Reset is required.";
                }
            }
        }

        // 4. Hard Blockers: Active Assignments, Active In-Progress Attempts, Issued Certificates, Finance
        if (!empty($targetAssessmentIds)) {
            // A. Active assignments
            $activeAssignmentsCount = CandidateTestAssignment::whereIn('test_id', $targetAssessmentIds)
                ->where('status', 'active')
                ->count();
            if ($activeAssignmentsCount > 0) {
                $blockers[] = "Target assessment(s) have {$activeAssignmentsCount} active candidate assignment(s). Active assignments strictly block content reset.";
            }

            // B. Active in-progress attempts
            $inProgressAttemptsCount = Attempt::whereIn('test_id', $targetAssessmentIds)
                ->where('status', AttemptStatus::InProgress)
                ->count();
            if ($inProgressAttemptsCount > 0) {
                $blockers[] = "Target assessment(s) have {$inProgressAttemptsCount} in-progress candidate attempt(s). Active sessions block content reset.";
            }

            // C. Valid issued certificates
            $validCertificatesCount = Certificate::whereIn(
                'attempt_id',
                Attempt::whereIn('test_id', $targetAssessmentIds)->pluck('id')
            )->where('status', 'valid')->count();

            if ($validCertificatesCount > 0) {
                $blockers[] = "Target assessment(s) have {$validCertificatesCount} active digital certificate(s) issued. Valid certificates block content reset.";
            }

            // D. Active commerce orders / payments
            $productIds = Product::whereIn('test_id', $targetAssessmentIds)->pluck('id');
            $activeOrdersCount = OrderItem::whereIn('product_id', $productIds)->count();
            if ($activeOrdersCount > 0) {
                $blockers[] = "Target assessment(s) are bound to {$activeOrdersCount} commercial order item(s). Finance dependencies block content reset.";
            }
        }

        // 5. Dependency Analysis & Shared Content Protection
        $targetSections = TestSection::whereIn('test_id', $targetAssessmentIds)->get();
        $targetSectionIds = $targetSections->pluck('id')->toArray();

        $targetBindings = TestQuestion::whereIn('test_section_id', $targetSectionIds)->get();
        $boundQuestionIds = $targetBindings->pluck('question_id')->unique()->toArray();

        $sharedQuestions = [];
        $deletableQuestions = [];

        foreach ($boundQuestionIds as $qId) {
            $question = Question::find($qId);
            if (!$question) {
                continue;
            }

            // Check if question is referenced by test sections outside the reset target
            $usedElsewhere = TestQuestion::where('question_id', $qId)
                ->whereNotIn('test_section_id', $targetSectionIds)
                ->exists();

            if ($usedElsewhere) {
                $sharedQuestions[] = [
                    'question_id' => $qId,
                    'prompt'      => $question->prompt,
                    'status'      => 'PRESERVED',
                    'reason'      => 'SHARED DEPENDENCY — PRESERVED',
                ];
            } else {
                $deletableQuestions[] = [
                    'question_id' => $qId,
                    'prompt'      => $question->prompt,
                    'status'      => 'SAFE_TO_DELETE',
                ];
            }
        }

        // 6. Attempts and transient UAT data
        $targetAttempts = Attempt::whereIn('test_id', $targetAssessmentIds)->get();

        // 7. Risk Classification & Status
        $isBlocked = count($blockers) > 0;
        $risk = match (true) {
            $isBlocked => 'critical',
            $mode === ContentResetDomains::MODE_HARD_RESET => 'high',
            default => 'low',
        };

        $status = $isBlocked 
            ? 'blocked' 
            : ($mode === ContentResetDomains::MODE_HARD_RESET ? 'awaiting_ceo_approval' : 'audit_completed');

        $auditReport = [
            'audit_generated_at'            => now()->toIso8601String(),
            'is_blocked'                    => $isBlocked,
            'blockers'                      => $blockers,
            'warnings'                      => $warnings,
            'risk_classification'           => $risk,
            'target_assessments_count'      => count($targetAssessmentIds),
            'target_assessment_ids'         => $targetAssessmentIds,
            'target_sections_count'         => $targetSections->count(),
            'test_question_bindings_count'  => $targetBindings->count(),
            'bound_questions_total'         => count($boundQuestionIds),
            'shared_questions_preserved'    => count($sharedQuestions),
            'shared_questions_detail'       => $sharedQuestions,
            'deletable_questions_count'     => count($deletableQuestions),
            'transient_attempts_count'      => $targetAttempts->count(),
            'excluded_protected_domains'    => ContentResetDomains::allProtectedDomains(),
        ];

        // Only update status if not already in an advanced state (approved/executing/completed)
        $preservedStatuses = ['approved', 'executing', 'completed'];
        $statusUpdate = in_array($request->status, $preservedStatuses, true) ? $request->status : $status;

        $request->update([
            'audit_report'        => $auditReport,
            'risk_classification' => $risk,
            'status'              => $isBlocked ? 'blocked' : $statusUpdate,
            'excluded_domains'    => ContentResetDomains::allProtectedDomains(),
        ]);


        ContentResetAuditLog::create([
            'request_id' => $request->id,
            'action'     => 'FORENSIC_AUDIT_GENERATED',
            'actor_id'   => $request->requested_by,
            'payload'    => [
                'is_blocked'          => $isBlocked,
                'risk_classification' => $risk,
                'blockers_count'      => count($blockers),
                'status'              => $status,
            ],
        ]);

        return $auditReport;
    }
}
