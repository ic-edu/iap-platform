<?php

namespace Tests\Feature;

use App\Models\AclAuditTrail;
use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Author Teacher',
            'email'  => 'teacher_author@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Academic Repository Manager',
            'email'  => 'repomanager_test@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Governance Admin',
            'email'  => 'superadmin_test@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');
    }

    protected function attachValidQuestion(AssessmentTest $test): void
    {
        $sec = \App\Modules\Assessment\Models\TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Core Section',
            'order'   => 1,
        ]);

        $bank = \App\Modules\QuestionBank\Models\QuestionBank::create([
            'title'       => 'Test Bank ' . $test->id,
            'slug'        => 'test-bank-' . $test->id . '-' . \Illuminate\Support\Str::random(5),
            'test_type'   => 'toefl',
            'status'      => 'published',
            'created_by'  => $this->teacher->id,
            'description' => 'Test bank',
        ]);

        $question = \App\Modules\QuestionBank\Models\Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Sample valid prompt?',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'difficulty'       => 'medium',
        ]);

        \App\Modules\QuestionBank\Models\QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Choice A',
            'is_correct'  => true,
        ]);

        \App\Modules\Assessment\Models\TestQuestion::create([
            'test_section_id' => $sec->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $test->refresh();
    }

    public function test_teacher_can_submit_draft_assessment_to_repository_manager_queue()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEFL iBT Simulation Draft',
            'slug'             => 'toefl-ibt-simulation-draft',
            'test_type'        => 'toefl',
            'duration_minutes' => 120,
            'pass_score'       => 70,
            'status'           => 'draft',
            'created_by'       => $this->teacher->id,
        ]);

        $this->attachValidQuestion($test);

        $res = $this->actingAs($this->teacher)
            ->from(route('teacher.dashboard'))
            ->post(route('admin.tests.submit', $test->id));

        $res->assertRedirect(route('teacher.dashboard'));
        $res->assertSessionHas('status');

        $test->refresh();
        $this->assertEquals('pending', $test->status);

        // Appears in Repository Manager Queue
        $resQueue = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $resQueue->assertStatus(200);
        $resQueue->assertSee('TOEFL iBT Simulation Draft');
    }

    public function test_repository_manager_can_view_and_approve_pending_assessment()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Full Assessment',
            'slug'             => 'toeic-full-assessment',
            'test_type'        => 'toeic',
            'duration_minutes' => 90,
            'pass_score'       => 75,
            'status'           => 'pending',
            'created_by'       => $this->teacher->id,
        ]);

        $this->attachValidQuestion($test);

        $resApprove = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id), [
            'notes' => 'Meets academic quality guidelines.',
        ]);

        $resApprove->assertRedirect(route('admin.publications.assessments', ['status' => 'approved', 'highlight' => $test->id]));

        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'action'        => 'approved',
        ]);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'action'        => 'assessment_approved',
        ]);
    }

    public function test_repository_manager_can_request_revision_on_assessment()
    {
        $test = AssessmentTest::create([
            'title'            => 'IELTS General Reading Test',
            'slug'             => 'ielts-general-reading-test',
            'test_type'        => 'ielts',
            'duration_minutes' => 60,
            'pass_score'       => 65,
            'status'           => 'pending',
            'created_by'       => $this->teacher->id,
        ]);

        $resRevision = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-revision', $test->id), [
            'notes' => 'Please add passage transcript citations.',
        ]);

        $resRevision->assertRedirect(route('admin.repository-manager.assessment-approval'));

        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);
        $this->assertFalse($test->is_published);

        $this->assertDatabaseHas('acl_audit_trails', [
            'resource_type' => 'Test',
            'resource_id'   => (string) $test->id,
            'action'        => 'assessment_revision_requested',
        ]);
    }

    public function test_dashboard_counters_are_synchronized_across_teacher_and_repository_manager()
    {
        AssessmentTest::create([
            'title'            => 'Pending Test 1',
            'slug'             => 'pending-test-1',
            'test_type'        => 'toefl',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending',
            'created_by'       => $this->teacher->id,
        ]);

        AssessmentTest::create([
            'title'            => 'Approved Test 1',
            'slug'             => 'approved-test-1',
            'test_type'        => 'toeic',
            'duration_minutes' => 90,
            'pass_score'       => 80,
            'status'           => 'approved',
            'created_by'       => $this->teacher->id,
        ]);

        $resRepoDash = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $resRepoDash->assertStatus(200);
        $resRepoDash->assertSee('Pending Test 1');

        $resTeacherDash = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));
        $resTeacherDash->assertStatus(200);
    }

    public function test_full_assessment_workflow_lifecycle_and_metric_synchronization()
    {
        $workflowService = app(\App\Services\AssessmentWorkflowService::class);

        // 1. Teacher creates draft assessment
        $test = AssessmentTest::create([
            'title'            => 'E2E Lifecycle Assessment',
            'slug'             => 'e2e-lifecycle-assessment',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacher->id,
        ]);

        $this->attachValidQuestion($test);

        $this->assertEquals(1, $workflowService->getTeacherMetrics($this->teacher)['draft']);
        $this->assertEquals(0, $workflowService->getTeacherMetrics($this->teacher)['pending']);
        $this->assertEquals(0, $workflowService->getRepositoryManagerMetrics()['pendingAssessmentsCount']);

        // 2. Teacher submits assessment -> status = pending, is_published = false
        $this->actingAs($this->teacher)
            ->from(route('teacher.dashboard'))
            ->post(route('admin.tests.submit', $test->id));

        $test->refresh();
        $this->assertEquals('pending', $test->status);
        $this->assertFalse($test->is_published);

        // Repository Manager Dashboard pending count increases
        $this->assertEquals(1, $workflowService->getRepositoryManagerMetrics()['pendingAssessmentsCount']);
        $this->assertEquals(1, $workflowService->getTeacherMetrics($this->teacher)['pending']);

        // 3. Repository Manager requests revision -> status = needs_revision, is_published = false
        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-revision', $test->id), [
                'notes' => 'Please add passage transcript.',
            ]);

        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);
        $this->assertFalse($test->is_published);

        // Teacher Dashboard updates (Needs Revision +1, Pending -1)
        $this->assertEquals(0, $workflowService->getTeacherMetrics($this->teacher)['pending']);
        $this->assertEquals(1, $workflowService->getTeacherMetrics($this->teacher)['needs_revision']);

        // Repository Manager sees non-clickable badge "Awaiting Teacher Resubmission"
        $resQueue = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'needs_revision']));
        $resQueue->assertSee('Awaiting Teacher Resubmission');

        // 4. Teacher edits & resubmits -> status = pending, is_published = false
        $this->actingAs($this->teacher)
            ->from(route('teacher.dashboard'))
            ->post(route('admin.tests.submit', $test->id));

        $test->refresh();
        $this->assertEquals('pending', $test->status);

        // 5. Repository Manager approves assessment -> status = approved, is_published = false (ready for publication)
        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id), [
                'notes' => 'E2E test approval.',
            ]);

        $test->refresh();
        // Repository Manager sees non-clickable badge "✓ Approved — Ready to Publish" in approved queue
        $resApprovedQueue = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval', ['status' => 'approved']));
        $resApprovedQueue->assertSee('Approved — Ready to Publish');

        // 6. Repository Manager publishes assessment -> status = published, is_published = true
        $this->actingAs($this->repoManager)
            ->post(route('admin.publications.assessments.publish', $test->id));

        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue((bool) $test->is_published);

        // Teacher Pending decreases, Repository Dashboard pending decreases
        $this->assertEquals(0, $workflowService->getTeacherMetrics($this->teacher)['pending']);
        $this->assertEquals(0, $workflowService->getRepositoryManagerMetrics()['pendingAssessmentsCount']);
    }
}
