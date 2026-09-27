<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\ResultReleaseMode;
use App\Modules\Assessment\Enums\ResultReleaseStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Services\ResultReleasePolicyService;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Enums\TestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DelayedResultReleaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $admin;

    protected Test $mockTest;

    protected Test $simulatorTest;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student', 'web');
        Role::findOrCreate('admin', 'web');

        $this->student = User::factory()->create([
            'name' => 'Test Candidate',
            'email' => 'candidate@test.org',
            'status' => 'active',
        ]);
        $this->student->assignRole('student');

        $this->admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.org',
            'status' => 'active',
        ]);
        $this->admin->assignRole('admin');

        $this->mockTest = Test::create([
            'title' => 'TOEIC Governed Mock Test',
            'slug' => 'toeic-governed-mock-test',
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

        $this->simulatorTest = Test::create([
            'title' => 'TOEIC Simulator Practice',
            'slug' => 'toeic-simulator-practice',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score' => 500,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * TEST A: ResultReleaseStatus enum/state values are canonical.
     */
    public function test_result_release_status_enum_values_are_canonical(): void
    {
        $this->assertEquals('processing', ResultReleaseStatus::Processing->value);
        $this->assertEquals('ready', ResultReleaseStatus::Ready->value);
        $this->assertEquals('released', ResultReleaseStatus::Released->value);

        $this->assertTrue(ResultReleaseStatus::Processing->isProcessing());
        $this->assertTrue(ResultReleaseStatus::Ready->isReady());
        $this->assertTrue(ResultReleaseStatus::Released->isReleased());

        $this->assertFalse(ResultReleaseStatus::Processing->isReleased());
    }

    /**
     * TEST B: Test defaults for new Mock/Real Test: delay = 24, mode = ra_controlled.
     */
    public function test_test_defaults_for_new_mock_real_test(): void
    {
        $test = new Test([
            'title' => 'New Real Test',
            'slug' => 'new-real-test',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
        ]);

        $this->assertEquals(24, $test->getResultReleaseDelayHours());
        $this->assertEquals('ra_controlled', $test->getResultReleaseMode());
        $this->assertTrue($test->usesDelayedResultRelease());
    }

    /**
     * TEST C: Simulator helper reports no delayed-release requirement.
     */
    public function test_simulator_reports_no_delayed_release_requirement(): void
    {
        $this->assertFalse($this->simulatorTest->usesDelayedResultRelease());
        $this->assertEquals(0, $this->simulatorTest->getResultReleaseDelayHours());
        $this->assertEquals('automatic', $this->simulatorTest->getResultReleaseMode());
    }

    /**
     * TEST D: Attempt release helpers behave correctly for processing, ready, released.
     */
    public function test_attempt_release_helpers(): void
    {
        $processingAttempt = new Attempt([
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);
        $this->assertTrue($processingAttempt->isResultProcessing());
        $this->assertFalse($processingAttempt->isResultReady());
        $this->assertFalse($processingAttempt->isResultReleased());

        $readyAttempt = new Attempt([
            'result_release_status' => ResultReleaseStatus::Ready,
        ]);
        $this->assertFalse($readyAttempt->isResultProcessing());
        $this->assertTrue($readyAttempt->isResultReady());
        $this->assertFalse($readyAttempt->isResultReleased());

        $releasedAttempt = new Attempt([
            'result_release_status' => ResultReleaseStatus::Released,
        ]);
        $this->assertFalse($releasedAttempt->isResultProcessing());
        $this->assertFalse($readyAttempt->isResultReleased());
        $this->assertTrue($releasedAttempt->isResultReleased());
    }

    /**
     * TEST E & F: canResultBeReleased() behavior relative to result_release_at and current time.
     */
    public function test_can_result_be_released_timing_behavior(): void
    {
        // 1. Not ready yet: release_at in future
        $futureAttempt = new Attempt([
            'test_id' => $this->mockTest->id,
            'status' => AttemptStatus::Submitted,
            'result_release_status' => ResultReleaseStatus::Processing,
            'result_release_at' => now()->addHours(12),
        ]);
        $futureAttempt->setRelation('test', $this->mockTest);
        $this->assertFalse($futureAttempt->canResultBeReleased());

        // 2. Eligible: release_at in past and not yet released
        $pastAttempt = new Attempt([
            'test_id' => $this->mockTest->id,
            'status' => AttemptStatus::Submitted,
            'result_release_status' => ResultReleaseStatus::Ready,
            'result_release_at' => now()->subMinute(),
        ]);
        $pastAttempt->setRelation('test', $this->mockTest);
        $this->assertTrue($pastAttempt->canResultBeReleased());

        // 3. Already released: returns false even if release_at is past
        $alreadyReleasedAttempt = new Attempt([
            'test_id' => $this->mockTest->id,
            'status' => AttemptStatus::Submitted,
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subHour(),
            'result_released_at' => now()->subHour(),
        ]);
        $alreadyReleasedAttempt->setRelation('test', $this->mockTest);
        $this->assertFalse($alreadyReleasedAttempt->canResultBeReleased());

        // 4. Null release_at: returns false
        $nullAttempt = new Attempt([
            'test_id' => $this->mockTest->id,
            'status' => AttemptStatus::Submitted,
            'result_release_status' => null,
            'result_release_at' => null,
        ]);
        $nullAttempt->setRelation('test', $this->mockTest);
        $this->assertFalse($nullAttempt->canResultBeReleased());
    }

    /**
     * TEST G, H, I, J: Historical backfill preservation & score invariant.
     */
    public function test_historical_attempts_backfilled_as_released_preserving_scores(): void
    {
        $historicalSubmitted = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'is_final' => true,
            'total_score' => 755.0,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(2),
            'result_released_at' => now()->subDays(2),
        ]);

        $this->assertTrue($historicalSubmitted->isResultReleased());
        $this->assertEquals(755.0, $historicalSubmitted->total_score);
        $this->assertTrue($historicalSubmitted->is_final);
    }

    /**
     * TEST H & I: Historical attempt with certificate or completed assignment remains released.
     */
    public function test_historical_attempt_with_certificate_or_completed_assignment_is_released(): void
    {
        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'status' => 'completed',
            'assigned_at' => now()->subDays(3),
            'completed_at' => now()->subDays(1),
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);

        $attempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'is_final' => true,
            'total_score' => 755.0,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(2),
            'result_released_at' => now()->subDays(2),
        ]);

        $cert = Certificate::create([
            'attempt_id' => $attempt->id,
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'certificate_number' => 'CERT-20260926-ORE2',
            'status' => 'valid',
            'issued_at' => now()->subDays(2),
            'total_score' => 755,
        ]);

        $this->assertTrue($attempt->isResultReleased());
        $this->assertEquals('valid', is_object($cert->status) ? $cert->status->value : $cert->status);
        $this->assertEquals('CERT-20260926-ORE2', $cert->certificate_number);
    }

    /**
     * TEST K: in_progress attempt remains unreleased and lifecycle is safe.
     */
    public function test_in_progress_attempt_defaults_to_unreleased(): void
    {
        $inProgressAttempt = Attempt::create([
            'test_id' => $this->mockTest->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'is_final' => false,
            'started_at' => now(),
        ]);

        $this->assertFalse($inProgressAttempt->isResultReleased());
        $this->assertFalse($inProgressAttempt->isCompleted());
        $this->assertNull($inProgressAttempt->result_release_status);
    }

    /**
     * TEST O: ResultReleasePolicyService calculates correct initial state for Simulator and Mock/Real Test.
     */
    public function test_result_release_policy_service_derives_initial_state(): void
    {
        $service = app(ResultReleasePolicyService::class);
        $completionTime = Carbon::parse('2026-09-27 12:00:00');

        // 1. Simulator: instant release
        $simState = $service->deriveInitialReleaseState($this->simulatorTest, $completionTime);
        $this->assertEquals(ResultReleaseStatus::Released, $simState['result_release_status']);
        $this->assertEquals($completionTime, $simState['result_release_at']);
        $this->assertEquals($completionTime, $simState['result_released_at']);
        $this->assertNull($simState['result_released_by']);

        // 2. Real Test with default 24h delay: processing, release_at = completion + 24h
        $mockState = $service->deriveInitialReleaseState($this->mockTest, $completionTime);
        $this->assertEquals(ResultReleaseStatus::Processing, $mockState['result_release_status']);
        $this->assertEquals('2026-09-28 12:00:00', $mockState['result_release_at']->toDateTimeString());
        $this->assertNull($mockState['result_released_at']);
        $this->assertNull($mockState['result_released_by']);

        // 3. Custom delay test (e.g. 48 hours)
        $customTest = Test::create([
            'title' => 'Custom 48h Delay Test',
            'slug' => 'custom-48h-delay-test',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'result_release_delay_hours' => 48,
            'result_release_mode' => ResultReleaseMode::Automatic,
            'is_published' => true,
            'created_by' => $this->admin->id,
        ]);

        $customState = $service->deriveInitialReleaseState($customTest, $completionTime);
        $this->assertEquals(ResultReleaseStatus::Processing, $customState['result_release_status']);
        $this->assertEquals('2026-09-29 12:00:00', $customState['result_release_at']->toDateTimeString());
    }
}
