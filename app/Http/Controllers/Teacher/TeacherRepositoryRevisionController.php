<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AclCategory;
use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
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
            ->whereIn('status', ['OPEN', 'IN_PROGRESS', 'RESUBMITTED'])
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

        if ($revisionRequest->questionBank) {
            app(\App\Services\RepositoryQualityService::class)->reconcileRevisionItems($revisionRequest->questionBank);
        }

        $revisionRequest->load(['questionBank.questions.choices', 'requestedBy', 'items.question']);

        return view('teacher.repository_revisions.show', compact('revisionRequest'));
    }

    /**
     * SPRINT RRUXO-REVISION-EDITOR-ENHANCEMENT: Full Question Editor in Repository Revision Mode.
     */
    public function editQuestion(Request $request, RepositoryRevisionRequest $revisionRequest, RepositoryRevisionItem $item): View|RedirectResponse
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $bank = $revisionRequest->questionBank;
        $isBankLocked = in_array($bank?->status, ['pending_approval', 'submitted', 'approved', 'published', 'pending_archive_approval', 'pending_restore_approval'], true)
            || in_array($revisionRequest->status, ['RESUBMITTED', 'CLOSED'], true);

        if ($isBankLocked) {
            return redirect()->route('admin.question-banks.show', [
                $revisionRequest->question_bank_id,
                'from'                => 'revision_task',
                'revision_request_id' => $revisionRequest->id,
            ])->with('info', 'Repository is locked while awaiting governance approval.');
        }

        $revisionRequest->load(['questionBank', 'requestedBy', 'items']);
        $item->load(['question.choices', 'question.mediaAsset']);

        $question = $item->question;

        // Backend Safety Guard: Missing question_id must NEVER reach computeQuestionValidation()
        if (!$question) {
            return redirect()->route('admin.question-banks.show', [
                $revisionRequest->question_bank_id,
                'from'                => 'revision_task',
                'revision_request_id' => $revisionRequest->id,
            ])->with('info', 'This finding is at the repository level. Please update repository details in the Question Bank workspace.');
        }

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

        $bank = $revisionRequest->questionBank;
        $isBankLocked = in_array($bank?->status, ['pending_approval', 'submitted', 'approved', 'published', 'pending_archive_approval', 'pending_restore_approval'], true)
            || in_array($revisionRequest->status, ['RESUBMITTED', 'CLOSED'], true);

        if ($isBankLocked) {
            return redirect()->route('admin.question-banks.show', [
                $revisionRequest->question_bank_id,
                'from'                => 'revision_task',
                'revision_request_id' => $revisionRequest->id,
            ])->with('error', 'Cannot modify repository questions while locked awaiting governance approval.');
        }

        $question = $item->question ?? Question::findOrFail($request->input('question_id'));

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
        $qTypeVal = is_object($question->question_type) ? $question->question_type->value : (string) $question->question_type;
        $nonChoiceTypes = ['essay', 'speaking', 'writing', 'short_answer'];
        $isChoiceType = !in_array($qTypeVal, $nonChoiceTypes, true);

        if ($isChoiceType) {
            if ($request->has('choices')) {
                $correctChoiceId = $request->input('correct_choice_id');
                foreach ($request->input('choices', []) as $cId => $cData) {
                    if (is_numeric($cId) || strlen($cId) > 10) {
                        $choice = QuestionChoice::find($cId);
                        if ($choice) {
                            $choice->content    = $cData['content'] ?? $choice->content;
                            $choice->label      = $cData['label'] ?? $choice->label;
                            $choice->is_correct = ((string) $correctChoiceId === (string) $cId) || (isset($cData['is_correct']) && $cData['is_correct'] == '1');
                            $choice->save();
                        } elseif (!empty($cData['content'])) {
                            QuestionChoice::create([
                                'question_id' => $question->id,
                                'label'       => $cData['label'] ?? chr(65 + (int)$cId),
                                'content'     => $cData['content'],
                                'is_correct'  => ((string) $correctChoiceId === (string) $cId) || (isset($cData['is_correct']) && $cData['is_correct'] == '1'),
                            ]);
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
        } else {
            // Essay / Non-choice question: delete any legacy choice records so they do not produce invalid choice warnings
            $question->choices()->delete();
        }

        // 4. Run IRQA Validation Check and Reconcile Repository Revision Items
        $qualityService = app(RepositoryQualityService::class);
        $qualityService->reconcileRevisionItems($bank);
        $item->refresh();

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
        $qTypeVal = is_object($question->question_type) ? $question->question_type->value : (string) ($question->question_type ?? 'multiple_choice');
        $nonChoiceTypes = ['essay', 'speaking', 'writing', 'short_answer'];
        $isChoiceType = !in_array($qTypeVal, $nonChoiceTypes, true);

        $hasPrompt      = !empty(trim($question->prompt ?? ''));
        $hasCategory    = !empty($bank->acl_category_id);
        $hasDifficulty  = !empty($question->difficulty);
        $hasExplanation = !empty(trim($question->explanation ?? ''));
        $hasMedia       = true;
        $hasMetadata    = !empty($bank->title) && !empty($bank->test_type);

        $checks = [
            'prompt'         => ['label' => 'Question Prompt', 'passed' => $hasPrompt],
            'category'       => ['label' => 'Category', 'passed' => $hasCategory],
            'difficulty'     => ['label' => 'Difficulty', 'passed' => $hasDifficulty],
        ];

        if ($isChoiceType) {
            $hasChoices = $question->choices->count() >= 2;
            $hasCorrect = $question->choices->where('is_correct', true)->count() >= 1;

            $checks['choices']        = ['label' => 'Answer Choices', 'passed' => $hasChoices];
            $checks['correct_answer'] = ['label' => 'Correct Answer', 'passed' => $hasCorrect];
        }

        $checks['explanation'] = ['label' => 'Explanation', 'passed' => $hasExplanation];
        $checks['media']       = ['label' => 'Media Attachment', 'passed' => $hasMedia];
        $checks['metadata']    = ['label' => 'Metadata', 'passed' => $hasMetadata];

        $passedCount = count(array_filter($checks, fn($c) => $c['passed']));
        $totalCount  = count($checks);

        return [
            'checks'       => $checks,
            'passed_count' => $passedCount,
            'total_count'  => $totalCount,
        ];
    }

    /**
     * Teacher initiates revision request on a Master Question / Question Bank.
     */
    public function requestRevision(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'question_bank_id' => ['required', 'exists:question_banks,id'],
            'question_id'      => ['nullable', 'exists:questions,id'],
            'notes'            => ['required', 'string', 'max:1000'],
        ]);

        $bank = \App\Modules\QuestionBank\Models\QuestionBank::findOrFail($validated['question_bank_id']);

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $user->id,
            'requested_by_id'  => $user->id,
            'status'           => 'OPEN',
            'notes'            => $validated['notes'],
        ]);

        $question = !empty($validated['question_id']) ? Question::find($validated['question_id']) : null;

        RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $bank->id,
            'question_id'                    => $question?->id,
            'feedback'                       => $validated['notes'],
            'finding_type'                   => 'teacher_revision_request',
            'severity'                       => 'medium',
            'suggested_fix'                  => 'Review question prompt/options as requested by teacher.',
            'status'                         => 'OPEN',
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => $user->id,
            'reviewer_id'   => $user->id,
            'action'        => 'teacher_requested_revision',
            'approval_note' => $validated['notes'],
        ]);

        return redirect()->route('teacher.repository-revisions.show', $revisionRequest->id)
            ->with('success', 'Repository revision request created. You can now edit question draft.');
    }
}
