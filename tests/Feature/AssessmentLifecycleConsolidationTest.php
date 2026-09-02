<?php

namespace Tests\Feature;

use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\Assessment\Engines\AssessmentEngine;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Notifications\EnterpriseSystemNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class AssessmentLifecycleConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected User $superAdmin;
    protected User $adminRa;
    protected User $candidate;
    protected AssignmentEngine $assignmentEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['name' => 'Teacher Author', 'email' => 'teacher@example.com']);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create(['name' => 'Dr. Eleanor Vance', 'email' => 'rm@example.com']);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin', 'email' => 'sa@example.com']);
        $this->superAdmin->assignRole('super-admin');

        $this->adminRa = User::factory()->create(['name' => 'Operational Admin', 'email' => 'admin@example.com']);
        $this->adminRa->assignRole('admin');

        $this->candidate = User::factory()->create(['name' => 'Jane Candidate', 'email' => 'candidate@example.com']);
        $this->candidate->assignRole('student');

        $this->assignmentEngine = app(AssignmentEngine::class);
    }

    private function createAssessment(string $status = 'draft', bool $isPublished = false, string $mode = 'simulator'): Test
    {
        $test = Test::create([
            'title'            => 'TOEIC Assessment ' . uniqid(),
            'slug'             => 'toeic-' . uniqid(),
            'test_type'        => 'toeic',
            'assessment_mode'  => $mode,
            'duration_minutes' => 90,
            'pass_score'       => 70,
            'status'           => $status,
            'is_published'     => $isPublished,
            'created_by'       => $this->teacher->id,
        ]);

        $sec = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 1: Listening Core',
            'order'   => 1,
        ]);

        $bank = QuestionBank::create([
            'title'      => 'Bank ' . uniqid(),
            'slug'       => 'bank-' . uniqid(),
            'test_type'  => 'toeic',
            'created_by' => $this->teacher->id,
            'status'     => 'published',
        ]);

        $q = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Sample question prompt ' . uniqid(),
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 10,
        ]);

        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => 'A',
            'content'     => 'Choice A',
            'is_correct'  => true,
        ]);

        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => 'B',
            'content'     => 'Choice B',
            'is_correct'  => false,
        ]);

        TestQuestion::create([
            'test_section_id' => $sec->id,
            'question_id'     => $q->id,
            'order'           => 1,
            'points'          => 10,
        ]);

        return $test;
    }

    /*
     * -------------------------------------------------------------
     * SECTION 45: FOCUSED TESTS — RM APPROVE (TEST 01 - TEST 06)
     * -------------------------------------------------------------
     */

    public function test_01_pending_approval_assessment_approved_by_rm_results_in_approved_and_not_published(): void
    {
        $test = $this->createAssessment('pending_approval', false);

        $res = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id), [
                'notes' => 'Meets institutional quality guidelines.',
            ]);

        $res->assertRedirect(route('admin.repository-manager.assessment-review', $test->id));
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    public function test_02_teacher_receives_assessment_approved_notification(): void
    {
        Notification::fake();
        $test = $this->createAssessment('pending_approval', false);

        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id), [
                'notes' => 'Quality verified.',
            ]);

        Notification::assertSentTo(
            $this->teacher,
            EnterpriseSystemNotification::class,
            fn($notif) => $notif->type === 'ASSESSMENT_APPROVED'
        );
    }

    public function test_03_approval_notification_says_ready_for_publication(): void
    {
        Notification::fake();
        $test = $this->createAssessment('pending_approval', false);

        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id), [
                'notes' => 'Approved by RM.',
            ]);

        Notification::assertSentTo(
            $this->teacher,
            EnterpriseSystemNotification::class,
            fn($notif) => str_contains($notif->message, 'ready for publication')
        );
    }

    public function test_04_approval_notification_does_not_imply_live_availability(): void
    {
        Notification::fake();
        $test = $this->createAssessment('pending_approval', false);

        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id), [
                'notes' => 'Approved by RM.',
            ]);

        Notification::assertSentTo(
            $this->teacher,
            EnterpriseSystemNotification::class,
            fn($notif) => !str_contains($notif->message, 'is now available for institutional use')
        );
    }

    public function test_05_direct_publish_cta_becomes_available_in_review_workspace_after_approval(): void
    {
        $test = $this->createAssessment('approved', false);

        $res = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-review', $test->id));

        $res->assertStatus(200);
        $res->assertSee('Governance Approved');
        $res->assertSee('Publish Assessment');
        $res->assertDontSee('Unpublish Assessment');
    }

    public function test_06_approved_assessment_enters_central_publication_queue(): void
    {
        $test = $this->createAssessment('approved', false);

        $res = $this->actingAs($this->repoManager)
            ->get(route('admin.publications.assessments'));

        $res->assertStatus(200);
        $res->assertSee($test->title);
        $res->assertSee('Publish');
    }

    /*
     * -------------------------------------------------------------
     * SECTION 46: CTA STATE TESTS (TEST 07 - TEST 14)
     * -------------------------------------------------------------
     */

    public function test_07_draft_assessment_review_shows_no_publish_or_unpublish(): void
    {
        $test = $this->createAssessment('draft', false);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertDontSee('🚀 Publish Assessment');
        $res->assertDontSee('⏸️ Unpublish Assessment');
    }

    public function test_08_pending_approval_assessment_shows_approve_and_no_publish(): void
    {
        $test = $this->createAssessment('pending_approval', false);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertSee('Approve Assessment');
        $res->assertDontSee('🚀 Publish Assessment');
        $res->assertDontSee('⏸️ Unpublish Assessment');
    }

    public function test_09_needs_revision_shows_no_publish_or_unpublish(): void
    {
        $test = $this->createAssessment('needs_revision', false);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertDontSee('🚀 Publish Assessment');
        $res->assertDontSee('⏸️ Unpublish Assessment');
    }

    public function test_10_approved_shows_publish_cta(): void
    {
        $test = $this->createAssessment('approved', false);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertSee('Publish Assessment');
    }

    public function test_11_approved_does_not_show_unpublish(): void
    {
        $test = $this->createAssessment('approved', false);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertDontSee('Unpublish Assessment');
    }

    public function test_12_published_shows_unpublish(): void
    {
        $test = $this->createAssessment('published', true);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertSee('Unpublish Assessment');
    }

    public function test_13_published_does_not_show_publish(): void
    {
        $test = $this->createAssessment('published', true);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertDontSee('🚀 Publish Assessment');
    }

    public function test_14_archived_shows_neither_publish_nor_unpublish(): void
    {
        $test = $this->createAssessment('archived', false);
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertDontSee('🚀 Publish Assessment');
        $res->assertDontSee('⏸️ Unpublish Assessment');
    }

    /*
     * -------------------------------------------------------------
     * SECTION 47: BACKEND CTA GUARD TESTS (TEST 15 - TEST 20)
     * -------------------------------------------------------------
     */

    public function test_15_direct_publish_post_on_draft_rejected(): void
    {
        $test = $this->createAssessment('draft', false);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $res->assertStatus(403);
    }

    public function test_16_direct_publish_post_on_pending_approval_rejected(): void
    {
        $test = $this->createAssessment('pending_approval', false);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $res->assertStatus(403);
    }

    public function test_17_direct_publish_post_on_needs_revision_rejected(): void
    {
        $test = $this->createAssessment('needs_revision', false);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $res->assertStatus(403);
    }

    public function test_18_direct_publish_post_on_approved_accepted(): void
    {
        $test = $this->createAssessment('approved', false);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $res->assertRedirect();
        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue((bool) $test->is_published);
    }

    public function test_19_unpublish_post_on_approved_rejected(): void
    {
        $test = $this->createAssessment('approved', false);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));
        $res->assertStatus(403);
    }

    public function test_20_unpublish_post_on_published_accepted(): void
    {
        $test = $this->createAssessment('published', true);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));
        $res->assertRedirect();
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    /*
     * -------------------------------------------------------------
     * SECTION 48: PUBLICATION TESTS (TEST 21 - TEST 24)
     * -------------------------------------------------------------
     */

    public function test_21_approved_false_publishes_to_published_true(): void
    {
        $test = $this->createAssessment('approved', false);
        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue($test->is_published);
    }

    public function test_22_non_approved_cannot_publish(): void
    {
        $test = $this->createAssessment('pending_approval', false);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $res->assertStatus(403);
        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);
    }

    public function test_23_teacher_receives_assessment_published_notification(): void
    {
        Notification::fake();
        $test = $this->createAssessment('approved', false);

        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));

        Notification::assertSentTo(
            $this->teacher,
            EnterpriseSystemNotification::class,
            fn($notif) => $notif->type === 'ASSESSMENT_PUBLISHED'
        );
    }

    public function test_24_audit_log_distinguishes_publish_from_approve(): void
    {
        $test = $this->createAssessment('pending_approval', false);

        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'action'        => 'approved',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'PUBLISH',
        ]);
    }

    /*
     * -------------------------------------------------------------
     * SECTION 49: ASSIGNMENT TESTS (TEST 25 - TEST 29)
     * -------------------------------------------------------------
     */

    public function test_25_approved_false_cannot_be_assigned(): void
    {
        $test = $this->createAssessment('approved', false, 'real_test');

        $this->expectException(InvalidArgumentException::class);
        $this->assignmentEngine->assignToUser($test, $this->candidate, $this->adminRa);
    }

    public function test_26_published_true_can_be_assigned(): void
    {
        $test = $this->createAssessment('published', true, 'simulator');

        $assignment = $this->assignmentEngine->assignToUser($test, $this->candidate, $this->adminRa);
        $this->assertInstanceOf(CandidateTestAssignment::class, $assignment);
        $this->assertEquals('active', $assignment->status);
    }

    public function test_27_non_canonical_published_false_rejected_from_assignment(): void
    {
        $test = $this->createAssessment('published', false, 'real_test');

        $this->expectException(InvalidArgumentException::class);
        $this->assignmentEngine->assignToUser($test, $this->candidate, $this->adminRa);
    }

    public function test_28_draft_pending_needs_revision_cannot_be_assigned(): void
    {
        foreach (['draft', 'pending_approval', 'needs_revision'] as $status) {
            $test = $this->createAssessment($status, false, 'real_test');
            try {
                $this->assignmentEngine->assignToUser($test, $this->candidate, $this->adminRa);
                $this->fail("Expected InvalidArgumentException for {$status}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('unpublished', $e->getMessage());
            }
        }
    }

    public function test_29_crafted_ra_assignment_cannot_bypass_publication_invariant(): void
    {
        $test = $this->createAssessment('approved', false, 'real_test');

        $res = $this->actingAs($this->adminRa)->post(route('admin.tests.assign-candidate', $test->id), [
            'candidate_id' => $this->candidate->id,
        ]);

        $res->assertSessionHas('error');
        $this->assertDatabaseMissing('candidate_test_assignments', [
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
        ]);
    }

    /*
     * -------------------------------------------------------------
     * SECTION 50: CANDIDATE TESTS (TEST 30 - TEST 34)
     * -------------------------------------------------------------
     */

    public function test_30_approved_false_hidden_from_candidate_portal(): void
    {
        $simTest = $this->createAssessment('approved', false, 'simulator');

        $res = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $res->assertStatus(200);
        $res->assertDontSee($simTest->title);
    }

    public function test_31_published_true_visible_to_candidate(): void
    {
        $simTest = $this->createAssessment('published', true, 'simulator');

        $res = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $res->assertStatus(200);
        $res->assertSee($simTest->title);
    }

    public function test_32_unpublished_real_test_cannot_create_new_attempt(): void
    {
        $test = $this->createAssessment('approved', false, 'real_test');

        CandidateTestAssignment::create([
            'test_id'     => $test->id,
            'user_id'     => $this->candidate->id,
            'status'      => 'active',
            'assigned_at' => now(),
        ]);

        $res = $this->actingAs($this->candidate)->get(route('candidate.tests.instructions', $test->id));
        $res->assertStatus(403);
    }

    public function test_33_published_real_test_still_requires_active_assignment(): void
    {
        $test = $this->createAssessment('published', true, 'real_test');

        // Without assignment -> 403
        $resWithoutAssignment = $this->actingAs($this->candidate)->get(route('candidate.tests.instructions', $test->id));
        $resWithoutAssignment->assertStatus(403);

        // With active assignment -> 200
        CandidateTestAssignment::create([
            'test_id'     => $test->id,
            'user_id'     => $this->candidate->id,
            'status'      => 'active',
            'assigned_at' => now(),
        ]);

        $resWithAssignment = $this->actingAs($this->candidate)->get(route('candidate.tests.instructions', $test->id));
        $resWithAssignment->assertStatus(200);
    }

    public function test_34_simulator_requires_canonical_published_state(): void
    {
        $draftSim = $this->createAssessment('draft', false, 'simulator');
        $approvedSim = $this->createAssessment('approved', false, 'simulator');
        $publishedSim = $this->createAssessment('published', true, 'simulator');

        $this->assertFalse($this->assignmentEngine->isEligibleToStart($draftSim, $this->candidate));
        $this->assertFalse($this->assignmentEngine->isEligibleToStart($approvedSim, $this->candidate));
        $this->assertTrue($this->assignmentEngine->isEligibleToStart($publishedSim, $this->candidate));
    }

    /*
     * -------------------------------------------------------------
     * SECTION 51: COMMERCE TESTS (TEST 35 - TEST 36)
     * -------------------------------------------------------------
     */

    public function test_35_approved_false_excluded_from_commerce_assessment_selection(): void
    {
        $approvedTest = $this->createAssessment('approved', false);

        $res = $this->actingAs($this->adminRa)->get(route('admin.commerce.index'));
        $res->assertStatus(200);
        $testsInDropdown = $res->viewData('tests');
        $this->assertFalse($testsInDropdown->contains('id', $approvedTest->id));
    }

    public function test_36_published_true_included_in_commerce_assessment_selection(): void
    {
        $publishedTest = $this->createAssessment('published', true);

        $res = $this->actingAs($this->adminRa)->get(route('admin.commerce.index'));
        $res->assertStatus(200);
        $testsInDropdown = $res->viewData('tests');
        $this->assertTrue($testsInDropdown->contains('id', $publishedTest->id));
    }

    /*
     * -------------------------------------------------------------
     * SECTION 52: SUPER ADMIN TESTS (TEST 37 - TEST 39)
     * -------------------------------------------------------------
     */

    public function test_37_super_admin_approval_produces_approved_and_not_published(): void
    {
        $test = $this->createAssessment('pending_approval', false);

        $this->actingAs($this->superAdmin)->post(route('admin.approvals.approve', $test->id));
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    public function test_38_super_admin_approval_does_not_expose_candidate_content(): void
    {
        $test = $this->createAssessment('pending_approval', false, 'simulator');
        $this->actingAs($this->superAdmin)->post(route('admin.approvals.approve', $test->id));

        $res = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $res->assertDontSee($test->title);
    }

    public function test_39_publication_remains_distinct_authorized_operation_for_super_admin(): void
    {
        $test = $this->createAssessment('approved', false);
        $this->actingAs($this->superAdmin)->post(route('admin.publications.assessments.publish', $test->id));
        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue($test->is_published);
    }

    /*
     * -------------------------------------------------------------
     * SECTION 53: RA GOVERNANCE TESTS (TEST 40 - TEST 43)
     * -------------------------------------------------------------
     */

    public function test_40_ra_cannot_approve(): void
    {
        $test = $this->createAssessment('pending_approval', false);
        $res = $this->actingAs($this->adminRa)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $res->assertStatus(403);
    }

    public function test_41_ra_cannot_publish(): void
    {
        $test = $this->createAssessment('approved', false);
        $res = $this->actingAs($this->adminRa)->post(route('admin.publications.assessments.publish', $test->id));
        $res->assertStatus(403);
    }

    public function test_42_ra_cannot_unpublish(): void
    {
        $test = $this->createAssessment('published', true);
        $res = $this->actingAs($this->adminRa)->post(route('admin.publications.assessments.unpublish', $test->id));
        $res->assertStatus(403);
    }

    public function test_43_ra_may_assign_only_canonical_published_assessment(): void
    {
        $approvedTest = $this->createAssessment('approved', false, 'simulator');
        $publishedTest = $this->createAssessment('published', true, 'simulator');

        $this->assertFalse($approvedTest->isPublished());
        $this->assertTrue($publishedTest->isPublished());

        $this->assertInstanceOf(
            CandidateTestAssignment::class,
            $this->assignmentEngine->assignToUser($publishedTest, $this->candidate, $this->adminRa)
        );
    }

    /*
     * -------------------------------------------------------------
     * SECTION 54: UNPUBLISH TESTS (TEST 44 - TEST 51)
     * -------------------------------------------------------------
     */

    public function test_44_published_true_unpublishes_to_approved_false(): void
    {
        $test = $this->createAssessment('published', true);
        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));
        $res->assertRedirect();
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    public function test_45_new_assignments_blocked_after_unpublish(): void
    {
        $test = $this->createAssessment('published', true, 'simulator');
        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));

        $test->refresh();
        $this->expectException(InvalidArgumentException::class);
        $this->assignmentEngine->assignToUser($test, $this->candidate, $this->adminRa);
    }

    public function test_46_candidate_catalog_hides_unpublished_assessment(): void
    {
        $test = $this->createAssessment('published', true, 'simulator');
        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));

        $res = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $res->assertDontSee($test->title);
    }

    public function test_47_candidate_without_started_attempt_cannot_create_new_attempt_after_unpublish(): void
    {
        $test = $this->createAssessment('published', true, 'real_test');

        CandidateTestAssignment::create([
            'test_id'     => $test->id,
            'user_id'     => $this->candidate->id,
            'status'      => 'active',
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));

        $res = $this->actingAs($this->candidate)->get(route('candidate.tests.instructions', $test->id));
        $res->assertStatus(403);
    }

    public function test_48_existing_in_progress_attempt_is_not_deleted_or_cancelled_by_unpublish(): void
    {
        $test = $this->createAssessment('published', true, 'simulator');

        $attempt = Attempt::create([
            'test_id'     => $test->id,
            'user_id'     => $this->candidate->id,
            'status'      => AttemptStatus::InProgress,
            'started_at'  => now(),
            'time_left'   => 5400,
        ]);

        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));

        $this->assertDatabaseHas('attempts', [
            'id'     => $attempt->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_49_completed_attempt_and_result_remains_intact_after_unpublish(): void
    {
        $test = $this->createAssessment('published', true, 'simulator');

        $attempt = Attempt::create([
            'test_id'      => $test->id,
            'user_id'      => $this->candidate->id,
            'status'       => AttemptStatus::Submitted,
            'started_at'   => now()->subHour(),
            'completed_at' => now(),
            'total_score'  => 85,
        ]);

        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));

        $this->assertDatabaseHas('attempts', [
            'id'          => $attempt->id,
            'status'      => 'submitted',
            'total_score' => 85,
        ]);
    }

    public function test_50_existing_candidate_test_assignment_record_is_preserved_after_unpublish(): void
    {
        $test = $this->createAssessment('published', true, 'simulator');

        $assignment = CandidateTestAssignment::create([
            'test_id'     => $test->id,
            'user_id'     => $this->candidate->id,
            'status'      => 'active',
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));

        $this->assertDatabaseHas('candidate_test_assignments', [
            'id'      => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $this->candidate->id,
            'status'  => 'active',
        ]);
    }

    public function test_51_rm_may_republish_approved_false_later_without_reauthoring(): void
    {
        $test = $this->createAssessment('published', true);

        // Unpublish
        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.unpublish', $test->id));
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);

        // Republish
        $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $test->id));
        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue((bool) $test->is_published);
    }

    /*
     * -------------------------------------------------------------
     * SECTION 55: ARCHIVE / REJECT TESTS (TEST 52 - TEST 54)
     * -------------------------------------------------------------
     */

    public function test_52_archive_forces_is_published_false(): void
    {
        $test = $this->createAssessment('pending_approval', false);

        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-archive', $test->id), [
            'notes' => 'Archiving redundant test.',
        ]);

        $test->refresh();
        $this->assertEquals('archived', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    public function test_53_rejected_assessment_remains_unpublished(): void
    {
        $test = $this->createAssessment('pending_approval', false);
        $this->actingAs($this->superAdmin)->post(route('admin.approvals.reject', $test->id), [
            'reason' => 'Quality standards not met.',
        ]);

        $test->refresh();
        $this->assertEquals('rejected', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    public function test_54_historical_attempt_data_remains_intact_when_test_archived(): void
    {
        $test = $this->createAssessment('pending_approval', false);

        $attempt = Attempt::create([
            'test_id'      => $test->id,
            'user_id'      => $this->candidate->id,
            'status'       => AttemptStatus::Submitted,
            'started_at'   => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'total_score'  => 90,
        ]);

        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-archive', $test->id));

        $this->assertDatabaseHas('attempts', [
            'id'          => $attempt->id,
            'total_score' => 90,
        ]);
    }

    /*
     * -------------------------------------------------------------
     * SECTION 56: SCOPE / HELPER TESTS (TEST 55 - TEST 58)
     * -------------------------------------------------------------
     */

    public function test_55_test_published_scope_returns_only_published_and_true(): void
    {
        $t1 = $this->createAssessment('published', true);
        $t2 = $this->createAssessment('approved', false);
        $t3 = $this->createAssessment('draft', false);

        $results = Test::published()->pluck('id');
        $this->assertTrue($results->contains($t1->id));
        $this->assertFalse($results->contains($t2->id));
        $this->assertFalse($results->contains($t3->id));
    }

    public function test_56_approved_true_is_not_canonical_published(): void
    {
        $t = $this->createAssessment('approved', true);
        $this->assertFalse($t->isPublished());
        $this->assertFalse(Test::published()->where('id', $t->id)->exists());
    }

    public function test_57_published_false_is_not_canonical_published(): void
    {
        $t = $this->createAssessment('published', false);
        $this->assertFalse($t->isPublished());
        $this->assertFalse(Test::published()->where('id', $t->id)->exists());
    }

    public function test_58_is_published_helper_matches_same_invariant(): void
    {
        $live = $this->createAssessment('published', true);
        $approved = $this->createAssessment('approved', false);
        $draft = $this->createAssessment('draft', false);

        $this->assertTrue($live->isPublished());
        $this->assertFalse($approved->isPublished());
        $this->assertFalse($draft->isPublished());

        $this->assertTrue($approved->isApproved());
        $this->assertFalse($live->isApproved());
    }
}
