<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_users_list_with_search_and_role_filters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $teacher = User::factory()->create(['name' => 'Professor Smith', 'email' => 'smith@icedu.org']);
        $teacher->assignRole('teacher');

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Professor', 'role' => 'teacher']));

        $response->assertOk();
        $response->assertSee('Professor Smith');
    }

    public function test_admin_can_create_new_user_with_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'John Finance',
                'email' => 'finance.john@icedu.org',
                'password' => 'password123',
                'role' => 'finance',
                'status' => 'active',
                'phone_number' => '+1234567890',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'finance.john@icedu.org']);

        $user = User::where('email', 'finance.john@icedu.org')->first();
        $this->assertTrue($user->hasRole('finance'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.created',
        ]);
    }

    public function test_admin_can_update_user_details_and_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $target = User::factory()->create(['name' => 'Old Name', 'email' => 'target@icedu.org']);
        $target->assignRole('student');

        $response = $this
            ->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => 'New Name',
                'email' => 'target@icedu.org',
                'role' => 'teacher',
                'status' => 'active',
                'phone_number' => '+9876543210',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'New Name',
            'phone_number' => '+9876543210',
        ]);

        $target->refresh();
        $this->assertTrue($target->hasRole('teacher'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.updated',
        ]);
    }

    public function test_admin_can_reset_user_password(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $target = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.users.reset-password', $target), [
                'password' => 'new-secret-pass-123',
            ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.password_reset',
        ]);
    }

    public function test_admin_can_toggle_user_status_and_inactive_user_cannot_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $user = User::factory()->create(['status' => 'active', 'email' => 'blocked@icedu.org', 'password' => bcrypt('password123')]);
        $user->assignRole('student');

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.users.toggle-status', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'inactive']);

        // Logout admin before attempting guest login as inactive user
        Auth::logout();

        $loginResponse = $this->post('/login', [
            'email' => 'blocked@icedu.org',
            'password' => 'password123',
        ]);

        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_other_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $target = User::factory()->create();
        $target->assignRole('student');

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.deleted',
        ]);
    }
}
