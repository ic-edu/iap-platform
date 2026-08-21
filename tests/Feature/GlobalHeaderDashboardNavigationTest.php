<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalHeaderDashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'candidate']);
        Role::firstOrCreate(['name' => 'finance']);
    }

    public function test_super_admin_header_renders_dashboard_button(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('super-admin.dashboard'));
        $response->assertSee('<span>Dashboard</span>', false);
        $response->assertDontSee('+ Quick Action');
        $response->assertDontSee('quick-action-modal');
    }

    public function test_regular_admin_header_renders_dashboard_button(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.dashboard'));
        $response->assertSee('<span>Dashboard</span>', false);
        $response->assertDontSee('+ Quick Action');
        $response->assertDontSee('quick-action-modal');
    }

    public function test_repository_manager_header_renders_dashboard_button(): void
    {
        $rm = User::factory()->create();
        $rm->assignRole('repository-manager');

        $response = $this->actingAs($rm)->get(route('admin.academic-library.explorer'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.dashboard'));
        $response->assertSee('<span>Dashboard</span>', false);
        $response->assertDontSee('+ Quick Action');
        $response->assertDontSee('quick-action-modal');
    }

    public function test_teacher_header_renders_dashboard_button(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('teacher.dashboard'));
        $response->assertSee('<span>Dashboard</span>', false);
        $response->assertDontSee('+ Quick Action');
        $response->assertDontSee('quick-action-modal');
    }

    public function test_canonical_dashboard_route_redirects_correctly_for_each_role(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $this->actingAs($superAdmin)->get('/dashboard')
            ->assertRedirect(route('super-admin.dashboard'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));

        $rm = User::factory()->create();
        $rm->assignRole('repository-manager');
        $this->actingAs($rm)->get('/dashboard')
            ->assertRedirect(route('admin.repository-manager.dashboard'));

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');
        $this->actingAs($teacher)->get('/dashboard')
            ->assertRedirect(route('teacher.dashboard'));

        $finance = User::factory()->create();
        $finance->assignRole('finance');
        $this->actingAs($finance)->get('/dashboard')
            ->assertRedirect(route('finance.dashboard'));

        $candidate = User::factory()->create();
        $candidate->assignRole('candidate');
        $this->actingAs($candidate)->get('/dashboard')
            ->assertRedirect(route('candidate.portal'));
    }
}
