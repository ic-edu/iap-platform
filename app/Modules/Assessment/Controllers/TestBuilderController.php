<?php

namespace App\Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TestBuilderController extends Controller
{
    public function __construct(
        protected TestBuilderService $builderService
    ) {}

    /**
     * Display listing of tests with search, filters, and metrics (TEACHER-002 Workspace).
     */
    public function index(Request $request): View
    {
        $user  = $request->user();
        $query = Test::with(['sections.testQuestions', 'creator']);

        // Search by Assessment Title or Test Type (Section 9)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('test_type', 'like', "%{$search}%");
            });
        }

        // Filter by Workflow Status (Section 10)
        if ($status = $request->input('status')) {
            if ($status === 'published') {
                $query->where(function ($q) {
                    $q->where('status', 'published')->orWhere('is_published', true);
                });
            } else {
                $query->where('status', $status);
            }
        }

        // Filter by Test Type
        if ($type = $request->input('type')) {
            $query->where('test_type', $type);
        }

        if ($user && $user->hasRole('teacher')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
            });
        }

        $tests = $query->latest('updated_at')->paginate(10)->withQueryString();

        // Calculate workspace KPI statistics for Teacher (Section 2 & 7)
        $allMyTestsQuery = Test::query();
        if ($user && $user->hasRole('teacher')) {
            $allMyTestsQuery->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
            });
        }

        $totalTests           = (clone $allMyTestsQuery)->count();
        $draftTests           = (clone $allMyTestsQuery)->where('status', 'draft')->count();
        $pendingApprovalTests = (clone $allMyTestsQuery)->where('status', 'pending_approval')->count();
        $approvedTests        = (clone $allMyTestsQuery)->where('status', 'approved')->count();
        $publishedTests       = (clone $allMyTestsQuery)->where(function ($q) {
            $q->where('status', 'published')->orWhere('is_published', true);
        })->count();
        $rejectedTests        = (clone $allMyTestsQuery)->where('status', 'rejected')->count();

        // Latest active draft for "Continue Draft" Quick Action
        $latestDraft = (clone $allMyTestsQuery)->where('status', 'draft')->latest('updated_at')->first();

        $questions = Question::all();
        $publishedQuestionBanks = \App\Modules\QuestionBank\Models\QuestionBank::with(['questions', 'aclCategory'])
            ->whereIn('status', ['published', 'approved'])
            ->get();

        /** @var view-string $viewName */
        $viewName = 'assessment::index';

        return view($viewName, compact(
            'tests',
            'questions',
            'publishedQuestionBanks',
            'totalTests',
            'draftTests',
            'pendingApprovalTests',
            'approvedTests',
            'publishedTests',
            'rejectedTests',
            'latestDraft'
        ));
    }

    /**
     * Store new test in draft mode.
     * Teacher & Admin MAY create draft tests. Super Admin MUST NOT create tests.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('super-admin')) {
            abort(403, 'Super Admin is an auditor/approver and cannot create tests directly.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'test_type' => ['required', 'string'],
            'scoring_method' => ['nullable', 'string', 'in:automatic,human,hybrid'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'pass_score' => ['required', 'integer', 'min:0'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

        $test = Test::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(5),
            'test_type' => $validated['test_type'],
            'scoring_method' => $validated['scoring_method'] ?? 'automatic',
            'duration_minutes' => $validated['duration_minutes'],
            'pass_score' => $validated['pass_score'],
            'shuffle_questions' => $request->has('shuffle_questions'),
            'shuffle_choices' => $request->has('shuffle_choices'),
            'created_by' => $user?->id,
            'status' => 'draft',
            'is_published' => false,
        ]);

        // Default section 1
        TestSection::create([
            'test_id' => $test->id,
            'title' => 'Section 1: General Core',
            'order' => 1,
        ]);

        return redirect()->route('admin.tests.index')
            ->with('status', "Assessment test '{$test->title}' created successfully in Draft mode.");
    }

    /**
     * Duplicate test.
     */
    public function duplicate(Test $test): RedirectResponse
    {
        $newTest = $test->replicate(['slug', 'status', 'is_published']);
        $newTest->title = $test->title.' (Copy)';
        $newTest->slug = Str::slug($newTest->title).'-'.Str::random(5);
        $newTest->status = 'draft';
        $newTest->is_published = false;
        $newTest->created_by = request()->user()?->id;
        $newTest->save();

        foreach ($test->sections as $section) {
            $newSection = $section->replicate();
            $newSection->test_id = $newTest->id;
            $newSection->save();
        }

        return redirect()->route('admin.tests.index')
            ->with('status', "Assessment test '{$test->title}' duplicated.");
    }

    /**
     * Submit test for Super Admin approval.
     */
    public function submitForApproval(Test $test): RedirectResponse
    {
        $validationResult = $this->validateAssessment($test);
        if (!$validationResult['is_valid']) {
            return redirect()->route('admin.tests.index')
                ->with('error', "Cannot submit Assessment for review: " . implode(' | ', $validationResult['errors']));
        }

        $test->update(['status' => 'pending_approval']);

        return redirect()->route('admin.tests.index')
            ->with('status', "Assessment '{$test->title}' submitted for Super Admin approval.");
    }

    /**
     * Admin publishes approved test.
     */
    public function publish(Test $test): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot publish assessments. Admin publication queue required.');
        }

        if ($test->status !== 'approved') {
            abort(403, 'Only approved assessments can be published.');
        }

        $test->update(['status' => 'published', 'is_published' => true]);

        return redirect()->route('admin.tests.index')
            ->with('status', "Assessment '{$test->title}' published live.");
    }

    /**
     * Delete test (Safe Delete Governance Workflow TASK 4).
     */
    public function destroy(Test $test): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot delete assessment tests.');
        }

        // TASK 4 Safe Delete Governance: Published/Approved tests enter pending_deletion for Super Admin approval
        if (($test->is_published || in_array($test->status, ['published', 'approved'])) && !$user->hasRole('super-admin')) {
            $test->update(['status' => 'pending_deletion']);

            \App\Models\RepositoryActivityLog::create([
                'resource_type' => 'Test',
                'resource_id'   => (string) $test->id,
                'actor_id'      => $user->id,
                'action'        => 'test_deletion_requested',
                'approval_note' => "Deletion requested for published assessment test '{$test->title}'. Awaiting Super Admin approval.",
            ]);

            return redirect()->route('admin.tests.index')
                ->with('status', "Deletion requested for published assessment '{$test->title}'. Awaiting Super Admin approval.");
        }

        $test->delete();

        \App\Models\RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $user->id,
            'action'        => 'test_deleted',
            'approval_note' => "Assessment '{$test->title}' soft deleted.",
        ]);

        return redirect()->route('admin.tests.index')
            ->with('status', "Assessment '{$test->title}' deleted.");
    }

    /**
     * Display Assessment Detail page for Teacher (TASK 1, 5, 8).
     */
    public function show(Request $request, Test $test): View
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        $test->load(['sections.testQuestions.question.choices', 'sections.testQuestions.question.questionBank', 'creator', 'assignedTeacher']);

        $publishedQuestionBanks = \App\Modules\QuestionBank\Models\QuestionBank::with(['questions.choices'])
            ->whereIn('status', ['published', 'approved'])
            ->get();

        $latestFeedbackLog = \App\Models\RepositoryActivityLog::where('resource_type', 'Test')
            ->where('resource_id', (string) $test->id)
            ->whereIn('action', ['revision_requested', 'rejected'])
            ->with(['reviewer'])
            ->latest()
            ->first();

        $workflowTimeline = [
            [
                'step' => 'Draft Created',
                'status' => 'completed',
                'date' => $test->created_at?->format('M d, Y H:i'),
            ],
            [
                'step' => 'Submitted for Approval',
                'status' => in_array($test->status, ['pending', 'pending_approval', 'needs_revision', 'revision_requested', 'approved', 'published']) ? 'completed' : 'pending',
                'date' => null,
            ],
            [
                'step' => 'Repository Review',
                'status' => in_array($test->status, ['needs_revision', 'revision_requested', 'approved', 'published']) ? 'completed' : 'pending',
                'date' => $latestFeedbackLog?->created_at?->format('M d, Y H:i'),
            ],
            [
                'step' => 'Needs Revision',
                'status' => in_array($test->status, ['needs_revision', 'revision_requested']) ? 'active' : (in_array($test->status, ['approved', 'published']) ? 'completed' : 'pending'),
                'date' => $latestFeedbackLog?->created_at?->format('M d, Y H:i'),
            ],
            [
                'step' => 'Approved & Live',
                'status' => in_array($test->status, ['approved', 'published']) ? 'completed' : 'pending',
                'date' => null,
            ],
        ];

        $validationResult = $this->validateAssessment($test);

        return view('teacher.assessment_detail', compact('test', 'latestFeedbackLog', 'workflowTimeline', 'validationResult', 'publishedQuestionBanks'));
    }

    /**
     * Update existing assessment details / Save Draft (TASK 4, 6).
     */
    public function update(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to update assessment test.');
        }

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'test_type'        => ['required', 'string'],
            'scoring_method'   => ['nullable', 'string', 'in:automatic,human,hybrid'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'pass_score'       => ['required', 'integer', 'min:0'],
        ]);

        $test->update([
            'title'            => $validated['title'],
            'test_type'        => $validated['test_type'],
            'scoring_method'   => $validated['scoring_method'] ?? $test->scoring_method ?? 'automatic',
            'duration_minutes' => $validated['duration_minutes'],
            'pass_score'       => $validated['pass_score'],
        ]);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment '{$test->title}' updated and saved to Draft successfully.");
    }

    /**
     * Attach an existing Master Question from an Institutional Question Bank to an Assessment section.
     */
    public function attachMasterQuestion(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $validated = $request->validate([
            'test_section_id' => ['required', 'exists:test_sections,id'],
            'question_id'     => ['required', 'exists:questions,id'],
        ]);

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();
        $question = Question::findOrFail($validated['question_id']);

        if ($question->question_bank_id === null) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->with('error', 'Selected question is not a Master Question.');
        }

        try {
            $this->builderService->assignQuestionToSection($section, $question->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Master Question attached to section '{$section->title}'.");
    }

    /**
     * Create an Assessment-authored question and attach it to a section.
     */
    public function createAssessmentQuestion(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $validated = $request->validate([
            'test_section_id' => ['required', 'exists:test_sections,id'],
            'prompt'          => ['required', 'string'],
            'question_type'   => ['required', 'string'],
            'difficulty'      => ['nullable', 'string'],
            'points'          => ['required', 'integer', 'min:1'],
            'explanation'     => ['nullable', 'string'],
            'choices'         => ['nullable', 'array'],
            'correct_choice'  => ['nullable'],
        ]);

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();

        $choices = [];
        if (!empty($validated['choices'])) {
            foreach ($validated['choices'] as $idx => $choiceText) {
                if (empty(trim($choiceText))) continue;
                $choices[] = [
                    'label'      => chr(65 + $idx),
                    'content'    => $choiceText,
                    'is_correct' => ((string) $idx === (string) ($request->input('correct_choice'))),
                ];
            }
        }

        $this->builderService->createAssessmentQuestion($section, [
            'prompt'        => $validated['prompt'],
            'question_type' => $validated['question_type'],
            'difficulty'    => $validated['difficulty'] ?? 'medium',
            'points'        => $validated['points'],
            'explanation'   => $validated['explanation'] ?? null,
            'choices'       => $choices,
        ]);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment-authored question created and added to section '{$section->title}'.");
    }

    /**
     * Remove question reference from Assessment.
     */
    public function destroyQuestion(Request $request, Test $test, Question $question): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $removed = $this->builderService->removeQuestionFromSection($test, $question->id);

        if (!$removed) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->with('error', 'Question not found in assessment.');
        }

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', 'Question reference removed from assessment.');
    }

    /**
     * Add section to assessment.
     */
    public function addSection(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $section = $this->builderService->addSection($test, $validated['title']);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Section '{$section->title}' added successfully.");
    }

    /**
     * Lazy Load Single Question for Editing (TASK 4 - Progressive Disclosure).
     */
    public function editQuestion(Request $request, Test $test, \App\Modules\QuestionBank\Models\Question $question)
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to question.');
        }

        // Assessment State Guard: Non-draft / approved / published / pending tests are immutable
        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $question->load(['choices', 'questionBank']);

        // Master Question Governance Guard: Master Questions are governed content and cannot be directly edited in Test Builder
        if ($question->question_bank_id !== null) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success'   => false,
                    'is_master' => true,
                    'message'   => 'Master Questions are governed content and cannot be edited directly from Test Builder. Request a Repository Revision through the governance workflow.',
                    'question'  => $question,
                ], 403);
            }

            return redirect()->route('teacher.tests.show', $test->id)
                ->with('info', 'Master Questions are governed content and cannot be edited directly from Test Builder. Request a Repository Revision through the governance workflow.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'question' => $question,
            ]);
        }

        return view('teacher.question_editor', compact('test', 'question'));
    }

    /**
     * Update an individual question linked to the assessment (TASK 3, TASK 9).
     */
    public function updateQuestion(Request $request, Test $test, \App\Modules\QuestionBank\Models\Question $question): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to update question.');
        }

        // Assessment State Guard: Non-draft / approved / published / pending tests are immutable
        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from question editing.");
        }

        // Master Question Governance Guard: Master Questions MUST NOT be mutated from Test Builder
        if ($question->question_bank_id !== null) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Master Questions are governed content and cannot be edited directly from Test Builder. Request a Repository Revision through the governance workflow.',
                ], 403);
            }

            return redirect()->route('teacher.tests.show', $test->id)
                ->with('error', 'Master Questions are governed content and cannot be edited directly from Test Builder. Request a Repository Revision through the governance workflow.');
        }

        $validated = $request->validate([
            'prompt'         => ['required', 'string'],
            'question_type'  => ['nullable', 'string'],
            'difficulty'     => ['nullable', 'string'],
            'explanation'    => ['nullable', 'string'],
            'choices'        => ['nullable', 'array'],
            'correct_choice' => ['nullable'],
        ]);

        // Reuse existing Question ID (TASK 3 & TASK 9)
        $question->prompt = $validated['prompt'];
        if (isset($validated['question_type'])) $question->question_type = $validated['question_type'];
        if (isset($validated['difficulty'])) $question->difficulty = $validated['difficulty'];
        if (isset($validated['explanation'])) $question->explanation = $validated['explanation'];
        $question->save();

        if ($request->has('choices')) {
            $choicesData = $request->input('choices', []);
            $correctChoiceIndex = $request->input('correct_choice');

            foreach ($choicesData as $idx => $choiceText) {
                if (empty(trim($choiceText))) continue;

                $choice = $question->choices()->skip($idx)->first();
                $isCorrect = ((string)$idx === (string)$correctChoiceIndex);

                if ($choice) {
                    $choice->update([
                        'label'       => chr(65 + $idx),
                        'content'     => $choiceText,
                        'choice_text' => $choiceText,
                        'is_correct'  => $isCorrect,
                    ]);
                } else {
                    $question->choices()->create([
                        'label'       => chr(65 + $idx),
                        'content'     => $choiceText,
                        'choice_text' => $choiceText,
                        'is_correct'  => $isCorrect,
                        'order'       => $idx + 1,
                    ]);
                }
            }
        }

        // Clear/resolve question review flag upon Teacher edit (TASK 6)
        \App\Models\TestQuestionReview::where('test_id', (string) $test->id)
            ->where('question_id', (string) $question->id)
            ->delete();

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Question #{$question->id} updated successfully.");
    }

    /**
     * Resubmit assessment to Repository Manager Approval Queue with Validation Panel check (TASK 5, TASK 7).
     */
    public function resubmit(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to resubmit assessment test.');
        }

        // TASK 5: Validation Panel Check before submission
        $validationResult = $this->validateAssessment($test);
        if (!$validationResult['is_valid']) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->with('error', "Cannot submit Assessment for review: " . implode(' | ', $validationResult['errors']));
        }

        $test->update([
            'status'       => 'pending_approval',
            'is_published' => false,
        ]);

        \App\Models\RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $user->id,
            'action'        => 'assessment_resubmitted',
            'approval_note' => 'Assessment resubmitted for review.',
        ]);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment resubmitted successfully.");
    }

    /**
     * Automated Question & Structure Validation (Delegates to TestBuilderService).
     */
    public function validateAssessment(Test $test): array
    {
        return $this->builderService->validateAssessment($test);
    }
}
