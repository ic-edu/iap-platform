<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryApproval;
use App\Models\RepositoryReviewRequest;
use App\Models\RepositoryVersion;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Models\RepositoryRevisionRequest;
use App\Models\RepositoryRevisionItem;
use App\Notifications\EnterpriseSystemNotification;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\AssessmentWorkflowService;

class RepositoryManagerController extends Controller
{
    public function dashboard(AssessmentWorkflowService $workflowService): View
    {
        // Self-Healing Resolution: Ensure every pending_approval QuestionBank has an active OPEN GovernanceApprovalTask
        if (Schema::hasTable('question_banks') && Schema::hasTable('governance_approval_tasks')) {
            $pendingBanks = QuestionBank::whereIn('status', ['pending', 'pending_approval'])->get();
            foreach ($pendingBanks as $bank) {
                \App\Models\GovernanceApprovalTask::firstOrCreate([
                    'question_bank_id' => $bank->id,
                    'status'           => 'OPEN',
                ], [
                    'teacher_id'   => $bank->created_by,
                    'workflow'     => 'APPROVAL',
                    'submitted_at' => $bank->updated_at ?? now(),
                ]);
            }

            // Close any orphan OPEN tasks for non-pending banks (archived, rejected, approved, etc.)
            \App\Models\GovernanceApprovalTask::where('status', 'OPEN')
                ->whereHas('questionBank', function ($q) {
                    $q->whereNotIn('status', ['pending', 'pending_approval']);
                })
                ->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        }

        $pendingGovernanceTaskCount = Schema::hasTable('governance_approval_tasks')
            ? \App\Models\GovernanceApprovalTask::where('status', 'OPEN')
                ->whereHas('questionBank', function ($q) {
                    $q->whereIn('status', ['pending', 'pending_approval']);
                })
                ->count()
            : 0;

        $pendingQuestionsCount = $pendingGovernanceTaskCount > 0
            ? $pendingGovernanceTaskCount
            : (Schema::hasTable('question_banks') ? QuestionBank::whereIn('status', ['pending', 'pending_approval'])->count() : 0);

        $pendingMediaCount = RepositoryReviewRequest::where('status', 'pending_review')->count();

        // Part 8: Total repositories accessible in Explorer
        $totalRepositoriesCount = Schema::hasTable('question_banks')
            ? QuestionBank::count()
            : 0;

        // PART A & PART E: Shared AssessmentWorkflowService Metrics (Single Source of Truth)
        $assessmentMetrics        = $workflowService->getRepositoryManagerMetrics();
        $pendingAssessmentsCount  = $assessmentMetrics['pendingAssessmentsCount'];
        $approvedAssessmentsToday = $assessmentMetrics['approvedAssessmentsToday'];
        $needsRevisionCount       = $assessmentMetrics['needsRevisionCount'];

        // TASK 5: Real Duplicate Count & Dynamic Health Metrics from RepositoryQualityService
        $qualityService  = app(\App\Services\RepositoryQualityService::class);
        $globalSummary   = $qualityService->getGlobalQualitySummary();
        $duplicatesData  = $globalSummary['duplicates'] ?? [];
        $duplicatesCount = count($duplicatesData['duplicate_titles'] ?? []) + count($duplicatesData['duplicate_prompts'] ?? []);
        $repositoryHealthScore = $globalSummary['avg_health_score'] ?? 100;
        $metadataCompleteness  = $qualityService->getAnalyticsData()['metadata_completion'] ?? 100;

        $recentActivityLogs = RepositoryActivityLog::with(['actor', 'reviewer'])
            ->latest()
            ->take(8)
            ->get();

        // PART 4: Refactored Urgent Academic Alerts (Excludes normal pending approvals)
        $irqaFailedCount = Schema::hasTable('repository_findings')
            ? \App\Models\RepositoryFinding::where('severity', 'high')->where('status', 'OPEN')->count()
            : 0;

        $urgentAlerts = [];
        if ($irqaFailedCount > 0) {
            $urgentAlerts[] = [
                'title' => 'Critical IRQA Findings Requiring Review',
                'count' => $irqaFailedCount,
                'type'  => 'urgent',
                'link'  => route('admin.academic-library.quality'),
            ];
        }
        if ($pendingMediaCount > 0) {
            $urgentAlerts[] = [
                'title' => 'Pending Media Review Requests',
                'count' => $pendingMediaCount,
                'type'  => 'warning',
                'link'  => route('admin.repository-manager.media-approval'),
            ];
        }
        if ($duplicatesCount > 0) {
            $urgentAlerts[] = [
                'title' => 'Duplicate Content Detected',
                'count' => $duplicatesCount,
                'type'  => 'warning',
                'link'  => route('admin.repository-manager.duplicates'),
            ];
        }

        $teacherSubmissionsQueue = Schema::hasTable('repository_revision_requests')
            ? \App\Models\RepositoryRevisionRequest::with(['teacher', 'questionBank', 'items.question'])
                ->whereIn('status', ['OPEN', 'RESUBMITTED'])
                ->latest()
                ->take(6)
                ->get()
            : collect([]);

        $teacherPerformanceSummary = [
            ['name' => 'Prof. Alexander Wright', 'submitted' => 18, 'approved' => 16, 'revisions' => 2],
            ['name' => 'Dr. Maria Santos', 'submitted' => 24, 'approved' => 24, 'revisions' => 0],
            ['name' => 'David Kim, M.Ed.', 'submitted' => 12, 'approved' => 10, 'revisions' => 2],
        ];

        $recentlyUpdatedRepositories = Schema::hasTable('question_banks')
            ? QuestionBank::latest()->take(5)->get()
            : collect([]);

        $openApprovalTasks = Schema::hasTable('governance_approval_tasks')
            ? \App\Models\GovernanceApprovalTask::with(['questionBank', 'teacher'])
                ->where('status', 'OPEN')
                ->whereHas('questionBank', function ($q) {
                    $q->whereIn('status', ['pending', 'pending_approval']);
                })
                ->latest()
                ->take(10)
                ->get()
            : collect([]);

        return view('admin.repository_manager.dashboard', compact(
            'pendingQuestionsCount',
            'pendingMediaCount',
            'totalRepositoriesCount',
            'pendingAssessmentsCount',
            'approvedAssessmentsToday',
            'needsRevisionCount',
            'duplicatesCount',
            'metadataCompleteness',
            'repositoryHealthScore',
            'recentActivityLogs',
            'urgentAlerts',
            'teacherSubmissionsQueue',
            'teacherPerformanceSummary',
            'recentlyUpdatedRepositories',
            'openApprovalTasks'
        ));
    }

    public function mediaApprovalCenter(Request $request): View
    {
        $status = $request->query('status', 'pending_review');
        $type = $request->query('type');

        $query = RepositoryReviewRequest::with(['submitter', 'reviewer'])
            ->where('resource_type', 'MediaAsset');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type) {
            $query->whereJsonContains('changes_data->media_type', $type);
        }

        $reviewRequests = $query->latest()->paginate(15);

        return view('admin.repository_manager.media_approval', compact('reviewRequests', 'status', 'type'));
    }

    public function mediaReview(RepositoryReviewRequest $reviewRequest): View
    {
        $media = MediaAsset::find($reviewRequest->resource_id);

        return view('admin.repository_manager.media_review', compact('reviewRequest', 'media'));
    }

    public function approveMedia(Request $request, RepositoryReviewRequest $reviewRequest): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Approved by Repository Manager after quality assurance audit.');

        $media = MediaAsset::findOrFail($reviewRequest->resource_id);

        // Apply changes
        $changes = $reviewRequest->changes_data ?? [];
        if (!empty($changes['new_title'])) $media->title = $changes['new_title'];
        if (!empty($changes['new_description'])) $media->description = $changes['new_description'];
        if (!empty($changes['new_category'])) $media->category = $changes['new_category'];
        if (!empty($changes['new_exam_type'])) $media->exam_type = $changes['new_exam_type'];
        if (!empty($changes['new_content_text'])) $media->content_text = $changes['new_content_text'];
        if (!empty($changes['new_tags'])) $media->tags = $changes['new_tags'];

        // Versioning (PART E)
        $currentVersion = $media->version ?? 'v1.0';
        $parts = explode('.', str_replace('v', '', $currentVersion));
        $newVersionNumber = 'v' . ($parts[0] ?? '1') . '.' . (((int)($parts[1] ?? 0)) + 1);

        $media->version = $newVersionNumber;
        $media->approval_status = 'approved';
        $media->status = 'published';
        $media->save();

        // Create version record
        RepositoryVersion::where('resource_type', 'MediaAsset')
            ->where('resource_id', $media->id)
            ->update(['is_current' => false]);

        RepositoryVersion::create([
            'resource_type'  => 'MediaAsset',
            'resource_id'    => $media->id,
            'version_number' => $newVersionNumber,
            'title'          => $media->title ?? $media->original_name,
            'snapshot_data'  => $media->toArray(),
            'created_by'     => $user->id,
            'change_reason'  => $note,
            'is_current'     => true,
        ]);

        // Update Review Request
        $reviewRequest->update([
            'status'       => 'approved',
            'reviewer_id'  => $user->id,
            'review_notes' => $note,
            'approved_at'  => now(),
        ]);

        // Create Approval Record
        RepositoryApproval::create([
            'review_request_id' => $reviewRequest->id,
            'approved_by'       => $user->id,
            'decision'          => 'approved',
            'notes'             => $note,
        ]);

        // Create Activity Log (PART I)
        RepositoryActivityLog::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $media->id,
            'actor_id'      => $reviewRequest->submitted_by,
            'reviewer_id'   => $user->id,
            'action'        => 'approved',
            'old_values'    => $changes['old_data'] ?? null,
            'new_values'    => $changes,
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $media->id,
                'action'        => 'approved',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'approver_id'   => $user->id,
                'created_by'    => $reviewRequest->submitted_by,
                'version'       => $newVersionNumber,
                'reason'        => $note,
                'metadata'      => [
                    'decision'    => 'approved',
                    'reviewer'    => $user->name,
                    'new_version' => $newVersionNumber,
                ],
            ]);
        }

        if (Schema::hasTable('acl_versions')) {
            \App\Models\AclVersion::where('resource_id', $media->id)->update(['is_current' => false]);
            \App\Models\AclVersion::create([
                'resource_type'  => 'MediaAsset',
                'resource_id'    => $media->id,
                'version_number' => $newVersionNumber,
                'title'          => $media->title ?? $media->original_name,
                'snapshot_data'  => $media->toArray(),
                'created_by'     => $user->id,
                'change_reason'  => $note,
                'is_current'     => true,
            ]);
        }

        return redirect()->route('admin.repository-manager.media-approval')
            ->with('success', "Media Revision Request approved successfully. Updated to version {$newVersionNumber}.");
    }

    public function requestRevisionMedia(Request $request, RepositoryReviewRequest $reviewRequest): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Revision requested. Please address reviewer feedback.');

        $media = MediaAsset::find($reviewRequest->resource_id);
        if ($media) {
            $media->approval_status = 'revision_requested';
            $media->save();
        }

        $reviewRequest->update([
            'status'       => 'revision_requested',
            'reviewer_id'  => $user->id,
            'review_notes' => $note,
        ]);

        RepositoryApproval::create([
            'review_request_id' => $reviewRequest->id,
            'approved_by'       => $user->id,
            'decision'          => 'revision_requested',
            'notes'             => $note,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $reviewRequest->resource_id,
            'actor_id'      => $reviewRequest->submitted_by,
            'reviewer_id'   => $user->id,
            'action'        => 'revision_requested',
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $reviewRequest->resource_id,
                'action'        => 'revision_requested',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'created_by'    => $reviewRequest->submitted_by,
                'version'       => $media?->version ?? '1.0',
                'reason'        => $note,
                'metadata'      => [
                    'decision' => 'revision_requested',
                    'reviewer' => $user->name,
                ],
            ]);
        }

        return redirect()->route('admin.repository-manager.media-approval')
            ->with('warning', 'Revision requested from author.');
    }

    public function rejectMedia(Request $request, RepositoryReviewRequest $reviewRequest): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Revision rejected by Repository Manager.');

        $media = MediaAsset::find($reviewRequest->resource_id);
        if ($media) {
            $media->approval_status = 'rejected';
            $media->save();
        }

        $reviewRequest->update([
            'status'       => 'rejected',
            'reviewer_id'  => $user->id,
            'review_notes' => $note,
        ]);

        RepositoryApproval::create([
            'review_request_id' => $reviewRequest->id,
            'approved_by'       => $user->id,
            'decision'          => 'rejected',
            'notes'             => $note,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'MediaAsset',
            'resource_id'   => $reviewRequest->resource_id,
            'actor_id'      => $reviewRequest->submitted_by,
            'reviewer_id'   => $user->id,
            'action'        => 'rejected',
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'MediaAsset',
                'resource_id'   => $reviewRequest->resource_id,
                'action'        => 'rejected',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'created_by'    => $reviewRequest->submitted_by,
                'version'       => $media?->version ?? '1.0',
                'reason'        => $note,
                'metadata'      => [
                    'decision' => 'rejected',
                    'reviewer' => $user->name,
                ],
            ]);
        }

        return redirect()->route('admin.repository-manager.media-approval')
            ->with('danger', 'Media revision request rejected.');
    }

    public function questionsApproval(): View
    {
        $questionBanks = Schema::hasTable('question_banks')
            ? QuestionBank::whereIn('status', ['pending', 'pending_approval', 'submitted', 'approved'])->latest()->paginate(15)
            : collect([]);

        return view('admin.repository_manager.questions_approval', compact('questionBanks'));
    }

    public function validateQuestionBank(QuestionBank $questionBank): View
    {
        $questionBank->load(['creator', 'questions', 'versions']);

        $questionBank->questions->each(function ($question) {
            if (method_exists($question, 'mediaAsset')) {
                $question->load('mediaAsset');
            }
        });

        $logs = RepositoryActivityLog::where('resource_type', 'QuestionBank')
            ->where('resource_id', $questionBank->id)
            ->with(['actor', 'reviewer'])
            ->latest()
            ->get();

        $activeRevisionRequest = Schema::hasTable('repository_revision_requests')
            ? \App\Models\RepositoryRevisionRequest::where('question_bank_id', $questionBank->id)
                ->whereIn('status', ['OPEN', 'RESUBMITTED'])
                ->with(['teacher', 'requestedBy', 'items.question'])
                ->latest()
                ->first()
            : null;

        return view('admin.repository_manager.question_bank_validate', compact('questionBank', 'logs', 'activeRevisionRequest'));
    }

    public function reviewComplete(QuestionBank $questionBank): View
    {
        $questionBank->load(['creator', 'questions']);

        $latestLog = RepositoryActivityLog::where('resource_type', 'QuestionBank')
            ->where('resource_id', $questionBank->id)
            ->with(['actor', 'reviewer'])
            ->latest()
            ->first();

        return view('admin.repository_manager.review_complete', compact('questionBank', 'latestLog'));
    }

    public function approveQuestionBank(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $governanceStates = ['pending', 'pending_approval', 'submitted', 'approved'];
        if (! in_array($questionBank->status, $governanceStates, true)) {
            return redirect()->back()->with(
                'danger',
                'Governance decisions can only be made for repositories awaiting approval or publication.'
            );
        }

        $user = $request->user();
        $isRestored = ($questionBank->status === 'approved');
        $defaultNote = $isRestored
            ? 'Restored question bank approved and published live by Repository Manager.'
            : 'Question bank approved for institutional publishing.';
        $note = $request->input('notes', $defaultNote) ?: $defaultNote;

        $questionBank->status = 'published';
        $questionBank->is_published = true;
        $questionBank->save();

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'actor_id'      => $questionBank->created_by,
            'reviewer_id'   => $user->id,
            'action'        => 'approved',
            'approval_note' => $note,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'actor_id'      => $questionBank->created_by,
            'reviewer_id'   => $user->id,
            'action'        => 'repository_manager_approved_repository',
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => (string) $questionBank->id,
                'action'        => 'published',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'approver_id'   => $user->id,
                'published_by'  => $user->id,
                'created_by'    => $questionBank->created_by,
                'version'       => $questionBank->current_version ?? '1.0',
                'reason'        => $note,
                'ip_address'    => $request->ip(),
                'metadata'      => [
                    'decision'   => 'published',
                    'reviewer'   => $user->name,
                    'is_restore' => $isRestored,
                ],
            ]);
        }

        if (Schema::hasTable('governance_approval_tasks')) {
            \App\Models\GovernanceApprovalTask::where('question_bank_id', $questionBank->id)
                ->where('status', 'OPEN')
                ->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        }

        // Notify Teacher Author
        if ($questionBank->creator && Schema::hasTable('notifications')) {
            try {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'question_bank_published',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $questionBank->created_by,
                    'data'            => json_encode([
                        'title'            => 'Question Bank Published',
                        'message'          => "Your Question Bank '{$questionBank->title}' has been published live by Repository Manager {$user->name}.",
                        'question_bank_id' => $questionBank->id,
                        'link'             => route('admin.question-banks.show', $questionBank->id),
                        'priority'         => 'HIGH',
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                // Silent in dev
            }
        }

        $successMsg = $isRestored
            ? 'Restored Question Bank successfully published to academic repository.'
            : 'Question bank successfully approved and published to academic repository.';

        return redirect()->route('admin.repository-manager.review-complete', $questionBank->id)
            ->with('success', $successMsg);
    }

    public function requestQuestionBankRevision(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $governanceStates = ['pending', 'pending_approval', 'submitted', 'needs_revision', 'approved'];
        if (! in_array($questionBank->status, $governanceStates, true)) {
            return redirect()->back()->with(
                'danger',
                'Governance decisions can only be made for repositories awaiting approval or active governance review.'
            );
        }

        $user = $request->user();
        $note = $request->input('notes', 'Revision requested. Please fix specified items.');

        $previousBankStatus = $questionBank->status;
        $questionBank->status = 'needs_revision';
        $questionBank->save();

        if (Schema::hasTable('governance_approval_tasks')) {
            \App\Models\GovernanceApprovalTask::where('question_bank_id', $questionBank->id)
                ->where('status', 'OPEN')
                ->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        }

        $teacherId = $questionBank->created_by ?: ($questionBank->creator?->id ?? $user->id);

        // HOTFIX GOVERNANCE WORKFLOW: Reuse existing active RepositoryRevisionRequest if present (prevent duplicate active tasks)
        $revisionRequest = \App\Models\RepositoryRevisionRequest::where('question_bank_id', $questionBank->id)
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->latest()
            ->first();

        if ($revisionRequest) {
            $revisionRequest->update([
                'teacher_id'       => $teacherId,
                'requested_by_id'  => $user->id,
                'status'           => 'OPEN',
                'notes'            => $note,
            ]);
        } else {
            $revisionRequest = \App\Models\RepositoryRevisionRequest::create([
                'question_bank_id' => $questionBank->id,
                'teacher_id'       => $teacherId,
                'requested_by_id'  => $user->id,
                'status'           => 'OPEN',
                'notes'            => $note,
            ]);
        }

        $qualityService = app(\App\Services\RepositoryQualityService::class);
        $audit = $qualityService->syncRepositoryFindings($questionBank);

        if (!empty($audit['warnings'])) {
            foreach ($audit['warnings'] as $warning) {
                \App\Models\RepositoryRevisionItem::firstOrCreate([
                    'repository_revision_request_id' => $revisionRequest->id,
                    'question_bank_id'               => $questionBank->id,
                    'feedback'                       => $warning,
                ], [
                    'finding_type'  => 'quality_warning',
                    'severity'      => 'high',
                    'suggested_fix' => 'Please review and update this repository item.',
                    'status'        => 'OPEN',
                ]);
            }
        } else {
            \App\Models\RepositoryRevisionItem::firstOrCreate([
                'repository_revision_request_id' => $revisionRequest->id,
                'question_bank_id'               => $questionBank->id,
                'feedback'                       => $note,
            ], [
                'finding_type'  => 'reviewer_feedback',
                'severity'      => 'medium',
                'suggested_fix' => 'Address reviewer notes in repository.',
                'status'        => 'OPEN',
            ]);
        }

        if (Schema::hasTable('notifications')) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'repository_revision_requested',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $questionBank->created_by,
                'data'            => json_encode([
                    'title'   => 'Repository Revision Requested',
                    'message' => "Repository Manager requested revision for '{$questionBank->title}'. Notes: {$note}",
                    'link'    => route('teacher.repository-revisions.show', $revisionRequest->id),
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'actor_id'      => $questionBank->created_by,
            'reviewer_id'   => $user->id,
            'action'        => 'revision_requested',
            'approval_note' => $note,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'actor_id'      => $questionBank->created_by,
            'reviewer_id'   => $user->id,
            'action'        => 'repository_manager_requested_revision',
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => (string) $questionBank->id,
                'action'        => 'question_bank_revision_requested',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'created_by'    => $questionBank->created_by,
                'version'       => '1.0',
                'reason'        => $note,
                'ip_address'    => $request->ip(),
                'metadata'      => [
                    'previous_status' => $previousBankStatus,
                    'new_status'      => 'needs_revision',
                    'reviewer'        => $user->name,
                ],
            ]);
        }

        // BUG #2 FIX: Synchronize all linked Assessments containing questions from this QuestionBank
        $questionIds = \App\Modules\QuestionBank\Models\Question::where('question_bank_id', $questionBank->id)->pluck('id');
        $sectionIds  = \App\Modules\Assessment\Models\TestQuestion::whereIn('question_id', $questionIds)->pluck('test_section_id');
        $testIds     = \App\Modules\Assessment\Models\TestSection::whereIn('id', $sectionIds)->pluck('test_id')->unique();

        $linkedTests = Test::whereIn('id', $testIds)->get();
        foreach ($linkedTests as $test) {
            $previousTestStatus = $test->status;
            $test->status = 'needs_revision';
            $test->is_published = false;
            $test->save();

            RepositoryActivityLog::create([
                'resource_type' => 'Test',
                'resource_id'   => (string) $test->id,
                'actor_id'      => $test->created_by,
                'reviewer_id'   => $user->id,
                'action'        => 'revision_requested',
                'approval_note' => "Cascade revision request due to linked Question Bank '{$questionBank->title}' returning for revision.",
            ]);

            if (Schema::hasTable('acl_audit_trails')) {
                \App\Models\AclAuditTrail::create([
                    'resource_type' => 'Test',
                    'resource_id'   => (string) $test->id,
                    'action'        => 'assessment_revision_requested',
                    'actor_id'      => $user->id,
                    'reviewer_id'   => $user->id,
                    'created_by'    => $test->created_by,
                    'version'       => '1.0',
                    'reason'        => "Cascade revision request from Question Bank '{$questionBank->title}'",
                    'ip_address'    => $request->ip(),
                    'metadata'      => [
                        'previous_status'   => $previousTestStatus,
                        'new_status'        => 'needs_revision',
                        'parent_bank_id'    => $questionBank->id,
                        'parent_bank_title' => $questionBank->title,
                    ],
                ]);
            }
        }

        return redirect()->route('admin.repository-manager.review-complete', $questionBank->id)
            ->with('warning', 'Revision requested from author for Question Bank and linked Assessments.');
    }

    public function rejectQuestionBank(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $governanceStates = ['pending', 'pending_approval', 'submitted'];
        if (! in_array($questionBank->status, $governanceStates, true)) {
            return redirect()->back()->with(
                'danger',
                'Governance decisions can only be made for repositories awaiting approval.'
            );
        }

        $user = $request->user();
        $note = $request->input('notes', 'Question bank repository rejected and archived.');

        $questionBank->status = 'archived';
        $questionBank->is_published = false;
        $questionBank->save();

        if (Schema::hasTable('governance_approval_tasks')) {
            \App\Models\GovernanceApprovalTask::where('question_bank_id', $questionBank->id)
                ->where('status', 'OPEN')
                ->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        }

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'actor_id'      => $questionBank->created_by,
            'reviewer_id'   => $user->id,
            'action'        => 'rejected',
            'approval_note' => $note,
        ]);

        $authorId = $questionBank->created_by ?: ($questionBank->creator?->id ?? null);
        if ($authorId && Schema::hasTable('notifications')) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'repository_rejected',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $authorId,
                'data'            => json_encode([
                    'title'            => 'Repository Rejected & Archived',
                    'message'          => "Repository Manager rejected '{$questionBank->title}'. Reason: {$note}",
                    'rejection_reason' => $note,
                    'question_bank_id' => $questionBank->id,
                    'link'             => route('teacher.question-banks.show', $questionBank->id),
                    'priority'         => 'HIGH',
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return redirect()->route('admin.repository-manager.review-complete', $questionBank->id)
            ->with('danger', 'Question bank repository rejected and archived.');
    }

    public function duplicates(): View
    {
        return view('admin.repository_manager.duplicates');
    }

    /**
     * Display Assessment Approval Queue for Repository Manager (TASK 1).
     */
    public function assessmentApprovalCenter(Request $request): View
    {
        $status = $request->query('status', 'pending');

        $query = Test::with(['creator', 'sections.testQuestions']);

        if ($status !== 'all') {
            if ($status === 'pending') {
                $query->whereIn('status', ['pending', 'pending_approval']);
            } else {
                $query->where('status', $status);
            }
        }

        $assessments = $query->latest()->paginate(15);
        $pendingCount = Test::whereIn('status', ['pending', 'pending_approval'])->count();
        $approvedCount = Test::where('status', 'approved')->count();
        $needsRevisionCount = Test::whereIn('status', ['needs_revision', 'revision_requested', 'rejected'])->count();

        return view('admin.repository_manager.assessment_approval', compact(
            'assessments',
            'status',
            'pendingCount',
            'approvedCount',
            'needsRevisionCount'
        ));
    }

    /**
     * Review assessment details with Question Review Items & Progress (TASK 1, 3).
     */
    public function assessmentReview(Test $test): View
    {
        $test->load([
            'creator',
            'sections.testQuestions.question.questionBank',
            'sections.testQuestions.question.choices',
            'sections.testQuestions.question.mediaAsset',
            'sections.testQuestions.question.passage',
        ]);

        $questionReviews = \App\Models\TestQuestionReview::where('test_id', (string) $test->id)
            ->get()
            ->keyBy('question_id');

        $totalQuestionsCount = 0;
        foreach ($test->sections as $sec) {
            $totalQuestionsCount += $sec->testQuestions->count();
        }

        $flaggedQuestions = $questionReviews->whereIn('status', ['needs_revision', 'critical_issue']);
        $flaggedQuestionsCount = $flaggedQuestions->count();
        $criticalCount = $questionReviews->where('status', 'critical_issue')->count();
        $defaultOkCount = max(0, $totalQuestionsCount - $flaggedQuestionsCount);

        // Business Rule 1 & 5: Review by Exception - Approved allowed if flaggedQuestionsCount === 0
        $isApprovalAllowed = ($totalQuestionsCount > 0 && $flaggedQuestionsCount === 0);
        $isRevisionAllowed = ($flaggedQuestionsCount > 0);

        $reviewProgress = [
            'total'               => $totalQuestionsCount,
            'default_ok'          => $defaultOkCount,
            'flagged'             => $flaggedQuestionsCount,
            'critical'            => $criticalCount,
            'is_allowed'          => $isApprovalAllowed,
            'is_revision_allowed' => $isRevisionAllowed,
        ];

        $logs = RepositoryActivityLog::where('resource_type', 'Test')
            ->where('resource_id', (string) $test->id)
            ->with(['actor', 'reviewer'])
            ->latest()
            ->get();

        return view('admin.repository_manager.assessment_review', compact('test', 'logs', 'questionReviews', 'reviewProgress'));
    }

    /**
     * Clear flag / Mark question as Default OK (BUSINESS RULE 1 & UX).
     */
    public function markQuestionReviewed(Request $request, Test $test, \App\Modules\QuestionBank\Models\Question $question): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        \App\Models\TestQuestionReview::where('test_id', (string) $test->id)
            ->where('question_id', (string) $question->id)
            ->delete();

        // Calculate updated counts
        $allReviews = \App\Models\TestQuestionReview::where('test_id', (string) $test->id)->get();
        $flaggedCount = $allReviews->whereIn('status', ['needs_revision', 'critical_issue'])->count();
        
        $totalQuestionsCount = 0;
        foreach ($test->sections as $sec) {
            $totalQuestionsCount += $sec->testQuestions->count();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'             => true,
                'message'             => 'Flag cleared — Question marked OK.',
                'question_id'         => (string) $question->id,
                'status'              => 'default_ok',
                'flagged_count'       => $flaggedCount,
                'default_ok_count'    => max(0, $totalQuestionsCount - $flaggedCount),
                'is_allowed'          => ($totalQuestionsCount > 0 && $flaggedCount === 0),
                'is_revision_allowed' => ($flaggedCount > 0),
            ]);
        }

        return redirect()->back()->with('success', "Question #{$question->id} flag cleared.");
    }

    /**
     * Request revision or mark critical issue for a specific question (BUSINESS RULE 2 & 3).
     */
    public function requestQuestionRevision(Request $request, Test $test, \App\Modules\QuestionBank\Models\Question $question): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status'   => ['nullable', 'string'],
            'field'    => ['required', 'string'],
            'comment'  => ['required', 'string'],
            'severity' => ['nullable', 'string'],
        ]);

        $status = $validated['status'] ?? ($validated['severity'] === 'critical' ? 'critical_issue' : 'needs_revision');

        $review = \App\Models\TestQuestionReview::updateOrCreate(
            ['test_id' => (string) $test->id, 'question_id' => (string) $question->id],
            [
                'status'      => $status,
                'field'       => $validated['field'],
                'comment'     => $validated['comment'],
                'severity'    => $validated['severity'] ?? 'warning',
                'reviewer_id' => $user->id,
            ]
        );

        // Derive Assessment Status -> needs_revision
        $test->update([
            'status'       => 'needs_revision',
            'is_published' => false,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $test->created_by ?? $user->id,
            'reviewer_id'   => $user->id,
            'action'        => 'revision_requested',
            'approval_note' => "Question flagged for revision on field '{$validated['field']}': {$validated['comment']}",
        ]);

        // Calculate updated counts
        $allReviews = \App\Models\TestQuestionReview::where('test_id', (string) $test->id)->get();
        $flaggedCount = $allReviews->whereIn('status', ['needs_revision', 'critical_issue'])->count();
        
        $totalQuestionsCount = 0;
        foreach ($test->sections as $sec) {
            $totalQuestionsCount += $sec->testQuestions->count();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'             => true,
                'message'             => 'Review saved.',
                'question_id'         => (string) $question->id,
                'status'              => $status,
                'field'               => $validated['field'],
                'comment'             => $validated['comment'],
                'severity'            => $validated['severity'] ?? 'warning',
                'flagged_count'       => $flaggedCount,
                'default_ok_count'    => max(0, $totalQuestionsCount - $flaggedCount),
                'is_allowed'          => ($totalQuestionsCount > 0 && $flaggedCount === 0),
                'is_revision_allowed' => ($flaggedCount > 0),
            ]);
        }

        return redirect()->back()->with('warning', "Question #{$question->id} flagged for revision.");
    }

    /**
     * Approve assessment with Review by Exception Guard (BUSINESS RULE 5).
     */
    public function approveAssessment(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        $test->load(['sections.testQuestions']);
        $totalQuestionsCount = $test->sections->sum(fn($sec) => $sec->testQuestions->count());
        if ($totalQuestionsCount === 0) {
            return redirect()->back()->with('error', 'Cannot approve empty assessment with 0 questions.');
        }

        // BUSINESS RULE 5: Approve Guard (Flagged Questions === 0)
        $flaggedCount = \App\Models\TestQuestionReview::where('test_id', (string) $test->id)
            ->whereIn('status', ['needs_revision', 'critical_issue'])
            ->count();

        if ($flaggedCount > 0) {
            return redirect()->back()->with('error', "Cannot approve assessment. {$flaggedCount} question(s) are flagged for revision. Resolve all flags before approving.");
        }

        $note = $request->input('notes', 'Assessment approved by Repository Manager.');

        $previousStatus = $test->status;
        $test->status = 'approved';
        $test->is_published = false;
        $test->save();

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $test->created_by ?? $user->id,
            'reviewer_id'   => $user->id,
            'action'        => 'approved',
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'Test',
                'resource_id'   => (string) $test->id,
                'action'        => 'assessment_approved',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'created_by'    => $test->created_by ?? $user->id,
                'version'       => '1.0',
                'reason'        => $note,
                'ip_address'    => $request->ip(),
                'metadata'      => [
                    'previous_status' => $previousStatus,
                    'new_status'      => 'approved',
                    'reviewer'        => $user->name,
                ],
            ]);
        }

        if ($test->creator) {
            try {
                $test->creator->notify(new \App\Notifications\EnterpriseSystemNotification(
                    title: 'Assessment Approved',
                    message: "Your Assessment Test '{$test->title}' was approved by Repository Manager {$user->name} and is ready for publication.",
                    type: 'ASSESSMENT_APPROVED',
                    priority: 'HIGH',
                    entityType: 'test',
                    entityId: (string) $test->id,
                    targetUrl: route('admin.tests.show', $test->id)
                ));
            } catch (\Throwable $e) {
                // Silently skip
            }
        }

        return redirect()->route('admin.repository-manager.assessment-review', $test->id)
            ->with('success', "Assessment '{$test->title}' approved successfully and is ready for publication.");
    }

    /**
     * Request revision for assessment (TASK 5).
     */
    public function requestRevisionAssessment(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Revision requested by Repository Manager.');

        $previousStatus = $test->status;
        $test->status = 'needs_revision';
        $test->is_published = false;
        $test->save();

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $test->created_by ?? $user->id,
            'reviewer_id'   => $user->id,
            'action'        => 'revision_requested',
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'Test',
                'resource_id'   => (string) $test->id,
                'action'        => 'assessment_revision_requested',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'created_by'    => $test->created_by ?? $user->id,
                'version'       => '1.0',
                'reason'        => $note,
                'ip_address'    => $request->ip(),
                'metadata'      => [
                    'previous_status' => $previousStatus,
                    'new_status'      => 'needs_revision',
                    'reviewer'        => $user->name,
                ],
            ]);
        }

        if ($test->creator) {
            try {
                $test->creator->notify(new \App\Notifications\EnterpriseSystemNotification(
                    title: 'Assessment Revision Requested',
                    message: "Your Assessment Test '{$test->title}' requires revision. Reviewer notes: {$note}",
                    type: 'ASSESSMENT_REVISION_REQUESTED',
                    priority: 'HIGH',
                    entityType: 'test',
                    entityId: (string) $test->id,
                    targetUrl: route('admin.tests.show', $test->id)
                ));
            } catch (\Throwable $e) {
                // Silently skip
            }
        }

        return redirect()->route('admin.repository-manager.assessment-approval')
            ->with('warning', "Revision requested for Assessment '{$test->title}'. Author notified.");
    }

    /**
     * Send Assessment to Archived (RM Decision: Rejected & Archived).
     */
    public function archiveAssessment(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        $note = $request->input('notes', 'Assessment submission rejected and moved to Archived.');

        $previousStatus = $test->status;
        $test->status = 'archived';
        $test->is_published = false;
        $test->save();

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $test->created_by ?? $user->id,
            'reviewer_id'   => $user->id,
            'action'        => 'assessment_archived',
            'approval_note' => $note,
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'Test',
                'resource_id'   => (string) $test->id,
                'action'        => 'assessment_archived',
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'created_by'    => $test->created_by ?? $user->id,
                'version'       => '1.0',
                'reason'        => $note,
                'ip_address'    => $request->ip(),
                'metadata'      => [
                    'previous_status' => $previousStatus,
                    'new_status'      => 'archived',
                    'reviewer'        => $user->name,
                ],
            ]);
        }

        $author = $test->assignedTeacher ?? $test->creator;
        if ($author) {
            try {
                $author->notify(new \App\Notifications\EnterpriseSystemNotification(
                    title: 'Assessment Submission Rejected and Archived',
                    message: "Assessment submission '{$test->title}' was rejected and moved to Archived. Reason: {$note}",
                    type: 'ASSESSMENT_REJECTED_AND_ARCHIVED',
                    priority: 'HIGH',
                    entityType: 'test',
                    entityId: (string) $test->id,
                    targetUrl: route('admin.tests.show', $test->id)
                ));
            } catch (\Throwable $e) {
                // Silently skip
            }
        }

        return redirect()->route('admin.repository-manager.assessment-approval')
            ->with('status', "Assessment '{$test->title}' has been rejected and moved to Archived.");
    }

    /**
     * Backward compatibility alias for Reject Assessment.
     */
    public function rejectAssessment(Request $request, Test $test): RedirectResponse
    {
        return $this->archiveAssessment($request, $test);
    }

    /**
     * Submit assessment for review (TASK 2).
     */
    public function submitAssessmentForReview(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        $validationResult = app(\App\Modules\Assessment\Services\TestBuilderService::class)->validateAssessment($test);
        if (!$validationResult['is_valid']) {
            return back()->with('error', "Cannot submit Assessment for review: " . implode(' | ', $validationResult['errors']));
        }

        $previousStatus = $test->status;

        $test->status = 'pending';
        $test->save();

        RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $user->id,
            'action'        => 'submitted',
            'approval_note' => 'Assessment submitted for Repository Manager review.',
        ]);

        if (Schema::hasTable('acl_audit_trails')) {
            \App\Models\AclAuditTrail::create([
                'resource_type' => 'Test',
                'resource_id'   => (string) $test->id,
                'action'        => 'assessment_submitted',
                'actor_id'      => $user->id,
                'created_by'    => $user->id,
                'version'       => '1.0',
                'reason'        => 'Teacher submitted assessment for Repository Manager review',
                'ip_address'    => $request->ip(),
                'metadata'      => [
                    'previous_status' => $previousStatus,
                    'new_status'      => 'pending',
                    'submitter'       => $user->name,
                ],
            ]);
        }

        return back()->with('status', 'Assessment submitted successfully for Repository Manager review.');
    }

    /**
     * Dedicated Repository Revision Queue for Repository Managers (SPRINT R1).
     */
    public function revisionsQueue(Request $request): View
    {
        $statusFilter = $request->input('status', 'actionable');

        $query = RepositoryRevisionRequest::with(['teacher', 'questionBank', 'items.question']);

        if ($statusFilter === 'actionable') {
            $query->whereIn('status', ['OPEN', 'RESUBMITTED']);
        } elseif ($statusFilter === 'open') {
            $query->where('status', 'OPEN');
        } elseif ($statusFilter === 'resubmitted') {
            $query->where('status', 'RESUBMITTED');
        } elseif ($statusFilter === 'needs_revision') {
            $query->where('status', 'IN_PROGRESS');
        } elseif ($statusFilter === 'approved') {
            $query->where('status', 'COMPLETED');
        } elseif ($statusFilter === 'rejected') {
            $query->where('status', 'REJECTED');
        }

        $revisions = $query->latest()->paginate(15)->withQueryString();

        $metrics = [
            'open_count'        => RepositoryRevisionRequest::where('status', 'OPEN')->count(),
            'resubmitted_count' => RepositoryRevisionRequest::where('status', 'RESUBMITTED')->count(),
            'needs_rev_count'   => RepositoryRevisionRequest::where('status', 'IN_PROGRESS')->count(),
            'approved_count'    => RepositoryRevisionRequest::where('status', 'COMPLETED')->count(),
        ];

        return view('admin.repository_manager.revisions_queue', compact('revisions', 'statusFilter', 'metrics'));
    }

    /**
     * Dedicated Repository Revision Review Workspace (SPRINT R1).
     */
    public function reviewRevision(RepositoryRevisionRequest $revisionRequest): View
    {
        $revisionRequest->load([
            'questionBank.questions.choices',
            'questionBank.questions.mediaAsset',
            'teacher',
            'requestedBy',
            'items.question.choices',
            'items.question.mediaAsset',
        ]);

        $questionBank = $revisionRequest->questionBank;

        $logs = RepositoryActivityLog::where('resource_type', 'QuestionBank')
            ->where('resource_id', $questionBank?->id)
            ->orWhere(function ($q) use ($revisionRequest) {
                $q->where('resource_type', 'Question')
                    ->whereIn('resource_id', $revisionRequest->items->pluck('question_id')->filter());
            })
            ->with(['actor', 'reviewer'])
            ->latest()
            ->take(15)
            ->get();

        return view('admin.repository_manager.revision_review', compact('revisionRequest', 'questionBank', 'logs'));
    }

    /**
     * RM Approves and atomically applies staged revision changes (SPRINT R1).
     */
    public function approveRevision(Request $request, RepositoryRevisionRequest $revisionRequest): RedirectResponse
    {
        if (!in_array($revisionRequest->status, ['OPEN', 'RESUBMITTED'], true)) {
            return redirect()->back()->with('error', 'Only active or resubmitted revision requests can be approved.');
        }

        $user = $request->user();
        $notes = $request->input('notes', 'Repository revision approved and applied to master repository.');

        DB::transaction(function () use ($revisionRequest, $user, $notes) {
            $bank = $revisionRequest->questionBank;

            foreach ($revisionRequest->items as $item) {
                if (!empty($item->proposed_data) && $item->question_id) {
                    $question = Question::find($item->question_id);
                    if ($question) {
                        $p = $item->proposed_data;

                        // Apply proposed core fields to Master Question
                        $question->prompt        = $p['prompt'] ?? $question->prompt;
                        $question->question_type = $p['question_type'] ?? $question->question_type;
                        $question->explanation   = $p['explanation'] ?? $question->explanation;
                        $question->difficulty    = $p['difficulty'] ?? $question->difficulty;
                        $question->points        = $p['points'] ?? $question->points;
                        if (isset($p['part_number'])) {
                            $question->part_number = $p['part_number'];
                        }
                        if (isset($p['section'])) {
                            $question->section = $p['section'];
                        }
                        if (array_key_exists('image_url', $p)) {
                            $question->image_url = $p['image_url'];
                        }
                        if (array_key_exists('audio_url', $p)) {
                            $question->audio_url = $p['audio_url'];
                        }
                        if (array_key_exists('passage_id', $p)) {
                            $question->passage_id = $p['passage_id'];
                        }
                        if (array_key_exists('passage_text', $p)) {
                            $question->passage_text = $p['passage_text'];
                        }
                        if (array_key_exists('audio_group_id', $p)) {
                            $question->audio_group_id = $p['audio_group_id'];
                        }
                        if (array_key_exists('passage_group_id', $p)) {
                            $question->passage_group_id = $p['passage_group_id'];
                        }
                        if (array_key_exists('media_asset_id', $p)) {
                            $question->media_asset_id = $p['media_asset_id'];
                        }
                        $question->save();

                        // Apply proposed choices
                        if (!empty($p['choices']) && is_array($p['choices'])) {
                            $existingChoices = $question->choices->keyBy('id');
                            $updatedIds = [];

                            foreach ($p['choices'] as $key => $c) {
                                $choiceId = is_numeric($key) && $existingChoices->has($key) ? $key : ($c['id'] ?? null);
                                if ($choiceId && $existingChoice = $existingChoices->get($choiceId)) {
                                    $existingChoice->label      = $c['label'] ?? 'A';
                                    $existingChoice->content    = $c['content'] ?? '';
                                    $existingChoice->is_correct = !empty($c['is_correct']);
                                    $existingChoice->save();
                                    $updatedIds[] = $existingChoice->id;
                                } else {
                                    $newChoice = QuestionChoice::create([
                                        'question_id' => $question->id,
                                        'label'       => $c['label'] ?? 'A',
                                        'content'     => $c['content'] ?? '',
                                        'is_correct'  => !empty($c['is_correct']),
                                    ]);
                                    $updatedIds[] = $newChoice->id;
                                }
                            }
                            $question->choices()->whereNotIn('id', $updatedIds)->delete();
                        }

                        // Category update if present
                        if (!empty($p['category_id']) && $bank) {
                            $bank->acl_category_id = $p['category_id'];
                            $bank->save();
                        }
                    }
                }
                $item->status = 'VERIFIED';
                $item->save();
            }

            // Mark Revision Request as COMPLETED
            $revisionRequest->status = 'COMPLETED';
            $revisionRequest->resolved_at = now();
            $revisionRequest->save();

            // Activity Log
            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => (string) ($bank?->id ?? $revisionRequest->question_bank_id),
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'action'        => 'rm_revision_approved',
                'approval_note' => $notes,
            ]);

            // Notify Teacher
            $teacher = $revisionRequest->teacher;
            if ($teacher) {
                $teacher->notify(new EnterpriseSystemNotification(
                    'Repository Revision Approved',
                    "Your revision for repository '{$bank?->title}' has been approved and published to the live repository.",
                    'REPOSITORY_REVISION_APPROVED',
                    'NORMAL',
                    route('teacher.repository-revisions.show', $revisionRequest->id)
                ));
            }
        });

        return redirect()->route('admin.repository-manager.revisions.index')
            ->with('status', 'Repository revision approved and changes successfully applied to the published master repository.');
    }

    /**
     * RM Requests further changes from Teacher (SPRINT R1).
     */
    public function requestRevisionChanges(Request $request, RepositoryRevisionRequest $revisionRequest): RedirectResponse
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $notes = $request->input('notes');

        DB::transaction(function () use ($revisionRequest, $user, $notes) {
            $bank = $revisionRequest->questionBank;

            $revisionRequest->status = 'IN_PROGRESS';
            $revisionRequest->notes = $notes;
            $revisionRequest->save();

            $revisionRequest->items()->update(['status' => 'OPEN']);

            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => (string) ($bank?->id ?? $revisionRequest->question_bank_id),
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'action'        => 'rm_revision_changes_requested',
                'approval_note' => $notes,
            ]);

            $teacher = $revisionRequest->teacher;
            if ($teacher) {
                $teacher->notify(new EnterpriseSystemNotification(
                    'Revision Changes Requested',
                    "Repository Manager requested changes on repository '{$bank?->title}': {$notes}",
                    'REPOSITORY_REVISION_CHANGES_REQUESTED',
                    'HIGH',
                    route('teacher.repository-revisions.show', $revisionRequest->id)
                ));
            }
        });

        return redirect()->route('admin.repository-manager.revisions.index')
            ->with('status', 'Changes requested from teacher for this repository revision.');
    }

    /**
     * RM Rejects Revision Request (SPRINT R1).
     */
    public function rejectRevision(Request $request, RepositoryRevisionRequest $revisionRequest): RedirectResponse
    {
        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $notes = $request->input('notes');

        DB::transaction(function () use ($revisionRequest, $user, $notes) {
            $bank = $revisionRequest->questionBank;

            $revisionRequest->status = 'REJECTED';
            $revisionRequest->resolved_at = now();
            $revisionRequest->save();

            $revisionRequest->items()->update(['status' => 'DISMISSED']);

            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => (string) ($bank?->id ?? $revisionRequest->question_bank_id),
                'actor_id'      => $user->id,
                'reviewer_id'   => $user->id,
                'action'        => 'rm_revision_rejected',
                'approval_note' => $notes,
            ]);

            $teacher = $revisionRequest->teacher;
            if ($teacher) {
                $teacher->notify(new EnterpriseSystemNotification(
                    'Repository Revision Rejected',
                    "Your revision for repository '{$bank?->title}' was rejected: {$notes}",
                    'REPOSITORY_REVISION_REJECTED',
                    'NORMAL',
                    route('teacher.repository-revisions.show', $revisionRequest->id)
                ));
            }
        });

        return redirect()->route('admin.repository-manager.revisions.index')
            ->with('status', 'Repository revision rejected.');
    }
}
