<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssessmentEngine;
use App\Modules\Assessment\Engines\ScoringEngine;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipleChoiceCorrectAnswerAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $candidate;
    protected Test $test;
    protected TestSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'IAP Teacher',
            'email'  => 'teacher_mcq@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->candidate = User::factory()->create([
            'name'   => 'IAP Student',
            'email'  => 'student_mcq@icedu.org',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->test = Test::create([
            'title'            => 'General Assessment for Question Authoring',
            'slug'             => 'general-assessment-question-authoring',
            'test_type'        => 'general',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $this->section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'order'        => 1,
        ]);
    }

    /**
     * Requirement 1: New MCQ starts with no correct answer selected in the Authoring view.
     */
    public function test_01_new_mcq_starts_with_no_default_correct_answer_in_modal_view(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.tests.show', $this->test->id));

        $response->assertStatus(200);
        // The modal choices should not have 'checked' on radio options by default
        $response->assertDontSee('name="correct_choice" value="0" checked', false);
        $response->assertDontSee('name="correct_choice" value="1" checked', false);
        $response->assertDontSee('name="correct_choice" value="2" checked', false);
        $response->assertDontSee('name="correct_choice" value="3" checked', false);
        $response->assertSee('✓ CORRECT ANSWER');
    }

    /**
     * Requirement 2: Save is blocked when no correct answer is selected.
     */
    public function test_02_save_is_blocked_when_no_correct_answer_is_selected(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson(route('teacher.tests.create-question', $this->test->id), [
                'test_section_id' => $this->section->id,
                'prompt'          => 'Look at the photograph and choose the statement.',
                'question_type'   => 'multiple_choice',
                'points'          => 1,
                'choices'         => ['Option A', 'Option B', 'Option C', 'Option D'],
                'correct_choice'  => null, // No answer selected
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['correct_choice']);
        $this->assertEquals(
            'Please select the correct answer.',
            $response->json('errors.correct_choice.0')
        );

        $this->assertDatabaseMissing('questions', [
            'prompt' => 'Look at the photograph and choose the statement.',
        ]);
    }

    /**
     * Requirement 3 & 4: Selecting C marks C as correct, persisting exactly one `is_correct = true`.
     */
    public function test_03_and_04_selecting_c_persists_exactly_one_correct_choice(): void
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.create-question', $this->test->id), [
                'test_section_id' => $this->section->id,
                'prompt'          => 'Which statement accurately describes image C?',
                'question_type'   => 'multiple_choice',
                'points'          => 1,
                'choices'         => [
                    'The woman is standing at the reception.',
                    'The woman is riding a bicycle.',
                    'The woman is checking into the hotel.', // Index 2 -> C
                    'The woman is cooking in the restaurant.',
                ],
                'correct_choice'  => 2, // Choice C
            ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $question = Question::where('prompt', 'Which statement accurately describes image C?')->first();
        $this->assertNotNull($question);

        $choices = $question->choices()->orderBy('label')->get();
        $this->assertCount(4, $choices);

        $this->assertEquals('A', $choices[0]->label);
        $this->assertFalse((bool) $choices[0]->is_correct);

        $this->assertEquals('B', $choices[1]->label);
        $this->assertFalse((bool) $choices[1]->is_correct);

        $this->assertEquals('C', $choices[2]->label);
        $this->assertTrue((bool) $choices[2]->is_correct);

        $this->assertEquals('D', $choices[3]->label);
        $this->assertFalse((bool) $choices[3]->is_correct);

        // Exactly one is_correct = true
        $this->assertEquals(1, $choices->where('is_correct', true)->count());
    }

    /**
     * Requirement 5: Editing reloads the correct answer correctly in the editor view.
     */
    public function test_05_editing_reloads_persisted_correct_answer_accurately(): void
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Where is the passenger luggage placed?',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'On the overhead rack.',
            'is_correct'  => false,
            'order'       => 1,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'B',
            'content'     => 'Under the passenger seat.',
            'is_correct'  => false,
            'order'       => 2,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'C',
            'content'     => 'Inside the front luggage compartment.',
            'is_correct'  => true, // Persisted as Correct
            'order'       => 3,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'D',
            'content'     => 'Beside the ticket counter.',
            'is_correct'  => false,
            'order'       => 4,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.tests.edit-question', ['test' => $this->test->id, 'question' => $question->id]));

        $response->assertStatus(200);
        $response->assertSee('value="2" id="edit-correct-2"', false);
        $response->assertSee('✓ CORRECT ANSWER');
    }

    /**
     * Requirement 6: Switching correct answer from A to C updates `is_correct`.
     */
    public function test_06_switching_correct_answer_from_a_to_c_updates_is_correct(): void
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Initial Question with Option A Correct',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        $choiceA = QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Initial Correct Option A',
            'is_correct'  => true,
            'order'       => 1,
        ]);
        $choiceB = QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'B',
            'content'     => 'Option B',
            'is_correct'  => false,
            'order'       => 2,
        ]);
        $choiceC = QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'C',
            'content'     => 'New Correct Option C',
            'is_correct'  => false,
            'order'       => 3,
        ]);
        $choiceD = QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'D',
            'content'     => 'Option D',
            'is_correct'  => false,
            'order'       => 4,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        // Teacher switches correct choice to C (index 2)
        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $question->id]), [
                'prompt'         => 'Updated Question with Option C Correct',
                'choices'        => [
                    'Initial Option A',
                    'Option B',
                    'New Correct Option C',
                    'Option D',
                ],
                'correct_choice' => 2, // Switched to C
            ]);

        $response->assertRedirect(route('teacher.tests.show', ['test' => $this->test->id, 'section' => $this->section->id, 'focus' => "question-card-{$question->id}"]));

        $choiceA->refresh();
        $choiceB->refresh();
        $choiceC->refresh();
        $choiceD->refresh();

        $this->assertFalse((bool) $choiceA->is_correct);
        $this->assertFalse((bool) $choiceB->is_correct);
        $this->assertTrue((bool) $choiceC->is_correct);
        $this->assertFalse((bool) $choiceD->is_correct);
    }

    /**
     * Requirement 7: Candidate delivery does NOT display "Correct Answer".
     */
    public function test_07_candidate_delivery_does_not_display_correct_answer_indicator(): void
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Select the appropriate response for the candidate test.',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Candidate choice option A',
            'is_correct'  => false,
            'order'       => 1,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'B',
            'content'     => 'Candidate choice option B (The real answer)',
            'is_correct'  => true,
            'order'       => 2,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        $attempt = Attempt::create([
            'test_id'       => $this->test->id,
            'user_id'       => $this->candidate->id,
            'attempt_token' => 'mcq-cand-token-' . uniqid(),
            'status'        => 'in_progress',
            'started_at'    => now(),
        ]);

        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.exam', $attempt));

        $response->assertStatus(200);
        $response->assertSee('Candidate choice option A');
        $response->assertSee('Candidate choice option B (The real answer)');
        $response->assertDontSee('CORRECT ANSWER');
        $response->assertDontSee('✓ (Correct)');
        $response->assertDontSee('is_correct');
    }

    /**
     * Requirement 8: Automatic scoring remains unchanged and accurately evaluates candidate attempt.
     */
    public function test_08_automatic_scoring_accurately_evaluates_attempt(): void
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'What is 5 + 5?',
            'question_type'    => 'multiple_choice',
            'points'           => 10,
        ]);

        $choiceWrong = QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => '8',
            'is_correct'  => false,
            'order'       => 1,
        ]);

        $choiceCorrect = QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'B',
            'content'     => '10',
            'is_correct'  => true,
            'order'       => 2,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 10,
        ]);

        $attempt = Attempt::create([
            'test_id'       => $this->test->id,
            'user_id'       => $this->candidate->id,
            'attempt_token' => 'score-token-' . uniqid(),
            'status'        => 'in_progress',
            'started_at'    => now(),
        ]);

        // 1. When student chooses correct answer:
        $answer = Answer::create([
            'attempt_id'         => $attempt->id,
            'question_id'        => $question->id,
            'selected_choice_id' => $choiceCorrect->id,
        ]);

        $scoringEngine = new ScoringEngine();
        $score = $scoringEngine->evaluateAttempt($attempt);

        $this->assertEquals(10.0, $score);
        $answer->refresh();
        $this->assertTrue((bool) $answer->is_correct);
        $this->assertEquals(10.0, (float) $answer->score_earned);

        // 2. When student chooses wrong answer:
        $answer->update([
            'selected_choice_id' => $choiceWrong->id,
            'is_correct'         => null,
            'score_earned'       => 0.0,
        ]);
        $attempt->refresh();

        $scoreWrong = $scoringEngine->evaluateAttempt($attempt);
        $this->assertEquals(0.0, $scoreWrong);
        $answer->refresh();
        $this->assertFalse((bool) $answer->is_correct);
        $this->assertEquals(0.0, (float) $answer->score_earned);
    }
}
