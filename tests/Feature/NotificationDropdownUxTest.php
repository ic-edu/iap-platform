<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDropdownUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $repoManager;
    protected User $teacher;
    protected User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin User']);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->repoManager = User::factory()->create(['name' => 'Repo Manager User']);
        $this->repoManager->assignRole('repository-manager');

        $this->teacher = User::factory()->create(['name' => 'Teacher User']);
        $this->teacher->assignRole('teacher');

        $this->finance = User::factory()->create(['name' => 'Finance User']);
        $this->finance->assignRole('finance');
    }

    /** 1: Global notification dropdown elements, RED unread badge, and 3D bell visual exist across user roles. */
    public function test_global_notification_dropdown_elements_and_red_badge_rendered_across_roles(): void
    {
        $rolesToTest = [
            'Super Admin' => [$this->superAdmin, route('super-admin.dashboard')],
            'Admin'       => [$this->admin, route('admin.dashboard')],
            'Teacher'     => [$this->teacher, route('teacher.question-banks.index')],
            'Finance'     => [$this->finance, route('finance.dashboard')],
        ];

        foreach ($rolesToTest as $roleName => [$user, $url]) {
            $response = $this->actingAs($user)->get($url);
            $response->assertStatus(200);

            // Verify Bell trigger button and ARIA attributes
            $response->assertSee('id="notifications-bell-btn"', false);
            $response->assertSee('aria-expanded="false"', false);
            $response->assertSee('aria-haspopup="true"', false);
            $response->assertSee('aria-controls="notifications-dropdown"', false);

            // Verify Dropdown container
            $response->assertSee('id="notifications-dropdown"', false);
            $response->assertSee('id="notifications-bell-container"', false);

            // Verify RED unread badge dot & header counter styling
            $response->assertSee('bg-rose-500', false);
            $response->assertSee('text-rose-300', false);

            // Verify 3D Bell SVG visual drop-shadow styling
            $response->assertSee('filter drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]', false);
        }
    }

    /** 2: Popover JS controller includes outside click, Escape key handling, and toggle/close methods. */
    public function test_popover_js_controller_includes_outside_click_and_escape_handling(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'));
        $response->assertStatus(200);

        // Verify JS popover functions exist
        $response->assertSee('window.toggleNotificationsDropdown', false);
        $response->assertSee('window.openNotificationsDropdown', false);
        $response->assertSee('window.closeNotificationsDropdown', false);

        // Verify Outside Click listener logic
        $response->assertSee('if (container && !container.contains(e.target))', false);

        // Verify Escape key listener logic
        $response->assertSee("e.key === 'Escape'", false);
    }
}
