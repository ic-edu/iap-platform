<?php

namespace Tests\Feature;

use App\Models\QuestionBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GovernanceLightThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $rm;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');

        $this->rm = User::factory()->create();
        $this->rm->assignRole('repository-manager');
    }

    public function test_question_banks_approval_queue_renders_cleanly(): void
    {
        $response = $this->actingAs($this->rm)->get(route('admin.repository-manager.questions-approval'));
        $response->assertStatus(200);
        $response->assertSee('Question Banks Approval Queue');
        $response->assertSee('gov-card');
    }

    public function test_media_approval_center_renders_cleanly(): void
    {
        $response = $this->actingAs($this->rm)->get(route('admin.repository-manager.media-approval'));
        $response->assertStatus(200);
        $response->assertSee('Media Approval Center (QA Queue)');
        $response->assertSee('Filter by Submission Status');
    }

    public function test_duplicate_detection_center_renders_cleanly(): void
    {
        $response = $this->actingAs($this->rm)->get(route('admin.repository-manager.duplicates'));
        $response->assertStatus(200);
        $response->assertSee('Duplicate Detection &amp; Content Similarity Center', false);
        $response->assertSee('Run Full Repository Scan');
    }

    public function test_irqa_dashboard_and_analytics_render_cleanly(): void
    {
        $respQuality = $this->actingAs($this->rm)->get(route('admin.academic-library.quality'));
        $respQuality->assertStatus(200);
        $respQuality->assertSee('Institutional Repository Quality Assurance (IRQA)');

        $respAnalytics = $this->actingAs($this->rm)->get(route('admin.academic-library.analytics'));
        $respAnalytics->assertStatus(200);
        $respAnalytics->assertSee('IRQA Quality Analytics');
    }

    public function test_repository_manager_command_center_renders_cleanly(): void
    {
        $response = $this->actingAs($this->rm)->get(route('admin.repository-manager.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Repository Manager Command Center');
        $response->assertSee('Enterprise Governance');
    }

    public function test_super_admin_approval_command_center_renders_cleanly(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.index'));
        $response->assertStatus(200);
        $response->assertSee('Super Admin Governance & Approval Command Center', false);
    }
}
