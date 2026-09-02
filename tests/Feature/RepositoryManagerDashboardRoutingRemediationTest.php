<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\AssessmentWorkflowService;
use App\Services\NavigationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryManagerDashboardRoutingRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $repoManager;
    protected User $adminUser;
    protected User $teacher;
    protected User $finance;
    protected User $multiRoleUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin User',
            'email'  => 'superadmin_test@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->repoManager = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance Test',
            'email'  => 'rm_routing_test@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->adminUser = User::factory()->create([
            'name'   => 'Operations Admin User',
            'email'  => 'ops_admin_test@icedu.org',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher User',
            'email'  => 'teacher_test@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->finance = User::factory()->create([
            'name'   => 'Finance Officer',
            'email'  => 'finance_test@icedu.org',
            'status' => 'active',
        ]);
        $this->finance->assignRole('finance');

        $this->multiRoleUser = User::factory()->create([
            'name'   => 'Multi-Role Staff',
            'email'  => 'multirole_test@icedu.org',
            'status' => 'active',
        ]);
        $this->multiRoleUser->assignRole(['repository-manager', 'super-admin']);
    }

    /**
     * TEST 01: Repository Manager opening publication page: ← Dashboard resolves to admin.repository-manager.dashboard.
     */
    public function test_01_rm_publication_page_breadcrumb_resolves_to_rm_command_center(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments'));
        $res->assertStatus(200);
        $res->assertSee('href="' . route('admin.repository-manager.dashboard') . '" class="breadcrumb-link"', false);
        $res->assertDontSee('href="' . route('admin.dashboard') . '" class="breadcrumb-link"', false);
    }

    /**
     * TEST 02: RM directly requesting /admin/dashboard is safely redirected to RM Command Center.
     */
    public function test_02_rm_requesting_admin_dashboard_redirects_to_command_center(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.dashboard'));
        $res->assertRedirect(route('admin.repository-manager.dashboard'));
    }

    /**
     * TEST 03: Registration Admin / Operations: /admin/dashboard still renders Operational Dashboard.
     */
    public function test_03_admin_user_renders_operational_dashboard(): void
    {
        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $res->assertStatus(200);
        $res->assertSee('Operational Dashboard');
        $res->assertSee('Total Registered Candidates');
    }

    /**
     * TEST 04: Teacher dashboard remains unchanged.
     */
    public function test_04_teacher_dashboard_remains_unchanged(): void
    {
        $route = NavigationService::getDashboardRouteForUser($this->teacher);
        $this->assertEquals(route('teacher.dashboard'), $route);

        $res = $this->actingAs($this->teacher)->get(route('dashboard'));
        $res->assertRedirect(route('teacher.dashboard'));
    }

    /**
     * TEST 05: Finance dashboard remains unchanged.
     */
    public function test_05_finance_dashboard_remains_unchanged(): void
    {
        $route = NavigationService::getDashboardRouteForUser($this->finance);
        $this->assertEquals(route('finance.dashboard'), $route);

        $res = $this->actingAs($this->finance)->get(route('dashboard'));
        $res->assertRedirect(route('finance.dashboard'));
    }

    /**
     * TEST 06: Super Admin dashboard remains unchanged.
     */
    public function test_06_super_admin_dashboard_remains_unchanged(): void
    {
        $route = NavigationService::getDashboardRouteForUser($this->superAdmin);
        $this->assertEquals(route('super-admin.dashboard'), $route);

        $res = $this->actingAs($this->superAdmin)->get(route('dashboard'));
        $res->assertRedirect(route('super-admin.dashboard'));
    }

    /**
     * TEST 07: Multi-role privileged user follows existing role precedence correctly.
     */
    public function test_07_multirole_privileged_user_follows_role_precedence(): void
    {
        // super-admin has precedence over repository-manager
        $route = NavigationService::getDashboardRouteForUser($this->multiRoleUser);
        $this->assertEquals(route('super-admin.dashboard'), $route);

        // Multi-role RM + admin can access /admin/dashboard without redirect
        $rmAdminUser = User::factory()->create(['status' => 'active']);
        $rmAdminUser->assignRole(['repository-manager', 'admin']);

        $res = $this->actingAs($rmAdminUser)->get(route('admin.dashboard'));
        $res->assertStatus(200);
        $res->assertSee('Operational Dashboard');
    }

    /**
     * TEST 08: RM-only user cannot accidentally enter Operational Dashboard through generic dashboard navigation.
     */
    public function test_08_rm_only_user_cannot_enter_operational_dashboard_via_generic_routes(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('dashboard'));
        $res->assertRedirect(route('admin.repository-manager.dashboard'));

        $resDirect = $this->actingAs($this->repoManager)->get('/admin/dashboard');
        $resDirect->assertRedirect(route('admin.repository-manager.dashboard'));
    }

    /**
     * TEST 09: Assessment Publication Queue breadcrumb is role-aware.
     */
    public function test_09_assessment_publication_queue_breadcrumb_is_role_aware(): void
    {
        // For RM
        $resRM = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments'));
        $resRM->assertSee('href="' . route('admin.repository-manager.dashboard') . '" class="breadcrumb-link"', false);

        // For Super Admin
        $resSA = $this->actingAs($this->superAdmin)->get(route('admin.publications.assessments'));
        $resSA->assertSee('href="' . route('super-admin.dashboard') . '" class="breadcrumb-link"', false);
    }

    /**
     * TEST 10: Question Bank publication sister view is role-aware.
     */
    public function test_10_question_bank_publication_view_is_role_aware(): void
    {
        $resRM = $this->actingAs($this->repoManager)->get(route('admin.publications.question-banks'));
        $resRM->assertStatus(200);
        $resRM->assertSee('href="' . route('admin.repository-manager.dashboard') . '" class="breadcrumb-link"', false);
    }

    /**
     * TEST 11: Published Registry breadcrumb is role-aware.
     */
    public function test_11_published_registry_breadcrumb_is_role_aware(): void
    {
        $resRM = $this->actingAs($this->repoManager)->get(route('admin.publications.published'));
        $resRM->assertStatus(200);
        $resRM->assertSee('href="' . route('admin.repository-manager.dashboard') . '" class="breadcrumb-link"', false);
    }

    /**
     * TEST 12: Archive Requests breadcrumb is role-aware.
     */
    public function test_12_archive_requests_breadcrumb_is_role_aware(): void
    {
        $resRM = $this->actingAs($this->repoManager)->get(route('admin.publications.archive-requests'));
        $resRM->assertStatus(200);
        $resRM->assertSee('href="' . route('admin.repository-manager.dashboard') . '" class="breadcrumb-link"', false);
    }

    /**
     * TEST 13: Assessment Publication Queue no longer contains legacy copy: "When Super Admin approves...".
     */
    public function test_13_assessment_publication_queue_no_legacy_super_admin_copy(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments'));
        $res->assertStatus(200);
        $res->assertDontSee('When Super Admin approves an Assessment');
        $res->assertSee('Approved assessments will appear here when they are ready for publication.');
    }

    /**
     * TEST 14: Replacement wording does not imply SA is required for routine RM publication.
     */
    public function test_14_question_bank_queue_no_legacy_super_admin_copy(): void
    {
        $res = $this->actingAs($this->repoManager)->get(route('admin.publications.question-banks'));
        $res->assertStatus(200);
        $res->assertDontSee('Super Admin approves it, it will appear here');
        $res->assertSee('Approved question banks will appear here when they are ready for publication.');
    }

    /**
     * TEST 15: pending_approval assessment remains in Assessment Approval KPI.
     */
    public function test_15_pending_approval_assessment_remains_in_approval_kpi(): void
    {
        $isolatedTest = AssessmentTest::create([
            'title'            => 'Isolated Pending Test',
            'slug'             => 'isolated-pending-test',
            'test_type'        => 'toeic',
            'status'           => 'pending_approval',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $metrics = app(AssessmentWorkflowService::class)->getRepositoryManagerMetrics();
        $this->assertGreaterThanOrEqual(1, $metrics['pendingAssessmentsCount']);
    }

    /**
     * TEST 16: pending_approval assessment does NOT appear Ready to Publish.
     */
    public function test_16_pending_approval_assessment_not_in_ready_to_publish(): void
    {
        $isolatedTest = AssessmentTest::create([
            'title'            => 'Isolated Pending Test 2',
            'slug'             => 'isolated-pending-test-2',
            'test_type'        => 'toeic',
            'status'           => 'pending_approval',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments', ['status' => 'approved']));
        $res->assertDontSee($isolatedTest->title);
    }

    /**
     * TEST 17: approved + false appears Ready to Publish.
     */
    public function test_17_approved_false_appears_ready_to_publish(): void
    {
        $isolatedApproved = AssessmentTest::create([
            'title'            => 'Isolated Ready Test',
            'slug'             => 'isolated-ready-test',
            'test_type'        => 'toeic',
            'status'           => 'approved',
            'is_published'     => false,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.publications.assessments', ['status' => 'approved']));
        $res->assertStatus(200);
        $res->assertSee($isolatedApproved->title);
    }

    /**
     * TEST 18: published + true is handled as published/live.
     */
    public function test_18_published_true_is_handled_as_published_live(): void
    {
        $isolatedLive = AssessmentTest::create([
            'title'            => 'Isolated Live Test',
            'slug'             => 'isolated-live-test',
            'test_type'        => 'toeic',
            'status'           => 'published',
            'is_published'     => true,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $this->assertTrue($isolatedLive->isPublished());

        $res = $this->actingAs($this->repoManager)->get(route('admin.publications.published'));
        $res->assertStatus(200);
        $res->assertSee($isolatedLive->title);
    }

    /**
     * TEST 19: RM remains authorized to publish approved assessment.
     */
    public function test_19_rm_remains_authorized_to_publish(): void
    {
        $isolatedTest = AssessmentTest::create([
            'title'            => 'Isolated Publishable Test',
            'slug'             => 'isolated-publishable-test',
            'test_type'        => 'toeic',
            'status'           => 'approved',
            'is_published'     => false,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $res = $this->actingAs($this->repoManager)->post(route('admin.publications.assessments.publish', $isolatedTest->id));
        $res->assertRedirect();

        $isolatedTest->refresh();
        $this->assertEquals('published', $isolatedTest->status);
        $this->assertTrue((bool)$isolatedTest->is_published);
    }

    /**
     * TEST 20: RA remains unauthorized to publish.
     */
    public function test_20_ra_remains_unauthorized_to_publish(): void
    {
        $isolatedTest = AssessmentTest::create([
            'title'            => 'Isolated RA Blocked Test',
            'slug'             => 'isolated-ra-blocked-test',
            'test_type'        => 'toeic',
            'status'           => 'approved',
            'is_published'     => false,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
        ]);

        $res = $this->actingAs($this->adminUser)->post(route('admin.publications.assessments.publish', $isolatedTest->id));
        $res->assertStatus(403);
    }
}
