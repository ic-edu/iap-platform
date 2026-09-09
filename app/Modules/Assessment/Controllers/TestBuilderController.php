<?php

namespace App\Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Services\QuestionDifficultyDetectionService;
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
                ->published()
                ->count();

            $simulatorsCount = (clone $countBase)
                ->where('assessment_mode', 'simulator')
                ->count();

            if ($tab === 'simulators') {
                $adminQuery->where('assessment_mode', 'simulator');
            } else {
                // Mock Tests: Strictly assessment_mode = real_test and canonically published
                $adminQuery->where('assessment_mode', 'real_test')
                    ->published();
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

        $previousStatus = $test->status;
        $isFirstSubmission = ($previousStatus === 'draft');

        $test->update(['status' => 'pending_approval']);

        $user = request()->user() ?? $test->creator;
        $this->notifyRepositoryManagersOfSubmission($test, $user, $isFirstSubmission);

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

        if ($test->status !== 'approved' || $test->is_published) {
            abort(403, 'Only approved and unpublished assessments can be published.');
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
    public function show(Request $request, Test $test): View|RedirectResponse
    {
        $user = $request->user();

        // Regular Admin Operational View: Render Assessment Candidate Assignment & Management workspace
        if ($user && $user->hasRole('admin') && !$user->hasRole(['teacher', 'repository-manager'])) {
            $assignedCandidates = $test->assignments()->with(['user', 'assignedBy'])->latest('assigned_at')->get();
            $availableStudents = $test->isSimulator() ? collect() : app(\App\Modules\Assessment\Engines\AssignmentEngine::class)->getEligibleCandidates($test);
            return view('assessment::admin_show', compact('test', 'assignedCandidates', 'availableStudents'));
        }

        // Repository Manager Governance: Prevent RM from accessing Teacher authoring surface
        if ($user && $user->hasRole('repository-manager') && !$user->hasRole('teacher')) {
            $assessmentRequest = $test->assessmentRequest ?? \App\Models\AssessmentRequest::where('test_id', $test->id)->first();
            if ($assessmentRequest) {
                return redirect()->route('admin.repository-manager.assessment-requests.assessment-show', $assessmentRequest->id);
            }

            if (in_array($test->status, ['pending_approval', 'approved', 'needs_revision', 'revision_requested'])) {
                return redirect()->route('admin.repository-manager.assessment-review', $test->id);
            }

            abort(403, 'Repository Managers inspect governed drafts via the Assessment Request Intake Queue.');
        }

        // Super Admin Governance (when not acting as Teacher): Redirect to RM inspection if linked to a request
        if ($user && $user->hasRole('super-admin') && !$user->hasRole('teacher')) {
            $assessmentRequest = $test->assessmentRequest ?? \App\Models\AssessmentRequest::where('test_id', $test->id)->first();
            if ($assessmentRequest) {
                return redirect()->route('admin.repository-manager.assessment-requests.assessment-show', $assessmentRequest->id);
            }
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

        $testLogs = \App\Models\RepositoryActivityLog::where('resource_type', 'Test')
            ->where('resource_id', (string) $test->id)
            ->with(['reviewer', 'actor'])
            ->orderBy('created_at', 'asc')
            ->get();

        $submissionLog = $testLogs->firstWhere('action', 'submitted') ?? $testLogs->firstWhere('action', 'submission');
        $revisionLog   = $testLogs->filter(fn($l) => in_array($l->action, ['revision_requested', 'rejected']))->last();
        $approvalLog   = $testLogs->firstWhere('action', 'approved');
        $publishLog    = $testLogs->filter(fn($l) => in_array($l->action, ['published', 'PUBLISH']))->last();
        $unpublishLog  = $testLogs->filter(fn($l) => in_array($l->action, ['unpublished', 'UNPUBLISH']))->last();

        $hadRevision = $revisionLog !== null || in_array($test->status, ['needs_revision', 'revision_requested']);
        $isApproved = in_array($test->status, ['approved', 'published']) || $approvalLog !== null;
        $isPublished = $test->is_published && $test->status === 'published';

        $workflowTimeline = [];

        // 1. Draft Created
        $workflowTimeline[] = [
            'step'   => 'Draft Created',
            'status' => 'completed',
            'date'   => $test->created_at?->format('M d, Y H:i'),
            'note'   => null,
        ];

        // 2. Submitted for Approval
        $workflowTimeline[] = [
            'step'   => 'Submitted for Approval',
            'status' => ($test->status !== 'draft') ? 'completed' : 'pending',
            'date'   => $submissionLog?->created_at?->format('M d, Y H:i') ?? ($test->status !== 'draft' ? $test->created_at?->format('M d, Y H:i') : null),
            'note'   => null,
        ];

        // 3. Repository Review
        $reviewStatus = 'pending';
        if (in_array($test->status, ['needs_revision', 'revision_requested', 'approved', 'published']) || $approvalLog !== null || $revisionLog !== null) {
            $reviewStatus = 'completed';
        } elseif (in_array($test->status, ['pending', 'pending_approval'])) {
            $reviewStatus = 'active';
        }
        $workflowTimeline[] = [
            'step'   => 'Repository Review',
            'status' => $reviewStatus,
            'date'   => $approvalLog?->created_at?->format('M d, Y H:i') ?? ($revisionLog?->created_at?->format('M d, Y H:i') ?? null),
            'note'   => null,
        ];

        // 4. Needs Revision (ONLY if applicable in lifecycle/history)
        if ($hadRevision) {
            $workflowTimeline[] = [
                'step'   => 'Needs Revision',
                'status' => in_array($test->status, ['needs_revision', 'revision_requested']) ? 'active' : 'completed',
                'date'   => $revisionLog?->created_at?->format('M d, Y H:i'),
                'note'   => $revisionLog?->approval_note,
            ];
        }

        // 5. Approved
        $workflowTimeline[] = [
            'step'   => 'Approved',
            'status' => $isApproved ? 'completed' : 'pending',
            'date'   => $approvalLog?->created_at?->format('M d, Y H:i'),
            'note'   => $isApproved ? 'Governance accepted, ready for publication.' : null,
        ];

        // 6. Published
        $publishStatus = 'pending';
        $publishNote = 'Pending / not yet published';
        if ($isPublished) {
            $publishStatus = 'completed';
            $publishNote = 'Live and available to candidates';
        } elseif ($unpublishLog !== null || ($test->status === 'approved' && !$test->is_published && $publishLog !== null)) {
            $publishStatus = 'pending';
            $publishNote = 'Currently unpublished / not live';
        }

        $workflowTimeline[] = [
            'step'   => 'Published',
            'status' => $publishStatus,
            'date'   => $isPublished ? $publishLog?->created_at?->format('M d, Y H:i') : null,
            'note'   => $publishNote,
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
            'sections.testQuestions.question.audioGroup.mediaAsset',
            'sections.testQuestions.question.mediaAsset',
            'sections.mediaAssets',
        ]);

        $validationResult = $this->validateAssessment($test);
        $sections = $test->sections->sortBy('order')->values();
        $deliveryData = \App\Modules\Assessment\Services\DeliveryUnitBuilder::build($test);

        return view('teacher.assessment_preview', array_merge([
            'test'             => $test,
            'sections'         => $sections,
            'validationResult' => $validationResult,
        ], $deliveryData));
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
            ->with('status', "Master Question attached to section '{$section->title}'.")
            ->with('expanded_section_id', $section->id);
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
        $rawPartNumber = $request->input('part_number');
        $promptRule = ToeicQuestionValidator::requiresPrompt($rawPartNumber) ? ['required', 'string'] : ['nullable', 'string'];

        $validated = $request->validate([
            'test_section_id' => ['required', 'exists:test_sections,id'],
            'prompt'          => $promptRule,
            'question_type'   => ['required', 'string'],
            'difficulty'      => ['nullable', 'string'],
            'points'          => ['nullable', 'integer', 'min:1'],
            'explanation'     => ['nullable', 'string'],
            'choices'         => ['nullable', 'array'],
            'correct_choice'  => ['nullable'],
            'media_asset_id'       => ['nullable', 'string'],
            'image_media_asset_id' => ['nullable', 'string'],
            'audio_media_asset_id' => ['nullable', 'string'],
            'image_url'            => ['nullable', 'string'],
            'audio_url'            => ['nullable', 'string'],
            'passage_id'           => ['nullable', 'string'],
            'passage_text'         => ['nullable', 'string'],
            'part_number'          => ['nullable', 'integer', 'between:1,7'],
            'section'              => ['nullable', 'string'],
        ]);

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();

        $detection = QuestionDifficultyDetectionService::detect($request->all());

        if ($request->filled('part_number')) {
            $partNumber = (int) $request->input('part_number');
            if (in_array($partNumber, [6, 7], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'part_number' => "Part {$partNumber} questions must be authored through a Passage Group.",
                ]);
            }

            $toeicData = $request->all();
            if ($partNumber === 2 && isset($toeicData['choices']) && is_array($toeicData['choices']) && count($toeicData['choices']) === 4) {
                $c3 = $toeicData['choices'][3] ?? null;
                $text3 = is_array($c3) ? ($c3['content'] ?? ($c3['choice_text'] ?? '')) : (string) ($c3 ?? '');
                $corr = $toeicData['correct_choice'] ?? null;
                if (empty(trim($text3)) && (string) $corr !== '3') {
                    $toeicData['choices'] = array_slice($toeicData['choices'], 0, 3);
                    if (isset($validated['choices'])) {
                        $validated['choices'] = array_slice($validated['choices'], 0, 3);
                    }
                }
            }
            $toeicData['prompt'] = $validated['prompt'] ?? '';
            $toeicData['difficulty'] = $detection['difficulty_level'];
            ToeicQuestionValidator::validate($toeicData);
            $sectionType = ToeicQuestionValidator::deriveSection($partNumber);

            // Server-side Integrity Defense: Ensure Part Number matches target Section type
            $targetSecType = is_object($section->section_type) ? $section->section_type->value : (string) $section->section_type;
            if ($sectionType !== $targetSecType) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'part_number' => "Part {$partNumber} ({$sectionType}) cannot be assigned to '{$section->title}' ({$targetSecType} section).",
                ]);
            }
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
        $isAudioOnlyChoice = ToeicQuestionValidator::isAudioOnlyChoicePart($partNumber);
        if (!empty($validated['choices'])) {
            foreach ($validated['choices'] as $idx => $choiceText) {
                if ($partNumber === 1 && count($choices) >= 4) break;
                if ($partNumber === 2 && count($choices) >= 3) break;
                if (!$isAudioOnlyChoice && empty(trim((string)$choiceText))) continue;
                $isCorrect = (!is_null($correctChoice) && $correctChoice !== '' && (string) $idx === (string) $correctChoice);
                if ($isCorrect) {
                    $hasCorrect = true;
                }
                $choices[] = [
                    'label'      => chr(65 + count($choices)),
                    'content'    => $choiceText ?? '',
                    'is_correct' => $isCorrect,
                    'order'      => count($choices) + 1,
                ];
            }
        }

        if ($isMcq && !$hasCorrect) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'correct_choice' => 'Please select the correct answer.',
            ]);
        }

        $this->builderService->createAssessmentQuestion($section, [
            'prompt'                 => $validated['prompt'] ?? '',
            'section'                => $sectionType,
            'part_number'            => $partNumber,
            'question_type'          => $validated['question_type'],
            'difficulty'             => $detection['difficulty_level'],
            'difficulty_score'       => $detection['difficulty_score'],
            'difficulty_status'      => $detection['difficulty_status'],
            'difficulty_source'      => 'auto',
            'difficulty_factors'     => $detection['difficulty_factors'],
            'difficulty_detected_at' => $detection['difficulty_detected_at'],
            'points'                 => $validated['points'] ?? 1,
            'explanation'            => $validated['explanation'] ?? null,
            'media_asset_id'         => $validated['media_asset_id'] ?? null,
            'image_media_asset_id'   => ((int) $partNumber === 2) ? null : ($validated['image_media_asset_id'] ?? null),
            'audio_media_asset_id'   => $validated['audio_media_asset_id'] ?? null,
            'image_url'              => ((int) $partNumber === 2) ? null : ($validated['image_url'] ?? null),
            'audio_url'              => $validated['audio_url'] ?? null,
            'passage_id'             => $validated['passage_id'] ?? null,
            'passage_text'           => $validated['passage_text'] ?? null,
            'choices'                => $choices,
        ]);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment-authored question created and added to section '{$section->title}'.")
            ->with('expanded_section_id', $section->id);
    }

    /**
     * Create an Assessment-authored Shared Audio Group (Part 3 / Part 4) with up to 3 child questions.
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
            'media_asset_id'  => ['nullable', 'exists:media_assets,id'],
            'audio_url'       => ['nullable', 'string'],
            'audio_script'    => ['nullable', 'string'],
            'questions'       => ['required', 'array', 'size:3'],
            'questions.*.prompt'         => ['nullable', 'string'],
            'questions.*.difficulty'     => ['nullable', 'string'],
            'questions.*.explanation'    => ['nullable', 'string'],
            'questions.*.choices'        => ['nullable', 'array'],
            'questions.*.correct_choice' => ['nullable'],
        ]);

        if ((int) $validated['part_number'] === 3 && $validated['group_type'] !== 'conversation') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'group_type' => ['Part 3 audio group must have group_type set to conversation.'],
            ]);
        }
        if ((int) $validated['part_number'] === 4 && $validated['group_type'] !== 'talk') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'group_type' => ['Part 4 audio group must have group_type set to talk.'],
            ]);
        }

        if (empty($validated['media_asset_id']) && empty($validated['audio_url'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'media_asset_id' => ['Please attach a shared audio file before saving this audio question group.'],
            ]);
        }

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();

        $audioGroup = $this->builderService->createAudioGroup($section, $validated);

        $statusMsg = $audioGroup->isComplete()
            ? "Part {$validated['part_number']} Shared Audio Group successfully created and attached to '{$section->title}' (3/3 Complete)."
            : "Part {$validated['part_number']} Shared Audio Group draft saved to '{$section->title}' ({$audioGroup->complete_questions_count}/3 Complete).";

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', $statusMsg)
            ->with('expanded_section_id', $section->id);
    }

    /**
     * Update an Assessment-authored Shared Audio Group (Part 3 / Part 4) and its child questions.
     */
    public function updateAudioGroup(Request $request, Test $test, AudioGroup $audioGroup): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $validated = $request->validate([
            'test_section_id' => ['nullable', 'exists:test_sections,id'],
            'title'           => ['nullable', 'string', 'max:255'],
            'group_type'      => ['nullable', 'string', 'in:conversation,talk'],
            'part_number'     => ['nullable', 'integer', 'in:3,4'],
            'media_asset_id'  => ['nullable', 'exists:media_assets,id'],
            'audio_url'       => ['nullable', 'string'],
            'audio_script'    => ['nullable', 'string'],
            'questions'       => ['nullable', 'array', 'max:3'],
            'questions.*.id'             => ['nullable', 'string'],
            'questions.*.prompt'         => ['nullable', 'string'],
            'questions.*.difficulty'     => ['nullable', 'string'],
            'questions.*.explanation'    => ['nullable', 'string'],
            'questions.*.choices'        => ['nullable', 'array'],
            'questions.*.correct_choice' => ['nullable'],
        ]);

        $section = null;
        if (!empty($validated['test_section_id'])) {
            $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->first();
        }

        $updatedGroup = $this->builderService->updateAudioGroup($audioGroup, $validated, $section);

        $statusMsg = $updatedGroup->isComplete()
            ? "Part {$updatedGroup->part_number} Audio Group saved successfully (3/3 Complete)."
            : "Part {$updatedGroup->part_number} Audio Group draft saved ({$updatedGroup->complete_questions_count}/3 Complete).";

        $redirect = redirect()->route('teacher.tests.show', $test->id)
            ->with('status', $statusMsg);

        if ($section) {
            $redirect->with('expanded_section_id', $section->id);
        }

        return $redirect;
    }

    /**
     * Remove an Assessment-authored Shared Audio Group from Assessment.
     */
    public function destroyAudioGroup(Request $request, Test $test, AudioGroup $audioGroup): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true) || $test->is_published) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        if ((string) $audioGroup->test_id !== (string) $test->id) {
            abort(404, 'Audio group does not belong to this assessment.');
        }

        $partNumber = $audioGroup->part_number;
        $originSection = $test->sections()->where('title', 'LIKE', "%Part {$partNumber}%")->orWhere('order', $partNumber)->first()
            ?? $test->sections()->where('section_type', 'listening')->first();
        $originSectionId = $originSection?->id;

        $groupType = $audioGroup->isTalk() ? 'Talk' : 'Conversation';

        try {
            $this->builderService->deleteAudioGroup($test, $audioGroup);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->withErrors($e->validator);
        }

        $redirect = redirect()->route('teacher.tests.show', array_filter([
            'test'    => $test->id,
            'section' => $originSectionId,
        ]))->with('status', "Part {$partNumber} {$groupType} Group removed successfully.");

        if ($originSectionId) {
            $redirect->with('expanded_section_id', $originSectionId);
        }

        return $redirect;
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
            'passages.*.id'            => ['nullable', 'string'],
            'passages.*.title'         => ['nullable', 'string', 'max:255'],
            'passages.*.content'       => ['nullable', 'string'],
            'passages.*.image_url'     => ['nullable', 'string'],
            'passages.*.media_asset_id'=> ['nullable', 'string'],
            'passages.*.content_mode'  => ['nullable', 'string'],
            'passages.*.document_type' => ['nullable', 'string'],
            'passages.*.order_in_group'=> ['nullable', 'integer'],
            'questions'        => ['required', 'array', 'min:2', 'max:5'],
            'questions.*.id'             => ['nullable', 'string'],
            'questions.*.prompt'         => ['nullable', 'string'],
            'questions.*.difficulty'     => ['nullable', 'string'],
            'questions.*.explanation'    => ['nullable', 'string'],
            'questions.*.choices'        => ['required', 'array', 'size:4'],
            'questions.*.correct_choice' => ['required'],
        ]);

        $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->firstOrFail();

        try {
            $this->builderService->createPassageGroup($section, $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('teacher.tests.show', array_filter([
                'test'    => $test->id,
                'section' => $section->id,
            ]))->withErrors($e->validator ?: $e->errors())->withInput()->with('expanded_section_id', $section->id);
        }

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Part {$validated['part_number']} " . ucfirst($validated['passage_type']) . " Passage Group successfully created and attached to '{$section->title}'.")
            ->with('expanded_section_id', $section->id);
    }

    /**
     * Update an Assessment-authored Shared Passage Group and its child questions.
     */
    public function updatePassageGroup(Request $request, Test $test, PassageGroup $passageGroup): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true)) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        $validated = $request->validate([
            'test_section_id'  => ['nullable', 'exists:test_sections,id'],
            'title'            => ['nullable', 'string', 'max:255'],
            'part_number'      => ['nullable', 'integer', 'in:6,7'],
            'passage_type'     => ['nullable', 'string', 'in:single,double,triple'],
            'context_metadata' => ['nullable', 'array'],
            'passages'         => ['required', 'array', 'min:1', 'max:3'],
            'passages.*.id'            => ['nullable', 'string'],
            'passages.*.title'         => ['nullable', 'string', 'max:255'],
            'passages.*.content'       => ['nullable', 'string'],
            'passages.*.image_url'     => ['nullable', 'string'],
            'passages.*.media_asset_id'=> ['nullable', 'string'],
            'passages.*.content_mode'  => ['nullable', 'string'],
            'passages.*.document_type' => ['nullable', 'string'],
            'passages.*.order_in_group'=> ['nullable', 'integer'],
            'questions'        => ['required', 'array', 'min:2', 'max:5'],
            'questions.*.id'             => ['nullable', 'string'],
            'questions.*.prompt'         => ['nullable', 'string'],
            'questions.*.difficulty'     => ['nullable', 'string'],
            'questions.*.explanation'    => ['nullable', 'string'],
            'questions.*.choices'        => ['required', 'array', 'size:4'],
            'questions.*.correct_choice' => ['required'],
        ]);

        $section = null;
        if (!empty($validated['test_section_id'])) {
            $section = TestSection::where('test_id', $test->id)->where('id', $validated['test_section_id'])->first();
        }

        try {
            $updatedGroup = $this->builderService->updatePassageGroup($passageGroup, $validated, $section);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('teacher.tests.show', array_filter([
                'test'    => $test->id,
                'section' => $section?->id,
            ]))->withErrors($e->validator ?: $e->errors())->withInput();
        }

        $partNum = $updatedGroup->part_number;
        $groupTypeName = ((int) $partNum === 6) ? 'Text Completion' : 'Reading Passage';
        $statusMsg = $updatedGroup->isComplete()
            ? "Part {$partNum} {$groupTypeName} Group saved successfully."
            : "Part {$partNum} {$groupTypeName} Group updated.";

        $redirect = redirect()->route('teacher.tests.show', array_filter([
            'test'    => $test->id,
            'section' => $section?->id,
            'focus'   => "passage-group-card-{$updatedGroup->id}",
        ]))->with('status', $statusMsg);

        if ($section) {
            $redirect->with('expanded_section_id', $section->id);
        }

        return $redirect;
    }

    /**
     * Remove an Assessment-authored Shared Passage Group from Assessment.
     */
    public function destroyPassageGroup(Request $request, Test $test, PassageGroup $passageGroup): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole('teacher') && (int) $test->created_by !== (int) $user->id && (int) $test->assigned_to !== (int) $user->id) {
            abort(403, 'Unauthorized access to assessment test.');
        }

        if (!in_array($test->status, ['draft', 'rejected', 'needs_revision', 'revision_requested'], true) || $test->is_published) {
            abort(403, "Assessment is {$test->status} and locked from editing.");
        }

        if ((string) $passageGroup->test_id !== (string) $test->id) {
            abort(404, 'Passage group does not belong to this assessment.');
        }

        $partNumber = $passageGroup->part_number;
        $originSection = $test->sections()->where('title', 'LIKE', "%Part {$partNumber}%")->orWhere('order', $partNumber)->first()
            ?? $test->sections()->where('section_type', 'reading')->first();
        $originSectionId = $originSection?->id;

        $isPart6 = ((int) $partNumber === 6);
        $groupType = $isPart6 ? 'Text Completion' : 'Reading Passage';

        try {
            $this->builderService->deletePassageGroup($test, $passageGroup);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('teacher.tests.show', $test->id)
                ->withErrors($e->validator);
        }

        $redirect = redirect()->route('teacher.tests.show', array_filter([
            'test'    => $test->id,
            'section' => $originSectionId,
        ]))->with('status', "Part {$partNumber} {$groupType} Group removed successfully.");

        if ($originSectionId) {
            $redirect->with('expanded_section_id', $originSectionId);
        }

        return $redirect;
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
            ->with('status', "Section '{$section->title}' updated successfully.")
            ->with('expanded_section_id', $section->id);
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
            ->with('status', 'Media asset attached to section successfully.')
            ->with('expanded_section_id', $section->id);
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
            ->with('status', 'Media asset detached from section.')
            ->with('expanded_section_id', $section->id);
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

        // Authoritative Return Context Resolution
        $originSectionId = $request->query('return_section') ?? $request->query('section');
        if ($originSectionId && !$test->sections()->where('id', (string) $originSectionId)->exists()) {
            $originSectionId = null;
        }
        if (!$originSectionId) {
            $originSectionId = \App\Modules\Assessment\Models\TestQuestion::where('question_id', (string) $question->id)
                ->whereHas('section', fn($q) => $q->where('test_id', (string) $test->id))
                ->first()?->test_section_id;
        }
        if (!$originSectionId && $question->part_number) {
            $originSectionId = $test->sections()->where('title', 'LIKE', "%Part {$question->part_number}%")->orWhere('order', $question->part_number)->first()?->id;
        }

        $returnFocus = $request->query('return_focus') ?? $request->query('focus') ?? ('question-card-' . $question->id);

        $returnUrl = $originSectionId
            ? route('teacher.tests.show', ['test' => $test->id, 'section' => $originSectionId, 'focus' => $returnFocus])
            : route('teacher.tests.show', $test->id);

        return view('teacher.question_editor', compact('test', 'question', 'originSectionId', 'returnFocus', 'returnUrl'));
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
        $rawPartNumber = $request->input('part_number') ?? $question->part_number;
        $promptRule = ToeicQuestionValidator::requiresPrompt($rawPartNumber) ? ['required', 'string'] : ['nullable', 'string'];

        $validated = $request->validate([
            'prompt'               => $promptRule,
            'question_type'        => ['nullable', 'string'],
            'difficulty'           => ['nullable', 'string'],
            'explanation'          => ['nullable', 'string'],
            'choices'              => ['nullable', 'array'],
            'correct_choice'       => ['nullable'],
            'media_asset_id'       => ['nullable', 'string'],
            'image_media_asset_id' => ['nullable', 'string'],
            'audio_media_asset_id' => ['nullable', 'string'],
            'image_url'            => ['nullable', 'string'],
            'audio_url'            => ['nullable', 'string'],
            'passage_id'           => ['nullable', 'string'],
            'passage_text'         => ['nullable', 'string'],
            'part_number'          => ['nullable', 'integer', 'between:1,7'],
            'section'              => ['nullable', 'string'],
        ]);

        $detection = QuestionDifficultyDetectionService::detect($request->all(), $question);

        if ($request->filled('part_number')) {
            $toeicData = $request->all();
            $partNumber = (int) $request->input('part_number');
            if ($partNumber === 2 && isset($toeicData['choices']) && is_array($toeicData['choices']) && count($toeicData['choices']) === 4) {
                $c3 = $toeicData['choices'][3] ?? null;
                $text3 = is_array($c3) ? ($c3['content'] ?? ($c3['choice_text'] ?? '')) : (string) ($c3 ?? '');
                $corr = $toeicData['correct_choice'] ?? null;
                if (empty(trim($text3)) && (string) $corr !== '3') {
                    $toeicData['choices'] = array_slice($toeicData['choices'], 0, 3);
                    if (isset($validated['choices'])) {
                        $validated['choices'] = array_slice($validated['choices'], 0, 3);
                    }
                }
            }
            $toeicData['prompt'] = $validated['prompt'] ?? '';
            $toeicData['difficulty'] = $detection['difficulty_level'];
            ToeicQuestionValidator::validate($toeicData, $question);
            $sectionType = ToeicQuestionValidator::deriveSection($partNumber);
            $question->part_number = $partNumber;
            $question->section = $sectionType;
        } elseif ($request->filled('section')) {
            $question->section = $request->input('section');
        } elseif (empty($question->section)) {
            $question->section = 'reading';
        }

        // Reuse existing Question ID (TASK 3 & TASK 9)
        $question->prompt = $validated['prompt'] ?? '';
        if (isset($validated['question_type'])) $question->question_type = $validated['question_type'];
        $question->difficulty = $detection['difficulty_level'];
        $question->difficulty_score = $detection['difficulty_score'];
        $question->difficulty_status = $detection['difficulty_status'];
        $question->difficulty_source = 'auto';
        $question->difficulty_factors = $detection['difficulty_factors'];
        $question->difficulty_detected_at = $detection['difficulty_detected_at'];
        if (isset($validated['explanation'])) $question->explanation = $validated['explanation'];
        if (isset($validated['passage_id'])) $question->passage_id = $validated['passage_id'];
        if (isset($validated['passage_text'])) $question->passage_text = $validated['passage_text'];

        $effectivePart = $question->part_number ?? $request->input('part_number');
        if ((int) $effectivePart === 2) {
            $question->image_url = null;
            $question->image_media_asset_id = null;
        } else {
            if ($request->has('image_media_asset_id')) {
                $question->image_media_asset_id = $request->input('image_media_asset_id') ?: null;
            }
            if ($request->has('image_url')) {
                $question->image_url = $request->input('image_url') ?: null;
            }
        }

        if ($request->has('audio_media_asset_id')) {
            $question->audio_media_asset_id = $request->input('audio_media_asset_id') ?: null;
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
            $effectivePart = $question->part_number ?? $request->input('part_number');
            $isAudioOnlyChoice = ToeicQuestionValidator::isAudioOnlyChoicePart($effectivePart);
            $isPart1 = ((int) $effectivePart === 1);
            $isPart2 = ((int) $effectivePart === 2);

            foreach ($choicesData as $idx => $choiceText) {
                if ($isPart1 && $validIdx >= 4) break;
                if ($isPart2 && $validIdx >= 3) break;
                if (!$isAudioOnlyChoice && empty(trim((string)$choiceText))) continue;

                $isCorrect = (!is_null($correctChoiceIndex) && $correctChoiceIndex !== '' && (string) $idx === (string) $correctChoiceIndex);
                if ($isCorrect) {
                    $hasCorrect = true;
                }

                $choice = $existingChoices->get($validIdx);

                if ($choice) {
                    $choice->update([
                        'label'       => chr(65 + $validIdx),
                        'content'     => $choiceText ?? '',
                        'choice_text' => $choiceText ?? '',
                        'is_correct'  => $isCorrect,
                    ]);
                } else {
                    $question->choices()->create([
                        'label'       => chr(65 + $validIdx),
                        'content'     => $choiceText ?? '',
                        'choice_text' => $choiceText ?? '',
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

        // Authoritative Return Context Resolution
        $returnSectionId = $request->input('return_section') ?? $request->input('section');
        if ($returnSectionId && !$test->sections()->where('id', (string) $returnSectionId)->exists()) {
            $returnSectionId = null;
        }
        if (!$returnSectionId) {
            $returnSectionId = \App\Modules\Assessment\Models\TestQuestion::where('question_id', (string) $question->id)
                ->whereHas('section', fn($q) => $q->where('test_id', (string) $test->id))
                ->first()?->test_section_id;
        }
        if (!$returnSectionId && $question->part_number) {
            $returnSectionId = $test->sections()->where('title', 'LIKE', "%Part {$question->part_number}%")->orWhere('order', $question->part_number)->first()?->id;
        }

        $returnFocus = $request->input('return_focus') ?? $request->input('focus') ?? ('question-card-' . $question->id);

        $redirectParams = ['test' => $test->id];
        if ($returnSectionId) {
            $redirectParams['section'] = $returnSectionId;
            $redirectParams['focus'] = $returnFocus;
        }

        $redirect = redirect()->route('teacher.tests.show', $redirectParams)
            ->with('status', "Question #{$question->id} updated successfully.");

        if ($returnSectionId) {
            $redirect->with('expanded_section_id', $returnSectionId);
        }

        return $redirect;
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

        $previousStatus = $test->status;
        $isFirstSubmission = ($previousStatus === 'draft');

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

        $this->notifyRepositoryManagersOfSubmission($test, $user, $isFirstSubmission);

        return redirect()->route('teacher.tests.show', $test->id)
            ->with('status', "Assessment resubmitted successfully.");
    }

    /**
     * Dispatch in-app EnterpriseSystemNotification to Repository Managers on assessment submission/resubmission.
     */
    private function notifyRepositoryManagersOfSubmission(Test $test, ?\App\Models\User $actor, bool $isFirstSubmission = true): void
    {
        $actorName = $actor?->name ?? 'Teacher';
        $title = $isFirstSubmission
            ? 'Assessment Submitted for Review'
            : 'Assessment Resubmitted for Review';
        $message = $isFirstSubmission
            ? "{$actorName} submitted '{$test->title}' for Repository review."
            : "{$actorName} resubmitted '{$test->title}' after revision.";
        $notifType = $isFirstSubmission
            ? 'ASSESSMENT_SUBMITTED'
            : 'ASSESSMENT_RESUBMITTED';

        $targetUrl = route('admin.repository-manager.assessment-review', $test->id);

        $repoManagers = \App\Models\User::role('repository-manager')->get();

        foreach ($repoManagers as $manager) {
            if (method_exists($manager, 'notify')) {
                try {
                    $manager->notify(new \App\Notifications\EnterpriseSystemNotification(
                        title: $title,
                        message: $message,
                        type: $notifType,
                        priority: 'HIGH',
                        entityType: 'Test',
                        entityId: (string) $test->id,
                        targetUrl: $targetUrl
                    ));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Failed to dispatch assessment submission notification to RM #{$manager->id} for Test #{$test->id}: " . $e->getMessage());
                }
            }
        }
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
        if ($test->isSimulator()) {
            return back()->with('error', "Test Simulator '{$test->title}' uses open candidate access and does not support manual candidate assignment.");
        }

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
