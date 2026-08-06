<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBoundaryIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher User Hotfix',
            'email'  => 'teacher_hotfix@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Hotfix',
            'email'  => 'repomanager_hotfix@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: Teacher login lands on Teacher Dashboard with ZERO Workflow Review Inbox modal or leakage.
     */
    public function test_1_teacher_dashboard_has_no_workflow_review_inbox_modal()
    {
        $res = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));
        $res->assertStatus(200);

        // Strict Role Boundary Assertions
        $res->assertDontSee('Workflow Review Inbox');
        $res->assertDontSee('workflow-inbox-modal');
        $res->assertSee('Teacher Workspace');
    }

    /**
     * TEST 2: Repository Manager login lands directly on Repository Manager Dashboard.
     */
    public function test_2_repository_manager_dashboard_renders_command_center()
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $res->assertStatus(200);

        $res->assertSee('Repository Manager Command Center');
    }

    /**
     * TEST 3: Dashboard route (/dashboard) redirects each role to its isolated dashboard.
     */
    public function test_3_dashboard_route_redirects_by_role()
    {
        // Teacher /dashboard -> teacher.dashboard
        $teacherRedirect = $this->actingAs($this->teacher)->get('/dashboard');
        $teacherRedirect->assertRedirect(route('teacher.dashboard'));

        // Repository Manager /dashboard -> admin.repository-manager.dashboard
        $repoManagerRedirect = $this->actingAs($this->repoManager)->get('/dashboard');
        $repoManagerRedirect->assertRedirect(route('admin.repository-manager.dashboard'));
    }
}
