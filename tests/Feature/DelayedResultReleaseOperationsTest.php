<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\ResultReleaseMode;
use App\Modules\Assessment\Enums\ResultReleaseStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Services\ResultReleaseService;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Enums\TestType;
use App\Notifications\EnterpriseSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DelayedResultReleaseOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $admin;

    protected User $superAdmin;

    protected User $teacher;

    protected User $repoManager;

    protected Test $mockTest;

    protected Test $simulatorTest;

    protected CandidateTestAssignment $assignment;

    protected ResultReleaseService $releaseService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('teacher', 'web');
        Role::findOrCreate('repository-manager', 'web');

        $this->student = User::factory()->create([
            'name' => 'Candidate Alpha',
            'email' => 'candidate.alpha@test.org',
            'status' => 'active',
        ]);
        $this->student->assignRole('student');

        $this->admin = User::factory()->create([
            'name' => 'Admin Operator',
            'email' => 'admin.operator@test.org',
            'status' => 'active',
        ]);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->teacher = User::factory()->create([
            'name' => 'Teacher User',
            'email' => 'teacher@test.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name' => 'Repo Manager',
            'email' => 'repomanager@test.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->mockTest = Test::create([
            'title' => 'Governed TOEIC Mock Assessment',
            'slug' => 'governed-toeic-mock-assessment',
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
            'title' => 'TOEIC Simulator Quick Drill',
            'slug' => 'toeic-simulator-quick-drill',
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
            'assigned_at' => now()->subDays(2),
            'max_attempts' => 2,
            'attempts_count' => 1,
        ]);

        $this->releaseService = app(ResultReleaseService::class);
    }

    /**
     * Helper to create a completed mock test attempt.
     */
    protected function createCompletedMockAttempt(array $overrides = []): Attempt
    {
        return Attempt::create(array_merge([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(26),
            'submitted_at' => now()->subHours(25),
            'total_score' => 820,
            'scaled_score' => 820,
            'raw_score' => 175,
            'percentage' => 82.0,
            'is_passed' => true,
            'is_final' => false,
            'decision_status' => 'pending_decision',
            'decision_deadline_at' => now()->addHours(47),
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Processing,
            'section_scores' => [
                'listening' => ['raw' => 88, 'scaled' => 420],
                'reading' => ['raw' => 87, 'scaled' => 400],
            ],
            'section_raw_scores' => [
                'listening' => 88,
                'reading' => 87,
            ],
        ], $overrides));
    }

    /**
     * TEST A: Completed Mock Test attempt with result_release_at in past can be released by RA.
     */
    public function test_a_completed_mock_test_attempt_with_result_release_at_in_past_can_be_released_by_ra(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'result_release_at' => now()->subMinutes(10),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $released = $this->releaseService->release($attempt, $this->admin);

        $this->assertEquals(ResultReleaseStatus::Released, $released->result_release_status);
        $this->assertNotNull($released->result_released_at);
        $this->assertEquals($this->admin->id, $released->result_released_by);
        $this->assertTrue($released->fresh()->isResultReleased());
    }

    /**
     * TEST B: Early release attempt throws exception and does NOT release.
     */
    public function test_b_early_release_attempt_throws_exception_and_does_not_release(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'result_release_at' => now()->addHours(3),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Result cannot be released before the configured release time.');

        try {
            $this->releaseService->release($attempt, $this->admin);
        } finally {
            $fresh = $attempt->fresh();
            $this->assertEquals(ResultReleaseStatus::Processing, $fresh->result_release_status);
            $this->assertNull($fresh->result_released_at);
            $this->assertNull($fresh->result_released_by);
            $this->assertFalse($fresh->isResultReleased());
        }
    }

    /**
     * TEST C: In-progress attempt cannot be released.
     */
    public function test_c_in_progress_attempt_cannot_be_released(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subHour(),
            'result_release_at' => now()->subMinutes(10),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only completed attempts (submitted or expired) can be released.');

        $this->releaseService->release($attempt, $this->admin);
    }

    /**
     * TEST D: Simulator attempt cannot be released via RA release service.
     */
    public function test_d_simulator_attempt_cannot_be_released_via_ra_release_service(): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->simulatorTest->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Released,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Simulator assessments do not require manual result release.');

        $this->releaseService->release($attempt, $this->admin);
    }

    /**
     * TEST E: Attempt with pending evaluation cannot be released.
     */
    public function test_e_attempt_with_pending_evaluation_cannot_be_released(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'evaluation_status' => 'pending_evaluation',
            'result_release_at' => now()->subMinutes(10),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attempt is still awaiting examiner evaluation and cannot be released.');

        $this->releaseService->release($attempt, $this->admin);
    }

    /**
     * TEST F: Releasing an already-released attempt is idempotent and safe.
     */
    public function test_f_releasing_an_already_released_attempt_is_idempotent_and_safe(): void
    {
        $originalReleasedAt = now()->subHours(5);
        $attempt = $this->createCompletedMockAttempt([
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => $originalReleasedAt,
            'result_released_by' => $this->admin->id,
        ]);

        $released = $this->releaseService->release($attempt, $this->admin);

        $this->assertEquals(ResultReleaseStatus::Released, $released->result_release_status);
        $this->assertEquals($originalReleasedAt->toIso8601String(), $released->result_released_at->toIso8601String());
        $this->assertEquals($this->admin->id, $released->result_released_by);
    }

    /**
     * TEST G: Releasing sets result_release_status to 'released'.
     */
    public function test_g_releasing_sets_result_release_status_to_released(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->assertEquals(ResultReleaseStatus::Processing, $attempt->result_release_status);

        $released = $this->releaseService->release($attempt, $this->admin);

        $this->assertEquals(ResultReleaseStatus::Released, $released->result_release_status);
        $this->assertEquals(ResultReleaseStatus::Released, $attempt->fresh()->result_release_status);
    }

    /**
     * TEST H: Releasing sets result_released_at to timestamp.
     */
    public function test_h_releasing_sets_result_released_at_to_timestamp(): void
    {
        Carbon::setTestNow('2026-09-28 12:00:00');

        $attempt = $this->createCompletedMockAttempt([
            'result_release_at' => now()->subHour(),
            'result_released_at' => null,
        ]);

        $released = $this->releaseService->release($attempt, $this->admin);

        $this->assertNotNull($released->result_released_at);
        $this->assertEquals('2026-09-28 12:00:00', $released->result_released_at->toDateTimeString());

        Carbon::setTestNow();
    }

    /**
     * TEST I: Releasing records result_released_by as actor ID.
     */
    public function test_i_releasing_records_result_released_by_as_actor_id(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'result_release_at' => now()->subHour(),
            'result_released_by' => null,
        ]);

        $released = $this->releaseService->release($attempt, $this->superAdmin);

        $this->assertEquals($this->superAdmin->id, $released->result_released_by);
        $this->assertEquals($this->superAdmin->id, $attempt->fresh()->result_released_by);
    }

    /**
     * TEST J: Releasing does NOT alter raw/scaled/total scores.
     */
    public function test_j_releasing_does_not_alter_raw_scaled_total_scores(): void
    {
        $originalScores = [
            'listening' => ['raw' => 90, 'scaled' => 430],
            'reading' => ['raw' => 88, 'scaled' => 420],
        ];

        $attempt = $this->createCompletedMockAttempt([
            'total_score' => 850,
            'section_scores' => $originalScores,
        ]);

        $released = $this->releaseService->release($attempt, $this->admin);

        $this->assertEquals(850, $released->total_score);
        $this->assertEquals($originalScores, $released->section_scores);

        $fresh = $attempt->fresh();
        $this->assertEquals(850, $fresh->total_score);
        $this->assertEquals($originalScores, $fresh->section_scores);
    }

    /**
     * TEST K: Releasing does NOT alter section scores.
     */
    public function test_k_releasing_does_not_alter_section_scores(): void
    {
        $originalSectionScores = [
            'listening' => ['raw' => 92, 'scaled' => 450],
            'reading' => ['raw' => 88, 'scaled' => 400],
        ];

        $attempt = $this->createCompletedMockAttempt([
            'section_scores' => $originalSectionScores,
        ]);

        $released = $this->releaseService->release($attempt, $this->admin);

        $this->assertEquals($originalSectionScores, $released->section_scores);

        $fresh = $attempt->fresh();
        $this->assertEquals($originalSectionScores, $fresh->section_scores);
    }

    /**
     * TEST L: Releasing does NOT alter pass/fail status.
     */
    public function test_l_releasing_does_not_alter_pass_fail_status(): void
    {
        // Passing attempt (820 >= 750 pass_score)
        $passAttempt = $this->createCompletedMockAttempt(['total_score' => 820]);
        $releasedPass = $this->releaseService->release($passAttempt, $this->admin);
        $this->assertTrue($releasedPass->total_score >= $this->mockTest->pass_score);
        $this->assertEquals(820, $passAttempt->fresh()->total_score);

        // Failing attempt (600 < 750 pass_score)
        $failAttempt = $this->createCompletedMockAttempt(['total_score' => 600]);
        $releasedFail = $this->releaseService->release($failAttempt, $this->admin);
        $this->assertFalse($releasedFail->total_score >= $this->mockTest->pass_score);
        $this->assertEquals(600, $failAttempt->fresh()->total_score);
    }

    /**
     * TEST M: Releasing does NOT alter is_final.
     */
    public function test_m_releasing_does_not_alter_is_final(): void
    {
        $attempt = $this->createCompletedMockAttempt(['is_final' => false]);
        $this->releaseService->release($attempt, $this->admin);

        $this->assertFalse($attempt->fresh()->is_final);
    }

    /**
     * TEST N: Releasing does NOT alter decision_status.
     */
    public function test_n_releasing_does_not_alter_decision_status(): void
    {
        $attempt = $this->createCompletedMockAttempt(['decision_status' => 'pending_decision']);
        $this->releaseService->release($attempt, $this->admin);

        $this->assertEquals('pending_decision', $attempt->fresh()->decision_status);
    }

    /**
     * TEST O: Releasing does NOT complete assignment.
     */
    public function test_o_releasing_does_not_complete_assignment(): void
    {
        $attempt = $this->createCompletedMockAttempt();
        $this->releaseService->release($attempt, $this->admin);

        $freshAssignment = $this->assignment->fresh();
        $this->assertEquals('active', $freshAssignment->status);
        $this->assertNull($freshAssignment->completed_at);
        $this->assertNull($freshAssignment->final_attempt_id);
    }

    /**
     * TEST P: Releasing does NOT issue certificate.
     */
    public function test_p_releasing_does_not_issue_certificate(): void
    {
        $initialCertificateCount = Certificate::count();
        $attempt = $this->createCompletedMockAttempt();

        $this->releaseService->release($attempt, $this->admin);

        $this->assertEquals($initialCertificateCount, Certificate::count());
        $this->assertNull(Certificate::where('attempt_id', $attempt->id)->first());
    }

    /**
     * TEST Q: Candidate notification is generated on release.
     */
    public function test_q_candidate_notification_is_generated_on_release(): void
    {
        Notification::fake();

        $attempt = $this->createCompletedMockAttempt();
        $this->releaseService->release($attempt, $this->admin);

        Notification::assertSentTo(
            $this->student,
            EnterpriseSystemNotification::class,
            function ($notification) use ($attempt) {
                return $notification->entityId === (string) $attempt->id
                    && $notification->type === 'RESULT_RELEASED';
            }
        );
    }

    /**
     * TEST R: Candidate notification does NOT contain numerical scores.
     */
    public function test_r_candidate_notification_does_not_contain_numerical_scores(): void
    {
        Notification::fake();

        $attempt = $this->createCompletedMockAttempt(['total_score' => 885]);
        $this->releaseService->release($attempt, $this->admin);

        Notification::assertSentTo(
            $this->student,
            EnterpriseSystemNotification::class,
            function ($notification) {
                // Must not contain numeric score 885 or raw numbers in title or message
                $title = $notification->title;
                $message = $notification->message;

                $this->assertStringNotContainsString('885', $title);
                $this->assertStringNotContainsString('885', $message);
                $this->assertStringNotContainsString('score:', strtolower($message));

                return true;
            }
        );
    }

    /**
     * TEST S: Candidate notification type is RESULT_RELEASED.
     */
    public function test_s_candidate_notification_type_is_result_released(): void
    {
        Notification::fake();

        $attempt = $this->createCompletedMockAttempt();
        $this->releaseService->release($attempt, $this->admin);

        Notification::assertSentTo(
            $this->student,
            EnterpriseSystemNotification::class,
            function ($notification) {
                return $notification->type === 'RESULT_RELEASED'
                    && $notification->priority === 'HIGH';
            }
        );
    }

    /**
     * TEST T: Audit log is written with action RESULT_RELEASED.
     */
    public function test_t_audit_log_is_written_with_action_result_released(): void
    {
        $attempt = $this->createCompletedMockAttempt();
        $this->releaseService->release($attempt, $this->admin);

        $log = ActivityLog::where('action', 'RESULT_RELEASED')
            ->where('subject_id', $attempt->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->admin->id, $log->user_id);
        $this->assertEquals($attempt->id, $log->properties['attempt_id']);
        $this->assertEquals($this->admin->id, $log->properties['released_by']);
    }

    /**
     * TEST U: RA dashboard counters compute correctly for processing, ready, released.
     */
    public function test_u_ra_dashboard_counters_compute_correctly_for_processing_ready_released(): void
    {
        // 1. Processing attempt (result_release_at in future)
        $this->createCompletedMockAttempt([
            'result_release_at' => now()->addHours(12),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        // 2. Ready attempt (result_release_at in past, not released)
        $this->createCompletedMockAttempt([
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        // 3. Released attempt (attempt #1, pending_decision)
        $this->createCompletedMockAttempt([
            'result_release_at' => now()->subHours(10),
            'result_release_status' => ResultReleaseStatus::Released,
            'result_released_at' => now()->subHours(8),
            'result_released_by' => $this->admin->id,
            'attempt_number' => 1,
            'is_final' => false,
            'decision_status' => 'pending_decision',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $viewData = $response->viewData('processingResultsCount');
        $this->assertEquals(1, $viewData);

        $viewDataReady = $response->viewData('readyResultsCount');
        $this->assertEquals(1, $viewDataReady);

        $viewDataReleased = $response->viewData('releasedAwaitingDecisionCount');
        $this->assertEquals(1, $viewDataReleased);
    }

    /**
     * TEST V: RA dashboard table lists ready attempts with release action.
     */
    public function test_v_ra_dashboard_table_lists_ready_attempts_with_release_action(): void
    {
        $readyAttempt = $this->createCompletedMockAttempt([
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee('Ready for Release');
        $response->assertSee(route('admin.assessment-attempts.release-result', $readyAttempt->id));
        $response->assertSee('Release Result');
    }

    /**
     * TEST W: RA dashboard table lists processing attempts with disabled/ineligible action.
     */
    public function test_w_ra_dashboard_table_lists_processing_attempts_with_disabled_ineligible_action(): void
    {
        $this->createCompletedMockAttempt([
            'result_release_at' => now()->addHours(12),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee('Processing');
        $response->assertSee('Release Ineligible');
    }

    /**
     * TEST X: POST /admin/assessment-attempts/{attempt}/release-result route authorization.
     */
    public function test_x_post_release_result_route_authorization(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        // 1. Unauthenticated -> Redirect to login
        $this->post(route('admin.assessment-attempts.release-result', $attempt->id))
            ->assertRedirect(route('login'));

        // 2. Candidate (student) -> 403 Forbidden
        $this->actingAs($this->student)
            ->post(route('admin.assessment-attempts.release-result', $attempt->id))
            ->assertForbidden();

        // 3. Teacher -> 403 Forbidden
        $this->actingAs($this->teacher)
            ->post(route('admin.assessment-attempts.release-result', $attempt->id))
            ->assertForbidden();

        // 4. Repository Manager -> 403 Forbidden
        $this->actingAs($this->repoManager)
            ->post(route('admin.assessment-attempts.release-result', $attempt->id))
            ->assertForbidden();

        // 5. Admin -> 302 Redirect with success
        $response = $this->actingAs($this->admin)
            ->post(route('admin.assessment-attempts.release-result', $attempt->id));
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertTrue($attempt->fresh()->isResultReleased());

        // 6. Super Admin -> 302 Redirect with success
        $attempt2 = $this->createCompletedMockAttempt([
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);
        $response2 = $this->actingAs($this->superAdmin)
            ->post(route('admin.assessment-attempts.release-result', $attempt2->id));
        $response2->assertRedirect();
        $response2->assertSessionHas('status');

        $this->assertTrue($attempt2->fresh()->isResultReleased());
    }

    /**
     * HARDENING TEST 1: Pending-evaluation attempt with past release_at has canResultBeReleased() == false.
     */
    public function test_pending_evaluation_attempt_with_past_release_at_cannot_be_released_predicate(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'evaluation_status' => 'pending_evaluation',
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->assertFalse($attempt->canResultBeReleased());
    }

    /**
     * HARDENING TEST 2 & 4: Pending-evaluation attempt does NOT render Release Result CTA and remains Processing/ineligible.
     */
    public function test_pending_evaluation_attempt_does_not_render_release_cta_and_shows_ineligible(): void
    {
        $pendingAttempt = $this->createCompletedMockAttempt([
            'evaluation_status' => 'pending_evaluation',
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        // Must NOT render actionable form for this attempt
        $response->assertDontSee(route('admin.assessment-attempts.release-result', $pendingAttempt->id));
        $response->assertSee('Release Ineligible');
        $response->assertSee('Processing');
    }

    /**
     * HARDENING TEST 3: Pending-evaluation attempt does NOT count in Ready for Release counter.
     */
    public function test_pending_evaluation_attempt_does_not_count_in_ready_for_release(): void
    {
        $this->createCompletedMockAttempt([
            'evaluation_status' => 'pending_evaluation',
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $this->assertEquals(0, $response->viewData('readyResultsCount'));
        $this->assertEquals(1, $response->viewData('processingResultsCount'));
    }

    /**
     * HARDENING TEST 5: Completed not-required evaluation attempt with past release_at has canResultBeReleased() == true.
     */
    public function test_completed_not_required_attempt_with_past_release_at_is_release_eligible(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'evaluation_status' => 'not_required',
            'result_release_at' => now()->subHours(2),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->assertTrue($attempt->canResultBeReleased());
    }

    /**
     * HARDENING TEST 6: Simulator with past release_at has canResultBeReleased() == false.
     */
    public function test_simulator_with_past_release_at_has_can_result_be_released_false(): void
    {
        $simAttempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->simulatorTest->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Released,
        ]);

        $this->assertFalse($simAttempt->canResultBeReleased());
    }

    /**
     * HARDENING TEST 7: In-progress attempt with past release_at has canResultBeReleased() == false.
     */
    public function test_in_progress_attempt_with_past_release_at_has_can_result_be_released_false(): void
    {
        $inProgressAttempt = Attempt::create([
            'user_id' => $this->student->id,
            'test_id' => $this->mockTest->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subHours(2),
            'result_release_at' => now()->subHour(),
            'result_release_status' => ResultReleaseStatus::Processing,
        ]);

        $this->assertFalse($inProgressAttempt->canResultBeReleased());
    }

    /**
     * HARDENING TEST 8: Service revalidates pending evaluation after lock and rejects.
     */
    public function test_service_revalidates_pending_evaluation_after_lock_and_rejects(): void
    {
        $attempt = $this->createCompletedMockAttempt([
            'evaluation_status' => 'pending_evaluation',
            'result_release_at' => now()->subMinutes(10),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attempt is still awaiting examiner evaluation and cannot be released.');

        $this->releaseService->release($attempt, $this->admin);
    }
}
