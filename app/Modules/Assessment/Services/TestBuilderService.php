<?php

namespace App\Modules\Assessment\Services;

use App\Models\MediaAsset;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TestBuilderService
{
    /**
     * Validate and publish a test.
     *
     * @throws ValidationException
     */
    public function publishTest(Test $test): Test
    {
        $sections = $test->sections()->with('testQuestions')->get();

        if ($sections->isEmpty()) {
            throw ValidationException::withMessages([
                'test' => 'Cannot publish test: At least one section is required.',
            ]);
        }

        foreach ($sections as $section) {
            if ($section->testQuestions->isEmpty()) {
                throw ValidationException::withMessages([
                    'test' => "Cannot publish test: Section '{$section->title}' has no questions assigned.",
                ]);
            }
        }

        if ($test->duration_minutes <= 0) {
            throw ValidationException::withMessages([
                'duration_minutes' => 'Test duration must be greater than 0 minutes.',
            ]);
        }

        if ($test->pass_score < 0) {
            throw ValidationException::withMessages([
                'pass_score' => 'Passing score cannot be negative.',
            ]);
        }

        $test->update(['is_published' => true]);

        return $test;
    }

    /**
     * Assign question to a test section preventing duplicates.
     *
     * @throws ValidationException
     */
    public function assignQuestionToSection(TestSection $section, string $questionId, int $order = 1, int $points = 1): TestQuestion
    {
        $exists = TestQuestion::where('test_section_id', $section->id)
            ->where('question_id', $questionId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'question_id' => 'Question is already assigned to this section.',
            ]);
        }

        return TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $questionId,
            'order' => $order,
            'points' => $points,
        ]);
    }

    /**
     * Create an Assessment-authored question (ad-hoc, question_bank_id = null) and attach it to a section.
     */
    public function createAssessmentQuestion(TestSection $section, array $data): TestQuestion
    {
        $question = \App\Modules\QuestionBank\Models\Question::create([
            'question_bank_id' => null,
            'media_asset_id'   => $data['media_asset_id'] ?? null,
            'image_url'        => $data['image_url'] ?? null,
            'audio_url'        => $data['audio_url'] ?? null,
            'prompt'           => $data['prompt'],
            'question_type'    => $data['question_type'] ?? 'multiple_choice',
            'difficulty'       => $data['difficulty'] ?? 'medium',
            'points'           => $data['points'] ?? 1,
            'explanation'      => $data['explanation'] ?? null,
            'passage_text'     => $data['passage_text'] ?? null,
        ]);

        if (!empty($data['choices']) && is_array($data['choices'])) {
            foreach ($data['choices'] as $choice) {
                \App\Modules\QuestionBank\Models\QuestionChoice::create([
                    'question_id' => $question->id,
                    'label'       => $choice['label'] ?? 'A',
                    'content'     => $choice['content'] ?? '',
                    'is_correct'  => !empty($choice['is_correct']),
                ]);
            }
        }

        $nextOrder = (TestQuestion::where('test_section_id', $section->id)->max('order') ?? 0) + 1;

        return TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => $nextOrder,
            'points'          => $data['points'] ?? 1,
        ]);
    }

    /**
     * Remove question from test section. If ad-hoc (question_bank_id is null), clean up question/choices.
     */
    public function removeQuestionFromSection(Test $test, string $questionId): bool
    {
        $sectionIds = $test->sections()->pluck('id');
        $testQuestion = TestQuestion::whereIn('test_section_id', $sectionIds)
            ->where('question_id', $questionId)
            ->first();

        if (!$testQuestion) {
            return false;
        }

        $question = \App\Modules\QuestionBank\Models\Question::find($questionId);
        $testQuestion->delete();

        // If it was an Assessment-authored question (not from a Question Bank), delete it
        if ($question && is_null($question->question_bank_id)) {
            $question->choices()->delete();
            $question->delete();
        }

        return true;
    }

    /**
     * Add a section to an assessment.
     */
    public function addSection(Test $test, string $title, ?string $sectionType = null, ?string $instructions = null): TestSection
    {
        $nextOrder = ($test->sections()->max('order') ?? 0) + 1;

        if (empty($sectionType)) {
            $sectionType = $this->deriveSectionType($title, $test);
        }

        return TestSection::create([
            'test_id'      => $test->id,
            'title'        => $title,
            'section_type' => $sectionType,
            'instructions' => $instructions,
            'order'        => $nextOrder,
        ]);
    }

    /**
     * Update section details.
     */
    public function updateSection(TestSection $section, array $data): TestSection
    {
        if (empty($data['section_type']) && !empty($data['title'])) {
            $data['section_type'] = $this->deriveSectionType($data['title'], $section->test);
        }

        $section->update(array_filter([
            'title'        => $data['title'] ?? $section->title,
            'section_type' => $data['section_type'] ?? $section->section_type,
            'instructions' => array_key_exists('instructions', $data) ? $data['instructions'] : $section->instructions,
        ], fn($v) => !is_null($v)));

        if (array_key_exists('instructions', $data)) {
            $section->instructions = $data['instructions'];
            $section->save();
        }

        return $section;
    }

    /**
     * Update assessment-level instructions.
     */
    public function updateInstructions(Test $test, ?string $instructions): Test
    {
        $test->update([
            'instructions' => $instructions,
        ]);

        return $test;
    }

    /**
     * Attach a MediaAsset to a TestSection.
     *
     * @throws ValidationException
     */
    public function attachMediaToSection(TestSection $section, string $mediaAssetId, ?string $caption = null, ?int $order = null): void
    {
        $test = $section->test;
        if (!$test || in_array($test->status, ['pending_approval', 'approved', 'published'], true) || $test->is_published) {
            throw ValidationException::withMessages([
                'assessment' => "Cannot attach media: Assessment is in {$test?->status} state and locked from editing.",
            ]);
        }

        $media = MediaAsset::find($mediaAssetId);
        if (!$media) {
            throw ValidationException::withMessages([
                'media_asset_id' => 'Media asset not found.',
            ]);
        }

        $alreadyAttached = $section->mediaAssets()->where('media_asset_id', $mediaAssetId)->exists();
        if ($alreadyAttached) {
            throw ValidationException::withMessages([
                'media_asset_id' => 'This media asset is already attached to this section.',
            ]);
        }

        if ($order === null) {
            $maxOrder = DB::table('test_section_media')->where('test_section_id', $section->id)->max('order') ?? 0;
            $order = $maxOrder + 1;
        }

        $section->mediaAssets()->attach($mediaAssetId, [
            'id'      => (string) Str::ulid(),
            'caption' => $caption,
            'order'   => $order,
        ]);
    }

    /**
     * Detach a MediaAsset from a TestSection.
     *
     * @throws ValidationException
     */
    public function detachMediaFromSection(TestSection $section, string $mediaAssetId): bool
    {
        $test = $section->test;
        if (!$test || in_array($test->status, ['pending_approval', 'approved', 'published'], true) || $test->is_published) {
            throw ValidationException::withMessages([
                'assessment' => "Cannot detach media: Assessment is in {$test?->status} state and locked from editing.",
            ]);
        }

        return $section->mediaAssets()->detach($mediaAssetId) > 0;
    }

    /**
     * Reorder media assets attached to a TestSection.
     *
     * @throws ValidationException
     */
    public function reorderSectionMedia(TestSection $section, array $mediaOrder): void
    {
        $test = $section->test;
        if (!$test || in_array($test->status, ['pending_approval', 'approved', 'published'], true) || $test->is_published) {
            throw ValidationException::withMessages([
                'assessment' => "Cannot reorder media: Assessment is in {$test?->status} state and locked from editing.",
            ]);
        }

        foreach ($mediaOrder as $order => $mediaAssetId) {
            DB::table('test_section_media')
                ->where('test_section_id', $section->id)
                ->where('media_asset_id', $mediaAssetId)
                ->update(['order' => (int) $order + 1]);
        }
    }

    /**
     * Deterministically derive canonical SectionType from title or test context.
     */
    public function deriveSectionType(string $title, ?Test $test = null): string
    {
        $lower = strtolower(trim($title));

        if (str_contains($lower, 'listen') || 
            str_contains($lower, 'part 1') || 
            str_contains($lower, 'part 2') || 
            str_contains($lower, 'part 3') || 
            str_contains($lower, 'part 4') || 
            str_contains($lower, 'photo') || 
            str_contains($lower, 'audio') || 
            str_contains($lower, 'conversation') || 
            str_contains($lower, 'talk')) {
            return 'listening';
        }

        if (str_contains($lower, 'speak') || 
            str_contains($lower, 'oral') || 
            str_contains($lower, 'interview') ||
            str_contains($lower, 'cue card')) {
            return 'speaking';
        }

        if (str_contains($lower, 'writ') || 
            str_contains($lower, 'essay') ||
            str_contains($lower, 'task 1') ||
            str_contains($lower, 'task 2')) {
            return 'writing';
        }

        if (str_contains($lower, 'read') || 
            str_contains($lower, 'part 5') || 
            str_contains($lower, 'part 6') || 
            str_contains($lower, 'part 7') || 
            str_contains($lower, 'incomplete') || 
            str_contains($lower, 'passage') || 
            str_contains($lower, 'structure') || 
            str_contains($lower, 'grammar') || 
            str_contains($lower, 'vocab')) {
            return 'reading';
        }

        return 'reading';
    }

    /**
     * Automated Question & Structure Validation for Assessment Submissions.
     * Canonical rule:
     * - Minimum 1 section.
     * - Total Questions >= 1.
     * - Every section must contain at least 1 question.
     * - Every assigned question must pass validation rules.
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

        // Structural Rule 1: Section existence
        if ($test->sections->isEmpty()) {
            $errors[] = "Assessment must have at least one section.";
        }

        // Structural Rule 2: Total questions >= 1
        $totalQuestions = $test->sections->sum(fn($s) => $s->testQuestions->count());
        if ($totalQuestions === 0) {
            $errors[] = "Assessment cannot be submitted with 0 questions. Please add at least one question.";
        }

        foreach ($test->sections as $section) {
            // Structural Rule 3: No empty sections
            if ($section->testQuestions->isEmpty()) {
                $errors[] = "Section '{$section->title}' has no questions assigned.";
            }

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

                $qType = is_object($q->question_type) ? $q->question_type->value : (string) $q->question_type;
                if (in_array($qType, ['multiple_choice', 'single_choice', 'true_false', 'select_one', 'toefl_listening_mcq', 'toefl_reading_mcq'], true)) {
                    $choices = $q->choices ?? collect();
                    if ($choices->isEmpty()) {
                        $qErrors[] = "No options/choices provided.";
                    } else {
                        $hasCorrect = $choices->contains(fn($c) => (bool) $c->is_correct);
                        if (!$hasCorrect) {
                            $qErrors[] = "No correct answer option selected.";
                        }
                    }
                }

                // Structured Repository Question Review Mapping
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
