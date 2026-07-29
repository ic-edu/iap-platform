<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCreationRequest;
use App\Models\UserDeletionRequest;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\ApprovalEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDashboardApprovalSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_approval_engine_aggregates_all_pending_categories_accurately(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create 2 pending question banks
        QuestionBank::create(['title' => 'QB 1', 'slug' => 'qb-1', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'pending_approval']);
        QuestionBank::create(['title' => 'QB 2', 'slug' => 'qb-2', 'created_by' => $teacher->id, 'test_type' => 'toefl', 'status' => 'pending_approval']);

        // Create 1 pending test
        AssessmentTest::create(['title' => 'Test 1', 'slug' => 'test-1', 'created_by' => $teacher->id, 'test_type' => 'ielts', 'status' => 'pending_approval']);

        // Create 1 pending staff creation request
        $staff = User::factory()->create(['status' => 'pending_approval']);
        UserCreationRequest::create(['user_id' => $staff->id, 'requested_by' => $admin->id, 'requested_role' => 'teacher', 'status' => 'pending']);

        // Create 1 pending user deletion request
        $targetUser = User::factory()->create(['status' => 'pending_delete_approval']);
        UserDeletionRequest::create(['user_id' => $targetUser->id, 'requested_by' => $admin->id, 'reason' => 'Duplicate account', 'status' => 'pending']);

        $pendingCounts = ApprovalEngine::getPendingCounts();
        $totalPending = ApprovalEngine::getTotalPendingCount();

        $this::assertEquals(2, $pendingCounts['question_banks']);
        $this::assertEquals(1, $pendingCounts['tests']);
        $this::assertEquals(1, $pendingCounts['user_creations']);
        $this::assertEquals(1, $pendingCounts['user_deletions']);
        $this::assertEquals(5, $totalPending);
    }

    public function test_executive_dashboard_displays_synchronized_pending_approvals_and_published_banks(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        // Question Banks in various statuses
        QuestionBank::create(['title' => 'Draft QB', 'slug' => 'draft-qb', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'draft']);
        QuestionBank::create(['title' => 'Pending QB', 'slug' => 'pending-qb', 'created_by' => $teacher->id, 'test_type' => 'toefl', 'status' => 'pending_approval']);
        QuestionBank::create(['title' => 'Published QB 1', 'slug' => 'pub-qb-1', 'created_by' => $teacher->id, 'test_type' => 'ielts', 'status' => 'published', 'is_published' => true]);
        QuestionBank::create(['title' => 'Published QB 2', 'slug' => 'pub-qb-2', 'created_by' => $teacher->id, 'test_type' => 'general', 'status' => 'published', 'is_published' => true]);

        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('publishedQuestionBanksCount', 2);
        $response->assertViewHas('pendingApprovalsCount', 1);
        $response->assertSee(route('admin.approvals.index'));
    }

    public function test_dashboard_updates_immediately_upon_approval_action(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $bank = QuestionBank::create(['title' => 'Pending Approval Bank', 'slug' => 'pending-approval-bank', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'pending_approval']);

        // Check initial dashboard count = 1
        $res1 = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $res1->assertViewHas('pendingApprovalsCount', 1);

        // Approve bank
        $this->actingAs($superAdmin)->post(route('admin.approvals.question-banks.approve', $bank->id));

        // Check updated dashboard count = 0
        $res2 = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $res2->assertViewHas('pendingApprovalsCount', 0);
    }
}
