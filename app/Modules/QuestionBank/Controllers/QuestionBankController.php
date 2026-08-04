<?php

namespace App\Modules\QuestionBank\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AclAuditTrail;
use App\Models\AclCategory;
use App\Models\AclVersion;
use App\Models\QuestionBankArchiveRequest;
use App\Models\User;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Notifications\SystemAlertNotification;
use App\Services\AclCoverageService;
use App\Services\AclHealthScoreService;
use App\Services\AclVersioningService;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    public function __construct(
        protected AclCoverageService $coverageService,
        protected AclHealthScoreService $healthScoreService,
        protected AclVersioningService $versioningService
    ) {}

    /**
     * Display listing of question banks with search, status filters, sorting, coverage indicators, and Content Health Score (TEACHER-003 / ACL).
     */
    public function index(Request $request): View
    {
        $user  = $request->user();
        $query = QuestionBank::with(['category', 'aclCategory', 'creator', 'questions', 'archiveRequests', 'versions', 'auditTrails']);

        // Filter by author=me or my=1
        if ($request->input('author') === 'me' || $request->has('my')) {
            $query->where('created_by', $user->id);
        }

        // Teacher sees only their own question banks unless admin
        if ($user && $user->hasRole('teacher')) {
            $query->where('created_by', $user->id);
        }

        // Search by Title, Keyword, Description
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Test Type or ACL Category filter
        if ($type = $request->input('test_type')) {
            $query->where('test_type', $type);
        }

        if ($aclCatId = $request->input('acl_category_id')) {
            $query->where('acl_category_id', $aclCatId);
        }

        // Workflow Status filter
        if ($status = $request->input('status')) {
            if ($status === 'published') {
                $query->where(function ($q) {
                    $q->where('status', 'published')->orWhere('status', 'approved');
                });
            } else {
                $query->where('status', $status);
            }
        }

        // Sorting
        $sort = $request->input('sort', 'updated');
        match ($sort) {
            'created'      => $query->latest('created_at'),
            'questions'    => $query->withCount('questions')->orderBy('questions_count', 'desc'),
            'alphabetical' => $query->orderBy('title', 'asc'),
            default        => $query->latest('updated_at'),
        };

        $banks = $query->paginate(10)->withQueryString();

        // Workspace KPI metrics for Teacher
        $myBanksQuery = QuestionBank::query();
        if ($user && $user->hasRole('teacher')) {
            $myBanksQuery->where('created_by', $user->id);
        }

        $totalBanks           = (clone $myBanksQuery)->count();
        $draftBanks           = (clone $myBanksQuery)->where('status', 'draft')->count();
        $pendingApprovalBanks = (clone $myBanksQuery)->whereIn('status', ['pending_approval', 'submitted', 'pending_archive_approval'])->count();
        $rejectedBanks        = (clone $myBanksQuery)->whereIn('status', ['rejected', 'revision_requested'])->count();
        $approvedBanks        = (clone $myBanksQuery)->whereIn('status', ['approved', 'published'])->count();

        // "Continue Working" spotlight card — last edited Question Bank
        $latestEditedBank = (clone $myBanksQuery)->with(['questions'])->latest('updated_at')->first();

        // Recent Activity
        $recentActivities = (clone $myBanksQuery)->latest('updated_at')->take(5)->get();

        // ACL Dynamic Coverage Indicators & Content Health Score
        $coverageReport = $this->coverageService->getCategoryCoverageReport();
        $healthData     = $this->healthScoreService->calculateHealthScore();
        $aclCategories  = AclCategory::where('is_active', true)->get();

        $categories = CourseCategory::all();

        /** @var view-string $viewName */
        $viewName = 'question_bank::index';

        return view($viewName, compact(
            'banks',
            'categories',
            'aclCategories',
            'totalBanks',
            'draftBanks',
            'pendingApprovalBanks',
            'rejectedBanks',
            'approvedBanks',
            'latestEditedBank',
            'recentActivities',
            'coverageReport',
            'healthData'
        ));
    }

    /**
     * Show detailed question bank authoring workspace.
     */
    public function show(QuestionBank $questionBank): View
    {
        $questionBank->load(['questions.choices', 'category', 'aclCategory', 'archiveRequests', 'versions.creator', 'auditTrails.actor']);

        /** @var view-string $viewName */
        $viewName = 'question_bank::show';

        return view($viewName, compact('questionBank'));
    }

    /**
     * Store a newly created Question Bank (Teacher Only).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot create question banks directly.');
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'test_type'       => ['required', 'string'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'category_id'     => ['nullable', 'exists:course_categories,id'],
            'acl_category_id' => ['nullable', 'exists:acl_categories,id'],
        ]);

        $aclCatId = $validated['acl_category_id'] ?? AclCategory::where('test_type', $validated['test_type'])->value('id');

        $bank = QuestionBank::create([
            'title'           => $validated['title'],
            'slug'            => Str::slug($validated['title']).'-'.Str::random(5),
            'test_type'       => $validated['test_type'],
            'description'     => $validated['description'] ?? null,
            'category_id'     => $validated['category_id'] ?? null,
            'acl_category_id' => $aclCatId,
            'status'          => 'draft',
            'current_version' => '1.0',
            'created_by'      => $user?->id,
        ]);

        ActivityLogger::log('question_bank_created', "Created question bank: {$bank->title}", $user);

        // Record Version 1.0 snapshot & Audit Log
        $this->versioningService->createVersion($bank, $user, 'Initial creation');

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'action'        => 'created',
            'actor_id'      => $user?->id,
            'created_by'    => $user?->id,
            'version'       => '1.0',
            'reason'        => 'Initial creation of academic question bank',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$bank->title}' created. Start adding questions below.");
    }

    /**
     * Update question bank details. If published, creates a new version snapshot.
     */
    public function update(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && $questionBank->created_by !== $user->id) {
            abort(403, 'You can only update your own question banks.');
        }

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'test_type'       => ['required', 'string'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'acl_category_id' => ['nullable', 'exists:acl_categories,id'],
        ]);

        $isPublished = $questionBank->status === 'published' || (bool) $questionBank->is_published;

        $questionBank->update($validated);

        ActivityLogger::log('QUESTION_BANK_EDITED', "Updated question bank: {$questionBank->title}", $user);

        // If content was published, create new version snapshot to preserve history
        if ($isPublished) {
            $this->versioningService->createVersion($questionBank, $user, 'Editing published content created new version');
        }

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'updated',
            'actor_id'      => $user?->id,
            'updated_by'    => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Updated question bank details',
        ]);

        return redirect()->route('admin.question-banks.show', $questionBank->id)
            ->with('status', 'Question Bank details updated.');
    }

    /**
     * Submit question bank for Super Admin approval.
     */
    public function submitForApproval(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();

        if ($user && $user->hasRole('teacher') && $questionBank->created_by !== $user->id) {
            abort(403);
        }

        $questionBank->update(['status' => 'pending_approval']);

        ActivityLogger::log('QUESTION_BANK_SUBMITTED', "Submitted question bank for approval: {$questionBank->title}", $user);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'submitted',
            'actor_id'      => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Submitted for Super Admin governance approval',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$questionBank->title}' submitted for Super Admin approval.");
    }

    /**
     * Review Question Bank (Admin / Super Admin).
     */
    public function review(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot perform formal review actions.');
        }

        $questionBank->update(['status' => 'reviewed']);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'reviewed',
            'actor_id'      => $user?->id,
            'reviewer_id'   => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Reviewed academic content',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$questionBank->title}' marked as reviewed.");
    }

    /**
     * Request revision / reject (Super Admin).
     */
    public function requestRevision(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('super-admin') && !$user->hasRole('admin'))) {
            abort(403);
        }

        $reason = $request->input('reason', 'Revision requested by reviewer.');

        $questionBank->update(['status' => 'rejected']);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'revision_requested',
            'actor_id'      => $user->id,
            'reviewer_id'   => $user->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => $reason,
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Revision requested for '{$questionBank->title}'.");
    }

    /**
     * Admin publishes approved question bank.
     */
    public function publish(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();

        if (!$user || !$user->hasRole('admin') || $user->hasRole('super-admin')) {
            abort(403, 'Publishing Question Banks is strictly reserved for Operational Admins.');
        }

        if ($questionBank->status !== 'approved') {
            abort(403, 'Cannot publish: Question Bank must be approved by Super Admin first.');
        }

        $questionBank->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        ActivityLogger::log('QUESTION_BANK_PUBLISHED', "Published Question Bank '{$questionBank->title}' live", $questionBank);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'published',
            'actor_id'      => $user?->id,
            'published_by'  => $user?->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Published live to institutional repository',
        ]);

        if ($questionBank->creator) {
            try {
                $questionBank->creator->notify(new SystemAlertNotification(
                    'Question Bank Published',
                    "Your Question Bank '{$questionBank->title}' has been published live by Operational Admin {$user->name}."
                ));
            } catch (\Throwable $e) {
                // Silently handle in dev
            }
        }

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question Bank '{$questionBank->title}' published live successfully.");
    }

    /**
     * Unpublish a Question Bank (Admin Only).
     */
    public function unpublish(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin') || $user->hasRole('super-admin')) {
            abort(403, 'Unpublishing Question Banks is strictly reserved for Operational Admins.');
        }

        $questionBank->update([
            'status'       => 'approved',
            'is_published' => false,
        ]);

        ActivityLogger::log('QUESTION_BANK_UNPUBLISHED', "Unpublished Question Bank '{$questionBank->title}'", $questionBank);

        return redirect()->route('admin.question-banks.index')->with('status', "Question Bank '{$questionBank->title}' unpublished.");
    }

    /**
     * Request Question Bank Archive.
     */
    public function requestArchive(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();

        if ($questionBank->hasPendingArchiveRequest()) {
            return redirect()->back()->with('error', 'An archive request for this Question Bank is already pending Super Admin approval.');
        }

        $reason = $request->input('reason', 'Archive requested.');

        QuestionBankArchiveRequest::create([
            'question_bank_id' => $questionBank->id,
            'requested_by'      => $user?->id,
            'reason'            => $reason,
            'status'            => 'pending',
        ]);

        $questionBank->update(['status' => 'pending_archive_approval']);

        AclAuditTrail::create([
            'resource_type'        => 'QuestionBank',
            'resource_id'          => $questionBank->id,
            'action'               => 'archive_requested',
            'actor_id'             => $user?->id,
            'archive_requested_by' => $user?->id,
            'version'              => $questionBank->current_version ?? '1.0',
            'reason'               => $reason,
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Archive request for Question Bank '{$questionBank->title}' submitted for Super Admin approval.");
    }

    /**
     * Request Question Bank Restoration from archive.
     */
    public function requestRestore(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();

        $questionBank->update(['status' => 'pending_restore_approval']);

        AclAuditTrail::create([
            'resource_type'        => 'QuestionBank',
            'resource_id'          => $questionBank->id,
            'action'               => 'restore_requested',
            'actor_id'             => $user?->id,
            'restore_requested_by' => $user?->id,
            'version'              => $questionBank->current_version ?? '1.0',
            'reason'               => 'Requested restoration from archive',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Restore request for '{$questionBank->title}' submitted for Super Admin approval.");
    }

    /**
     * Super Admin Approves Restore.
     */
    public function approveRestore(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Only Super Admin can approve restoration.');
        }

        $questionBank->update(['status' => 'approved']);

        AclAuditTrail::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $questionBank->id,
            'action'        => 'restored',
            'actor_id'      => $user->id,
            'approver_id'   => $user->id,
            'version'       => $questionBank->current_version ?? '1.0',
            'reason'        => 'Approved restoration to active repository',
        ]);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question Bank '{$questionBank->title}' restored to approved status.");
    }

    /**
     * Rollback to a specific snapshot version.
     */
    public function rollbackVersion(AclVersion $version): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot execute version rollbacks directly.');
        }

        $bank = $this->versioningService->rollbackVersion($version, $user);

        return redirect()->route('admin.question-banks.show', $bank->id)
            ->with('status', "Rolled back Question Bank '{$bank->title}' to version {$version->version_number}.");
    }

    /**
     * Author a new question into a question bank (QB-002: Teacher Only).
     */
    public function storeQuestion(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot author questions directly.');
        }

        $validated = $request->validate([
            'prompt'                => ['required', 'string'],
            'question_type'         => ['required', 'string'],
            'difficulty'            => ['required', 'string'],
            'points'                => ['required', 'integer', 'min:1'],
            'explanation'           => ['nullable', 'string'],
            'passage_text'          => ['nullable', 'string'],
            'audio_url'             => ['nullable', 'string'],
            'choices'               => ['nullable', 'array'],
            'choices.*.label'       => ['nullable', 'string'],
            'choices.*.content'     => ['nullable', 'string'],
            'correct_choice'        => ['nullable'],
            'correct_choices'       => ['nullable', 'array'],
            'tf_correct_choice'     => ['nullable', 'string'],
            'short_answer_text'     => ['nullable', 'string'],
            'reference_answer_text' => ['nullable', 'string'],
        ]);

        $qType = $validated['question_type'];

        $question = Question::create([
            'question_bank_id' => $questionBank->id,
            'prompt'           => $validated['prompt'],
            'question_type'    => $qType,
            'difficulty'        => $validated['difficulty'],
            'points'            => $validated['points'],
            'explanation'       => $validated['explanation'] ?? ($validated['reference_answer_text'] ?? null),
            'passage_text'      => $validated['passage_text'] ?? null,
            'audio_url'         => $validated['audio_url'] ?? null,
        ]);

        $this->saveChoicesForQuestion($question, $qType, $validated);

        return redirect()->route('admin.question-banks.show', $questionBank->id)
            ->with('status', "Question successfully authored and saved. Total questions in bank: {$questionBank->questions()->count()}.");
    }

    /**
     * Update an existing question (QB-002: Teacher Only).
     */
    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot edit questions directly.');
        }

        $validated = $request->validate([
            'prompt'                => ['required', 'string'],
            'question_type'         => ['required', 'string'],
            'difficulty'            => ['required', 'string'],
            'points'                => ['required', 'integer', 'min:1'],
            'explanation'           => ['nullable', 'string'],
            'passage_text'          => ['nullable', 'string'],
            'audio_url'             => ['nullable', 'string'],
            'choices'               => ['nullable', 'array'],
            'choices.*.label'       => ['nullable', 'string'],
            'choices.*.content'     => ['nullable', 'string'],
            'correct_choice'        => ['nullable'],
            'correct_choices'       => ['nullable', 'array'],
            'tf_correct_choice'     => ['nullable', 'string'],
            'short_answer_text'     => ['nullable', 'string'],
            'reference_answer_text' => ['nullable', 'string'],
        ]);

        $qType = $validated['question_type'];

        $question->update([
            'prompt'        => $validated['prompt'],
            'question_type' => $qType,
            'difficulty'    => $validated['difficulty'],
            'points'        => $validated['points'],
            'explanation'   => $validated['explanation'] ?? ($validated['reference_answer_text'] ?? null),
            'passage_text'  => $validated['passage_text'] ?? null,
            'audio_url'     => $validated['audio_url'] ?? null,
        ]);

        $question->choices()->delete();
        $this->saveChoicesForQuestion($question, $qType, $validated);

        return redirect()->route('admin.question-banks.show', $question->question_bank_id)
            ->with('status', 'Question updated successfully.');
    }

    /**
     * Duplicate a question inside a question bank (QB-002: Teacher Only).
     */
    public function duplicateQuestion(Request $request, Question $question): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            abort(403, 'Administrators are Content Operators and cannot duplicate/author questions directly.');
        }

        $newQ = $question->replicate();
        $newQ->prompt = 'Copy of '.$question->prompt;
        $newQ->save();

        foreach ($question->choices as $choice) {
            $newC = $choice->replicate();
            $newC->question_id = (string) $newQ->id;
            $newC->save();
        }

        return redirect()->route('admin.question-banks.show', $question->question_bank_id)
            ->with('status', 'Question duplicated successfully.');
    }

    /**
     * Delete a question with QUESTION_DELETE audit log.
     */
    public function destroyQuestion(Question $question): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers are not permitted to delete questions.');
        }

        ActivityLogger::log(
            action: 'QUESTION_DELETE',
            description: "Deleted Question #{$question->id}: {$question->prompt}",
            subject: $question,
            properties: [
                'question_id'    => $question->id,
                'question_title' => $question->prompt,
            ]
        );

        $bankId = $question->question_bank_id;
        $question->delete();

        return redirect()->route('admin.question-banks.show', $bankId)->with('status', 'Question deleted successfully.');
    }

    /**
     * Delete question bank (Admin / Super Admin only).
     */
    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot delete question banks.');
        }

        $questionBank->delete();

        ActivityLogger::log('question_bank_deleted', "Deleted question bank: {$questionBank->title}", $user);

        return redirect()->route('admin.question-banks.index')
            ->with('status', "Question bank '{$questionBank->title}' deleted.");
    }

    /**
     * Helper to save choices for a question based on its question_type.
     */
    private function saveChoicesForQuestion(Question $question, string $qType, array $validated): void
    {
        if (in_array($qType, ['single_choice', 'multiple_choice', 'listening', 'reading'])) {
            $choices = $validated['choices'] ?? [];
            $correctIdx = $validated['correct_choice'] ?? null;

            foreach ($choices as $idx => $choiceData) {
                if (!empty($choiceData['content']) || !empty($choiceData['label'])) {
                    QuestionChoice::create([
                        'question_id' => (string) $question->id,
                        'label'       => $choiceData['label'] ?? chr(65 + (int)$idx),
                        'content'     => $choiceData['content'] ?? '',
                        'is_correct'  => ((string) $idx === (string) $correctIdx),
                    ]);
                }
            }
        } elseif ($qType === 'multiple_response') {
            $choices = $validated['choices'] ?? [];
            $correctIndices = $validated['correct_choices'] ?? [];

            foreach ($choices as $idx => $choiceData) {
                if (!empty($choiceData['content']) || !empty($choiceData['label'])) {
                    QuestionChoice::create([
                        'question_id' => (string) $question->id,
                        'label'       => $choiceData['label'] ?? chr(65 + (int)$idx),
                        'content'     => $choiceData['content'] ?? '',
                        'is_correct'  => in_array((string) $idx, array_map('strval', $correctIndices), true),
                    ]);
                }
            }
        } elseif ($qType === 'true_false') {
            $tfCorrect = $validated['tf_correct_choice'] ?? 'true';
            QuestionChoice::create([
                'question_id' => (string) $question->id,
                'label'       => 'A',
                'content'     => 'True',
                'is_correct'  => ($tfCorrect === 'true'),
            ]);
            QuestionChoice::create([
                'question_id' => (string) $question->id,
                'label'       => 'B',
                'content'     => 'False',
                'is_correct'  => ($tfCorrect === 'false'),
            ]);
        } elseif (in_array($qType, ['short_answer', 'essay', 'speaking', 'writing'])) {
            $answerText = $validated['short_answer_text'] ?? ($validated['reference_answer_text'] ?? '');
            if (!empty($answerText)) {
                QuestionChoice::create([
                    'question_id' => (string) $question->id,
                    'label'       => 'Key',
                    'content'     => $answerText,
                    'is_correct'  => true,
                ]);
            }
        }
    }
}
