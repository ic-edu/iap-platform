<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\DeliveryEngine;
use App\Modules\Assessment\Engines\ReviewEngine;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipleChoiceTemplateChoiceCountTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $candidate;
    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name' => 'Teacher Author',
            'email' => 'teacher_author@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->candidate = User::factory()->create([
            'name' => 'Candidate Student',
            'email' => 'candidate_student@test.com',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->bank = QuestionBank::create([
            'title' => 'Core English Question Bank',
            'slug' => 'core-english-question-bank-' . uniqid(),
            'test_type' => 'general',
            'status' => 'draft',
            'is_published' => false,
            'created_by' => $this->teacher->id,
        ]);
    }

    /**
     * 1. New Multiple Choice question authored with 4 default choices (A, B, C, D) saves exactly 4 choices.
     */
    public function test_new_multiple_choice_question_creates_exactly_4_default_choices_a_b_c_d()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.store-question', $this->bank->id), [
                'prompt' => 'Which of the following is a primary color in additive RGB model?',
                'question_type' => 'multiple_choice',
                'difficulty' => 'easy',
                'points' => 10,
                'explanation' => 'Red, Green, and Blue are primary colors.',
                'choices' => [
                    0 => ['label' => 'A', 'content' => 'Red'],
                    1 => ['label' => 'B', 'content' => 'Yellow'],
                    2 => ['label' => 'C', 'content' => 'Magenta'],
                    3 => ['label' => 'D', 'content' => 'Cyan'],
                ],
                'correct_choice' => 0,
            ]);

        $response->assertRedirect();

        $question = Question::where('question_bank_id', $this->bank->id)->first();
        $this->assertNotNull($question);
        $this->assertEquals('multiple_choice', $question->question_type->value ?? (string) $question->question_type);

        $choices = $question->choices()->orderBy('id')->get();
        $this->assertCount(4, $choices);

        $labels = $choices->pluck('label')->toArray();
        $this->assertEquals(['A', 'B', 'C', 'D'], $labels);

        $this->assertTrue($choices[0]->is_correct);
        $this->assertFalse($choices[1]->is_correct);
        $this->assertFalse($choices[2]->is_correct);
        $this->assertFalse($choices[3]->is_correct);
    }

    /**
     * 2. Existing questions with 5 or 6 choices (E, F) remain completely preserved in the database.
     */
    public function test_existing_questions_with_e_and_f_choices_are_preserved()
    {
        $question = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Select all European capital cities (Extended 6-choice item):',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 20,
            'created_by' => $this->teacher->id,
        ]);

        $choiceLabels = ['A' => 'Paris', 'B' => 'Berlin', 'C' => 'Rome', 'D' => 'Madrid', 'E' => 'Vienna', 'F' => 'Tokyo'];
        $idx = 0;
        foreach ($choiceLabels as $lbl => $txt) {
            QuestionChoice::create([
                'question_id' => $question->id,
                'label' => $lbl,
                'content' => $txt,
                'is_correct' => ($lbl !== 'F'),
                'order' => ++$idx,
            ]);
        }

        $this->assertEquals(6, $question->choices()->count());

        // Refresh and verify all 6 choices exist intact
        $question->refresh();
        $this->assertCount(6, $question->choices);
        $this->assertEquals(['A', 'B', 'C', 'D', 'E', 'F'], $question->choices->pluck('label')->toArray());
    }

    /**
     * 3. Authoring editor supports adding a 5th and 6th choice (Option E, Option F) and persists correctly.
     */
    public function test_authoring_supports_adding_fifth_and_sixth_choices_manually()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.store-question', $this->bank->id), [
                'prompt' => 'Which hex code corresponds to pure white?',
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'points' => 15,
                'explanation' => '#FFFFFF represents white.',
                'choices' => [
                    0 => ['label' => 'A', 'content' => '#000000'],
                    1 => ['label' => 'B', 'content' => '#111111'],
                    2 => ['label' => 'C', 'content' => '#888888'],
                    3 => ['label' => 'D', 'content' => '#CCCCCC'],
                    4 => ['label' => 'E', 'content' => '#FFFFFF'],
                    5 => ['label' => 'F', 'content' => '#EEEEEE'],
                ],
                'correct_choice' => 4,
            ]);

        $response->assertRedirect();

        $question = Question::where('prompt', 'Which hex code corresponds to pure white?')->first();
        $this->assertNotNull($question);

        $choices = $question->choices()->orderBy('id')->get();
        $this->assertCount(6, $choices);
        $this->assertEquals(['A', 'B', 'C', 'D', 'E', 'F'], $choices->pluck('label')->toArray());
        $this->assertTrue($choices[4]->is_correct);
        $this->assertEquals('#FFFFFF', $choices[4]->content);
    }

    /**
     * 4. Authoring editor supports editing an existing question and removing optional extra choices.
     */
    public function test_authoring_editor_allows_removing_optional_extra_choices()
    {
        $question = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Original 5-choice question to be trimmed to 4 choices',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
            'created_by' => $this->teacher->id,
        ]);

        foreach (['A', 'B', 'C', 'D', 'E'] as $idx => $lbl) {
            QuestionChoice::create([
                'question_id' => $question->id,
                'label' => $lbl,
                'content' => "Option {$lbl} Content",
                'is_correct' => ($lbl === 'A'),
            ]);
        }

        $this->assertEquals(5, $question->choices()->count());

        // Update with only 4 choices (removing choice E)
        $response = $this->actingAs($this->teacher)
            ->put(route('admin.question-banks.update-question', $question->id), [
                'prompt' => 'Updated trimmed question to standard 4 choices',
                'question_type' => 'multiple_choice',
                'difficulty' => 'easy',
                'points' => 10,
                'choices' => [
                    0 => ['label' => 'A', 'content' => 'Option A Content'],
                    1 => ['label' => 'B', 'content' => 'Option B Content'],
                    2 => ['label' => 'C', 'content' => 'Option C Content'],
                    3 => ['label' => 'D', 'content' => 'Option D Content'],
                ],
                'correct_choice' => 0,
            ]);

        $response->assertRedirect();

        $question->refresh();
        $this->assertEquals('Updated trimmed question to standard 4 choices', $question->prompt);
        $this->assertCount(4, $question->choices);
        $this->assertEquals(['A', 'B', 'C', 'D'], $question->choices->pluck('label')->toArray());
    }

    /**
     * 5. Assessment delivery dynamically renders stored choices (both 4-choice and 6-choice items) without hardcoding.
     */
    public function test_question_delivery_renders_exactly_stored_choices()
    {
        $test = Test::create([
            'title' => 'Choice Count Delivery Test',
            'slug' => 'choice-count-delivery-test-' . uniqid(),
            'test_type' => TestType::General,
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 60,
            'pass_score' => 50,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Main Section',
            'order' => 1,
        ]);

        // Q1 with 4 choices
        $q4 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Standard 4-choice Question?',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 50,
            'created_by' => $this->teacher->id,
        ]);
        foreach (['A' => 'Choice A', 'B' => 'Choice B', 'C' => 'Choice C', 'D' => 'Choice D'] as $lbl => $txt) {
            QuestionChoice::create(['question_id' => $q4->id, 'label' => $lbl, 'content' => $txt, 'is_correct' => ($lbl === 'A')]);
        }
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q4->id, 'order' => 1, 'points' => 50]);

        // Q2 with 6 choices
        $q6 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Extended 6-choice Question?',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 50,
            'created_by' => $this->teacher->id,
        ]);
        foreach (['A' => 'Choice A', 'B' => 'Choice B', 'C' => 'Choice C', 'D' => 'Choice D', 'E' => 'Choice E', 'F' => 'Choice F'] as $lbl => $txt) {
            QuestionChoice::create(['question_id' => $q6->id, 'label' => $lbl, 'content' => $txt, 'is_correct' => ($lbl === 'B')]);
        }
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q6->id, 'order' => 2, 'points' => 50]);

        /** @var AttemptEngine $attemptEngine */
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        // Access the candidate exam view
        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.exam', $attempt));

        $response->assertStatus(200);

        // Verify choices rendered on exam page
        $response->assertSee('Standard 4-choice Question?');
        $response->assertSee('Extended 6-choice Question?');
        $response->assertSee('Choice E');
        $response->assertSee('Choice F');
    }
}
