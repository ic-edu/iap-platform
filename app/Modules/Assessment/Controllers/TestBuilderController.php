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
}
