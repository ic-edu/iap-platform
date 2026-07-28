<?php

namespace App\Modules\QuestionBank\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\CourseCategory;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ActivityLogger;
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

        return redirect()->route('admin.question-banks.index')->with('status', 'Question bank created successfully.');
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
            'choices.*.label' => ['nullable', 'string'],
            'choices.*.content' => ['nullable', 'string'],
            'correct_choice' => ['nullable'],
            'correct_choices' => ['nullable', 'array'],
            'tf_correct_choice' => ['nullable', 'string'],
            'short_answer_text' => ['nullable', 'string'],
            'reference_answer_text' => ['nullable', 'string'],
        ]);

        $qType = $validated['question_type'];

        $question = Question::create([
            'question_bank_id' => $questionBank->id,
            'prompt' => $validated['prompt'],
            'question_type' => $qType,
            'difficulty' => $validated['difficulty'],
            'points' => $validated['points'],
            'explanation' => $validated['explanation'] ?? ($validated['reference_answer_text'] ?? null),
            'passage_text' => $validated['passage_text'] ?? null,
            'audio_url' => $validated['audio_url'] ?? null,
        ]);

        $this->saveChoicesForQuestion($question, $qType, $validated);

        return redirect()->route('admin.question-banks.show', $questionBank->id)
            ->with('status', "Question successfully authored and saved. Total questions in bank: {$questionBank->questions()->count()}.");
    }

    /**
     * Update an existing question.
     */
    public function updateQuestion(Request $request, Question $question): RedirectResponse
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
            'choices.*.label' => ['nullable', 'string'],
            'choices.*.content' => ['nullable', 'string'],
            'correct_choice' => ['nullable'],
            'correct_choices' => ['nullable', 'array'],
            'tf_correct_choice' => ['nullable', 'string'],
            'short_answer_text' => ['nullable', 'string'],
            'reference_answer_text' => ['nullable', 'string'],
        ]);

        $qType = $validated['question_type'];

        $question->update([
            'prompt' => $validated['prompt'],
            'question_type' => $qType,
            'difficulty' => $validated['difficulty'],
            'points' => $validated['points'],
            'explanation' => $validated['explanation'] ?? ($validated['reference_answer_text'] ?? null),
            'passage_text' => $validated['passage_text'] ?? null,
            'audio_url' => $validated['audio_url'] ?? null,
        ]);

        // Rebuild choices for updated question while preserving Question ID
        $question->choices()->delete();
        $this->saveChoicesForQuestion($question, $qType, $validated);

        return redirect()->route('admin.question-banks.show', $question->question_bank_id)
            ->with('status', 'Question updated successfully.');
    }

    /**
     * Duplicate a question inside a question bank.
     */
    public function duplicateQuestion(Question $question): RedirectResponse
    {
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
     * Helper to save choices for a question based on its question_type.
     */
    private function saveChoicesForQuestion(Question $question, string $qType, array $validated): void
    {
        if (in_array($qType, ['single_choice', 'listening', 'reading'])) {
            if (!empty($validated['choices'])) {
                $correctIdx = (int) ($validated['correct_choice'] ?? 0);
                foreach ($validated['choices'] as $idx => $choiceData) {
                    if (!empty($choiceData['content'])) {
                        QuestionChoice::create([
                            'question_id' => $question->id,
                            'label' => $choiceData['label'] ?? chr(65 + $idx),
                            'content' => $choiceData['content'],
                            'is_correct' => ($idx === $correctIdx),
                        ]);
                    }
                }
            }
        } elseif ($qType === 'multiple_choice') {
            if (!empty($validated['choices'])) {
                $correctIndices = array_map('intval', $validated['correct_choices'] ?? []);
                foreach ($validated['choices'] as $idx => $choiceData) {
                    if (!empty($choiceData['content'])) {
                        QuestionChoice::create([
                            'question_id' => $question->id,
                            'label' => $choiceData['label'] ?? chr(65 + $idx),
                            'content' => $choiceData['content'],
                            'is_correct' => in_array($idx, $correctIndices, true),
                        ]);
                    }
                }
            }
        } elseif ($qType === 'true_false') {
            $tfCorrect = strtolower($validated['tf_correct_choice'] ?? 'true');
            QuestionChoice::create([
                'question_id' => $question->id,
                'label' => 'A',
                'content' => 'True',
                'is_correct' => ($tfCorrect === 'true'),
            ]);
            QuestionChoice::create([
                'question_id' => $question->id,
                'label' => 'B',
                'content' => 'False',
                'is_correct' => ($tfCorrect === 'false'),
            ]);
        } elseif ($qType === 'short_answer') {
            $answerText = $validated['short_answer_text'] ?? 'Correct Answer';
            QuestionChoice::create([
                'question_id' => $question->id,
                'label' => 'A',
                'content' => $answerText,
                'is_correct' => true,
            ]);
        }
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

        return redirect()->route('admin.question-banks.index')->with('status', 'Question bank duplicated successfully.');
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
                'question_id' => $question->id,
                'question_title' => $question->prompt,
            ]
        );

        $bankId = $question->question_bank_id;
        $question->delete();

        return redirect()->route('admin.question-banks.show', $bankId)->with('status', 'Question deleted successfully.');
    }

    /**
     * Remove the specified question bank.
     */
    public function destroy(QuestionBank $questionBank): RedirectResponse
    {
        $user = request()->user();
        if ($user && $user->hasRole('teacher')) {
            abort(403, 'Teachers are not permitted to delete question banks.');
        }

        $questionBank->delete();

        return redirect()->route('admin.question-banks.index')->with('status', 'Question bank deleted successfully.');
    }
}
