<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AclCategory;
use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\RepositoryQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TeacherRepositoryRevisionController extends Controller
{
    /**
     * Display listing of pending repository revision requests for teacher.
     */
    public function index(Request $request): View
    {
        $teacherId = Auth::id();

        $revisionRequests = RepositoryRevisionRequest::with(['questionBank', 'requestedBy', 'items'])
            ->where(function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId)
                  ->orWhereHas('questionBank', function ($bq) use ($teacherId) {
                      $bq->where('created_by', $teacherId);
                  });
            })
            ->latest()
            ->paginate(12);

        return view('teacher.repository_revisions.index', compact('revisionRequests'));
    }

    /**
     * Workspace for inspecting repository issues.
     */
    public function show(Request $request, RepositoryRevisionRequest $revisionRequest): View
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $revisionRequest->question_bank_id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_opened_revision',
            'approval_note' => 'Teacher opened repository revision task.',
        ]);

        $revisionRequest->load(['questionBank.questions.choices', 'requestedBy', 'items.question']);

        return view('teacher.repository_revisions.show', compact('revisionRequest'));
    }

    /**
     * SPRINT RRUXO-REVISION-EDITOR-ENHANCEMENT: Full Question Editor in Repository Revision Mode.
     */
    public function editQuestion(Request $request, RepositoryRevisionRequest $revisionRequest, RepositoryRevisionItem $item): View
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $revisionRequest->load(['questionBank', 'requestedBy', 'items']);
        $item->load(['question.choices', 'question.mediaAsset']);

        $question = $item->question ?? $revisionRequest->questionBank->questions()->first();
        $bank     = $revisionRequest->questionBank;

        // Categories & Media assets for dropdown selection
        $categories  = AclCategory::all();
        $mediaAssets = Schema::hasTable('media_assets') ? MediaAsset::latest()->take(50)->get() : collect();

        // Compute Live Validation Checklist
        $validationData = $this->computeQuestionValidation($question, $bank);

        RepositoryActivityLog::create([
            'resource_type' => 'Question',
            'resource_id'   => (string) ($question->id ?? $item->id),
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_edited_question',
            'approval_note' => "Teacher editing question #{$question->id} in Full Repository Revision Editor.",
        ]);

        return view('teacher.repository_revisions.focused_editor', compact(
            'revisionRequest',
            'item',
            'question',
            'bank',
            'categories',
            'mediaAssets',
            'validationData'
        ));
    }

    /**
     * SPRINT RRUXO-REVISION-EDITOR-ENHANCEMENT: Full Save Question Workflow with Live Validation.
     */
    public function updateQuestion(Request $request, RepositoryRevisionRequest $revisionRequest, RepositoryRevisionItem $item): RedirectResponse
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $question = $item->question ?? Question::findOrFail($request->input('question_id'));
        $bank     = $revisionRequest->questionBank;

        // 1. Update Question core fields
        $question->prompt        = $request->input('prompt', $question->prompt);
        $question->question_type = $request->input('question_type', $question->question_type ?? 'multiple_choice');
        $question->explanation   = $request->input('explanation', $question->explanation);
        $question->difficulty    = $request->input('difficulty', $question->difficulty);
        $question->points        = $request->input('points', $question->points);

        // Update Media Attachment
        if ($request->has('remove_media') && $request->input('remove_media') == '1') {
            $question->media_asset_id = null;
        } elseif ($request->filled('media_asset_id')) {
            $question->media_asset_id = $request->input('media_asset_id');
        }

        $question->save();

        // 2. Update Question Bank Category if selected
        if ($request->filled('category_id') && $bank) {
            $bank->acl_category_id = $request->input('category_id');
            $bank->save();
        }

        // 3. Update / Create Answer Choices & Correct Answer Selector
        if ($request->has('choices')) {
            $correctChoiceId = $request->input('correct_choice_id');
            foreach ($request->input('choices', []) as $cId => $cData) {
                if (is_numeric($cId) || strlen($cId) > 10) {
                    $choice = QuestionChoice::find($cId);
                    if ($choice) {
                        $choice->content    = $cData['content'] ?? $choice->content;
                        $choice->label      = $cData['label'] ?? $choice->label;
                        $choice->is_correct = ($correctChoiceId == $cId) || (isset($cData['is_correct']) && $cData['is_correct'] == '1');
                        $choice->save();
                    }
                }
            }
        }

        // Handle Adding New Answer Choice
        if ($request->filled('new_choice_content')) {
            QuestionChoice::create([
                'question_id' => $question->id,
                'label'       => $request->input('new_choice_label', 'A'),
                'content'     => $request->input('new_choice_content'),
                'is_correct'  => $request->has('new_choice_is_correct'),
            ]);
        }

        // 4. Run IRQA Validation Check
        $qualityService = app(RepositoryQualityService::class);
        $rescanAudit = $qualityService->validateRepository($bank);
        $currentWarnings = $rescanAudit['warnings'] ?? [];

        $stillPresent = false;
        foreach ($currentWarnings as $cw) {
            if (str_contains(strtolower($item->feedback), strtolower(substr($cw, 0, 15)))) {
                $stillPresent = true;
                break;
            }
        }

        if (!$stillPresent) {
            $item->status = 'CLOSED';
            $item->save();
        }

        RepositoryActivityLog::create([
            'resource_type' => 'Question',
            'resource_id'   => (string) $question->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_saved_revision',
            'approval_note' => "Teacher saved full question revision for item #{$item->id}.",
        ]);

        if ($item->status === 'CLOSED') {
            return redirect()->route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id])
                ->with('success', '✔ Finding Fixed! Validation passed for this item. Complete remaining findings to resubmit.');
        }

        return redirect()->route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id])
            ->with('info', 'Question saved. Please review the live validation checklist below to ensure all requirements are satisfied.');
    }

    /**
     * Resubmit repository & trigger Automatic IRQA Re-Scan.
     */
    public function resubmit(Request $request, RepositoryRevisionRequest $revisionRequest): RedirectResponse
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        // Auto Validate Before Resubmit (Check remaining open findings)
        $unresolvedCount = $revisionRequest->items()->where('status', '!=', 'CLOSED')->count();
        if ($unresolvedCount > 0) {
            return redirect()->back()
                ->with('error', "Cannot resubmit repository. Remaining unresolved findings ({$unresolvedCount}) must be fixed first.");
        }

        $bank = $revisionRequest->questionBank;

        // 1. Audit Log: Teacher Resubmitted Repository
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_resubmitted_repository',
            'approval_note' => 'Teacher resubmitted repository for governance review.',
        ]);

        // 2. Update status
        $revisionRequest->status = 'RESUBMITTED';
        $revisionRequest->save();

        if ($bank) {
            $bank->status = 'pending_approval';
            $bank->save();

            // Guarantee NEW GovernanceApprovalTask creation upon resubmission (Idempotent)
            if (\Illuminate\Support\Facades\Schema::hasTable('governance_approval_tasks')) {
                \App\Models\GovernanceApprovalTask::firstOrCreate([
                    'question_bank_id' => $bank->id,
                    'status'           => 'OPEN',
                ], [
                    'teacher_id'       => Auth::id(),
                    'workflow'         => 'APPROVAL',
                    'submitted_at'     => now(),
                ]);
            }

            // Dispatch Repository Manager Notification for resubmission
            if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                $repoManagers = \App\Models\User::role(['repository-manager', 'super-admin'])->get();
                foreach ($repoManagers as $rm) {
                    \Illuminate\Support\Facades\DB::table('notifications')->insert([
                        'id'              => (string) \Illuminate\Support\Str::uuid(),
                        'type'            => 'repository_resubmitted_for_approval',
                        'notifiable_type' => 'App\Models\User',
                        'notifiable_id'   => $rm->id,
                        'data'            => json_encode([
                            'title'        => 'Repository Resubmitted for Governance Approval',
                            'message'      => "Repository '{$bank->title}' has been revised and resubmitted by " . (Auth::user()?->name ?? 'Teacher'),
                            'repository'   => $bank->title,
                            'submitted_by' => Auth::user()?->name ?? 'Teacher',
                            'link'         => route('admin.repository-manager.question-bank-validate', $bank->id),
                        ]),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }
        }

        // 3. Audit Log: Automatic IRQA Scan Started
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'irqa_scan_started',
            'approval_note' => 'Automatic IRQA Re-Scan initiated by governance engine.',
        ]);

        // 4. Execute Automatic IRQA Re-Scan
        $qualityService = app(RepositoryQualityService::class);
        $rescanAudit = $qualityService->syncRepositoryFindings($bank);
        $currentWarnings = $rescanAudit['warnings'] ?? [];

        // Audit Log: Automatic IRQA Scan Completed
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'irqa_scan_completed',
            'approval_note' => 'Automatic IRQA Re-Scan execution completed.',
        ]);

        $irqaPassed = empty($currentWarnings) && ($rescanAudit['health_score'] ?? 0) >= 90;

        if ($irqaPassed) {
            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $bank->id,
                'actor_id'      => Auth::id(),
                'reviewer_id'   => $revisionRequest->requested_by_id,
                'action'        => 'irqa_passed',
                'approval_note' => 'Automatic IRQA re-scan passed with zero remaining quality findings.',
            ]);

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'repository_resubmitted_irqa_passed',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $revisionRequest->requested_by_id,
                    'data'            => json_encode([
                        'title'   => 'Repository IRQA Verification Passed',
                        'message' => "Repository has passed automatic IRQA verification. Ready for governance review.",
                        'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        } else {
            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $bank->id,
                'actor_id'      => Auth::id(),
                'reviewer_id'   => $revisionRequest->requested_by_id,
                'action'        => 'irqa_failed',
                'approval_note' => 'Automatic IRQA re-scan detected unresolved findings. Governance review required by Repository Manager.',
            ]);

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'id'              => (string) \Illuminate\Support\Str::uuid(),
                    'type'            => 'repository_resubmitted_irqa_failed',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id'   => $revisionRequest->requested_by_id,
                    'data'            => json_encode([
                        'title'   => 'Governance Review Required',
                        'message' => "Automatic IRQA detected remaining findings. Governance review required.",
                        'link'    => route('admin.repository-manager.question-bank-validate', $bank->id),
                    ]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->insert([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'repository_resubmission_received',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => Auth::id(),
                'data'            => json_encode([
                    'title'   => 'Resubmission Received',
                    'message' => "Repository successfully resubmitted. Waiting Repository Manager review.",
                    'link'    => route('teacher.repository-revisions.index'),
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return redirect()->route('teacher.repository-revisions.index')
            ->with('success', 'Repository successfully resubmitted. Waiting Repository Manager review.');
    }

    /**
     * Compute Live Validation Checklist for Full Question Editor.
     */
    protected function computeQuestionValidation(Question $question, $bank): array
    {
        $hasPrompt      = !empty(trim($question->prompt ?? ''));
        $hasCategory    = !empty($bank->acl_category_id);
        $hasDifficulty  = !empty($question->difficulty);
        $hasChoices     = $question->choices->count() >= 2;
        $hasCorrect     = $question->choices->where('is_correct', true)->count() >= 1;
        $hasExplanation = !empty(trim($question->explanation ?? ''));
        $hasMedia       = true;
        $hasMetadata    = !empty($bank->title) && !empty($bank->test_type);

        $checks = [
            'prompt'         => ['label' => 'Question Prompt', 'passed' => $hasPrompt],
            'category'       => ['label' => 'Category', 'passed' => $hasCategory],
            'difficulty'     => ['label' => 'Difficulty', 'passed' => $hasDifficulty],
            'choices'        => ['label' => 'Answer Choices', 'passed' => $hasChoices],
            'correct_answer' => ['label' => 'Correct Answer', 'passed' => $hasCorrect],
            'explanation'    => ['label' => 'Explanation', 'passed' => $hasExplanation],
            'media'          => ['label' => 'Media Attachment', 'passed' => $hasMedia],
            'metadata'       => ['label' => 'Metadata', 'passed' => $hasMetadata],
        ];

        $passedCount = count(array_filter($checks, fn($c) => $c['passed']));
        $totalCount  = count($checks);

        return [
            'checks'       => $checks,
            'passed_count' => $passedCount,
            'total_count'  => $totalCount,
        ];
    }
}
