<?php

namespace App\Modules\QuestionBank\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
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
     * Show detailed question bank authoring workspace.
     */
    public function show(QuestionBank $questionBank): View
    {
        $questionBank->load(['questions.choices', 'category']);

        /** @var view-string $viewName */
        $viewName = 'question_bank::show';

        return view($viewName, compact('questionBank'));
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
     * Author a new question into a question bank.
     */
    public function storeQuestion(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string'],
            'question_type' => ['required', 'string'],
            'difficulty' => ['required', 'string'],
            'points' => ['required', 'integer', 'min:1'],
            'explanation' => ['nullable', 'string'],
            'passage_text' => ['nullable', 'string'],
            'audio_url' => ['nullable', 'string'],
            'choices' => ['nullable', 'array'],
            'choices.*.label' => ['required_with:choices', 'string'],
            'choices.*.content' => ['required_with:choices', 'string'],
            'correct_choice' => ['nullable'],
        ]);

        $question = Question::create([
            'question_bank_id' => $questionBank->id,
            'prompt' => $validated['prompt'],
            'question_type' => $validated['question_type'],
            'difficulty' => $validated['difficulty'],
            'points' => $validated['points'],
            'explanation' => $validated['explanation'] ?? null,
            'passage_text' => $validated['passage_text'] ?? null,
            'audio_url' => $validated['audio_url'] ?? null,
        ]);

        if (!empty($validated['choices'])) {
            $correctIdx = (int) ($validated['correct_choice'] ?? 0);
            foreach ($validated['choices'] as $idx => $choiceData) {
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'label' => $choiceData['label'],
                    'content' => $choiceData['content'],
                    'is_correct' => ($idx === $correctIdx),
                ]);
            }
        }

        return redirect()->route('admin.question-banks.show', $questionBank->id)->with('status', 'question-created');
    }

    /**
     * Import questions in bulk from CSV data.
     */
    public function importQuestions(Request $request, QuestionBank $questionBank): RedirectResponse
    {
        $validated = $request->validate([
            'csv_content' => ['required', 'string'],
        ]);

        $lines = explode("\n", trim($validated['csv_content']));
        $importedCount = 0;

        foreach ($lines as $line) {
            $cols = str_getcsv(trim($line));
            if (count($cols) >= 6) {
                // Prompt, Choice A, Choice B, Choice C, Choice D, Correct Index (0-3)
                $prompt = $cols[0];
                $q = Question::create([
                    'question_bank_id' => $questionBank->id,
                    'prompt' => $prompt,
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'points' => 5,
                    'explanation' => 'Imported via CSV batch processor.',
                ]);

                $correctIdx = (int) ($cols[5] ?? 0);
                $labels = ['A', 'B', 'C', 'D'];
                for ($i = 0; $i < 4; $i++) {
                    if (isset($cols[$i + 1])) {
                        QuestionChoice::create([
                            'question_id' => $q->id,
                            'label' => $labels[$i],
                            'content' => $cols[$i + 1],
                            'is_correct' => ($i === $correctIdx),
                        ]);
                    }
                }
                $importedCount++;
            }
        }

        return redirect()->route('admin.question-banks.show', $questionBank->id)->with('status', "Imported {$importedCount} questions successfully.");
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
     * Delete a question.
     */
    public function destroyQuestion(Question $question): RedirectResponse
    {
        $bankId = $question->question_bank_id;
        $question->delete();

        return redirect()->route('admin.question-banks.show', $bankId)->with('status', 'question-deleted');
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
