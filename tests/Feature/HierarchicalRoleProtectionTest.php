<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchicalRoleProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_manage_all_roles(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $adminTarget = User::factory()->create();
        $adminTarget->assignRole('admin');

        // Super Admin updates Admin account
        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $adminTarget), [
            'name' => 'Updated Admin Name',
            'email' => $adminTarget->email,
            'role' => 'admin',
            'status' => 'active',
        ]);
        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $adminTarget->id, 'name' => 'Updated Admin Name']);
    }

    public function test_admin_cannot_update_super_admin_and_creates_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdminTarget = User::factory()->create();
        $superAdminTarget->assignRole('super-admin');

        $response = $this->actingAs($admin)->put(route('admin.users.update', $superAdminTarget), [
            'name' => 'Forged Edit Attempt',
            'email' => $superAdminTarget->email,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'FORBIDDEN_USER_MANAGEMENT',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_delete_super_admin_and_returns_403(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdminTarget = User::factory()->create();
        $superAdminTarget->assignRole('super-admin');

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $superAdminTarget));

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $superAdminTarget->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'FORBIDDEN_USER_MANAGEMENT',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_toggle_status_of_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdminTarget = User::factory()->create();
        $superAdminTarget->assignRole('super-admin');

        $response = $this->actingAs($admin)->post(route('admin.users.toggle-status', $superAdminTarget));

        $response->assertStatus(403);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'FORBIDDEN_USER_MANAGEMENT',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_reset_password_of_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdminTarget = User::factory()->create();
        $superAdminTarget->assignRole('super-admin');

        $response = $this->actingAs($admin)->post(route('admin.users.reset-password', $superAdminTarget), [
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'FORBIDDEN_USER_MANAGEMENT',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_create_super_admin_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Escalated Super Admin',
            'email' => 'escalated@icedu.org',
            'password' => 'password123',
            'role' => 'super-admin',
            'status' => 'active',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'escalated@icedu.org']);
    }

    public function test_self_protection_prevents_self_deletion_and_self_deactivation(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        // Self deletion attempt
        $delResponse = $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $superAdmin));
        $delResponse->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);

        // Self status toggle attempt
        $statusResponse = $this->actingAs($superAdmin)->post(route('admin.users.toggle-status', $superAdmin));
        $statusResponse->assertRedirect();
        $this->assertEquals('active', $superAdmin->fresh()->status);
    }

    public function test_teacher_and_finance_cannot_access_user_management(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $this->actingAs($teacher)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($finance)->get(route('admin.users.index'))->assertStatus(403);
    }
}
