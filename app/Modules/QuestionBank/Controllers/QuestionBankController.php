<?php

namespace App\Modules\QuestionBank\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    /**
     * Display a listing of question banks.
     */
    public function index(Request $request): View
    {
        $query = QuestionBank::with(['category', 'creator', 'questions']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('test_type')) {
            $query->where('test_type', $type);
        }

        $banks = $query->latest()->paginate(10)->withQueryString();
        $categories = CourseCategory::all();

        /** @var view-string $viewName */
        $viewName = 'question_bank::index';

        return view($viewName, compact('banks', 'categories'));
    }

    /**
     * Store a newly created question bank.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:course_categories,id'],
            'test_type' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        QuestionBank::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(5),
            'category_id' => $validated['category_id'] ?? null,
            'created_by' => $request->user() ? $request->user()->id : 1,
            'test_type' => $validated['test_type'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('admin.question-banks.index')->with('status', 'question-bank-created');
    }

    /**
     * Duplicate an existing question bank.
     */
    public function duplicate(QuestionBank $questionBank): RedirectResponse
    {
        $newBank = $questionBank->replicate();
        $newBank->title = $questionBank->title.' (Copy)';
        $newBank->slug = Str::slug($newBank->title).'-'.Str::random(5);
        $newBank->save();

        foreach ($questionBank->questions as $question) {
            $newQ = $question->replicate();
            $newQ->question_bank_id = (string) $newBank->id;
            $newQ->save();

            foreach ($question->choices as $choice) {
                $newC = $choice->replicate();
                $newC->question_id = (string) $newQ->id;
                $newC->save();
            }
        }

        return redirect()->route('admin.question-banks.index')->with('status', 'question-bank-duplicated');
    }

    /**
     * Remove the specified question bank.
     */
    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        $questionBank->delete();

        return redirect()->route('admin.question-banks.index')->with('status', 'question-bank-deleted');
    }
}
