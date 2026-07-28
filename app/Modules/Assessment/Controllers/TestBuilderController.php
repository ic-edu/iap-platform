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
     */
    public function store(Request $request): RedirectResponse
    {
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
            'created_by' => $request->user() ? $request->user()->id : 1,
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
    public function duplicate(Test $test): RedirectResponse
    {
        $newTest = $test->replicate();
        $newTest->title = $test->title.' (Copy)';
        $newTest->slug = Str::slug($newTest->title).'-'.Str::random(5);
        $newTest->is_published = false;
        $newTest->save();

        foreach ($test->sections as $sec) {
            $newSec = $sec->replicate();
            $newSec->test_id = (string) $newTest->id;
            $newSec->save();
        }

        return redirect()->route('admin.tests.index')->with('status', 'test-duplicated');
    }

    /**
     * Submit draft test for approval.
     */
    public function submitForApproval(Test $test): RedirectResponse
    {
        $test->update(['is_published' => false]);

        return redirect()->route('admin.tests.index')->with('status', 'Test submitted for Super Admin review & approval successfully.');
    }

    /**
     * Approve and publish test.
     */
    public function publish(Test $test): RedirectResponse
    {
        $this->builderService->publishTest($test);

        return redirect()->route('admin.tests.index')->with('status', 'test-published');
    }

    /**
     * Reject and revert test to draft.
     */
    public function reject(Test $test): RedirectResponse
    {
        $test->update(['is_published' => false]);

        return redirect()->route('admin.tests.index')->with('status', 'test-rejected');
    }

    /**
     * Delete a test.
     */
    public function destroy(Test $test): RedirectResponse
    {
        $test->delete();

        return redirect()->route('admin.tests.index')->with('status', 'test-deleted');
    }
}
