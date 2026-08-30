<?php

namespace App\Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Question;
use App\Services\ToeicQuestionValidator;
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

        // Filter by Active Assignments
        if ($filter = $request->input('filter')) {
            if ($filter === 'active-assignments' || $filter === 'active') {
                $query->whereHas('assignments', function ($aq) {
                    $aq->where('status', 'active');
                });
            }
        }

        if ($user && $user->hasRole('teacher')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
            });
        }

        $tests = $query->latest('updated_at')->paginate(10)->withQueryString();

        // Regular Admin Operational View: Render Assessment Assignment & Operations workspace with catalog separation
        if ($user && $user->hasRole('admin') && !$user->hasRole(['teacher', 'repository-manager'])) {
            $rawTab = $request->input('tab', $request->input('mode', 'mock_tests'));
            $tab = in_array($rawTab, ['simulators', 'simulator']) ? 'simulators' : 'mock_tests';

            $adminQuery = Test::with(['sections.testQuestions', 'creator']);

            if ($search = $request->input('search')) {
                $adminQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('test_type', 'like', "%{$search}%");
                });
            }

            if ($type = $request->input('type')) {
                $adminQuery->where('test_type', $type);
            }

            if ($filter = $request->input('filter')) {
                if ($filter === 'active-assignments' || $filter === 'active') {
                    $adminQuery->whereHas('assignments', function ($aq) {
                        $aq->where('status', 'active');
                    });
                }
            }

            $countBase = clone $adminQuery;
            $mockTestsCount = (clone $countBase)
                ->where('assessment_mode', 'real_test')
                ->where(function ($q) {
                    $q->where('status', 'published')
                      ->orWhere('status', 'approved')
                      ->orWhere('is_published', true);
                })
                ->count();

            $simulatorsCount = (clone $countBase)
                ->where('assessment_mode', 'simulator')
                ->count();

            if ($tab === 'simulators') {
                $adminQuery->where('assessment_mode', 'simulator');
            } else {
                // Mock Tests: Strictly assessment_mode = real_test and published/approved live
                $adminQuery->where('assessment_mode', 'real_test')
                    ->where(function ($q) {
                        $q->where('status', 'published')
                          ->orWhere('status', 'approved')
                          ->orWhere('is_published', true);
                    });
            }

            $tests = $adminQuery->latest('updated_at')->paginate(10)->withQueryString();

            return view('assessment::admin_operations', compact('tests', 'tab', 'mockTestsCount', 'simulatorsCount'));
        }

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
     * Teacher & RM MAY create draft tests. Admin & Super Admin MUST NOT create tests directly.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && ($user->hasRole('super-admin') || ($user->hasRole('admin') && !$user->hasRole(['teacher', 'repository-manager'])))) {
            abort(403, 'Regular Admin cannot author assessment tests. Assessment authoring belongs to Teachers.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'test_type' => ['required', 'string'],
            'assessment_mode' => ['nullable', 'string', 'in:simulator,real_test'],
            'scoring_method' => ['nullable', 'string', 'in:automatic,human,hybrid'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'pass_score' => ['required', 'integer', 'min:0'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_choices' => ['nullable', 'boolean'],
        ]);

        $mode = $validated['assessment_mode'] ?? 'simulator';

        if ($mode === 'real_test' && (!$user || (!$user->hasRole('repository-manager') && !$user->hasRole('super-admin')))) {
            abort(403, 'Mock Test assessments must originate from Repository Manager intake.');
        }

        $test = Test::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(5),
            'test_type' => $validated['test_type'],
            'assessment_mode' => $mode,
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
        if (! $user || (! $user->hasRole('repository-manager') && ! $user->hasRole('super-admin'))) {
            abort(403, 'Operational Admins and Teachers cannot publish assessments. Assessment publishing is strictly reserved for Repository Managers.');
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

        // Regular Admin Operational View: Render Assessment Candidate Assignment & Management workspace
        if ($user && $user->hasRole('admin') && !$user->hasRole(['teacher', 'repository-manager'])) {
            $assignedCandidates = $test->assignments()->with(['user', 'assignedBy'])->latest('assigned_at')->get();
            $availableStudents = \App\Models\User::role('student')->get();
            return view('assessment::admin_show', compact('test', 'assignedCandidates', 'availableStudents'));
        }

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        $test->load(['sections.testQuestions.question.choices', 'sections.testQuestions.question.questionBank', 'sections.mediaAssets', 'creator', 'assignedTeacher']);

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

        if ($user && ($user->hasRole('super-admin') || ($user->hasRole('admin') && !$user->hasRole(['teacher', 'repository-manager'])))) {
            abort(403, 'Regular Admin cannot edit assessment definitions. Assessment authoring belongs to Teachers.');
        }

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to update assessment test.');
        }

        $validated = $request->validate([
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'test_type'        => ['sometimes', 'required', 'string'],
            'scoring_method'   => ['nullable', 'string', 'in:automatic,human,hybrid'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'pass_score'       => ['sometimes', 'required', 'integer', 'min:0'],
            'instructions'     => ['nullable', 'string'],
        ]);

        $updateData = [];
        if (isset($validated['title'])) $updateData['title'] = $validated['title'];
        if (isset($validated['test_type'])) $updateData['test_type'] = $validated['test_type'];
        if (isset($validated['scoring_method'])) $updateData['scoring_method'] = $validated['scoring_method'];
        if (isset($validated['duration_minutes'])) $updateData['duration_minutes'] = $validated['duration_minutes'];
        if (isset($validated['pass_score'])) $updateData['pass_score'] = $validated['pass_score'];
        if (array_key_exists('instructions', $validated)) $updateData['instructions'] = $validated['instructions'];

        $test->update($updateData);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment '{$test->title}' updated and saved to Draft successfully.");
    }

    /**
     * Preview assessment as a candidate (read-only, non-persistent, no attempt created).
     */
    public function previewAsCandidate(Request $request, Test $test): View
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        $test->load([
            'sections.testQuestions.question.choices',
            'sections.testQuestions.question.passage',
            'sections.testQuestions.question.passageGroup.passages',
            'sections.testQuestions.question.audioGroup',
            'sections.testQuestions.question.mediaAsset',
            'sections.mediaAssets',
        ]);

        $validationResult = $this->validateAssessment($test);

        $orderedQuestions = collect();
        $sectionFirstQuestionIndex = [];
        $questionSectionMap = [];

        $sections = $test->sections->sortBy('order')->values();

        foreach ($sections as $secIndex => $section) {
            $firstIdxForThisSection = $orderedQuestions->count();
            $sectionFirstQuestionIndex[$section->id] = $firstIdxForThisSection;

            $secQuestions = $section->testQuestions->sortBy('order')->map(function ($tq) use ($section) {
                $q = $tq->question;
                if ($q) {
                    $q->section_model = $section;
                    $q->test_question_order = $tq->order;
                }
                return $q;
            })->filter()->values();

            foreach ($secQuestions as $q) {
                $idx = $orderedQuestions->count();
                $questionSectionMap[$idx] = $section->id;
                $orderedQuestions->push($q);
            }
        }

        return view('teacher.assessment_preview', [
            'test'                      => $test,
            'sections'                  => $sections,
            'questions'                 => $orderedQuestions,
            'sectionFirstQuestionIndex' => $sectionFirstQuestionIndex,
            'questionSectionMap'        => $questionSectionMap,
            'validationResult'          => $validationResult,
        ]);
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

        $isToeic = ToeicQuestionValidator::isToeic($test) || $request->filled('part_number');

        $validated = $request->validate([
            'test_section_id' => ['required', 'exists:test_sections,id'],
            'prompt'          => ['required', 'string'],
            'question_type'   => ['required', 'string'],
            'difficulty'      => ['nullable', 'string'],
            'points'          => ['nullable', 'integer', 'min:1'],
            'explanation'     => ['nullable', 'string'],
            'choices'         => ['nullable', 'array'],
            'correct_choice'  => ['nullable'],
            'media_asset_id'  => ['nullable', 'string'],
            'image_url'       => ['nullable', 'string'],
            'audio_url'       => ['nullable', 'string'],
            'passage_id'      => ['nullable', 'string'],
            'passage_text'    => ['nullable', 'string'],
            'part_number'     => ['nullable', 'integer', 'between:1,7'],
            'section'         => ['nullable', 'string'],
        ]);

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();

        if ($request->filled('part_number')) {
            $toeicData = $request->all();
            $toeicData['prompt'] = $validated['prompt'];
            $toeicData['difficulty'] = $validated['difficulty'] ?? 'medium';
            ToeicQuestionValidator::validate($toeicData);
            $partNumber = (int) $request->input('part_number');
            $sectionType = ToeicQuestionValidator::deriveSection($partNumber);
        } else {
            $partNumber = $validated['part_number'] ?? null;
            $sectionType = $validated['section'] ?? ($section->section_type->value ?? $section->section_type ?? 'reading');
        }

        $qType = $validated['question_type'] ?? 'multiple_choice';
        if (is_object($qType)) {
            $qType = $qType->value;
        }
        $isMcq = in_array($qType, ['multiple_choice', 'single_choice', 'true_false', 'select_one', 'toefl_listening_mcq', 'toefl_reading_mcq'], true) || !empty($validated['choices']);

        $correctChoice = $request->input('correct_choice');
        if ($isMcq && (is_null($correctChoice) || $correctChoice === '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'correct_choice' => 'Please select the correct answer.',
            ]);
        }

        $choices = [];
        $hasCorrect = false;
        if (!empty($validated['choices'])) {
            foreach ($validated['choices'] as $idx => $choiceText) {
                if (empty(trim($choiceText))) continue;
                $isCorrect = (!is_null($correctChoice) && $correctChoice !== '' && (string) $idx === (string) $correctChoice);
                if ($isCorrect) {
                    $hasCorrect = true;
                }
                $choices[] = [
                    'label'      => chr(65 + count($choices)),
                    'content'    => $choiceText,
                    'is_correct' => $isCorrect,
                ];
            }
        }

        if ($isMcq && !$hasCorrect) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'correct_choice' => 'Please select the correct answer.',
            ]);
        }

        $this->builderService->createAssessmentQuestion($section, [
            'prompt'         => $validated['prompt'],
            'section'        => $sectionType,
            'part_number'    => $partNumber,
            'question_type'  => $validated['question_type'],
            'difficulty'     => $validated['difficulty'] ?? 'medium',
            'points'         => $validated['points'] ?? 1,
            'explanation'    => $validated['explanation'] ?? null,
            'media_asset_id' => $validated['media_asset_id'] ?? null,
            'image_url'      => $validated['image_url'] ?? null,
            'audio_url'      => $validated['audio_url'] ?? null,
            'passage_id'     => $validated['passage_id'] ?? null,
            'passage_text'   => $validated['passage_text'] ?? null,
            'choices'        => $choices,
        ]);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment-authored question created and added to section '{$section->title}'.");
    }

    /**
     * Create an Assessment-authored Shared Audio Group (Part 3 / Part 4) with 3 child questions.
     */
    public function createAudioGroup(Request $request, Test $test): RedirectResponse
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
            'title'           => ['nullable', 'string', 'max:255'],
            'group_type'      => ['required', 'string', 'in:conversation,talk'],
            'part_number'     => ['required', 'integer', 'in:3,4'],
            'media_asset_id'  => ['nullable', 'string'],
            'audio_url'       => ['nullable', 'string'],
            'audio_script'    => ['nullable', 'string'],
            'questions'       => ['required', 'array', 'size:3'],
            'questions.*.prompt'         => ['required', 'string'],
            'questions.*.difficulty'     => ['required', 'string'],
            'questions.*.explanation'    => ['nullable', 'string'],
            'questions.*.choices'        => ['required', 'array', 'size:4'],
            'questions.*.correct_choice' => ['required'],
        ]);

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();

        $this->builderService->createAudioGroup($section, $validated);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Part {$validated['part_number']} Shared Audio Group successfully created and attached to '{$section->title}'.");
    }

    /**
     * Create an Assessment-authored Shared Passage Group (Part 6 / Part 7) with passages and child questions.
     */
    public function createPassageGroup(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $validated = $request->validate([
            'test_section_id'  => ['required', 'exists:test_sections,id'],
            'title'            => ['nullable', 'string', 'max:255'],
            'part_number'      => ['required', 'integer', 'in:6,7'],
            'passage_type'     => ['required', 'string', 'in:single,double,triple'],
            'context_metadata' => ['nullable', 'array'],
            'passages'         => ['required', 'array', 'min:1', 'max:3'],
            'passages.*.title'         => ['nullable', 'string', 'max:255'],
            'passages.*.content'       => ['required', 'string'],
            'passages.*.document_type' => ['nullable', 'string'],
            'passages.*.order_in_group'=> ['nullable', 'integer'],
            'questions'        => ['required', 'array', 'min:2', 'max:5'],
            'questions.*.prompt'         => ['required', 'string'],
            'questions.*.difficulty'     => ['required', 'string'],
            'questions.*.explanation'    => ['nullable', 'string'],
            'questions.*.choices'        => ['required', 'array', 'size:4'],
            'questions.*.correct_choice' => ['required'],
        ]);

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();

        $this->builderService->createPassageGroup($section, $validated);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Part {$validated['part_number']} " . ucfirst($validated['passage_type']) . " Passage Group successfully created and attached to '{$section->title}'.");
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
            'title'        => ['required', 'string', 'max:255'],
            'section_type' => ['nullable', 'string', 'in:listening,reading,speaking,writing'],
            'instructions' => ['nullable', 'string'],
        ]);

        $section = $this->builderService->addSection(
            $test,
            $validated['title'],
            $validated['section_type'] ?? null,
            $validated['instructions'] ?? null
        );

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Section '{$section->title}' added successfully.");
    }

    /**
     * Update section title, type, or directions.
     */
    public function updateSection(Request $request, Test $test, TestSection $section): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment section.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'section_type' => ['nullable', 'string', 'in:listening,reading,speaking,writing'],
            'instructions' => ['nullable', 'string'],
        ]);

        $this->builderService->updateSection($section, $validated);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Section '{$section->title}' updated successfully.");
    }

    /**
     * Delete section from assessment.
     */
    public function destroySection(Request $request, Test $test, TestSection $section): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment section.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true) || $test->is_published) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        if ((string) $section->test_id !== (string) $test->id) {
            abort(404, 'Section does not belong to this assessment.');
        }

        $sectionTitle = $section->title;

        try {
            $this->builderService->deleteSection($test, $section);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->withErrors($e->validator);
        }

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Section '{$sectionTitle}' removed successfully.");
    }

    /**
     * Attach existing MediaAsset to TestSection.
     */
    public function attachSectionMedia(Request $request, Test $test, TestSection $section): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        if ((string) $section->test_id !== (string) $test->id) {
            abort(404, 'Section does not belong to this assessment.');
        }

        $validated = $request->validate([
            'media_asset_id' => ['required', 'string', 'exists:media_assets,id'],
            'caption'        => ['nullable', 'string', 'max:255'],
            'order'          => ['nullable', 'integer', 'min:1'],
        ]);

        $this->builderService->attachMediaToSection(
            $section,
            $validated['media_asset_id'],
            $validated['caption'] ?? null,
            $validated['order'] ?? null
        );

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', 'Media asset attached to section successfully.');
    }

    /**
     * Detach MediaAsset from TestSection.
     */
    public function detachSectionMedia(Request $request, Test $test, TestSection $section, MediaAsset $media): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        if ((string) $section->test_id !== (string) $test->id) {
            abort(404, 'Section does not belong to this assessment.');
        }

        $this->builderService->detachMediaFromSection($section, $media->id);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', 'Media asset detached from section.');
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

        $isToeic = ToeicQuestionValidator::isToeic($test) || ToeicQuestionValidator::isToeic($question) || $request->filled('part_number');

        $validated = $request->validate([
            'prompt'         => ['required', 'string'],
            'question_type'  => ['nullable', 'string'],
            'difficulty'     => ['nullable', 'string'],
            'explanation'    => ['nullable', 'string'],
            'choices'        => ['nullable', 'array'],
            'correct_choice' => ['nullable'],
            'media_asset_id' => ['nullable', 'string'],
            'image_url'      => ['nullable', 'string'],
            'audio_url'      => ['nullable', 'string'],
            'passage_id'     => ['nullable', 'string'],
            'passage_text'   => ['nullable', 'string'],
            'part_number'    => ['nullable', 'integer', 'between:1,7'],
            'section'        => ['nullable', 'string'],
        ]);

        if ($request->filled('part_number')) {
            $toeicData = $request->all();
            $toeicData['prompt'] = $validated['prompt'];
            $toeicData['difficulty'] = $validated['difficulty'] ?? $question->difficulty ?? 'medium';
            ToeicQuestionValidator::validate($toeicData, $question);
            $partNumber = (int) $request->input('part_number');
            $sectionType = ToeicQuestionValidator::deriveSection($partNumber);
            $question->part_number = $partNumber;
            $question->section = $sectionType;
        } elseif ($request->filled('section')) {
            $question->section = $request->input('section');
        } elseif (empty($question->section)) {
            $question->section = 'reading';
        }

        // Reuse existing Question ID (TASK 3 & TASK 9)
        $question->prompt = $validated['prompt'];
        if (isset($validated['question_type'])) $question->question_type = $validated['question_type'];
        if (isset($validated['difficulty'])) $question->difficulty = $validated['difficulty'];
        if (isset($validated['explanation'])) $question->explanation = $validated['explanation'];
        if (isset($validated['passage_id'])) $question->passage_id = $validated['passage_id'];
        if (isset($validated['passage_text'])) $question->passage_text = $validated['passage_text'];
        
        if ($request->has('image_url')) {
            $question->image_url = $request->input('image_url') ?: null;
        }
        if ($request->has('audio_url')) {
            $question->audio_url = $request->input('audio_url') ?: null;
        }
        if ($request->has('media_asset_id')) {
            $question->media_asset_id = $request->input('media_asset_id') ?: null;
        }

        $question->save();

        $qType = $validated['question_type'] ?? $question->question_type ?? 'multiple_choice';
        if (is_object($qType)) {
            $qType = $qType->value;
        }
        $isMcq = in_array($qType, ['multiple_choice', 'single_choice', 'true_false', 'select_one', 'toefl_listening_mcq', 'toefl_reading_mcq'], true) || $request->has('choices');

        if ($request->has('choices')) {
            $choicesData = $request->input('choices', []);
            $correctChoiceIndex = $request->input('correct_choice');

            if ($isMcq && (is_null($correctChoiceIndex) || $correctChoiceIndex === '')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'correct_choice' => 'Please select the correct answer.',
                ]);
            }

            $validIdx = 0;
            $hasCorrect = false;
            $existingChoices = $question->choices()->get();

            foreach ($choicesData as $idx => $choiceText) {
                if (empty(trim($choiceText))) continue;

                $isCorrect = (!is_null($correctChoiceIndex) && $correctChoiceIndex !== '' && (string) $idx === (string) $correctChoiceIndex);
                if ($isCorrect) {
                    $hasCorrect = true;
                }

                $choice = $existingChoices->get($validIdx);

                if ($choice) {
                    $choice->update([
                        'label'       => chr(65 + $validIdx),
                        'content'     => $choiceText,
                        'choice_text' => $choiceText,
                        'is_correct'  => $isCorrect,
                    ]);
                } else {
                    $question->choices()->create([
                        'label'       => chr(65 + $validIdx),
                        'content'     => $choiceText,
                        'choice_text' => $choiceText,
                        'is_correct'  => $isCorrect,
                        'order'       => $validIdx + 1,
                    ]);
                }
                $validIdx++;
            }

            if ($existingChoices->count() > $validIdx) {
                for ($i = $validIdx; $i < $existingChoices->count(); $i++) {
                    $existingChoices->get($i)?->delete();
                }
            }

            if ($isMcq && !$hasCorrect && $validIdx > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'correct_choice' => 'Please select the correct answer.',
                ]);
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

    /**
     * Assign a candidate to an assessment test (Admin/RM action).
     */
    public function assignCandidate(Request $request, Test $test): RedirectResponse
    {
        $validated = $request->validate([
            'candidate_id' => ['required', 'exists:users,id'],
        ]);

        $candidate = \App\Models\User::findOrFail($validated['candidate_id']);
        $assignmentEngine = app(\App\Modules\Assessment\Engines\AssignmentEngine::class);

        try {
            $assignment = $assignmentEngine->assignToUser($test, $candidate, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Services\ActivityLogger::log('CANDIDATE_ASSIGNED', "Assigned candidate '{$candidate->name}' to test '{$test->title}'", $assignment);

        return back()->with('status', "Candidate '{$candidate->name}' successfully assigned to '{$test->title}'.");
    }

    /**
     * Unassign a candidate from an assessment test.
     */
    public function unassignCandidate(Request $request, Test $test, \App\Models\User $user): RedirectResponse
    {
        $assignmentEngine = app(\App\Modules\Assessment\Engines\AssignmentEngine::class);
        $assignmentEngine->unassign($test, $user);

        return back()->with('status', "Candidate '{$user->name}' unassigned from '{$test->title}'.");
    }
}
