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
use App\Modules\Assessment\Services\ResultDecisionService;
use App\Modules\Assessment\Services\ResultReleaseService;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Enums\TestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DelayedResultDecisionAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $admin;

    protected Test $mockTest;

    protected Test $simulatorTest;

    protected CandidateTestAssignment $assignment;

    protected ResultReleaseService $releaseService;

    protected ResultDecisionService $decisionService;

    protected AttemptEngine $attemptEngine;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-admin', 'web');

        $this->student = User::factory()->create([
            'name' => 'Decision Candidate',
            'email' => 'candidate.decision@test.org',
            'status' => 'active',
        ]);
        $this->student->assignRole('student');

        $this->admin = User::factory()->create([
            'name' => 'Decision Admin',
            'email' => 'admin.decision@test.org',
            'status' => 'active',
        ]);
        $this->admin->assignRole('admin');

        $this->mockTest = Test::create([
            'title' => 'Governed TOEIC Decision Mock Test',
            'slug' => 'governed-toeic-decision-mock-test',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 0,
            'result_release_delay_hours' => 24,
            'result_release_mode' => ResultReleaseMode::RaControlled,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $this->simulatorTest = Test::create([
            'title' => 'TOEIC Simulator Decision Drill',
            'slug' => 'toeic-simulator-decision-drill',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score' => 500,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $this->assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assigned_by' => $this->admin->id,
            'status' => 'active',
            'assigned_at' => now()->subDays(3),
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);

        $this->releaseService = app(ResultReleaseService::class);
        $this->decisionService = app(ResultDecisionService::class);
        $this->attemptEngine = app(AttemptEngine::class);
    }

    /**
     * TEST A & B: Attempt #2 submit does NOT finalize assignment and does NOT issue certificate.
     */
    public function test_a_and_b_attempt_2_submit_does_not_finalize_assignment_or_issue_cert(): void
    {
        // Attempt 1: submitted & released
        $attempt1 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'total_score' => 700,
            'is_final' => false,
            'decision_status' => 'retried',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(1),
            'result_released_at' => now()->subDays(1),
            'result_released_by' => $this->admin->id,
        ]);

        // Attempt 2: in-progress -> submitted
        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subHour(),
            'total_score' => 800,
            'is_final' => false,
            'decision_status' => 'pending_decision',
        ]);

        $this->attemptEngine->submitAttempt($attempt2);

        $freshAttempt2 = $attempt2->fresh();
        $freshAssignment = $this->assignment->fresh();

        $this->assertEquals(AttemptStatus::Submitted, $freshAttempt2->status);
        $this->assertEquals(ResultReleaseStatus::Processing, $freshAttempt2->result_release_status);
        $this->assertFalse($freshAttempt2->is_final);
        $this->assertEquals('active', $freshAssignment->status);
        $this->assertNull($freshAssignment->final_attempt_id);
        $this->assertNull($freshAssignment->completed_at);
        $this->assertEquals(0, Certificate::count());
    }

    /**
     * TEST C & D: Attempt #2 expire does NOT finalize assignment and does NOT issue certificate.
     */
    public function test_c_and_d_attempt_2_expire_does_not_finalize_assignment_or_issue_cert(): void
    {
        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subHours(3),
            'total_score' => 780,
            'is_final' => false,
            'decision_status' => 'pending_decision',
        ]);

        $this->attemptEngine->expireAttempt($attempt2);

        $freshAttempt2 = $attempt2->fresh();
        $freshAssignment = $this->assignment->fresh();

        $this->assertEquals(AttemptStatus::Expired, $freshAttempt2->status);
        $this->assertEquals(ResultReleaseStatus::Processing, $freshAttempt2->result_release_status);
        $this->assertFalse($freshAttempt2->is_final);
        $this->assertEquals('active', $freshAssignment->status);
        $this->assertNull($freshAssignment->final_attempt_id);
        $this->assertEquals(0, Certificate::count());
    }

    /**
     * TEST E & F: Attempt #2 processing or ready-but-unreleased leaves assignment active.
     */
    public function test_e_and_f_attempt_2_processing_or_ready_unreleased_leaves_assignment_active(): void
    {
        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(25),
            'submitted_at' => now()->subHours(24),
            'total_score' => 810,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        // Attempt is ready for release by timing, but not yet released by RA
        $this->assertTrue($attempt2->canResultBeReleased());
        $this->assertEquals('active', $this->assignment->fresh()->status);
        $this->assertNull($this->assignment->fresh()->final_attempt_id);
    }

    /**
     * TEST G, H, K, L, M, N: Releasing Attempt #2 triggers best-score resolution (Attempt #2 higher wins).
     */
    public function test_g_h_k_l_m_n_releasing_attempt_2_higher_score_wins(): void
    {
        $attempt1 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'total_score' => 680,
            'is_final' => false,
            'decision_status' => 'retried',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(1),
            'result_released_at' => now()->subDays(1),
            'result_released_by' => $this->admin->id,
        ]);

        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(26),
            'submitted_at' => now()->subHours(25),
            'total_score' => 755,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->releaseService->release($attempt2, $this->admin);

        $fresh1 = $attempt1->fresh();
        $fresh2 = $attempt2->fresh();
        $freshAssignment = $this->assignment->fresh();

        $this->assertTrue($fresh2->isResultReleased());
        $this->assertTrue($fresh2->is_final);
        $this->assertEquals('finalized', $fresh2->decision_status);

        $this->assertFalse($fresh1->is_final);
        $this->assertEquals('retried', $fresh1->decision_status);

        $this->assertEquals('completed', $freshAssignment->status);
        $this->assertEquals($attempt2->id, $freshAssignment->final_attempt_id);

        $cert = Certificate::where('attempt_id', $attempt2->id)->first();
        $this->assertNotNull($cert);
        $this->assertEquals($attempt2->id, $cert->attempt_id);
        $this->assertEquals(1, Certificate::count());
    }

    /**
     * TEST I: Attempt #1 higher score still wins after Attempt #2 is released.
     */
    public function test_i_attempt_1_higher_score_wins_after_attempt_2_released(): void
    {
        $attempt1 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'total_score' => 780,
            'is_final' => false,
            'decision_status' => 'retried',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(1),
            'result_released_at' => now()->subDays(1),
            'result_released_by' => $this->admin->id,
        ]);

        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(26),
            'submitted_at' => now()->subHours(25),
            'total_score' => 730,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->releaseService->release($attempt2, $this->admin);

        $fresh1 = $attempt1->fresh();
        $fresh2 = $attempt2->fresh();
        $freshAssignment = $this->assignment->fresh();

        $this->assertTrue($fresh1->is_final);
        $this->assertEquals('finalized', $fresh1->decision_status);

        $this->assertFalse($fresh2->is_final);
        $this->assertEquals('retried', $fresh2->decision_status);

        $this->assertEquals('completed', $freshAssignment->status);
        $this->assertEquals($attempt1->id, $freshAssignment->final_attempt_id);

        $cert = Certificate::where('attempt_id', $attempt1->id)->first();
        $this->assertNotNull($cert);
        $this->assertEquals($attempt1->id, $cert->attempt_id);
    }

    /**
     * TEST J: Equal score tie selects earlier completed attempt.
     */
    public function test_j_equal_score_tie_selects_earlier_completed_attempt(): void
    {
        $attempt1 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'total_score' => 760,
            'is_final' => false,
            'decision_status' => 'retried',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(1),
            'result_released_at' => now()->subDays(1),
            'result_released_by' => $this->admin->id,
        ]);

        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(26),
            'submitted_at' => now()->subHours(25),
            'total_score' => 760,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->releaseService->release($attempt2, $this->admin);

        $fresh1 = $attempt1->fresh();
        $fresh2 = $attempt2->fresh();

        $this->assertTrue($fresh1->is_final);
        $this->assertEquals('finalized', $fresh1->decision_status);

        $this->assertFalse($fresh2->is_final);
        $this->assertEquals('retried', $fresh2->decision_status);
        $this->assertEquals($attempt1->id, $this->assignment->fresh()->final_attempt_id);
    }

    /**
     * TEST O: One certificate only under repeated/idempotent calls.
     */
    public function test_o_one_certificate_only_under_repeated_release_calls(): void
    {
        $attempt1 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'total_score' => 700,
            'is_final' => false,
            'decision_status' => 'retried',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(1),
            'result_released_at' => now()->subDays(1),
            'result_released_by' => $this->admin->id,
        ]);

        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(26),
            'submitted_at' => now()->subHours(25),
            'total_score' => 755,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->releaseService->release($attempt2, $this->admin);
        $this->assertEquals(1, Certificate::count());

        // Repeated release call is idempotent
        $this->releaseService->release($attempt2, $this->admin);
        $this->assertEquals(1, Certificate::count());
    }

    /**
     * TEST P: Attempt #1 released starts 72-hour decision window from result_released_at.
     */
    public function test_p_attempt_1_decision_window_starts_from_result_released_at(): void
    {
        $releasedTime = Carbon::parse('2026-09-28 10:00:00');

        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => $releasedTime->copy()->subHours(30),
            'submitted_at' => $releasedTime->copy()->subHours(29),
            'total_score' => 800,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => $releasedTime->copy()->subHours(5),
            'result_released_at' => $releasedTime,
            'result_released_by' => $this->admin->id,
        ]);

        $deadline = $attempt->getDecisionDeadline();
        $this->assertEquals('2026-10-01 10:00:00', $deadline->format('Y-m-d H:i:s'));
        $this->assertEquals(72, $releasedTime->diffInHours($deadline));
    }

    /**
     * TEST Q & T: Manual Finalize inside 72h succeeds and is idempotent.
     */
    public function test_q_and_t_manual_finalize_inside_72h_succeeds_and_is_idempotent(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(10),
            'submitted_at' => now()->subHours(9),
            'total_score' => 820,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subHours(5),
            'result_released_at' => now()->subHours(5),
            'result_released_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->student)
            ->post(route('candidate.exam.finalize', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $response->assertSessionHas('status');

        $fresh = $attempt->fresh();
        $this->assertTrue($fresh->is_final);
        $this->assertEquals('finalized', $fresh->decision_status);
        $this->assertEquals('completed', $this->assignment->fresh()->status);
        $this->assertEquals($attempt->id, $this->assignment->fresh()->final_attempt_id);

        // Repeated finalize call is safe and idempotent
        $response2 = $this->actingAs($this->student)
            ->post(route('candidate.exam.finalize', $attempt));
        $response2->assertRedirect(route('candidate.review', $attempt));
        $this->assertEquals(1, Certificate::count());
    }

    /**
     * TEST R: Retry inside 72h succeeds and creates Attempt #2.
     */
    public function test_r_retry_inside_72h_succeeds(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(10),
            'submitted_at' => now()->subHours(9),
            'total_score' => 700,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subHours(5),
            'result_released_at' => now()->subHours(5),
            'result_released_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->student)
            ->post(route('candidate.exam.retry', $attempt));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)
            ->where('attempt_number', 2)
            ->first();

        $this->assertNotNull($attempt2);
        $response->assertRedirect(route('candidate.exam', $attempt2));
        $this->assertEquals(AttemptStatus::InProgress, $attempt2->status);
        $this->assertEquals('retried', $attempt->fresh()->decision_status);
        $this->assertEquals('active', $this->assignment->fresh()->status);
    }

    /**
     * TEST S: Retry after 72h is rejected server-side.
     */
    public function test_s_retry_after_72h_is_rejected(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(100),
            'submitted_at' => now()->subHours(99),
            'total_score' => 700,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subHours(80),
            'result_released_at' => now()->subHours(75), // > 72 hours ago
            'result_released_by' => $this->admin->id,
        ]);

        $this->assertTrue($attempt->isDecisionWindowExpired());

        $response = $this->actingAs($this->student)
            ->post(route('candidate.exam.retry', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $response->assertSessionHas('error', 'The retry decision window has expired. Your first result is being finalized.');

        $this->assertNull(Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first());
    }

    /**
     * TEST U & V: Auto-finalize worker finalizes expired pending Attempt #1 and skips non-expired.
     */
    public function test_u_and_v_auto_finalize_worker_behavior(): void
    {
        // 1. Expired attempt (released 75 hours ago)
        $expiredAttempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(100),
            'submitted_at' => now()->subHours(99),
            'total_score' => 770,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subHours(80),
            'result_released_at' => now()->subHours(75),
            'result_released_by' => $this->admin->id,
        ]);

        // 2. Active attempt (released 20 hours ago)
        $student2 = User::factory()->create(['status' => 'active']);
        $student2->assignRole('student');
        $assignment2 = CandidateTestAssignment::create([
            'user_id' => $student2->id,
            'test_id' => $this->mockTest->id,
            'status' => 'active',
            'assigned_at' => now()->subDays(2),
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);
        $activeAttempt = Attempt::create([
            'user_id' => $student2->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $assignment2->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(25),
            'submitted_at' => now()->subHours(24),
            'total_score' => 790,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subHours(20),
            'result_released_at' => now()->subHours(20),
            'result_released_by' => $this->admin->id,
        ]);

        $this->artisan('iap:finalize-expired-result-decisions')
            ->expectsOutputToContain('Found 1 expired decision attempt(s) eligible for auto-finalization.')
            ->assertSuccessful();

        $this->assertTrue($expiredAttempt->fresh()->is_final);
        $this->assertEquals('finalized', $expiredAttempt->fresh()->decision_status);
        $this->assertEquals('completed', $this->assignment->fresh()->status);

        $this->assertFalse($activeAttempt->fresh()->is_final);
        $this->assertEquals('pending_decision', $activeAttempt->fresh()->decision_status);
        $this->assertEquals('active', $assignment2->fresh()->status);
    }

    /**
     * TEST W: Worker skips lifecycle if Attempt #2 exists.
     */
    public function test_w_worker_skips_lifecycle_if_attempt_2_exists(): void
    {
        $attempt1 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(100),
            'submitted_at' => now()->subHours(99),
            'total_score' => 700,
            'is_final' => false,
            'decision_status' => 'retried',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subHours(75),
            'result_released_by' => $this->admin->id,
        ]);

        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subHour(),
        ]);

        $this->artisan('iap:finalize-expired-result-decisions')
            ->expectsOutputToContain('Found 0 expired decision attempt(s) eligible for auto-finalization.')
            ->assertSuccessful();

        $this->assertEquals('active', $this->assignment->fresh()->status);
    }

    /**
     * TEST X: Worker repeated run is idempotent.
     */
    public function test_x_worker_repeated_run_is_idempotent(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(100),
            'submitted_at' => now()->subHours(99),
            'total_score' => 770,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subHours(75),
            'result_released_by' => $this->admin->id,
        ]);

        $this->artisan('iap:finalize-expired-result-decisions')->assertSuccessful();
        $this->assertEquals(1, Certificate::count());

        $this->artisan('iap:finalize-expired-result-decisions')->assertSuccessful();
        $this->assertEquals(1, Certificate::count());
    }

    /**
     * TEST Y: Worker uses result_released_at, not submitted_at.
     */
    public function test_y_worker_uses_result_released_at_not_submitted_at(): void
    {
        // Submitted 80 hours ago, but only released 10 hours ago -> Not expired yet!
        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(81),
            'submitted_at' => now()->subHours(80),
            'total_score' => 770,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subHours(10), // Released only 10 hours ago
            'result_released_by' => $this->admin->id,
        ]);

        $this->assertFalse($attempt->isDecisionWindowExpired());

        $this->artisan('iap:finalize-expired-result-decisions')
            ->expectsOutputToContain('Found 0 expired decision attempt(s) eligible for auto-finalization.')
            ->assertSuccessful();

        $this->assertFalse($attempt->fresh()->is_final);
    }

    /**
     * TEST Z & AA: Historical CA01-style finalized lifecycle and simulators are ignored by worker.
     */
    public function test_z_and_aa_historical_and_simulators_ignored_by_worker(): void
    {
        // Simulator attempt
        Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->simulatorTest->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(100),
            'submitted_at' => now()->subHours(99),
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subHours(99),
        ]);

        // Historical finalized CA01 attempt
        Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'is_final' => true,
            'decision_status' => 'finalized',
            'started_at' => now()->subHours(100),
            'submitted_at' => now()->subHours(99),
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subHours(99),
        ]);

        $this->artisan('iap:finalize-expired-result-decisions')
            ->expectsOutputToContain('Found 0 expired decision attempt(s) eligible for auto-finalization.')
            ->assertSuccessful();
    }

    /**
     * TEST AB: Assignment remains active throughout delayed Attempt #2 lifecycle until release.
     */
    public function test_ab_assignment_remains_active_throughout_delayed_attempt_2_lifecycle(): void
    {
        // 1. Attempt 1 finalized/retried
        $attempt1 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'total_score' => 700,
            'is_final' => false,
            'decision_status' => 'retried',
            'result_release_status' => ResultReleaseStatus::Released,
            'result_release_at' => now()->subDays(1),
            'result_released_at' => now()->subDays(1),
            'result_released_by' => $this->admin->id,
        ]);

        // 2. Attempt 2 created (in progress) -> assignment still active
        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subHours(2),
            'is_final' => false,
        ]);
        $this->assertEquals('active', $this->assignment->fresh()->status);

        // 3. Attempt 2 submitted (processing) -> assignment still active
        $this->attemptEngine->submitAttempt($attempt2);
        $this->assertEquals('active', $this->assignment->fresh()->status);
        $this->assertNull($this->assignment->fresh()->final_attempt_id);

        // 4. Attempt 2 ready (delay elapsed, but not released) -> assignment still active
        $attempt2->update(['result_release_at' => now()->subMinute()]);
        $this->assertEquals('active', $this->assignment->fresh()->status);

        // 5. Attempt 2 released -> assignment completed
        $this->releaseService->release($attempt2, $this->admin);
        $this->assertEquals('completed', $this->assignment->fresh()->status);
    }

    /**
     * TEST AC: Secure result confidentiality remains intact (unreleased score hidden).
     */
    public function test_ac_secure_result_confidentiality_remains_intact(): void
    {
        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'total_score' => 850,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_at' => now()->addHours(22),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->assertFalse($attempt2->isResultReleased());
        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt2));
        $response->assertOk();
        $response->assertSee('RESULT PROCESSING');
        $response->assertSee('Assessment Result Under Processing');
        $response->assertDontSee('850');
    }

    /**
     * TEST AD: ResultReleaseService Sprint 3 early-release protections remain PASS.
     */
    public function test_ad_result_release_service_early_release_protections_remain_pass(): void
    {
        $attempt2 = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'total_score' => 850,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'result_release_at' => now()->addHours(5), // in future
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Result cannot be released before the configured release time.');

        $this->releaseService->release($attempt2, $this->admin);
    }
}
