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
        $response->assertSee('window.setNotifFilter', false);
        $response->assertSee('window.markAllNotificationsRead', false);

        // Verify Filter controls & Read All elements
        $response->assertSee('id="notif-filter-all"', false);
        $response->assertSee('id="notif-filter-unread"', false);
        $response->assertSee('id="notif-filter-read"', false);
        $response->assertSee('id="notif-read-all-btn"', false);

        // Verify Outside Click listener logic
        $response->assertSee('if (container && !container.contains(e.target))', false);

        // Verify Escape key listener logic
        $response->assertSee("e.key === 'Escape'", false);
    }

    /** 3: Notification read/unread state synchronization & Read All API integration. */
    public function test_notification_read_unread_synchronization_and_read_all(): void
    {
        // 1. Create 3 notifications for Super Admin
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            [
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'system_alert',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $this->superAdmin->id,
                'data'            => json_encode(['title' => 'Alert 1', 'message' => 'Message 1']),
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'system_alert',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $this->superAdmin->id,
                'data'            => json_encode(['title' => 'Alert 2', 'message' => 'Message 2']),
                'read_at'         => null,
                'created_at'      => now()->subMinute(),
                'updated_at'      => now()->subMinute(),
            ],
            [
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'type'            => 'system_alert',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $this->superAdmin->id,
                'data'            => json_encode(['title' => 'Alert 3', 'message' => 'Message 3']),
                'read_at'         => null,
                'created_at'      => now()->subMinutes(2),
                'updated_at'      => now()->subMinutes(2),
            ],
        ]);

        // Check initial feed
        $resFeed1 = $this->actingAs($this->superAdmin)->getJson(route('notifications.feed'));
        $resFeed1->assertOk();
        $resFeed1->assertJson(['success' => true, 'unread_count' => 3]);

        $firstNotifId = $resFeed1->json('data.0.id');

        // Mark 1 notification as read via JSON endpoint
        $resMarkRead = $this->actingAs($this->superAdmin)->postJson(route('notifications.read', $firstNotifId));
        $resMarkRead->assertOk();
        $resMarkRead->assertJson(['success' => true, 'unread_count' => 2]);

        // Verify updated feed returns unread_count = 2
        $resFeed2 = $this->actingAs($this->superAdmin)->getJson(route('notifications.feed'));
        $resFeed2->assertOk();
        $resFeed2->assertJson(['success' => true, 'unread_count' => 2]);

        // Mark all as read via JSON endpoint
        $resMarkAll = $this->actingAs($this->superAdmin)->postJson(route('notifications.read-all'));
        $resMarkAll->assertOk();
        $resMarkAll->assertJson(['success' => true, 'unread_count' => 0]);

        // Verify final feed unread_count = 0, but history records still exist (3 total items)
        $resFeed3 = $this->actingAs($this->superAdmin)->getJson(route('notifications.feed'));
        $resFeed3->assertOk();
        $resFeed3->assertJson(['success' => true, 'unread_count' => 0]);
        $this->assertCount(3, $resFeed3->json('data'));
    }
}
