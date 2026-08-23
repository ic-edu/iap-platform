<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegularAdminThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');
    }

    public function test_app_css_contains_semantic_role_badge_contracts_for_both_themes(): void
    {
        $cssContent = file_get_contents(resource_path('css/app.css'));

        // Verify base role badge class
        $this->assertStringContainsString('.role-badge', $cssContent);

        // Verify all 6 role variants exist in CSS
        $this->assertStringContainsString('.role-badge--super-admin', $cssContent);
        $this->assertStringContainsString('.role-badge--admin', $cssContent);
        $this->assertStringContainsString('.role-badge--teacher', $cssContent);
        $this->assertStringContainsString('.role-badge--finance', $cssContent);
        $this->assertStringContainsString('.role-badge--student', $cssContent);
        $this->assertStringContainsString('.role-badge--repository-manager', $cssContent);

        // Verify dark mode overrides exist for all role variants
        $this->assertStringContainsString('html.dark .role-badge--super-admin', $cssContent);
        $this->assertStringContainsString('html.dark .role-badge--admin', $cssContent);
        $this->assertStringContainsString('html.dark .role-badge--teacher', $cssContent);
        $this->assertStringContainsString('html.dark .role-badge--finance', $cssContent);
        $this->assertStringContainsString('html.dark .role-badge--student', $cssContent);
        $this->assertStringContainsString('html.dark .role-badge--repository-manager', $cssContent);
    }

    public function test_app_css_contains_candidate_status_semantic_classes_for_both_themes(): void
    {
        $cssContent = file_get_contents(resource_path('css/app.css'));

        // Base candidate status card & items
        $this->assertStringContainsString('.candidate-status-card', $cssContent);
        $this->assertStringContainsString('.candidate-status-item', $cssContent);
        $this->assertStringContainsString('.candidate-status-dot', $cssContent);
        $this->assertStringContainsString('.candidate-status-count', $cssContent);

        // Specific status items (Active, In-Progress, Passed, Failed)
        $this->assertStringContainsString('.candidate-status-item--active', $cssContent);
        $this->assertStringContainsString('.candidate-status-item--in-progress', $cssContent);
        $this->assertStringContainsString('.candidate-status-item--passed', $cssContent);
        $this->assertStringContainsString('.candidate-status-item--failed', $cssContent);

        // Dark theme overrides
        $this->assertStringContainsString('html.dark .candidate-status-item--active', $cssContent);
        $this->assertStringContainsString('html.dark .candidate-status-item--in-progress', $cssContent);
        $this->assertStringContainsString('html.dark .candidate-status-item--passed', $cssContent);
        $this->assertStringContainsString('html.dark .candidate-status-item--failed', $cssContent);
    }

    public function test_role_badge_blade_component_renders_correct_semantic_classes(): void
    {
        $view = $this->blade('<x-role-badge role="super-admin" />');
        $view->assertSee('role-badge');
        $view->assertSee('role-badge--super-admin');
        $view->assertSee('SUPER ADMIN');

        $viewStudent = $this->blade('<x-role-badge role="student" />');
        $viewStudent->assertSee('role-badge--student');
        $viewStudent->assertSee('STUDENT');

        $viewRepo = $this->blade('<x-role-badge role="repository-manager" />');
        $viewRepo->assertSee('role-badge--repository-manager');
        $viewRepo->assertSee('REPOSITORY MANAGER');

        $viewTeacher = $this->blade('<x-role-badge role="teacher" />');
        $viewTeacher->assertSee('role-badge--teacher');
        $viewTeacher->assertSee('TEACHER');

        $viewFinance = $this->blade('<x-role-badge role="finance" />');
        $viewFinance->assertSee('role-badge--finance');
        $viewFinance->assertSee('FINANCE');

        $viewAdmin = $this->blade('<x-role-badge role="admin" />');
        $viewAdmin->assertSee('role-badge--admin');
        $viewAdmin->assertSee('ADMIN');
    }

    public function test_regular_admin_user_management_renders_role_badges_properly(): void
    {
        $studentUser = User::factory()->create(['name' => 'John Candidate']);
        $studentUser->assignRole('student');

        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));
        $response->assertStatus(200);
        $response->assertSee('role-badge');
        $response->assertSee('role-badge--student');
    }

    public function test_regular_admin_dashboard_renders_operational_kpis_and_links(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Operational Dashboard');
        $response->assertSee('text-indigo-600 dark:text-indigo-400', false);
        $response->assertSee('text-emerald-600 dark:text-emerald-400', false);
    }

    public function test_super_admin_dashboard_renders_role_badges_and_responsive_kpis(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/super-admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('role-badge');
        $response->assertSee('text-purple-600 dark:text-purple-400', false);
        $response->assertSee('text-emerald-600 dark:text-emerald-400', false);
    }

    public function test_operational_reporting_view_renders_candidate_status_semantics(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reporting.index'));
        $response->assertStatus(200);
        $response->assertSee('candidate-status-card');
        $response->assertSee('candidate-status-item--active');
        $response->assertSee('candidate-status-item--in-progress');
        $response->assertSee('candidate-status-item--passed');
        $response->assertSee('candidate-status-item--failed');
    }

    public function test_approval_views_render_standardized_role_badges(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.staff-creations'));
        $response->assertStatus(200);

        $responseDeletions = $this->actingAs($this->superAdmin)->get(route('admin.approvals.user-deletions'));
        $responseDeletions->assertStatus(200);
    }

    public function test_app_css_contains_semantic_ra_status_badge_contracts_for_both_themes(): void
    {
        $cssContent = file_get_contents(resource_path('css/app.css'));

        // Verify base ra status badge class
        $this->assertStringContainsString('.ra-status-badge', $cssContent);

        // Verify all status variants exist in CSS
        $this->assertStringContainsString('.ra-status--ready-for-assignment', $cssContent);
        $this->assertStringContainsString('.ra-status--assigned', $cssContent);
        $this->assertStringContainsString('.ra-status--placement-required', $cssContent);
        $this->assertStringContainsString('.ra-status--waiting-review', $cssContent);
        $this->assertStringContainsString('.ra-status--awaiting-assignment', $cssContent);
        $this->assertStringContainsString('.ra-status--completed', $cssContent);
        $this->assertStringContainsString('.ra-status--rejected', $cssContent);
        $this->assertStringContainsString('.ra-status--waived', $cssContent);

        // Verify dark theme overrides exist for all status variants
        $this->assertStringContainsString('html.dark .ra-status--ready-for-assignment', $cssContent);
        $this->assertStringContainsString('html.dark .ra-status--assigned', $cssContent);
        $this->assertStringContainsString('html.dark .ra-status--placement-required', $cssContent);
        $this->assertStringContainsString('html.dark .ra-status--waiting-review', $cssContent);
        $this->assertStringContainsString('html.dark .ra-status--awaiting-assignment', $cssContent);
        $this->assertStringContainsString('html.dark .ra-status--completed', $cssContent);
        $this->assertStringContainsString('html.dark .ra-status--rejected', $cssContent);
        $this->assertStringContainsString('html.dark .ra-status--waived', $cssContent);
    }

    public function test_status_badge_blade_component_renders_correct_semantic_classes(): void
    {
        $viewReady = $this->blade('<x-status-badge status="ready_for_assignment" />');
        $viewReady->assertSee('ra-status-badge');
        $viewReady->assertSee('ra-status--ready-for-assignment');
        $viewReady->assertSee('READY FOR ASSIGNMENT');

        $viewAssigned = $this->blade('<x-status-badge status="assigned" />');
        $viewAssigned->assertSee('ra-status--assigned');
        $viewAssigned->assertSee('ASSIGNED');

        $viewPlacement = $this->blade('<x-status-badge status="placement_required" />');
        $viewPlacement->assertSee('ra-status--placement-required');
        $viewPlacement->assertSee('PLACEMENT REQUIRED');

        $viewReview = $this->blade('<x-status-badge status="waiting_review" />');
        $viewReview->assertSee('ra-status--waiting-review');
        $viewReview->assertSee('WAITING REVIEW');

        $viewAwaiting = $this->blade('<x-status-badge status="awaiting_assignment" />');
        $viewAwaiting->assertSee('ra-status--awaiting-assignment');
        $viewAwaiting->assertSee('AWAITING ASSIGNMENT');
    }

    public function test_student_applications_view_renders_semantic_status_badges(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.academic-operations.applications'));
        $response->assertStatus(200);
        $response->assertSee('ra-status-badge');
        $response->assertSee('ra-status--ready-for-assignment');
        $response->assertSee('ra-status--placement-required');
        $response->assertSee('ra-status--assigned');
        $response->assertSee('ra-status--waiting-review');
    }

    public function test_operational_dashboard_candidates_requiring_action_renders_readable_badge(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('ra-status--awaiting-assignment');
        $response->assertSee('Awaiting Assignment');
    }
}
