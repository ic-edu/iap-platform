<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateCbtAttemptAccessAndScopeSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidateA;

    protected User $candidateB;

    protected Test $testA;

    protected Test $testB;

    protected Question $questionA1;

    protected Question $questionA2;

    protected Question $questionB1;

    protected QuestionChoice $choiceA1_1;

    protected QuestionChoice $choiceA1_2;

    protected QuestionChoice $choiceA2_1;

    protected Attempt $attemptA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->candidateA = User::factory()->create(['name' => 'Candidate Alpha', 'email' => 'alpha@iap.test']);
        $this->candidateA->assignRole('student');

        $this->candidateB = User::factory()->create(['name' => 'Candidate Beta', 'email' => 'beta@iap.test']);
        $this->candidateB->assignRole('student');

        // Setup Test A
        $this->testA = Test::create([
            'title' => 'TOEIC Practice Test A',
            'slug' => 'toeic-practice-test-a',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidateA->id,
        ]);

        $sectionA = TestSection::create([
            'test_id' => $this->testA->id,
            'title' => 'Listening Comprehension',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 30,
            'order' => 1,
        ]);

        $this->questionA1 = Question::create([
            'prompt' => 'Question A1 Prompt',
            'section' => SectionType::Listening,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        $this->choiceA1_1 = QuestionChoice::create([
            'question_id' => $this->questionA1->id,
            'label' => 'A',
            'content' => 'Option A',
            'is_correct' => true,
        ]);
        $this->choiceA1_2 = QuestionChoice::create([
            'question_id' => $this->questionA1->id,
            'label' => 'B',
            'content' => 'Option B',
            'is_correct' => false,
        ]);

        $this->questionA2 = Question::create([
            'prompt' => 'Question A2 Prompt',
            'section' => SectionType::Listening,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        $this->choiceA2_1 = QuestionChoice::create([
            'question_id' => $this->questionA2->id,
            'label' => 'A',
            'content' => 'Option A2',
            'is_correct' => true,
        ]);

        TestQuestion::create([
            'test_section_id' => $sectionA->id,
            'question_id' => $this->questionA1->id,
            'order' => 1,
            'points' => 5,
        ]);
        TestQuestion::create([
            'test_section_id' => $sectionA->id,
            'question_id' => $this->questionA2->id,
            'order' => 2,
            'points' => 5,
        ]);

        // Setup Test B (unrelated test)
        $this->testB = Test::create([
            'title' => 'TOEIC Practice Test B',
            'slug' => 'toeic-practice-test-b',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidateB->id,
        ]);

        $sectionB = TestSection::create([
            'test_id' => $this->testB->id,
            'title' => 'Reading Comprehension',
            'section_type' => SectionType::Reading,
            'duration_minutes' => 30,
            'order' => 1,
        ]);

        $this->questionB1 = Question::create([
            'prompt' => 'Question B1 Prompt (Unrelated)',
            'section' => SectionType::Reading,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);

        TestQuestion::create([
            'test_section_id' => $sectionB->id,
            'question_id' => $this->questionB1->id,
            'order' => 1,
            'points' => 5,
        ]);

        // Create in-progress attempt for Candidate A
        $this->attemptA = Attempt::create([
            'test_id' => $this->testA->id,
            'user_id' => $this->candidateA->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at' => now(),
            'seed' => 'seed-12345',
        ]);
    }

    public function test_candidate_b_is_forbidden_from_candidate_a_attempt_endpoints(): void
    {
        // 1. exam
        $response = $this->actingAs($this->candidateB)->get(route('candidate.exam', $this->attemptA));
        $response->assertStatus(403);

        // 2. autosave
        $response = $this->actingAs($this->candidateB)->postJson(route('candidate.exam.autosave', $this->attemptA), [
            'question_id' => $this->questionA1->id,
            'selected_choice' => $this->choiceA1_1->id,
        ]);
        $response->assertStatus(403);

        // 3. flag
        $response = $this->actingAs($this->candidateB)->postJson(route('candidate.exam.flag', $this->attemptA), [
            'question_id' => $this->questionA1->id,
        ]);
        $response->assertStatus(403);

        // 4. violation
        $response = $this->actingAs($this->candidateB)->postJson(route('candidate.exam.violation', $this->attemptA), [
            'violation_type' => 'window_blur',
        ]);
        $response->assertStatus(403);

        // 5. audio stream
        $response = $this->actingAs($this->candidateB)->get(route('candidate.exam.audio-stream', [
            'attempt' => $this->attemptA,
            'question' => $this->questionA1,
        ]));
        $response->assertStatus(403);

        // 6. submit
        $response = $this->actingAs($this->candidateB)->post(route('candidate.exam.submit', $this->attemptA));
        $response->assertStatus(403);

        // 7. review
        $response = $this->actingAs($this->candidateB)->get(route('candidate.review', $this->attemptA));
        $response->assertStatus(403);

        // 8. wrong answers
        $response = $this->actingAs($this->candidateB)->get(route('candidate.simulator.wrong-answers', $this->attemptA));
        $response->assertStatus(403);

        // 9. finalize
        $response = $this->actingAs($this->candidateB)->post(route('candidate.exam.finalize', $this->attemptA));
        $response->assertStatus(403);

        // 10. retry
        $response = $this->actingAs($this->candidateB)->post(route('candidate.exam.retry', $this->attemptA));
        $response->assertStatus(403);
    }

    public function test_candidate_a_legitimately_accesses_attempt_endpoints(): void
    {
        // Candidate A access to exam
        $response = $this->actingAs($this->candidateA)->get(route('candidate.exam', $this->attemptA));
        $response->assertStatus(200);

        // Candidate A autosave with valid question and valid choice
        $response = $this->actingAs($this->candidateA)->postJson(route('candidate.exam.autosave', $this->attemptA), [
            'question_id' => $this->questionA1->id,
            'selected_choice' => $this->choiceA1_1->id,
        ]);
        $response->assertStatus(200)->assertJson(['status' => 'saved']);

        $this->assertDatabaseHas('answers', [
            'attempt_id' => $this->attemptA->id,
            'question_id' => $this->questionA1->id,
            'selected_choice_id' => $this->choiceA1_1->id,
        ]);

        // Candidate A flag
        $response = $this->actingAs($this->candidateA)->postJson(route('candidate.exam.flag', $this->attemptA), [
            'question_id' => $this->questionA1->id,
        ]);
        $response->assertStatus(200)->assertJson(['status' => 'saved', 'flagged' => true]);
    }

    public function test_cross_test_question_submission_is_rejected(): void
    {
        // Candidate A submits Question B1 (which belongs to Test B, not Test A)
        $response = $this->actingAs($this->candidateA)->postJson(route('candidate.exam.autosave', $this->attemptA), [
            'question_id' => $this->questionB1->id,
            'selected_choice' => 'some-choice-id',
        ]);
        $response->assertStatus(403);

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $this->attemptA->id,
            'question_id' => $this->questionB1->id,
        ]);

        // Flagging cross-test question
        $response = $this->actingAs($this->candidateA)->postJson(route('candidate.exam.flag', $this->attemptA), [
            'question_id' => $this->questionB1->id,
        ]);
        $response->assertStatus(403);

        // Audio stream on cross-test question
        $response = $this->actingAs($this->candidateA)->get(route('candidate.exam.audio-stream', [
            'attempt' => $this->attemptA,
            'question' => $this->questionB1,
        ]));
        $response->assertStatus(403);

        // Violation with cross-test question
        $response = $this->actingAs($this->candidateA)->postJson(route('candidate.exam.violation', $this->attemptA), [
            'violation_type' => 'audio_completed',
            'question_id' => $this->questionB1->id,
        ]);
        $response->assertStatus(403);
    }

    public function test_cross_question_choice_submission_is_rejected(): void
    {
        // Candidate A submits Choice A2_1 for Question A1 (Choice belongs to Question A2)
        $response = $this->actingAs($this->candidateA)->postJson(route('candidate.exam.autosave', $this->attemptA), [
            'question_id' => $this->questionA1->id,
            'selected_choice' => $this->choiceA2_1->id,
        ]);
        $response->assertStatus(422);

        // Verify that invalid choice was NOT persisted
        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $this->attemptA->id,
            'question_id' => $this->questionA1->id,
            'selected_choice_id' => $this->choiceA2_1->id,
        ]);
    }

    public function test_section_header_renders_without_duplicate_section_suffix(): void
    {
        $response = $this->actingAs($this->candidateA)->get(route('candidate.exam', $this->attemptA));
        $response->assertStatus(200);
        $response->assertSee('Listening Section');
        $response->assertDontSee('Listening Section Section');
    }
}
