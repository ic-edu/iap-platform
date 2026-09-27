<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\ResultReleaseMode;
use App\Modules\Assessment\Enums\ResultReleaseStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DelayedResultReleaseCandidateVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $admin;

    protected Test $mockTest;

    protected Test $simulatorTest;

    protected TestSection $mockSection;

    protected Question $mockQuestion;

    protected TestSection $simSection;

    protected Question $simQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student', 'web');
        Role::findOrCreate('admin', 'web');

        $this->student = User::factory()->create([
            'name' => 'Candidate User',
            'email' => 'candidate.visibility@test.org',
            'status' => 'active',
        ]);
        $this->student->assignRole('student');

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin.visibility@test.org',
            'status' => 'active',
        ]);
        $this->admin->assignRole('admin');

        $bank = QuestionBank::create([
            'title' => 'Test Bank',
            'slug' => 'test-bank',
            'test_type' => TestType::Toeic,
            'created_by' => $this->admin->id,
        ]);

        $this->mockTest = Test::create([
            'title' => 'TOEIC Mock Test Visibility',
            'slug' => 'toeic-mock-test-visibility',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 750,
            'result_release_delay_hours' => 24,
            'result_release_mode' => ResultReleaseMode::RaControlled,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $this->mockSection = TestSection::create([
            'test_id' => $this->mockTest->id,
            'title' => 'Mock Section 1',
            'order' => 1,
            'instructions' => 'Section instructions',
        ]);

        $this->mockQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'What is the correct answer?',
            'points' => 10,
        ]);

        QuestionChoice::create([
            'question_id' => $this->mockQuestion->id,
            'label' => 'A',
            'content' => 'Choice A',
            'is_correct' => true,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->mockSection->id,
            'question_id' => $this->mockQuestion->id,
            'order' => 1,
            'points' => 10,
        ]);

        $this->simulatorTest = Test::create([
            'title' => 'TOEIC Simulator Visibility',
            'slug' => 'toeic-simulator-visibility',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score' => 500,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $this->simSection = TestSection::create([
            'test_id' => $this->simulatorTest->id,
            'title' => 'Simulator Section 1',
            'order' => 1,
            'instructions' => 'Sim instructions',
        ]);

        $this->simQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'Sim Question 1',
            'points' => 10,
        ]);

        QuestionChoice::create([
            'question_id' => $this->simQuestion->id,
            'label' => 'A',
            'content' => 'Sim Choice A',
            'is_correct' => true,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->simSection->id,
            'question_id' => $this->simQuestion->id,
            'order' => 1,
            'points' => 10,
        ]);
    }

    /**
     * TEST 1: Future Mock Test submission initializes processing state and +24h release_at.
     */
    public function test_future_mock_test_submission_initializes_processing_state(): void
    {
        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 0,
        ]);

        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(30),
            'seed' => 'testseed1',
        ]);

        $engine = app(AttemptEngine::class);
        $submitted = $engine->submitAttempt($attempt);

        $this->assertEquals(AttemptStatus::Submitted, $submitted->status);
        $this->assertEquals(ResultReleaseStatus::Processing, $submitted->result_release_status);
        $this->assertNotNull($submitted->result_release_at);
        $this->assertNull($submitted->result_released_at);
        $this->assertNull($submitted->result_released_by);
        $this->assertTrue($submitted->isResultProcessing());
        $this->assertFalse($submitted->isResultReleased());

        $expectedRelease = $submitted->submitted_at->copy()->addHours(24);
        $this->assertEquals($expectedRelease->toDateTimeString(), $submitted->result_release_at->toDateTimeString());
    }

    /**
     * TEST 2: Future Mock Test expiry initializes processing state and +24h release_at.
     */
    public function test_future_mock_test_expiry_initializes_processing_state(): void
    {
        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 0,
        ]);

        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(130),
            'seed' => 'testseed2',
        ]);

        $engine = app(AttemptEngine::class);
        $expired = $engine->expireAttempt($attempt);

        $this->assertEquals(AttemptStatus::Expired, $expired->status);
        $this->assertEquals(ResultReleaseStatus::Processing, $expired->result_release_status);
        $this->assertNotNull($expired->result_release_at);
        $this->assertNull($expired->result_released_at);
        $this->assertTrue($expired->isResultProcessing());
        $this->assertFalse($expired->isResultReleased());
    }

    /**
     * TEST 3: Future Simulator submission initializes released state immediately.
     */
    public function test_future_simulator_submission_initializes_released_state(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(20),
            'seed' => 'simseed1',
        ]);

        $engine = app(AttemptEngine::class);
        $submitted = $engine->submitAttempt($attempt);

        $this->assertEquals(AttemptStatus::Submitted, $submitted->status);
        $this->assertEquals(ResultReleaseStatus::Released, $submitted->result_release_status);
        $this->assertNotNull($submitted->result_released_at);
        $this->assertTrue($submitted->isResultReleased());
        $this->assertFalse($submitted->isResultProcessing());
    }

    /**
     * TEST 4: Candidate review server gate prevents unreleased Mock Test score leakage.
     */
    public function test_unreleased_mock_test_review_renders_processing_view_with_no_scores(): void
    {
        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);

        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(60),
            'submitted_at' => now()->subMinutes(10),
            'total_score' => 850,
            'result_release_status' => ResultReleaseStatus::Processing,
            'result_release_at' => now()->addHours(23),
            'seed' => 'testseed4',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertStatus(200);
        $response->assertSee('RESULT PROCESSING');
        $response->assertSee('Assessment Result Under Processing');
        $response->assertDontSee('850');
        $response->assertDontSee('RESULT: PASSED');
        $response->assertDontSee('RESULT: FAILED');
        $response->assertDontSee('Scaled Score');
        $response->assertDontSee('Finalize Result');
        $response->assertDontSee('Retry Second Attempt');
        $response->assertDontSee('Digital Certificate Issued');
    }

    /**
     * TEST 5: Finalize attempt rejected when Mock Test result is still processing.
     */
    public function test_finalize_attempt_rejected_when_unreleased(): void
    {
        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);

        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(60),
            'submitted_at' => now()->subMinutes(10),
            'total_score' => 850,
            'result_release_status' => ResultReleaseStatus::Processing,
            'result_release_at' => now()->addHours(23),
            'seed' => 'testseed5',
        ]);

        $response = $this->actingAs($this->student)->post(route('candidate.exam.finalize', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $response->assertSessionHas('error', 'Your result is still processing. Retry/finalization becomes available after the result is released.');
        $this->assertFalse((bool) $attempt->fresh()->is_final);
    }

    /**
     * TEST 6: Retry attempt rejected when Mock Test result is still processing.
     */
    public function test_retry_attempt_rejected_when_unreleased(): void
    {
        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);

        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(60),
            'submitted_at' => now()->subMinutes(10),
            'total_score' => 850,
            'result_release_status' => ResultReleaseStatus::Processing,
            'result_release_at' => now()->addHours(23),
            'seed' => 'testseed6',
        ]);

        $response = $this->actingAs($this->student)->post(route('candidate.exam.retry', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $response->assertSessionHas('error', 'Your result is still processing. Retry/finalization becomes available after the result is released.');
        $this->assertEquals(1, $assignment->fresh()->attempts()->count());
    }

    /**
     * TEST 7: Released Mock Test attempt allows candidate to review score and make decision.
     */
    public function test_released_mock_test_allows_full_review_and_decision(): void
    {
        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);

        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(60),
            'submitted_at' => now()->subMinutes(10),
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subMinute(),
            'seed' => 'testseed7',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertStatus(200);
        $response->assertSee('Mock Test Result Decision');
        $response->assertSee('Finalize Result &amp; Release Final Score', false);
        $response->assertSee('Retry Second Attempt');
    }

    /**
     * TEST 8: Candidate myResults view masks scores and renders Processing badge for unreleased tests.
     */
    public function test_my_results_masks_scores_for_unreleased_test(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(60),
            'submitted_at' => now()->subMinutes(10),
            'total_score' => 910,
            'result_release_status' => ResultReleaseStatus::Processing,
            'result_release_at' => now()->addHours(23),
            'seed' => 'testseed8',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.my-results'));

        $response->assertStatus(200);
        $response->assertSee('Processing');
        $response->assertSee('PROCESSING');
        $response->assertDontSee('910');
        $response->assertDontSee('PASSED');
    }

    /**
     * TEST 9: Candidate myAttempts view renders Processing for unreleased test.
     */
    public function test_my_attempts_renders_processing_for_unreleased_test(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(60),
            'submitted_at' => now()->subMinutes(10),
            'total_score' => 910,
            'result_release_status' => ResultReleaseStatus::Processing,
            'result_release_at' => now()->addHours(23),
            'seed' => 'testseed9',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.my-attempts'));

        $response->assertStatus(200);
        $response->assertSee('Processing');
        $response->assertDontSee('910');
    }

    /**
     * TEST 10: Future Simulator expiry initializes released state immediately.
     */
    public function test_future_simulator_expiry_initializes_released_state(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(70),
            'seed' => 'simseed10',
        ]);

        $engine = app(AttemptEngine::class);
        $expired = $engine->expireAttempt($attempt);

        $this->assertEquals(AttemptStatus::Expired, $expired->status);
        $this->assertEquals(ResultReleaseStatus::Released, $expired->result_release_status);
        $this->assertNotNull($expired->result_released_at);
        $this->assertTrue($expired->isResultReleased());
    }

    /**
     * TEST 11: Candidate cannot access review when attempt is in progress.
     */
    public function test_candidate_cannot_access_review_when_attempt_is_in_progress(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(10),
            'seed' => 'testseed11',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertRedirect(route('candidate.exam', $attempt));
        $response->assertSessionHas('error', 'Assessment is still in progress. Please submit before viewing results.');
    }

    /**
     * TEST 12: Idempotent initialization does not overwrite existing result release state.
     */
    public function test_initialization_is_idempotent_and_preserves_existing_release_state(): void
    {
        $fixedReleaseAt = now()->addDays(2);
        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(30),
            'result_release_status' => ResultReleaseStatus::Ready,
            'result_release_at' => $fixedReleaseAt,
            'seed' => 'testseed12',
        ]);

        $engine = app(AttemptEngine::class);
        $submitted = $engine->submitAttempt($attempt);

        $this->assertEquals(ResultReleaseStatus::Ready, $submitted->result_release_status);
        $this->assertEquals($fixedReleaseAt->toDateTimeString(), $submitted->result_release_at->toDateTimeString());
    }

    /**
     * TEST 13: Simulator review renders instant result without processing gate.
     */
    public function test_simulator_review_renders_instant_result_without_gate(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(5),
            'total_score' => 10,
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subMinutes(5),
            'seed' => 'simseed13',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertStatus(200);
        $response->assertDontSee('RESULT PROCESSING');
        $response->assertSee('Practice Score');
    }

    /**
     * TEST 14: Historical released records remain released and viewable.
     */
    public function test_historical_released_records_remain_viewable(): void
    {
        $histTest = Test::create([
            'title' => 'Historical Test',
            'slug' => 'hist-test',
            'test_type' => TestType::General,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 500,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $attempt = Attempt::create([
            'test_id' => $histTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'is_final' => true,
            'decision_status' => 'finalized',
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2)->addHours(2),
            'total_score' => 755,
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subDays(2)->addHours(2),
            'seed' => 'histseed14',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertStatus(200);
        $response->assertSee('755');
        $response->assertSee('Final Mock Test Result Confirmed');
        $response->assertDontSee('RESULT PROCESSING');
    }
}
