<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDeletionRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDeletionApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_cannot_directly_delete_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $student = User::factory()->create();
        $student->assignRole('student');

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $student));
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'deleted_at' => null]);
    }

    public function test_admin_can_submit_user_deletion_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $response = $this->actingAs($admin)->post(route('admin.users.request-delete', $teacher), [
            'reason' => 'Teacher account deactivated due to contract expiration.',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('user_deletion_requests', [
            'user_id' => $teacher->id,
            'requested_by' => $admin->id,
            'status' => 'pending',
            'reason' => 'Teacher account deactivated due to contract expiration.',
        ]);

        $this->assertEquals('pending_delete_approval', $teacher->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'DELETE_REQUEST_SUBMITTED',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_request_deletion_of_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($admin)->post(route('admin.users.request-delete', $superAdmin), [
            'reason' => 'Malicious request attempt.',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('user_deletion_requests', ['user_id' => $superAdmin->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'FORBIDDEN_USER_MANAGEMENT',
            'user_id' => $admin->id,
        ]);
    }

    public function test_prevent_duplicate_deletion_requests(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $student = User::factory()->create();
        $student->assignRole('student');

        UserDeletionRequest::create([
            'user_id' => $student->id,
            'requested_by' => $admin->id,
            'reason' => 'Existing request',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.request-delete', $student), [
            'reason' => 'Duplicate request',
        ]);

        $response->assertRedirect();
        $this->assertEquals(1, UserDeletionRequest::where('user_id', $student->id)->count());
    }

    public function test_super_admin_can_approve_user_deletion_and_soft_delete(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $student = User::factory()->create();
        $student->assignRole('student');

        $request = UserDeletionRequest::create([
            'user_id' => $student->id,
            'requested_by' => $admin->id,
            'reason' => 'Requested for purge.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.approvals.users.approve', $request));
        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertEquals('approved', $request->fresh()->status);
        $this->assertSoftDeleted('users', ['id' => $student->id]);
        $this->assertEquals('deleted', User::withTrashed()->find($student->id)->status);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'DELETE_REQUEST_APPROVED',
            'user_id' => $superAdmin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'USER_SOFT_DELETED',
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_super_admin_can_reject_user_deletion(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $student = User::factory()->create();
        $student->assignRole('student');

        $request = UserDeletionRequest::create([
            'user_id' => $student->id,
            'requested_by' => $admin->id,
            'reason' => 'Erroneous deletion request.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.approvals.users.reject', $request), [
            'reason' => 'User still has active course enrollments.',
        ]);
        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertEquals('rejected', $request->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'deleted_at' => null, 'status' => 'active']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'DELETE_REQUEST_REJECTED',
            'user_id' => $superAdmin->id,
        ]);
    }
}
