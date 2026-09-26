<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateCbtAutosaveAndFlagHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $otherStudent;

    protected User $teacher;

    protected Test $test;

    protected QuestionBank $bank;

    protected Question $q1;

    protected Question $q2;

    protected QuestionChoice $c1_a;

    protected QuestionChoice $c1_b;

    protected QuestionChoice $c2_a;

    protected Attempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->student = User::factory()->create(['name' => 'Candidate Autosave Test', 'status' => 'active']);
        $this->student->assignRole('student');

        $this->otherStudent = User::factory()->create(['name' => 'Candidate Other', 'status' => 'active']);
        $this->otherStudent->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Teacher Autosave Test', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->test = Test::create([
            'title' => 'Autosave Hardening Test',
            'slug' => 'autosave-hardening-test',
            'test_type' => TestType::Toeic,
            'duration_minutes' => 60,
            'pass_score' => 500,
            'is_published' => true,
            'status' => 'approved',
            'assessment_mode' => AssessmentMode::Simulator,
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->test->id,
            'title' => 'Part 1: Photographs',
            'section_type' => SectionType::Listening,
            'instructions' => 'Listen and select statement.',
            'order' => 1,
        ]);

        $this->bank = QuestionBank::create([
            'title' => 'Autosave Bank',
            'slug' => 'autosave-bank',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Autosave Question 1',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        $this->c1_a = QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        $this->c1_b = QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);

        $this->q2 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Autosave Question 2',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        $this->c2_a = QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => 'Choice 2A', 'is_correct' => true]);

        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q2->id, 'order' => 2]);

        $this->attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'flagged_questions' => [],
            'shuffled_question_ids' => [$this->q1->id, $this->q2->id],
        ]);
    }

    public function test_autosave_persists_answer_and_returns_authoritative_response(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson(route('candidate.exam.autosave', $this->attempt), [
                'question_id' => $this->q1->id,
                'selected_choice' => $this->c1_a->id,
            ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'saved',
            'question_id' => $this->q1->id,
            'selected_choice' => $this->c1_a->id,
        ]);

        $this->assertDatabaseHas('answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->c1_a->id,
        ]);
    }

    public function test_autosave_rejects_invalid_choice_with_422(): void
    {
        // Choice 2A does not belong to Question 1
        $response = $this->actingAs($this->student)
            ->postJson(route('candidate.exam.autosave', $this->attempt), [
                'question_id' => $this->q1->id,
                'selected_choice' => $this->c2_a->id,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->q1->id,
        ]);
    }

    public function test_autosave_rejects_question_not_in_attempt_with_403(): void
    {
        $foreignQuestion = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Foreign Question',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        $foreignChoice = QuestionChoice::create(['question_id' => $foreignQuestion->id, 'label' => 'A', 'content' => 'F Choice']);

        $response = $this->actingAs($this->student)
            ->postJson(route('candidate.exam.autosave', $this->attempt), [
                'question_id' => $foreignQuestion->id,
                'selected_choice' => $foreignChoice->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_autosave_rejects_immutable_attempt_with_403(): void
    {
        $this->attempt->update(['status' => AttemptStatus::Submitted, 'submitted_at' => now()]);

        $response = $this->actingAs($this->student)
            ->postJson(route('candidate.exam.autosave', $this->attempt), [
                'question_id' => $this->q1->id,
                'selected_choice' => $this->c1_a->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_autosave_rapid_choice_changes_updates_persisted_record(): void
    {
        // Choice A
        $this->actingAs($this->student)
            ->postJson(route('candidate.exam.autosave', $this->attempt), [
                'question_id' => $this->q1->id,
                'selected_choice' => $this->c1_a->id,
            ])
            ->assertOk();

        // Switch to Choice B
        $this->actingAs($this->student)
            ->postJson(route('candidate.exam.autosave', $this->attempt), [
                'question_id' => $this->q1->id,
                'selected_choice' => $this->c1_b->id,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'saved',
                'question_id' => $this->q1->id,
                'selected_choice' => $this->c1_b->id,
            ]);

        $this->assertDatabaseHas('answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->c1_b->id,
        ]);
        $this->assertEquals(1, Answer::where('attempt_id', $this->attempt->id)->where('question_id', $this->q1->id)->count());
    }

    public function test_toggle_flag_persists_flagged_state_authoritatively(): void
    {
        // 1. Flag question 1
        $response = $this->actingAs($this->student)
            ->postJson(route('candidate.exam.flag', $this->attempt), [
                'question_id' => $this->q1->id,
            ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'saved',
            'flagged' => true,
            'question_id' => $this->q1->id,
        ]);

        $this->attempt->refresh();
        $this->assertContains($this->q1->id, $this->attempt->flagged_questions);

        // 2. Unflag question 1
        $response2 = $this->actingAs($this->student)
            ->postJson(route('candidate.exam.flag', $this->attempt), [
                'question_id' => $this->q1->id,
            ]);

        $response2->assertOk();
        $response2->assertJson([
            'status' => 'saved',
            'flagged' => false,
            'question_id' => $this->q1->id,
        ]);

        $this->attempt->refresh();
        $this->assertNotContains($this->q1->id, $this->attempt->flagged_questions ?? []);
    }

    public function test_toggle_flag_rejects_question_not_in_attempt(): void
    {
        $foreignQuestion = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Foreign Question',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);

        $response = $this->actingAs($this->student)
            ->postJson(route('candidate.exam.flag', $this->attempt), [
                'question_id' => $foreignQuestion->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_exam_blade_view_rehydrates_saved_answers_and_flags(): void
    {
        // Save an answer for Q1
        Answer::create([
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->c1_a->id,
            'is_correct' => true,
            'points_awarded' => 5,
        ]);

        // Flag Q2
        $this->attempt->update(['flagged_questions' => [$this->q2->id]]);

        $response = $this->actingAs($this->student)
            ->get(route('candidate.exam', $this->attempt));

        $response->assertOk();
        // Check that confirmed answer map contains Q1 -> C1_A
        $response->assertSee($this->q1->id);
        $response->assertSee($this->c1_a->id);
        // Check that initial flagged questions contains Q2
        $response->assertSee($this->q2->id);
    }
}
