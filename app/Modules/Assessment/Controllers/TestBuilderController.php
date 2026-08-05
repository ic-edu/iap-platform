<?php

namespace App\Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Question;
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

        $tests = $query->latest('updated_at')->paginate(10)->withQueryString();

        // Calculate workspace KPI statistics for Teacher (Section 2 & 7)
        $allMyTestsQuery = Test::query();
        if ($user && $user->hasRole('teacher')) {
            $allMyTestsQuery->where('created_by', $user->id);
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
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'pass_score' => ['required', 'integer', 'min:0'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

        $test = Test::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(5),
            'test_type' => $validated['test_type'],
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
     * Delete test.
     */
    public function destroy(Test $test): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers cannot delete assessment tests.');
        }

        $test->delete();

        return redirect()->route('admin.tests.index')
            ->with('status', "Assessment '{$test->title}' deleted.");
    }

    /**
     * Display Assessment Detail page for Teacher (TASK 1, 5, 8).
     */
    public function show(Request $request, Test $test): View
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        $test->load(['sections.testQuestions.question', 'creator']);

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

        return view('teacher.assessment_detail', compact('test', 'latestFeedbackLog', 'workflowTimeline', 'validationResult'));
    }

    /**
     * Update existing assessment details / Save Draft (TASK 4, 6).
     */
    public function update(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized access to update assessment test.');
        }

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'test_type'        => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'pass_score'       => ['required', 'integer', 'min:0'],
        ]);

        $test->update([
            'title'            => $validated['title'],
            'test_type'        => $validated['test_type'],
            'duration_minutes' => $validated['duration_minutes'],
            'pass_score'       => $validated['pass_score'],
        ]);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment '{$test->title}' updated and saved to Draft successfully.");
    }

    /**
     * Lazy Load Single Question for Editing (TASK 4 - Progressive Disclosure).
     */
    public function editQuestion(Request $request, Test $test, \App\Modules\QuestionBank\Models\Question $question)
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized access to question.');
        }

        $question->load(['choices']);

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
    public function updateQuestion(Request $request, Test $test, \App\Modules\QuestionBank\Models\Question $question): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized access to update question.');
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

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized access to resubmit assessment test.');
        }

        // TASK 5: Validation Panel Check before submission
        $validationResult = $this->validateAssessment($test);
        if (!$validationResult['is_valid']) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->with('error', "Cannot submit invalid assessment test. Please fix the following errors: " . implode(' | ', $validationResult['errors']));
        }

        $test->update([
            'status'       => 'pending_approval',
            'is_published' => false,
        ]);

        \App\Models\RepositoryActivityLog::create([
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'actor_id'      => $user->id,
            'action'        => 'resubmitted',
            'approval_note' => 'Teacher resubmitted assessment test after completing requested revisions and passing validation.',
        ]);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment '{$test->title}' resubmitted successfully to Repository Manager Approval Queue.");
    }

    /**
     * Automated Question & Structure Validation (TASK 5).
     */
    public function validateAssessment(Test $test): array
    {
        $test->load(['sections.testQuestions.question.choices']);
        $repoReviews = \App\Models\TestQuestionReview::where('test_id', (string) $test->id)
            ->get()
            ->keyBy('question_id');

        $errors = [];
        $allQuestions = [];
        $questionIndex = 1;

        foreach ($test->sections as $section) {
            foreach ($section->testQuestions as $tq) {
                $q = $tq->question;
                if (!$q) {
                    $errors[] = "Question #{$questionIndex} in Section '{$section->title}' is missing or unlinked.";
                    $questionIndex++;
                    continue;
                }

                $qErrors = [];
                if (empty(trim($q->prompt ?? ''))) {
                    $qErrors[] = "Stem / Prompt text is empty.";
                }

                if (in_array($q->question_type, ['multiple_choice', 'single_choice', 'true_false', 'select_one'])) {
                    $choices = $q->choices ?? collect();
                    if ($choices->isEmpty()) {
                        $qErrors[] = "No options/choices provided.";
                    } else {
                        $hasCorrect = $choices->contains('is_correct', true);
                        if (!$hasCorrect) {
                            $qErrors[] = "No correct answer option selected.";
                        }
                    }
                }

                // TASK 5: Structured Repository Question Review Mapping
                $qRev = $repoReviews[$q->id] ?? null;
                if ($qRev && $qRev->status === 'needs_revision') {
                    $qErrors[] = "Repository Feedback (" . ucfirst($qRev->field ?? 'general') . "): " . ($qRev->comment ?? 'Revision requested');
                }

                if (!empty($qErrors)) {
                    $snippet = \Illuminate\Support\Str::limit($q->prompt ?? 'Question #'.$q->id, 25);
                    foreach ($qErrors as $err) {
                        $errors[] = "Q#{$questionIndex} ({$snippet}): {$err}";
                    }
                    $q->validation_warning = implode(' ', $qErrors);
                } else {
                    $q->validation_warning = null;
                }

                $allQuestions[] = [
                    'number'   => $questionIndex,
                    'question' => $q,
                    'section'  => $section,
                    'warnings' => $qErrors,
                ];

                $questionIndex++;
            }
        }

        return [
            'is_valid'  => empty($errors),
            'errors'    => $errors,
            'questions' => $allQuestions,
        ];
    }
}
