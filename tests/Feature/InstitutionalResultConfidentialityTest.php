<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\BestResultResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstitutionalResultConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $candidate;

    protected Test $realTest;

    protected Test $simulatorTest;

    protected Question $q1;

    protected Question $q2;

    protected CandidateTestAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'student']);

        $this->admin = User::factory()->create(['email' => 'admin.conf@icedu.org', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->candidate = User::factory()->create(['name' => 'John Candidate', 'email' => 'john.conf@icedu.org', 'status' => 'active']);
        $this->candidate->assignRole('student');

        // Create Question Bank & 2 Questions
        $bank = QuestionBank::create([
            'title' => 'TOEIC Bank Conf',
            'slug' => 'toeic-bank-conf',
            'type' => 'toeic',
            'created_by' => $this->admin->id,
            'is_approved' => true,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'CONFIDENTIAL_QUESTION_PROMPT_1_XYZ',
            'explanation' => 'CONFIDENTIAL_EXPLANATION_1_ABC',
            'points' => 100,
        ]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'CONFIDENTIAL_OPTION_A_CORRECT', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => 'CONFIDENTIAL_OPTION_B_WRONG', 'is_correct' => false]);

        $this->q2 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'CONFIDENTIAL_QUESTION_PROMPT_2_XYZ',
            'explanation' => 'CONFIDENTIAL_EXPLANATION_2_ABC',
            'points' => 100,
        ]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => 'CONFIDENTIAL_OPTION_2A_CORRECT', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => 'CONFIDENTIAL_OPTION_2B_WRONG', 'is_correct' => false]);

        // Real Test
        $this->realTest = Test::create([
            'title' => 'Institutional Real Mock Exam',
            'slug' => 'institutional-real-mock-exam',
            'assessment_mode' => 'real_test',
            'status' => 'published',
            'is_published' => true,
            'duration' => 120,
            'pass_score' => 700,
            'created_by' => $this->admin->id,
        ]);

        $sec = TestSection::create([
            'test_id' => $this->realTest->id,
            'title' => 'Listening Section',
            'order' => 1,
            'time_limit' => 45,
        ]);

        TestQuestion::create(['test_id' => $this->realTest->id, 'test_section_id' => $sec->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_id' => $this->realTest->id, 'test_section_id' => $sec->id, 'question_id' => $this->q2->id, 'order' => 2]);

        // Simulator Test
        $this->simulatorTest = Test::create([
            'title' => 'Simulator Practice Exam',
            'slug' => 'simulator-practice-exam',
            'assessment_mode' => 'simulator',
            'status' => 'published',
            'is_published' => true,
            'duration' => 60,
            'pass_score' => 75,
            'created_by' => $this->admin->id,
        ]);

        $simSec = TestSection::create([
            'test_id' => $this->simulatorTest->id,
            'title' => 'Practice Section',
            'order' => 1,
            'time_limit' => 30,
        ]);
        TestQuestion::create(['test_id' => $this->simulatorTest->id, 'test_section_id' => $simSec->id, 'question_id' => $this->q1->id, 'order' => 1]);

        $this->assignment = CandidateTestAssignment::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'status' => 'active',
            'assigned_by' => $this->admin->id,
            'attempts_count' => 1,
            'max_attempts' => 2,
        ]);
    }

    public function test_real_test_submitted_a1_pending_decision_hides_all_item_details_and_answer_keys(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'score' => 500,
            'total_score' => 500,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertOk();

        // Assert aggregate score and notice are visible
        $response->assertSee('Institutional Mock Test Result Decision');
        $response->assertSee('Final Test Score');
        $response->assertSee('Question-level review is not available for secure institutional assessments');

        // Assert NO leakage of confidential questions, choices, or explanations
        $response->assertDontSee('CONFIDENTIAL_QUESTION_PROMPT_1_XYZ');
        $response->assertDontSee('CONFIDENTIAL_EXPLANATION_1_ABC');
        $response->assertDontSee('CONFIDENTIAL_OPTION_A_CORRECT');
        $response->assertDontSee('CONFIDENTIAL_OPTION_B_WRONG');
        $response->assertDontSee('CONFIDENTIAL_QUESTION_PROMPT_2_XYZ');
        $response->assertDontSee('Detailed Question Review');
    }

    public function test_real_test_expired_a1_pending_decision_hides_all_item_details_and_answer_keys(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'score' => 0,
            'total_score' => 0,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertOk();

        $response->assertSee('Institutional Mock Test Result Decision');
        $response->assertSee('Final Test Score');
        $response->assertSee('Question-level review is not available for secure institutional assessments');
        $response->assertDontSee('CONFIDENTIAL_QUESTION_PROMPT_1_XYZ');
        $response->assertDontSee('CONFIDENTIAL_EXPLANATION_1_ABC');
        $response->assertDontSee('CONFIDENTIAL_OPTION_A_CORRECT');
        $response->assertDontSee('CONFIDENTIAL_OPTION_B_WRONG');
        $response->assertDontSee('Detailed Question Review');
    }

    public function test_real_test_retried_a1_hides_all_item_details_and_answer_keys(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'score' => 450,
            'total_score' => 450,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'retried',
            'is_final' => false,
            'started_at' => now()->subHours(3),
            'submitted_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertOk();

        $response->assertSee('Question-level review is not available for secure institutional assessments');
        $response->assertDontSee('CONFIDENTIAL_QUESTION_PROMPT_1_XYZ');
        $response->assertDontSee('Detailed Question Review');
    }

    public function test_real_test_finalized_a1_hides_all_item_details_and_answer_keys(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'score' => 850,
            'total_score' => 850,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'finalized',
            'is_final' => true,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertOk();

        // Even finalized real tests MUST NEVER disclose question items
        $response->assertSee('Authoritative Institutional Result Finalized');
        $response->assertSee('Question-level review is not available for secure institutional assessments');
        $response->assertDontSee('CONFIDENTIAL_QUESTION_PROMPT_1_XYZ');
        $response->assertDontSee('Detailed Question Review');
    }

    public function test_real_test_finalized_a2_hides_all_item_details_and_answer_keys(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'score' => 890,
            'total_score' => 890,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'finalized',
            'is_final' => true,
            'started_at' => now()->subHours(1),
            'submitted_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertOk();

        $response->assertSee('Question-level review is not available for secure institutional assessments');
        $response->assertDontSee('CONFIDENTIAL_QUESTION_PROMPT_1_XYZ');
        $response->assertDontSee('Detailed Question Review');
    }

    public function test_real_test_losing_historical_attempt_hides_all_item_details_and_answer_keys(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'score' => 400,
            'total_score' => 400,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'retried',
            'is_final' => false,
            'started_at' => now()->subHours(4),
            'submitted_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertOk();

        $response->assertSee('Question-level review is not available for secure institutional assessments');
        $response->assertDontSee('CONFIDENTIAL_QUESTION_PROMPT_1_XYZ');
        $response->assertDontSee('CONFIDENTIAL_OPTION_A_CORRECT');
        $response->assertDontSee('CONFIDENTIAL_EXPLANATION_1_ABC');
        $response->assertDontSee('Detailed Question Review');
    }

    public function test_simulator_completed_attempt_allows_detailed_and_wrong_answer_learning_review(): void
    {
        $simAssignment = CandidateTestAssignment::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->simulatorTest->id,
            'status' => 'completed',
            'assigned_by' => $this->admin->id,
            'attempts_count' => 1,
            'max_attempts' => 1,
        ]);

        $simAttempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->simulatorTest->id,
            'assignment_id' => $simAssignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'score' => 100,
            'total_score' => 100,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'is_final' => true,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $simAttempt));
        $response->assertOk();

        // Simulator MUST show detailed questions for formative learning
        $response->assertSee('Detailed Question Review');
        $response->assertSee('CONFIDENTIAL_QUESTION_PROMPT_1_XYZ');
        $response->assertSee('CONFIDENTIAL_EXPLANATION_1_ABC');
    }

    public function test_real_test_blocks_direct_candidate_simulator_wrong_answers_route_with_403(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'score' => 500,
            'total_score' => 500,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.simulator.wrong-answers', $attempt));
        $response->assertForbidden();
    }

    public function test_expired_attempt_one_can_be_finalized_successfully(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'score' => 0,
            'total_score' => 0,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt));
        $response->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertTrue($attempt->is_final);
        $this->assertEquals('finalized', $attempt->decision_status);

        $this->assignment->refresh();
        $this->assertEquals('completed', $this->assignment->status);
    }

    public function test_expired_attempt_one_can_be_retried_successfully_creating_attempt_two(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'score' => 0,
            'total_score' => 0,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt));

        $attempt->refresh();
        $this->assertEquals('retried', $attempt->decision_status);
        $this->assertFalse($attempt->is_final);

        $this->assignment->refresh();
        $this->assertEquals(2, $this->assignment->attempts_count);
        $this->assertEquals('active', $this->assignment->status);

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)
            ->where('attempt_number', 2)
            ->first();

        $this->assertNotNull($attempt2);
        $this->assertEquals(AttemptStatus::InProgress, $attempt2->status);
        $response->assertRedirect(route('candidate.exam', $attempt2));
    }

    public function test_best_result_resolver_picks_expired_a1_over_submitted_a2_if_expired_has_higher_score(): void
    {
        $a1 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'score' => 750,
            'total_score' => 750,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'retried',
            'is_final' => false,
            'started_at' => now()->subHours(4),
            'submitted_at' => now()->subHours(3),
        ]);

        $a2 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'score' => 600,
            'total_score' => 600,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'finalized',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $resolver = new BestResultResolver;
        $winningAttempt = $resolver->resolve($this->assignment);

        $this->assertNotNull($winningAttempt);
        $this->assertEquals($a1->id, $winningAttempt->id);
        $this->assertEquals(750, $winningAttempt->total_score);
    }

    public function test_best_result_resolver_picks_submitted_a1_over_expired_a2_if_submitted_has_higher_score(): void
    {
        $a1 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'score' => 800,
            'total_score' => 800,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'retried',
            'is_final' => false,
            'started_at' => now()->subHours(4),
            'submitted_at' => now()->subHours(3),
        ]);

        $a2 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'score' => 300,
            'total_score' => 300,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'finalized',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $resolver = new BestResultResolver;
        $winningAttempt = $resolver->resolve($this->assignment);

        $this->assertNotNull($winningAttempt);
        $this->assertEquals($a1->id, $winningAttempt->id);
    }

    public function test_best_result_resolver_evaluates_between_two_expired_attempts(): void
    {
        $a1 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'score' => 200,
            'total_score' => 200,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'retried',
            'is_final' => false,
            'started_at' => now()->subHours(4),
            'submitted_at' => now()->subHours(3),
        ]);

        $a2 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'score' => 450,
            'total_score' => 450,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'finalized',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $resolver = new BestResultResolver;
        $winningAttempt = $resolver->resolve($this->assignment);

        $this->assertNotNull($winningAttempt);
        $this->assertEquals($a2->id, $winningAttempt->id);
    }

    public function test_best_result_resolver_breaks_tie_with_expired_attempt_by_earlier_submitted_at(): void
    {
        $a1 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'score' => 500,
            'total_score' => 500,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'retried',
            'is_final' => false,
            'started_at' => now()->subHours(4),
            'submitted_at' => now()->subHours(3),
        ]);

        $a2 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'score' => 500,
            'total_score' => 500,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'finalized',
            'is_final' => false,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $resolver = new BestResultResolver;
        $winningAttempt = $resolver->resolve($this->assignment);

        $this->assertNotNull($winningAttempt);
        // Earlier submitted_at wins the tie
        $this->assertEquals($a1->id, $winningAttempt->id);
    }

    public function test_best_result_resolver_excludes_in_progress_and_cancelled_attempts(): void
    {
        $a1 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Cancelled,
            'score' => 990,
            'total_score' => 990,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'is_final' => false,
            'started_at' => now()->subHours(4),
            'submitted_at' => now()->subHours(3),
        ]);

        $a2 = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::InProgress,
            'score' => 950,
            'total_score' => 950,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'decision_status' => 'pending_decision',
            'is_final' => false,
            'started_at' => now()->subHours(1),
            'submitted_at' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $resolver = new BestResultResolver;
        $resolver->resolve($this->assignment);
    }
}
