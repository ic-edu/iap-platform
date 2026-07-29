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
     * Display listing of tests.
     */
    public function index(): View
    {
        $tests = Test::with(['sections', 'creator'])->latest()->paginate(10);
        $questions = Question::all();

        /** @var view-string $viewName */
        $viewName = 'assessment::index';

        return view($viewName, compact('tests', 'questions'));
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
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'shuffle_choices' => $request->boolean('shuffle_choices'),
            'is_published' => false,
            'status' => 'draft',
            'created_by' => $user ? $user->id : 1,
        ]);

        // Auto-create default section for rapid test building
        TestSection::create([
            'test_id' => $test->id,
            'title' => 'Main Section',
            'duration_minutes' => $test->duration_minutes,
            'order' => 1,
        ]);

        return redirect()->route('admin.tests.index')->with('status', 'test-created');
    }

    /**
     * Duplicate an assessment test.
     */
    public function duplicate(Request $request, Test $test): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('super-admin')) {
            abort(403, 'Super Admin is an auditor/approver and cannot duplicate/create tests directly.');
        }

        $newTest = $test->replicate();
        $newTest->title = $test->title.' (Copy)';
        $newTest->slug = Str::slug($newTest->title).'-'.Str::random(5);
        $newTest->is_published = false;
        $newTest->status = 'draft';
        $newTest->save();

        foreach ($test->sections as $sec) {
            $newSec = $sec->replicate();
            $newSec->test_id = (string) $newTest->id;
            $newSec->save();
        }

        return redirect()->route('admin.tests.index')->with('status', 'test-duplicated');
    }

    /**
     * Submit draft test for Super Admin approval.
     */
    public function submitForApproval(Test $test): RedirectResponse
    {
        $test->update([
            'status' => 'pending_approval',
            'is_published' => false,
        ]);

        return redirect()->route('admin.tests.index')->with('status', 'Test submitted for Super Admin review & approval successfully.');
    }

    /**
     * Publish an approved test (Operational Admin Only).
     * Constraint: Test MUST have status === 'approved'.
     */
    public function publish(Test $test): RedirectResponse
    {
        $user = request()->user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Only Operational Admin can publish approved tests.');
        }

        if ($test->status !== 'approved') {
            abort(403, 'Assessment tests can only be published after receiving Super Admin approval.');
        }

        $test->update([
            'status' => 'published',
            'is_published' => true,
        ]);

        $this->builderService->publishTest($test);

        return redirect()->route('admin.tests.index')->with('status', 'test-published');
    }

    /**
     * Reject and revert test to draft.
     */
    public function reject(Test $test): RedirectResponse
    {
        $user = request()->user();
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Only Super Admin can reject test approvals.');
        }

        $test->update([
            'status' => 'rejected',
            'is_published' => false,
        ]);

        return redirect()->route('admin.tests.index')->with('status', 'test-rejected');
    }

    /**
     * Delete a test.
     */
    public function destroy(Test $test): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers are not permitted to delete tests.');
        }

        if ($test->is_published && !$user?->hasRole('super-admin')) {
            abort(403, 'Published tests cannot be deleted except by Super Admin.');
        }

        $test->delete();

        return redirect()->route('admin.tests.index')->with('status', 'test-deleted');
    }
}
