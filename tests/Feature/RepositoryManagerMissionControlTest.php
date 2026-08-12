<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryManagerMissionControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected User $superAdmin;
    protected QuestionBank $bank;
    protected AssessmentTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher Mission Control',
            'email'  => 'teacher_mc@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Mission Control',
            'email'  => 'repomanager_mc@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin Mission Control',
            'email'  => 'superadmin_mc@icedu.org',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->bank = QuestionBank::create([
            'title'      => 'MC Bank 01',
            'slug'       => 'mc-bank-01',
            'test_type'  => 'toeic',
            'status'     => 'pending_approval',
            'created_by' => $this->teacher->id,
        ]);

        $this->test = AssessmentTest::create([
            'title'            => 'MC Test 01',
            'slug'             => 'mc-test-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 700,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacher->id,
        ]);
    }

    /**
     * TEST 1: Mission Control Header Buttons & Actionability.
     */
    public function test_1_dashboard_renders_mission_control_header_action_buttons()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('🔔 Notifications');
        $response->assertSee('🔍 IRQA Explorer');
        $response->assertSee('📚 Question Bank Governance');
        $response->assertSee('⚡ Open Repository Governance Queue');
        $response->assertSee('(Recently Updated Repositories)');
    }

    /**
     * TEST 2: Compact empty states for Revision Queue and Alerts.
     */
    public function test_2_dashboard_renders_compact_empty_states()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('✓ No pending teacher revisions.');
    }

    /**
     * TEST 3: Actionability rule - Every KPI card has an explicit, distinct destination.
     */
    public function test_3_kpi_cards_and_widgets_have_distinct_actionable_ctas()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Question Bank Governance');
        $response->assertSee('Assessment Approval');
        $response->assertSee('Governance Queue →');
        $response->assertSee('Assessment Queue →');
        $response->assertSee('Media Queue →');
        $response->assertSee('Duplicate Center →');
        $response->assertSee('Total Repositories →');
        $response->assertSee('Metadata Validator →');
        $response->assertSee('Full Analytics →');

        // Question Bank Governance KPI target must be Question Banks Approval Queue
        $response->assertSee(route('admin.repository-manager.questions-approval'));
        // Assessment Approval KPI target must be Assessment Approval Queue
        $response->assertSee(route('admin.repository-manager.assessment-approval'));
    }

    /**
     * TEST 4: Domain routing matrix verification - Question Bank vs Assessment approval paths.
     */
    public function test_4_governance_domain_routing_matrix_consistency()
    {
        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);

        // 1. Question Bank Governance CTA points to questions approval route
        $questionsApprovalUrl = route('admin.repository-manager.questions-approval');
        $assessmentApprovalUrl = route('admin.repository-manager.assessment-approval');

        $response->assertSee($questionsApprovalUrl);
        $response->assertSee($assessmentApprovalUrl);

        // 2. Open Repository Governance Queue CTA points to questions approval route
        $response->assertSee('⚡ Open Repository Governance Queue');

        // 3. Question Bank validation workspace links point to validateQuestionBank route
        $validationUrl = route('admin.repository-manager.question-bank-validate', $this->bank->id);
        $response->assertSee($validationUrl);
    }

    /**
     * TEST 5: IRQA Detail Page uses global context-aware ← Back navigation.
     */
    public function test_5_irqa_detail_page_uses_context_aware_back_navigation()
    {
        $category = \App\Models\AclCategory::firstOrCreate(
            ['slug' => 'toefl-reading-mc'],
            ['name' => 'TOEFL Reading MC', 'description' => 'Test Category']
        );

        $response = $this->actingAs($this->repoManager)->get(route('admin.academic-library.show', $category->slug));

        $response->assertStatus(200);
        $response->assertSee('← Back');
        $response->assertDontSee('Back to Question Banks');
    }
}
