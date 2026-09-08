<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepositoryManagerNavigationAndLifecycleStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $repoManager;
    protected User $teacher;
    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'student']);

        $this->superAdmin = User::factory()->create([
            'email' => 'sa_gov@test.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->repoManager = User::factory()->create([
            'email' => 'rm_gov@test.com',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->teacher = User::factory()->create([
            'email' => 'teacher_gov@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->bank = QuestionBank::create([
            'title'      => 'Core Bank',
            'slug'       => 'core-bank-' . uniqid(),
            'test_type'  => 'toeic',
            'status'     => 'approved',
            'created_by' => $this->teacher->id,
        ]);
    }

    private function createAssessmentWithQuestion(string $status = 'pending_approval', bool $isPublished = false): AssessmentTest
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Lifecycle Test ' . uniqid(),
            'slug'             => 'toeic-lifecycle-' . uniqid(),
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => $status,
            'is_published'     => $isPublished,
            'created_by'       => $this->teacher->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening Section', 'order' => 1]);
        $q = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Select correct answer',
            'type'             => 'single_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Option A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Option B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

        return $test;
    }

    /**
     * RM-NAV-01: RM dashboard Card 2 exposes Request Intake Queue link to canonical route.
     */
    public function test_rm_nav_01_dashboard_card_2_exposes_request_intake_queue_link(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $res->assertStatus(200);
        $res->assertSee(route('admin.repository-manager.assessment-requests.index'));
        $res->assertSee('Request Intake Queue →');
    }

    /**
     * RM-NAV-02: RM dashboard Card 2 retains Assessment Queue and Ready to Publish links.
     */
    public function test_rm_nav_02_dashboard_card_2_retains_assessment_and_publication_links(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $res->assertStatus(200);
        $res->assertSee(route('admin.repository-manager.assessment-approval'));
        $res->assertSee('Assessment Queue →');
        $res->assertSee(route('admin.publications.assessments'));
        $res->assertSee('Ready to Publish');
    }

    /**
     * RM-NAV-03: Super Admin and RM can access Request Intake Queue directly.
     */
    public function test_rm_nav_03_rm_and_super_admin_can_access_assessment_requests_index(): void
    {
        $resRm = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-requests.index'));
        $resRm->assertStatus(200);
        $resRm->assertSee('Assessment Request Intake Queue');

        $resSa = $this->actingAs($this->superAdmin)->get(route('admin.repository-manager.assessment-requests.index'));
        $resSa->assertStatus(200);
        $resSa->assertSee('Assessment');
    }

    /**
     * RM-NAV-04: Unauthorized roles cannot access Request Intake Queue.
     */
    public function test_rm_nav_04_teacher_cannot_access_assessment_requests_index(): void
    {
        $res = $this->actingAs($this->teacher)->get(route('admin.repository-manager.assessment-requests.index'));
        $res->assertStatus(403);
    }

    /**
     * RM-LIFE-01: Approved assessment renders '✓ Approved — Ready to Publish' badge.
     */
    public function test_rm_life_01_approved_assessment_renders_ready_to_publish_badge(): void
    {
        $test = $this->createAssessmentWithQuestion('approved', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'approved']));

        $res->assertStatus(200);
        $res->assertSee('✓ Approved — Ready to Publish');
    }

    /**
     * RM-LIFE-02: Approved assessment renders only Publish action and NO Review link.
     */
    public function test_rm_life_02_approved_assessment_renders_only_publish_action_and_no_review_link(): void
    {
        $test = $this->createAssessmentWithQuestion('approved', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'approved']));

        $res->assertStatus(200);
        $res->assertSee(route('admin.publications.assessments.publish', $test->id));
        $res->assertSee('🚀 Publish');
        $res->assertDontSee(route('admin.repository-manager.assessment-review', $test->id));
    }

    /**
     * RM-LIFE-03: Published assessment renders only Unpublish action and NO Review link.
     */
    public function test_rm_life_03_published_assessment_renders_only_unpublish_action_and_no_review_link(): void
    {
        $test = $this->createAssessmentWithQuestion('published', true);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'published']));

        $res->assertStatus(200);
        $res->assertSee(route('admin.publications.assessments.unpublish', $test->id));
        $res->assertSee('⏸ Unpublish');
        $res->assertDontSee(route('admin.repository-manager.assessment-review', $test->id));
    }

    /**
     * RM-LIFE-04: Direct access to review URL for approved assessment redirects to publication center.
     */
    public function test_rm_life_04_direct_review_url_for_approved_assessment_redirects(): void
    {
        $test = $this->createAssessmentWithQuestion('approved', false);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));

        $res->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]));
        $res->assertSessionHas('status', 'This assessment has already been approved and is ready for publication.');
    }

    /**
     * RM-LIFE-05: Direct access to review URL for published assessment redirects to publication center.
     */
    public function test_rm_life_05_direct_review_url_for_published_assessment_redirects(): void
    {
        $test = $this->createAssessmentWithQuestion('published', true);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));

        $res->assertRedirect(route('admin.publications.assessments', ['status' => 'published', 'highlight' => $test->id]));
        $res->assertSessionHas('status', 'This assessment has already been published live.');
    }

    /**
     * RM-LIFE-06: Review mutation endpoints reject mutation on approved assessment.
     */
    public function test_rm_life_06_review_mutation_endpoints_reject_mutation_on_approved_assessment(): void
    {
        $test = $this->createAssessmentWithQuestion('approved', false);
        $section = $test->sections()->first();
        $q = $section->testQuestions()->first()->question;

        // 1. Mark question reviewed via AJAX
        $resAjax = $this->actingAs($this->repoManager)->json('POST', route('admin.repository-manager.question-review-ok', [
            'test' => $test->id,
            'question' => $q->id,
        ]));
        $resAjax->assertStatus(403);
        $resAjax->assertJsonFragment(['success' => false]);

        // 2. Request question revision via AJAX
        $resRev = $this->actingAs($this->repoManager)->json('POST', route('admin.repository-manager.question-request-revision', [
            'test' => $test->id,
            'question' => $q->id,
        ]), [
            'field' => 'prompt',
            'comment' => 'Late review attempt',
        ]);
        $resRev->assertStatus(403);
        $resRev->assertJsonFragment(['success' => false]);

        // 3. Approve assessment again -> redirects to publication center with error
        $resApprove = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $resApprove->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]));
        $resApprove->assertSessionHas('error');

        // 4. Request assessment revision -> blocked
        $resReqRev = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-revision', $test->id), [
            'notes' => 'Attempt to revert approved test',
        ]);
        $resReqRev->assertRedirect();
        $resReqRev->assertSessionHas('error');

        // 5. Reject / Archive assessment -> blocked
        $resArch = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-archive', $test->id), [
            'notes' => 'Attempt to archive approved test',
        ]);
        $resArch->assertRedirect();
        $resArch->assertSessionHas('error');

        // Verify status remains unchanged
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    /**
     * RM-LIFE-07: Pending assessment allows review page access and action.
     */
    public function test_rm_life_07_pending_assessment_renders_review_cta_and_review_page(): void
    {
        $test = $this->createAssessmentWithQuestion('pending_approval', false);

        $resQueue = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $resQueue->assertStatus(200);
        $resQueue->assertSee(route('admin.repository-manager.assessment-review', $test->id));
        $resQueue->assertSee('Review &amp; Governance', false);

        $resReview = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $resReview->assertStatus(200);
        $resReview->assertSee('Review by Exception Mode');
        $resReview->assertSee('Approve Assessment');
    }

    /**
     * RM-LIFE-08: Approving pending assessment sets status to approved and redirects to publication center.
     */
    public function test_rm_life_08_approving_assessment_sets_status_approved_and_redirects(): void
    {
        $test = $this->createAssessmentWithQuestion('pending_approval', false);

        $res = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id), [
            'notes' => 'Quality standards verified',
        ]);

        $res->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]));
        $res->assertSessionHas('success');

        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }

    /**
     * RM-LIFE-09: Needs revision state renders awaiting teacher resubmission and teacher resubmission restores pending review.
     */
    public function test_rm_life_09_needs_revision_workflow_and_teacher_resubmission(): void
    {
        $test = $this->createAssessmentWithQuestion('needs_revision', false);

        $resQueue = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'needs_revision']));
        $resQueue->assertStatus(200);
        $resQueue->assertSee('📝 Awaiting Teacher Resubmission');
        $resQueue->assertDontSee(route('admin.repository-manager.assessment-review', $test->id));

        // Teacher resubmits test
        $resTeacher = $this->actingAs($this->teacher)->post(route('teacher.tests.resubmit', $test->id));
        $resTeacher->assertRedirect(route('teacher.tests.show', $test->id));

        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);
        $this->assertFalse((bool) $test->is_published);

        // RM sees Review CTA again
        $resQueueAfter = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $resQueueAfter->assertStatus(200);
        $resQueueAfter->assertSee(route('admin.repository-manager.assessment-review', $test->id));
    }
}
