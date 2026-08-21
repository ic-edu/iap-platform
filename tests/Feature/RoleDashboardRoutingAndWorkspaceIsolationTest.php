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
        \App\Modules\QuestionBank\Models\QuestionChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        $section = $test->sections()->firstOrFail();
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $question->id, 'order' => 1, 'points' => 10]);

        // 2. Teacher submits test for approval
        $this->actingAs($teacher)->post(route('admin.tests.submit', $test))->assertRedirect();
        $test->refresh();
        $this->assertEquals('pending', $test->status);

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

    /**
     * Requirement 1: Teacher with only pending Assessment:
     * - sees "ASSESSMENT REVIEW IN PROGRESS"
     * - sees "View Submitted Assessments"
     * - link targets admin.tests.index?status=pending_approval
     * - does NOT show "View Submitted Repositories"
     */
    public function test_teacher_with_only_pending_assessment_sees_assessment_spotlight(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        AssessmentTest::create([
            'title'       => 'SMK Assessment Test 1',
            'slug'        => 'smk-assessment-test-1',
            'test_type'   => 'general',
            'status'      => 'pending_approval',
            'created_by'  => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('⏳ ASSESSMENT REVIEW IN PROGRESS', false);
        $response->assertSee('Assessment Submitted for Review', false);
        $response->assertSee('You have 1 assessment(s) awaiting institutional review.', false);
        $response->assertSee('📋 View Submitted Assessments →', false);
        $response->assertSee(route('admin.tests.index', ['status' => 'pending_approval']), false);

        $response->assertDontSee('⏳ REPOSITORY REVIEW IN PROGRESS', false);
        $response->assertDontSee('📋 View Submitted Repositories →', false);
    }

    /**
     * Requirement 2: Teacher with only pending Question Bank:
     * - sees "REPOSITORY REVIEW IN PROGRESS"
     * - sees "View Submitted Repositories"
     * - link targets admin.question-banks.index?status=pending_approval
     * - does NOT show "View Submitted Assessments"
     */
    public function test_teacher_with_only_pending_question_bank_sees_repository_spotlight(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        QuestionBank::create([
            'title'      => 'SMK Question Bank 1',
            'slug'       => 'smk-qb-1',
            'test_type'  => 'general',
            'status'     => 'pending_approval',
            'created_by' => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('⏳ REPOSITORY REVIEW IN PROGRESS', false);
        $response->assertSee('Repository Submitted for Review', false);
        $response->assertSee('You have 1 repository bank(s) awaiting institutional review.', false);
        $response->assertSee('📋 View Submitted Repositories →', false);
        $response->assertSee(route('admin.question-banks.index', ['status' => 'pending_approval']), false);

        $response->assertDontSee('⏳ ASSESSMENT REVIEW IN PROGRESS', false);
        $response->assertDontSee('📋 View Submitted Assessments →', false);
    }

    /**
     * Requirement 3: Teacher with both pending:
     * - sees "INSTITUTIONAL REVIEW IN PROGRESS"
     * - sees both CTAs
     */
    public function test_teacher_with_both_pending_sees_dual_cta_spotlight(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        AssessmentTest::create([
            'title'       => 'SMK Assessment Test Dual',
            'slug'        => 'smk-assessment-test-dual',
            'test_type'   => 'general',
            'status'      => 'pending_approval',
            'created_by'  => $teacher->id,
        ]);

        QuestionBank::create([
            'title'      => 'SMK Question Bank Dual',
            'slug'       => 'smk-qb-dual',
            'test_type'  => 'general',
            'status'     => 'pending_approval',
            'created_by' => $teacher->id,
        ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('⏳ INSTITUTIONAL REVIEW IN PROGRESS', false);
        $response->assertSee('You have work requiring attention', false);
        $response->assertSee('You have 1 repository bank(s) and 1 assessment(s) awaiting institutional review.', false);
        $response->assertSee('📋 View Submitted Assessments →', false);
        $response->assertSee(route('admin.tests.index', ['status' => 'pending_approval']), false);
        $response->assertSee('📁 View Submitted Repositories →', false);
        $response->assertSee(route('admin.question-banks.index', ['status' => 'pending_approval']), false);
    }

    /**
     * Requirement 4: Teacher with neither pending:
     * - existing "all caught up" state remains intact.
     */
    public function test_teacher_with_neither_pending_sees_all_caught_up(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)
            ->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee("You're all caught up.", false);
        $response->assertDontSee('⏳ ASSESSMENT REVIEW IN PROGRESS', false);
        $response->assertDontSee('⏳ REPOSITORY REVIEW IN PROGRESS', false);
        $response->assertDontSee('⏳ INSTITUTIONAL REVIEW IN PROGRESS', false);
    }
}
