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

        $resApprove = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id), [
            'notes' => 'Meets academic quality guidelines.',
        ]);

        $resApprove->assertRedirect(route('admin.repository-manager.assessment-approval'));

        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertTrue($test->is_published);

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
}
