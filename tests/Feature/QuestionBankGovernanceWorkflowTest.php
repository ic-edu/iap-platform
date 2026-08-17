<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_question_bank_edit_persists_data_correctly_to_database(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $bank = QuestionBank::create([
            'title' => 'Initial Title',
            'slug' => 'initial-title',
            'created_by' => $teacher->id,
            'test_type' => 'general',
            'description' => 'Initial Description',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($teacher)->put(route('admin.question-banks.update', $bank->id), [
            'title' => 'Updated Title Value',
            'test_type' => 'toeic',
            'description' => 'Updated Description Value',
        ]);

        $response->assertRedirect(route('admin.question-banks.show', $bank->id));

        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'title' => 'Updated Title Value',
            'description' => 'Updated Description Value',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'QUESTION_BANK_EDITED',
            'user_id' => $teacher->id,
        ]);
    }

    public function test_teacher_dashboard_statistics_returns_independent_counters(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        QuestionBank::create(['title' => 'Draft Bank', 'slug' => 'draft-b', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'draft']);
        QuestionBank::create(['title' => 'Pending Bank', 'slug' => 'pending-b', 'created_by' => $teacher->id, 'test_type' => 'toefl', 'status' => 'pending_approval']);
        QuestionBank::create(['title' => 'Published Bank', 'slug' => 'pub-b', 'created_by' => $teacher->id, 'test_type' => 'ielts', 'status' => 'published', 'is_published' => true]);

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalQuestionBanks', 3);
        $response->assertViewHas('draftQuestionBanks', 1);
        $response->assertViewHas('pendingApprovalQuestionBanks', 1);
        $response->assertViewHas('publishedQuestionBanks', 1);
    }

    public function test_full_question_bank_governance_workflow_and_role_restrictions(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $repoManager = User::factory()->create();
        $repoManager->assignRole('repository-manager');

        // 1. Teacher creates draft
        $bank = QuestionBank::create([
            'title' => 'IELTS Listening Bank',
            'slug' => 'ielts-listening-bank',
            'created_by' => $teacher->id,
            'test_type' => 'ielts',
            'status' => 'draft',
        ]);

        // 2. Teacher submits for approval
        $this->actingAs($teacher)->post(route('admin.question-banks.submit', $bank->id));
        $this->assertEquals('pending_approval', $bank->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'QUESTION_BANK_SUBMITTED']);

        // 3. Teacher MUST NOT approve or publish
        $this->actingAs($teacher)->post(route('admin.approvals.question-banks.approve', $bank->id))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.question-banks.publish', $bank->id))->assertForbidden();

        // 4. Admin MUST NOT approve or publish
        $this->actingAs($admin)->post(route('admin.approvals.question-banks.approve', $bank->id))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.question-banks.publish', $bank->id))->assertForbidden();

        // 5. Super Admin approves Question Bank
        $this->actingAs($superAdmin)->post(route('admin.approvals.question-banks.approve', $bank->id));
        $this->assertEquals('approved', $bank->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'QUESTION_BANK_APPROVED']);

        // 6. Super Admin MUST NOT publish directly
        $this->actingAs($superAdmin)->post(route('admin.question-banks.publish', $bank->id))->assertForbidden();

        // 7. Repository Manager publishes approved Question Bank
        $this->actingAs($repoManager)->post(route('admin.question-banks.publish', $bank->id));
        $this->assertEquals('published', $bank->fresh()->status);
        $this->assertTrue((bool) $bank->fresh()->is_published);
        $this->assertDatabaseHas('activity_logs', ['action' => 'QUESTION_BANK_PUBLISHED']);

        // 8. Repository Manager unpublishes Question Bank
        $this->actingAs($repoManager)->post(route('admin.question-banks.unpublish', $bank->id));
        $this->assertEquals('approved', $bank->fresh()->status);
        $this->assertFalse((bool) $bank->fresh()->is_published);
        $this->assertDatabaseHas('activity_logs', ['action' => 'QUESTION_BANK_UNPUBLISHED']);
    }

    public function test_super_admin_can_reject_question_bank(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $bank = QuestionBank::create([
            'title' => 'TOEFL Speaking Bank',
            'slug' => 'toefl-speaking-bank',
            'created_by' => $teacher->id,
            'test_type' => 'toefl',
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.approvals.question-banks.reject', $bank->id), [
            'reason' => 'Audio files missing for speaking section.',
        ]);

        $response->assertRedirect(route('admin.approvals.index'));
        $this->assertEquals('rejected', $bank->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'QUESTION_BANK_REJECTED']);
    }

    public function test_approval_center_displays_pending_question_banks(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $bank = QuestionBank::create([
            'title' => 'Pending Audit Bank',
            'slug' => 'pending-audit-bank',
            'created_by' => $teacher->id,
            'test_type' => 'toeic',
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.approvals.question-banks'));

        $response->assertOk();
        $response->assertSee('Pending Audit Bank');
    }
}
