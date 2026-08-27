<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $teacher;
    protected User $finance;
    protected User $student;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');

        $this->teacher = User::factory()->create();
        $this->teacher->assignRole('teacher');

        $this->finance = User::factory()->create();
        $this->finance->assignRole('finance');

        $this->student = User::factory()->create();
        $this->student->assignRole('student');

        $this->repoManager = User::factory()->create();
        $this->repoManager->assignRole('repository-manager');
    }

    public function test_global_semantic_theme_tokens_exist_in_css(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // Surfaces
        $this->assertStringContainsString('--app-bg', $css);
        $this->assertStringContainsString('--surface-base', $css);
        $this->assertStringContainsString('--surface-raised', $css);
        $this->assertStringContainsString('--surface-elevated', $css);
        $this->assertStringContainsString('--surface-muted', $css);

        // Text
        $this->assertStringContainsString('--text-primary', $css);
        $this->assertStringContainsString('--text-secondary', $css);
        $this->assertStringContainsString('--text-muted', $css);
        $this->assertStringContainsString('--text-disabled', $css);
        $this->assertStringContainsString('--text-inverse', $css);

        // Borders
        $this->assertStringContainsString('--border-default', $css);
        $this->assertStringContainsString('--border-subtle', $css);
        $this->assertStringContainsString('--border-strong', $css);

        // Interaction
        $this->assertStringContainsString('--primary', $css);
        $this->assertStringContainsString('--primary-hover', $css);
        $this->assertStringContainsString('--primary-foreground', $css);
        $this->assertStringContainsString('--secondary', $css);
        $this->assertStringContainsString('--focus-ring', $css);

        // Status
        $this->assertStringContainsString('--success', $css);
        $this->assertStringContainsString('--success-surface', $css);
        $this->assertStringContainsString('--success-foreground', $css);
        $this->assertStringContainsString('--warning', $css);
        $this->assertStringContainsString('--warning-surface', $css);
        $this->assertStringContainsString('--warning-foreground', $css);
        $this->assertStringContainsString('--danger', $css);
        $this->assertStringContainsString('--danger-surface', $css);
        $this->assertStringContainsString('--danger-foreground', $css);
        $this->assertStringContainsString('--info', $css);
        $this->assertStringContainsString('--info-surface', $css);
        $this->assertStringContainsString('--info-foreground', $css);

        // Metrics / KPI
        $this->assertStringContainsString('--metric-primary', $css);
        $this->assertStringContainsString('--metric-success', $css);
        $this->assertStringContainsString('--metric-warning', $css);
        $this->assertStringContainsString('--metric-danger', $css);
        $this->assertStringContainsString('--metric-info', $css);
        $this->assertStringContainsString('--metric-neutral', $css);
    }

    public function test_global_kpi_metric_styling_contract_exists_in_css(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.kpi-card', $css);
        $this->assertStringContainsString('.metric-card', $css);
        $this->assertStringContainsString('.kpi-label', $css);
        $this->assertStringContainsString('min-height: 1.85rem;', $css);
        $this->assertStringContainsString('flex-direction: column;', $css);
        $this->assertStringContainsString('.kpi-value', $css);
        $this->assertStringContainsString('.metric-value', $css);
        $this->assertStringContainsString('.metric-value--primary', $css);
        $this->assertStringContainsString('.metric-value--success', $css);
        $this->assertStringContainsString('.metric-value--warning', $css);
        $this->assertStringContainsString('.metric-value--danger', $css);
        $this->assertStringContainsString('.metric-value--info', $css);
        $this->assertStringContainsString('.metric-value--neutral', $css);
        $this->assertStringContainsString('html.light .metric-value', $css);
    }

    public function test_teacher_dashboard_theme_contract_and_rendering(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('tw-hero');
        $response->assertSee('tw-kpi');
    }

    public function test_regular_admin_operational_dashboard_kpis_and_theme_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('text-slate-900 dark:text-white', false);
        $response->assertSee('ra-status--awaiting-assignment');
    }

    public function test_repository_manager_dashboard_and_quality_analytics_render_cleanly(): void
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('gov-hero');
        $response->assertSee('gov-card');

        $responseQuality = $this->actingAs($this->repoManager)->get(route('admin.academic-library.quality'));
        $responseQuality->assertStatus(200);
        $responseQuality->assertSee('gov-card');

        $responseAnalytics = $this->actingAs($this->repoManager)->get(route('admin.academic-library.analytics'));
        $responseAnalytics->assertStatus(200);
        $responseAnalytics->assertSee('gov-card');
    }

    public function test_finance_dashboard_renders_responsive_kpis_and_status_badges(): void
    {
        \App\Modules\Commerce\Domain\Models\Payment::create([
            'reference_number' => 'PAY-THEME-001',
            'user_id'          => $this->student->id,
            'amount'           => 750000,
            'payment_gateway'  => 'manual_transfer',
            'status'           => \App\Modules\Commerce\Domain\Enums\PaymentStatus::Pending,
        ]);

        $response = $this->actingAs($this->finance)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('text-emerald-600 dark:text-emerald-400', false);
        $response->assertSee('text-slate-900 dark:text-white', false);
        $response->assertSee('ra-status-badge');
    }

    public function test_candidate_portal_renders_responsive_kpis(): void
    {
        $response = $this->actingAs($this->student)->get(route('candidate.portal'));
        $response->assertStatus(200);
        $response->assertSee('text-slate-900 dark:text-white', false);
        $response->assertSee('text-indigo-600 dark:text-indigo-400', false);
    }

    public function test_operational_analytics_reporting_kpis_and_theme_contract(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reporting.index'));
        $response->assertStatus(200);

        // Verify all 8 KPI labels
        $response->assertSee('Total Submissions');
        $response->assertSee('Pass Rate');
        $response->assertSee('Failed Tests');
        $response->assertSee('Certificates');
        $response->assertSee('Active Assignments');
        $response->assertSee('In Progress');
        $response->assertSee('Paid / Eligible');
        $response->assertSee('Registered Students');

        // Verify canonical semantic KPI contract classes
        $response->assertSee('kpi-card');
        $response->assertSee('kpi-label');
        $response->assertSee('kpi-value');
        $response->assertSee('kpi-sub');
        $response->assertSee('metric-value--neutral');
        $response->assertSee('metric-value--success');
        $response->assertSee('metric-value--danger');
        $response->assertSee('metric-value--primary');
        $response->assertSee('metric-value--warning');
        $response->assertSee('metric-value--info');

        // Ensure no raw text-white without dark mode prefix on KPI values
        $content = $response->getContent();
        $this->assertStringNotContainsString('text-2xl font-black text-white mt-1 block', $content);

        // Ensure app.css does not have destructive wildcard white text on card surfaces
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringNotContainsString('.bg-slate-900\/90 *', $css);
        $this->assertStringNotContainsString('.bg-slate-900\/80 *', $css);
    }
}
