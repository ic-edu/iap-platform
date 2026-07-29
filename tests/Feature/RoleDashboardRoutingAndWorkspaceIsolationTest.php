<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
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

    public function test_super_admin_statistics_count_only_own_role_without_overlapping(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $student = User::factory()->create();
        $student->assignRole('student');

        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('superAdminsCount', 1);
        $response->assertViewHas('adminsCount', 1);
        $response->assertViewHas('teachersCount', 1);
        $response->assertViewHas('financeCount', 1);
        $response->assertViewHas('studentsCount', 1);
    }

    public function test_super_admin_cannot_create_questions_or_tests(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $bank = QuestionBank::create(['title' => 'Bank X', 'slug' => 'bank-x', 'test_type' => 'toefl', 'created_by' => $superAdmin->id]);

        // Super Admin cannot create question
        $qResponse = $this->actingAs($superAdmin)->post(route('admin.question-banks.store-question', $bank), [
            'prompt' => 'Super admin prompt',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'points' => 5,
        ]);
        $qResponse->assertStatus(403);

        // Super Admin cannot create test
        $tResponse = $this->actingAs($superAdmin)->post(route('admin.tests.store'), [
            'title' => 'Super Admin Test',
            'test_type' => 'toefl',
            'duration_minutes' => 60,
            'pass_score' => 50,
        ]);
        $tResponse->assertStatus(403);
    }

    public function test_approval_workflow_lifecycle_draft_submit_approve_publish(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        // 1. Teacher creates Draft Test
        $this->actingAs($teacher)->post(route('admin.tests.store'), [
            'title' => 'TOEFL iBT Comprehensive',
            'test_type' => 'toefl',
            'duration_minutes' => 120,
            'pass_score' => 70,
        ])->assertRedirect(route('admin.tests.index'));

        $test = AssessmentTest::where('title', 'TOEFL iBT Comprehensive')->firstOrFail();
        $this->assertEquals('draft', $test->status);
        $this->assertFalse((bool) $test->is_published);

        // Admin CANNOT publish draft test (Must be approved first)
        $this->actingAs($admin)->post(route('admin.tests.publish', $test))->assertStatus(403);

        // Attach question to test section so validator passes upon publication
        $bank = QuestionBank::create(['title' => 'Pool A', 'slug' => 'pool-a', 'test_type' => 'toefl', 'created_by' => $teacher->id]);
        $question = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Item 1', 'question_type' => 'multiple_choice', 'difficulty' => 'easy', 'points' => 10]);
        $section = $test->sections()->firstOrFail();
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $question->id, 'order' => 1, 'points' => 10]);

        // 2. Teacher submits test for approval
        $this->actingAs($teacher)->post(route('admin.tests.submit-approval', $test))->assertRedirect(route('admin.tests.index'));
        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);

        // Admin CANNOT approve test (Only Super Admin can approve)
        $this->actingAs($admin)->post(route('admin.approvals.approve', $test))->assertStatus(403);

        // 3. Super Admin approves test
        $this->actingAs($superAdmin)->post(route('admin.approvals.approve', $test))->assertRedirect(route('admin.approvals.index'));
        $test->refresh();
        $this->assertEquals('approved', $test->status);

        // 4. Admin publishes approved test
        $this->actingAs($admin)->post(route('admin.tests.publish', $test))->assertRedirect(route('admin.tests.index'));
        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue((bool) $test->is_published);
    }

    public function test_admin_cannot_access_super_admin_governance_modules(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.monitoring.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('admin.approvals.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertStatus(403);
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertStatus(403);
    }

    public function test_finance_role_access_isolation(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $this->actingAs($finance)->get(route('finance.dashboard'))->assertOk();
        $this->actingAs($finance)->get(route('admin.question-banks.index'))->assertStatus(403);
        $this->actingAs($finance)->get(route('admin.tests.index'))->assertStatus(403);
        $this->actingAs($finance)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($finance)->get(route('admin.monitoring.index'))->assertStatus(403);
    }
}
