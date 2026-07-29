<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCreationRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCreationApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_creating_student_is_immediately_active_and_can_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'John Student',
            'email' => 'student.john@icedu.org',
            'password' => 'password123',
            'role' => 'student',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $student = User::where('email', 'student.john@icedu.org')->firstOrFail();
        $this->assertEquals('active', $student->status);
        $this->assertTrue($student->hasRole('student'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'USER_CREATED',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ACCOUNT_ACTIVATED',
            'user_id' => $admin->id,
        ]);

        // Logout admin and verify Student can login immediately
        $this->post('/logout');

        $loginResponse = $this->post('/login', [
            'email' => 'student.john@icedu.org',
            'password' => 'password123',
        ]);
        $loginResponse->assertRedirect(route('candidate.portal'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_admin_creating_teacher_enters_pending_approval_and_login_is_denied(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Jane Teacher',
            'email' => 'teacher.jane@icedu.org',
            'password' => 'password123',
            'role' => 'teacher',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $teacher = User::where('email', 'teacher.jane@icedu.org')->firstOrFail();
        $this->assertEquals('pending_approval', $teacher->status);

        $this->assertDatabaseHas('user_creation_requests', [
            'user_id' => $teacher->id,
            'requested_by' => $admin->id,
            'requested_role' => 'teacher',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'APPROVAL_SUBMITTED',
            'user_id' => $admin->id,
        ]);

        // Logout admin and attempt login as pending teacher
        $this->post('/logout');

        $loginResponse = $this->post('/login', [
            'email' => 'teacher.jane@icedu.org',
            'password' => 'password123',
        ]);
        $loginResponse->assertSessionHasErrors(['email' => 'Your account is awaiting approval.']);
        $this->assertGuest();
    }

    public function test_super_admin_can_approve_staff_creation_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $financeUser = User::factory()->create(['status' => 'pending_approval']);
        $financeUser->assignRole('finance');

        $request = UserCreationRequest::create([
            'user_id' => $financeUser->id,
            'requested_by' => $admin->id,
            'requested_role' => 'finance',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.approvals.users.creation.approve', $request));
        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertEquals('approved', $request->fresh()->status);
        $this->assertEquals('active', $financeUser->fresh()->status);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'APPROVAL_APPROVED',
            'user_id' => $superAdmin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ACCOUNT_ACTIVATED',
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_super_admin_can_reject_staff_creation_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $adminUser = User::factory()->create(['status' => 'pending_approval']);
        $adminUser->assignRole('admin');

        $request = UserCreationRequest::create([
            'user_id' => $adminUser->id,
            'requested_by' => $admin->id,
            'requested_role' => 'admin',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.approvals.users.creation.reject', $request), [
            'reason' => 'Invalid administrative credentials provided.',
        ]);
        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertEquals('rejected', $request->fresh()->status);
        $this->assertEquals('inactive', $adminUser->fresh()->status);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'APPROVAL_REJECTED',
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_super_admin_direct_creation_is_immediately_active(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'Direct Teacher',
            'email' => 'direct.teacher@icedu.org',
            'password' => 'password123',
            'role' => 'teacher',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $teacher = User::where('email', 'direct.teacher@icedu.org')->firstOrFail();
        $this->assertEquals('active', $teacher->status);
    }
}
