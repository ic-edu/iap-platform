<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardRoutingAndWorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_login_redirects_and_renders_super_admin_dashboard(): void
    {
        $superAdmin = User::factory()->create(['password' => bcrypt('password123')]);
        $superAdmin->assignRole('super-admin');

        $loginResponse = $this->post('/login', [
            'email' => $superAdmin->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('super-admin.dashboard'));

        $dashResponse = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Platform Overview Dashboard');
    }

    public function test_admin_login_redirects_and_renders_operational_admin_dashboard(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('password123')]);
        $admin->assignRole('admin');

        $loginResponse = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));

        $dashResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Operational Admin Dashboard');

        // Admin cannot access super-admin dashboard
        $forbiddenResponse = $this->actingAs($admin)->get(route('super-admin.dashboard'));
        $forbiddenResponse->assertStatus(403);
    }

    public function test_teacher_login_redirects_and_renders_teacher_dashboard(): void
    {
        $teacher = User::factory()->create(['password' => bcrypt('password123')]);
        $teacher->assignRole('teacher');

        $loginResponse = $this->post('/login', [
            'email' => $teacher->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('teacher.dashboard'));

        $dashResponse = $this->actingAs($teacher)->get(route('teacher.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Teacher Workspace Dashboard');

        // Teacher cannot access admin, super-admin, or finance dashboards
        $this->actingAs($teacher)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($teacher)->get(route('super-admin.dashboard'))->assertStatus(403);
        $this->actingAs($teacher)->get(route('finance.dashboard'))->assertStatus(403);
    }

    public function test_finance_login_redirects_and_renders_finance_dashboard(): void
    {
        $finance = User::factory()->create(['password' => bcrypt('password123')]);
        $finance->assignRole('finance');

        $loginResponse = $this->post('/login', [
            'email' => $finance->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('finance.dashboard'));

        $dashResponse = $this->actingAs($finance)->get(route('finance.dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Finance Workspace Dashboard');

        // Finance cannot access admin, super-admin, or teacher dashboards
        $this->actingAs($finance)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($finance)->get(route('super-admin.dashboard'))->assertStatus(403);
        $this->actingAs($finance)->get(route('teacher.dashboard'))->assertStatus(403);
    }

    public function test_student_login_redirects_to_candidate_portal(): void
    {
        $student = User::factory()->create(['password' => bcrypt('password123')]);
        $student->assignRole('student');

        $loginResponse = $this->post('/login', [
            'email' => $student->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('candidate.portal'));

        // Student cannot access administrative dashboards
        $this->actingAs($student)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($student)->get(route('super-admin.dashboard'))->assertStatus(403);
        $this->actingAs($student)->get(route('teacher.dashboard'))->assertStatus(403);
        $this->actingAs($student)->get(route('finance.dashboard'))->assertStatus(403);
    }
}
