<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AclCategory;
use App\Models\MediaAsset;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Notifications\EnterpriseSystemNotification;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\RepositoryQualityService;
use App\Services\ToeicQuestionValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $isLocked = in_array($revisionRequest->status, ['RESUBMITTED', 'CLOSED', 'APPROVED'], true);

        if ($isLocked) {
            return redirect()->route('admin.question-banks.show', [
                $revisionRequest->question_bank_id,
                'from'                => 'revision_task',
                'revision_request_id' => $revisionRequest->id,
            ])->with('info', 'Repository revision is locked while awaiting governance approval.');
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

        // Snapshot baseline_data if not yet present
        if (empty($item->baseline_data)) {
            $item->baseline_data = [
                'prompt' => $question->prompt,
                'explanation' => $question->explanation,
                'difficulty' => $question->difficulty,
                'points' => $question->points,
                'question_type' => is_object($question->question_type) ? $question->question_type->value : (string) ($question->question_type ?? 'multiple_choice'),
                'media_asset_id' => $question->media_asset_id,
                'part_number' => $question->part_number,
                'section' => $question->section,
                'choices' => $question->choices->map(fn($c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'content' => $c->content,
                    'is_correct' => (bool) $c->is_correct,
                ])->toArray(),
            ];
            $item->save();
        }

        // If proposed_data exists, overlay it onto question object for view rendering
        if (!empty($item->proposed_data)) {
            $prop = $item->proposed_data;
            $question->prompt = $prop['prompt'] ?? $question->prompt;
            $question->explanation = $prop['explanation'] ?? $question->explanation;
            $question->difficulty = $prop['difficulty'] ?? $question->difficulty;
            $question->points = $prop['points'] ?? $question->points;
            $question->question_type = $prop['question_type'] ?? $question->question_type;
            $question->part_number = $prop['part_number'] ?? $question->part_number;
            $question->section = $prop['section'] ?? $question->section;
            if (isset($prop['media_asset_id'])) {
                $question->media_asset_id = $prop['media_asset_id'];
            }
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
     * SPRINT RRUXO-REVISION-EDITOR-ENHANCEMENT: Full Save Question Workflow with Staged Revision.
     */
    public function updateQuestion(Request $request, RepositoryRevisionRequest $revisionRequest, RepositoryRevisionItem $item): RedirectResponse
    {
        if ($revisionRequest->teacher_id !== Auth::id()) {
            abort(403, 'Unauthorized access to repository revision request.');
        }

        $bank = $revisionRequest->questionBank;
        $isLocked = in_array($revisionRequest->status, ['RESUBMITTED', 'CLOSED', 'APPROVED'], true);

        if ($isLocked) {
            return redirect()->route('admin.question-banks.show', [
                $revisionRequest->question_bank_id,
                'from'                => 'revision_task',
                'revision_request_id' => $revisionRequest->id,
            ])->with('error', 'Cannot modify repository questions while locked awaiting governance approval.');
        }

        $question = $item->question ?? Question::findOrFail($request->input('question_id'));

        if ($request->filled('part_number')) {
            $toeicData = $request->all();
            ToeicQuestionValidator::validate($toeicData, $question);
            $partNumber = (int) $request->input('part_number');
            $section = ToeicQuestionValidator::deriveSection($partNumber);
        } else {
            $partNumber = $question->part_number;
            $section = $question->section ?? 'reading';
        }

        // Ensure baseline_data snapshot is recorded
        if (empty($item->baseline_data) && $question) {
            $item->baseline_data = [
                'prompt' => $question->prompt,
                'explanation' => $question->explanation,
                'difficulty' => $question->difficulty,
                'points' => $question->points,
                'question_type' => is_object($question->question_type) ? $question->question_type->value : (string) ($question->question_type ?? 'multiple_choice'),
                'media_asset_id' => $question->media_asset_id,
                'part_number' => $question->part_number,
                'section' => $question->section,
                'choices' => $question->choices->map(fn($c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'content' => $c->content,
                    'is_correct' => (bool) $c->is_correct,
                ])->toArray(),
            ];
        }

        // Prepare staged proposed_data
        $proposedChoices = [];
        $qTypeVal = $request->input('question_type', is_object($question->question_type) ? $question->question_type->value : (string) ($question->question_type ?? 'multiple_choice'));
        $nonChoiceTypes = ['essay', 'speaking', 'writing', 'short_answer'];
        $isChoiceType = !in_array($qTypeVal, $nonChoiceTypes, true);

        if ($isChoiceType) {
            if ($request->has('choices')) {
                $correctChoiceId = $request->input('correct_choice_id');
                foreach ($request->input('choices', []) as $cId => $cData) {
                    if (!empty($cData['content'])) {
                        $proposedChoices[] = [
                            'id' => $cId,
                            'label' => $cData['label'] ?? 'A',
                            'content' => $cData['content'],
                            'is_correct' => ((string) $correctChoiceId === (string) $cId) || (isset($cData['is_correct']) && $cData['is_correct'] == '1'),
                        ];
                    }
                }
            }
            if ($request->filled('new_choice_content')) {
                $proposedChoices[] = [
                    'id' => 'new_' . uniqid(),
                    'label' => $request->input('new_choice_label', 'A'),
                    'content' => $request->input('new_choice_content'),
                    'is_correct' => $request->has('new_choice_is_correct'),
                ];
            }
        }

        $mediaAssetId = $question->media_asset_id;
        if ($request->has('remove_media') && $request->input('remove_media') == '1') {
            $mediaAssetId = null;
        } elseif ($request->filled('media_asset_id')) {
            $mediaAssetId = $request->input('media_asset_id');
        }

        $proposedData = [
            'prompt'        => $request->input('prompt', $question->prompt),
            'question_type' => $qTypeVal,
            'explanation'   => $request->input('explanation', $question->explanation),
            'difficulty'    => $request->input('difficulty', $question->difficulty),
            'points'        => (int) $request->input('points', $question->points ?? 1),
            'part_number'   => $partNumber,
            'section'       => $section,
            'image_url'     => $request->input('image_url') ?: null,
            'audio_url'     => $request->input('audio_url') ?: null,
            'passage_id'    => $request->input('passage_id') ?: null,
            'passage_text'  => $request->input('passage_text') ?: null,
            'audio_group_id' => $request->input('audio_group_id') ?: null,
            'passage_group_id' => $request->input('passage_group_id') ?: null,
            'media_asset_id' => $mediaAssetId,
            'category_id'   => $request->input('category_id'),
            'choices'       => $proposedChoices,
        ];

        $isPublished = ($bank && $bank->status === 'published');

        // Always save staged proposed_data and status on the revision item
        $item->proposed_data = $proposedData;
        $item->status = 'FIXED';
        $item->save();

        if (!$isPublished) {
            // Initial intake / draft authoring: update draft question directly
            $question->prompt        = $proposedData['prompt'];
            $question->question_type = $proposedData['question_type'];
            $question->explanation   = $proposedData['explanation'];
            $question->difficulty    = $proposedData['difficulty'];
            $question->points        = $proposedData['points'];
            $question->part_number   = $proposedData['part_number'];
            $question->section       = $proposedData['section'];
            $question->image_url     = $proposedData['image_url'];
            $question->audio_url     = $proposedData['audio_url'];
            $question->passage_id    = $proposedData['passage_id'];
            $question->passage_text  = $proposedData['passage_text'];
            $question->audio_group_id = $proposedData['audio_group_id'];
            $question->passage_group_id = $proposedData['passage_group_id'];
            $question->media_asset_id = $proposedData['media_asset_id'];
            $question->save();

            // choices
            if ($isChoiceType && !empty($proposedChoices)) {
                $existingChoices = $question->choices->keyBy('id');
                $updatedIds = [];

                foreach ($proposedChoices as $key => $c) {
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
            } elseif (!$isChoiceType) {
                $question->choices()->delete();
            }

            if (!empty($proposedData['category_id']) && $bank) {
                $bank->acl_category_id = $proposedData['category_id'];
                $bank->save();
            }

            // Run IRQA Validation Check and Reconcile Repository Revision Items for draft
            $qualityService = app(RepositoryQualityService::class);
            $qualityService->reconcileRevisionItems($bank);
            $item->refresh();
        }

        RepositoryActivityLog::create([
            'resource_type' => 'Question',
            'resource_id'   => (string) $question->id,
            'actor_id'      => Auth::id(),
            'reviewer_id'   => $revisionRequest->requested_by_id,
            'action'        => 'teacher_saved_revision',
            'approval_note' => $isPublished ? "Teacher staged question revision for item #{$item->id}." : "Teacher updated draft question #{$question->id}.",
        ]);

        $successMsg = $isPublished
            ? '✔ Proposed revision saved to staged draft! Master repository remains unchanged until Repository Manager approval.'
            : '✔ Question updated successfully.';

        return redirect()->route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id])
            ->with('success', $successMsg);
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
            if ($bank->status !== 'published') {
                $bank->status = 'pending_approval';
                $bank->save();
            }

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
                $this->notifyRepositoryManagersOfRevision(
                    $bank,
                    Auth::user(),
                    'Teacher Revision Resubmitted',
                    (Auth::user()?->name ?? 'Teacher') . " resubmitted changes for repository '{$bank->title}'.",
                    'REPOSITORY_RESUBMITTED',
                    $revisionRequest
                );
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
        } else {
            RepositoryActivityLog::create([
                'resource_type' => 'QuestionBank',
                'resource_id'   => $bank->id,
                'actor_id'      => Auth::id(),
                'reviewer_id'   => $revisionRequest->requested_by_id,
                'action'        => 'irqa_failed',
                'approval_note' => 'Automatic IRQA re-scan detected unresolved findings. Governance review required by Repository Manager.',
            ]);
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
        $isToeic = ($bank && ToeicQuestionValidator::isToeic($bank) && !empty($question->part_number)) || !empty($question->part_number);

        if ($isToeic) {
            $toeicCheck = ToeicQuestionValidator::check($question->toArray(), $question);
            $errors = $toeicCheck['errors'] ?? [];
            $part = $toeicCheck['part_number'] ?? ($question->part_number ?? 1);

            $hasPrompt     = !isset($errors['prompt']);
            $hasCategory   = !empty($bank->acl_category_id);
            $hasDifficulty = !isset($errors['difficulty']);
            $hasChoices    = !isset($errors['choices']);
            $hasCorrect    = !isset($errors['correct_choice']);
            $hasExplanation = !empty(trim($question->explanation ?? ''));
            $hasMetadata   = !empty($bank->title) && !empty($bank->test_type);

            $mediaLabel = match ($part) {
                1 => 'Image & Audio Required',
                2, 3, 4 => 'Audio Required',
                5 => 'No Audio Allowed',
                6, 7 => 'Passage Required',
                default => 'Media Attachment',
            };
            $hasMedia = !isset($errors['media']) && !isset($errors['image_url']) && !isset($errors['audio_url']) && !isset($errors['passage']);

            $choicesLabel = ($part === 2) ? '3 Answer Choices (A, B, C)' : '4 Answer Choices (A, B, C, D)';

            $checks = [
                'prompt'         => ['label' => "Question Prompt (Part {$part})", 'passed' => $hasPrompt],
                'category'       => ['label' => 'Category', 'passed' => $hasCategory],
                'difficulty'     => ['label' => 'Difficulty', 'passed' => $hasDifficulty],
                'choices'        => ['label' => $choicesLabel, 'passed' => $hasChoices],
                'correct_answer' => ['label' => 'Correct Answer', 'passed' => $hasCorrect],
                'media'          => ['label' => $mediaLabel, 'passed' => $hasMedia],
                'explanation'    => ['label' => 'Explanation', 'passed' => $hasExplanation],
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

        $baselineData = null;
        if ($question) {
            $baselineData = [
                'prompt' => $question->prompt,
                'explanation' => $question->explanation,
                'difficulty' => $question->difficulty,
                'points' => $question->points,
                'question_type' => is_object($question->question_type) ? $question->question_type->value : (string) ($question->question_type ?? 'multiple_choice'),
                'media_asset_id' => $question->media_asset_id,
                'part_number' => $question->part_number,
                'section' => $question->section,
                'choices' => $question->choices->map(fn($c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'content' => $c->content,
                    'is_correct' => (bool) $c->is_correct,
                ])->toArray(),
            ];
        }

        RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $bank->id,
            'question_id'                    => $question?->id,
            'feedback'                       => $validated['notes'],
            'finding_type'                   => 'teacher_revision_request',
            'severity'                       => 'medium',
            'suggested_fix'                  => 'Review question prompt/options as requested by teacher.',
            'status'                         => 'OPEN',
            'baseline_data'                  => $baselineData,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => $user->id,
            'reviewer_id'   => $user->id,
            'action'        => 'teacher_requested_revision',
            'approval_note' => $validated['notes'],
        ]);

        // Dispatch in-app notification to Repository Managers
        $this->notifyRepositoryManagersOfRevision(
            $bank,
            $user,
            'New Teacher Revision Request',
            "{$user->name} requested a revision on repository '{$bank->title}'.",
            'REPOSITORY_REVISION_REQUESTED',
            $revisionRequest
        );

        return redirect()->route('teacher.repository-revisions.show', $revisionRequest->id)
            ->with('success', 'Repository revision request created. You can now edit question draft.');
    }

    /**
     * Dispatch in-app EnterpriseSystemNotification to Repository Managers on Question Bank revision events.
     */
    private function notifyRepositoryManagersOfRevision(
        \App\Modules\QuestionBank\Models\QuestionBank $bank,
        User $actor,
        string $title,
        string $message,
        string $notifType,
        ?RepositoryRevisionRequest $revisionRequest = null
    ): void {
        $targetUrl = $revisionRequest
            ? route('admin.repository-manager.revisions.review', $revisionRequest->id)
            : route('admin.repository-manager.revisions.index');
        $repoManagers = User::role('repository-manager')->get();

        foreach ($repoManagers as $manager) {
            if (method_exists($manager, 'notify')) {
                try {
                    $manager->notify(new EnterpriseSystemNotification(
                        title: $title,
                        message: $message,
                        type: $notifType,
                        priority: 'HIGH',
                        entityType: 'RepositoryRevisionRequest',
                        entityId: $revisionRequest ? (string) $revisionRequest->id : (string) $bank->id,
                        targetUrl: $targetUrl
                    ));
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch repository revision notification to RM #{$manager->id} for QuestionBank #{$bank->id}: " . $e->getMessage());
                }
            }
        }
    }
}
