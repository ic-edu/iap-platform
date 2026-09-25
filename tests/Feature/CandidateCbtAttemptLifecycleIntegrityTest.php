<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
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

class CandidateCbtAttemptLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected Test $simulatorTest;
    protected Test $realTest;
    protected Question $questionSim;
    protected QuestionChoice $choiceSimA;
    protected QuestionChoice $choiceSimB;
    protected Question $questionReal;
    protected QuestionChoice $choiceRealA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->candidate = User::factory()->create(['name' => 'Test Candidate', 'email' => 'candidate.lifecycle@iap.test']);
        $this->candidate->assignRole('student');

        // 1. Simulator Test (60 mins)
        $this->simulatorTest = Test::create([
            'title' => 'TOEIC Simulator Lifecycle Test',
            'slug' => 'toeic-sim-lifecycle-test',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidate->id,
        ]);

        $sectionSim = TestSection::create([
            'test_id' => $this->simulatorTest->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 30,
            'order' => 1,
        ]);

        $this->questionSim = Question::create([
            'prompt' => 'Simulator Question 1',
            'section' => SectionType::Listening,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
        ]);
        $this->choiceSimA = QuestionChoice::create([
            'question_id' => $this->questionSim->id,
            'label' => 'A',
            'content' => 'Correct Option',
            'is_correct' => true,
        ]);
        $this->choiceSimB = QuestionChoice::create([
            'question_id' => $this->questionSim->id,
            'label' => 'B',
            'content' => 'Wrong Option',
            'is_correct' => false,
        ]);

        TestQuestion::create([
            'test_section_id' => $sectionSim->id,
            'question_id' => $this->questionSim->id,
            'order' => 1,
            'points' => 10,
        ]);

        // 2. Real Test (60 mins)
        $this->realTest = Test::create([
            'title' => 'TOEIC Official Real Test Lifecycle',
            'slug' => 'toeic-real-lifecycle-test',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidate->id,
        ]);

        $sectionReal = TestSection::create([
            'test_id' => $this->realTest->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 30,
            'order' => 1,
        ]);

        $this->questionReal = Question::create([
            'prompt' => 'Real Test Question 1',
            'section' => SectionType::Listening,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
        ]);
        $this->choiceRealA = QuestionChoice::create([
            'question_id' => $this->questionReal->id,
            'label' => 'A',
            'content' => 'Correct Choice Real',
            'is_correct' => true,
        ]);

        TestQuestion::create([
            'test_section_id' => $sectionReal->id,
            'question_id' => $this->questionReal->id,
            'order' => 1,
            'points' => 10,
        ]);
    }

    public function test_post_submit_mutation_protection(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at' => now(),
            'seed' => 'seed-lifecycle-1',
        ]);

        // Candidate answers question correctly
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->questionSim->id,
            'selected_choice' => $this->choiceSimA->id,
        ])->assertStatus(200);

        // Candidate submits attempt
        $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt))
            ->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
        $originalTotalScore = $attempt->total_score;
        $originalAnswer = Answer::where('attempt_id', $attempt->id)->where('question_id', $this->questionSim->id)->first();
        $this->assertNotNull($originalAnswer);
        $this->assertEquals($this->choiceSimA->id, $originalAnswer->selected_choice_id);

        // Candidate tries to call autosave again after submit to change answer
        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->questionSim->id,
            'selected_choice' => $this->choiceSimB->id,
        ]);
        $response->assertStatus(403);

        // Candidate tries to call flag after submit
        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.flag', $attempt), [
            'question_id' => $this->questionSim->id,
        ]);
        $response->assertStatus(403);

        // Candidate tries to record violation after submit
        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'window_blur',
        ]);
        $response->assertStatus(403);

        // Verify that original answer and total score were not mutated
        $originalAnswer->refresh();
        $attempt->refresh();
        $this->assertEquals($this->choiceSimA->id, $originalAnswer->selected_choice_id);
        $this->assertEquals($originalTotalScore, $attempt->total_score);
    }

    public function test_repeated_submit_idempotency(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at' => now(),
            'seed' => 'seed-lifecycle-2',
        ]);

        // Answer and submit
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->questionSim->id,
            'selected_choice' => $this->choiceSimA->id,
        ]);
        $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));

        $attempt->refresh();
        $firstSubmittedAt = $attempt->submitted_at;
        $firstScore = $attempt->total_score;
        $answer = Answer::where('attempt_id', $attempt->id)->first();
        $firstIsCorrect = $answer->is_correct;
        $firstScoreEarned = $answer->score_earned;

        // Repeated submit
        $repeatResponse = $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $repeatResponse->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $answer->refresh();

        $this->assertEquals($firstSubmittedAt->timestamp, $attempt->submitted_at->timestamp);
        $this->assertEquals($firstScore, $attempt->total_score);
        $this->assertEquals($firstIsCorrect, $answer->is_correct);
        $this->assertEquals($firstScoreEarned, $answer->score_earned);
    }

    public function test_server_time_expiration_blocks_mutation_but_permits_timeout_submit(): void
    {
        // Attempt started 120 minutes ago on a 60 minute test
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at' => now()->subMinutes(120),
            'seed' => 'seed-lifecycle-3',
        ]);

        // Autosave should be rejected due to time expiration
        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->questionReal->id,
            'selected_choice' => $this->choiceRealA->id,
        ]);
        $response->assertStatus(403);

        // Flag should be rejected due to time expiration
        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.flag', $attempt), [
            'question_id' => $this->questionReal->id,
        ]);
        $response->assertStatus(403);

        // Violation should be rejected due to time expiration
        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'window_blur',
        ]);
        $response->assertStatus(403);

        // Real test audio streaming should be rejected due to time expiration
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [
            'attempt' => $attempt,
            'question' => $this->questionReal,
        ]));
        $response->assertStatus(403);

        // However, timeout-submission of the expired in_progress attempt is permitted
        $response = $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $response->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
    }

    public function test_review_gating_redirects_in_progress_attempt_to_exam(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at' => now(),
            'seed' => 'seed-lifecycle-4',
        ]);

        // While in_progress, candidate.review must redirect to candidate.exam
        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertRedirect(route('candidate.exam', $attempt));

        // After submit, candidate.review renders successfully
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->questionSim->id,
            'selected_choice' => $this->choiceSimA->id,
        ]);
        $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertStatus(200);
    }

    public function test_terminal_statuses_block_mutations(): void
    {
        foreach ([AttemptStatus::Expired, AttemptStatus::Cancelled] as $terminalStatus) {
            $attempt = Attempt::create([
                'test_id' => $this->simulatorTest->id,
                'user_id' => $this->candidate->id,
                'attempt_number' => 1,
                'status' => $terminalStatus,
                'evaluation_status' => EvaluationStatus::NotRequired,
                'started_at' => now()->subHours(5),
                'seed' => 'seed-lifecycle-terminal-' . $terminalStatus->value,
            ]);

            $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
                'question_id' => $this->questionSim->id,
                'selected_choice' => $this->choiceSimA->id,
            ])->assertStatus(403);

            $this->actingAs($this->candidate)->postJson(route('candidate.exam.flag', $attempt), [
                'question_id' => $this->questionSim->id,
            ])->assertStatus(403);

            $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
                'violation_type' => 'window_blur',
            ])->assertStatus(403);
        }
    }
}
