<?php

namespace Tests\Feature;

use App\Models\RepositoryFinding;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrqaAlertCenterNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'IRQA Teacher',
            'email'  => 'irqa_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'IRQA Repo Manager',
            'email'  => 'irqa_repomanager@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1 & 2: RM dashboard renders Critical IRQA Findings count derived from RepositoryFinding.
     */
    public function test_1_and_2_dashboard_renders_critical_irqa_findings_count_from_repository_finding_model()
    {
        $bank = QuestionBank::create([
            'title'      => 'Target Bank For Findings',
            'slug'       => 'target-bank-for-findings',
            'test_type'  => 'toeic',
            'status'     => 'pending_approval',
            'created_by' => $this->teacher->id,
        ]);

        RepositoryFinding::create([
            'question_bank_id' => $bank->id,
            'finding_code'     => 'CRIT_01',
            'finding_type'     => 'METADATA_MISSING',
            'title'            => 'Critical Finding 1',
            'severity'         => 'high',
            'status'           => 'OPEN',
        ]);

        RepositoryFinding::create([
            'question_bank_id' => $bank->id,
            'finding_code'     => 'CRIT_02',
            'finding_type'     => 'DIFFICULTY_UNBALANCED',
            'title'            => 'Critical Finding 2',
            'severity'         => 'high',
            'status'           => 'OPEN',
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Critical IRQA Findings Requiring Review');
        $response->assertSee('2');
    }

    /**
     * TEST 3 & 4: Alert Center header link routes to IRQA Quality Overview, NOT Assessment Approval Queue.
     */
    public function test_3_and_4_alert_center_routes_to_irqa_quality_not_assessment_approval()
    {
        $bank = QuestionBank::create([
            'title'      => 'Target Bank Alert Center',
            'slug'       => 'target-bank-alert-center',
            'test_type'  => 'toefl',
            'status'     => 'pending_approval',
            'created_by' => $this->teacher->id,
        ]);

        RepositoryFinding::create([
            'question_bank_id' => $bank->id,
            'finding_code'     => 'CRIT_03',
            'finding_type'     => 'METADATA_MISSING',
            'title'            => 'High Severity Finding',
            'severity'         => 'high',
            'status'           => 'OPEN',
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.dashboard'));

        $response->assertStatus(200);

        // Assert header Alert Center link points to admin.academic-library.quality
        $response->assertSee(route('admin.academic-library.quality'));

        // Assert header Alert Center link does NOT point to assessment-approval for Alert Center
        $content = $response->getContent();
        $this->assertStringContainsString(
            '<a href="' . route('admin.academic-library.quality') . '" style="font-size:.78rem;color:#f87171;font-weight:700;text-decoration:none;">Alert Center →</a>',
            $content
        );
    }

    /**
     * TEST 5: Alert Center destination (admin.academic-library.quality) renders active IRQA findings.
     */
    public function test_5_irqa_quality_center_destination_renders_quality_dashboard()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality'));

        $response->assertStatus(200);
        $response->assertSee('IRQA Quality Dashboard');
        $response->assertSee(route('admin.repository-manager.dashboard'));
    }

    /**
     * TEST 6: Assessment Approval Queue remains accessible independently through its own route.
     */
    public function test_6_assessment_approval_queue_remains_independently_accessible()
    {
        Test::create([
            'title'      => 'Assessment Test Pending',
            'slug'       => 'assessment-test-pending',
            'status'     => 'pending_approval',
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-approval'));

        $response->assertStatus(200);
        $response->assertSee('Assessment Governance');
        $response->assertSee('Assessment Test Pending');
    }

    /**
     * TEST 7: Critical IRQA Findings and Assessment Needs Revision counts are not conflated.
     */
    public function test_7_irqa_findings_and_assessment_needs_revision_are_not_conflated()
    {
        $bank = QuestionBank::create([
            'title'      => 'IRQA Finding Bank',
            'slug'       => 'irqa-finding-bank',
            'test_type'  => 'toeic',
            'status'     => 'pending_approval',
            'created_by' => $this->teacher->id,
        ]);

        // 3 IRQA Findings
        for ($i = 0; $i < 3; $i++) {
            RepositoryFinding::create([
                'question_bank_id' => $bank->id,
                'finding_code'     => "CRIT_F_{$i}",
                'finding_type'     => 'QUALITY_WARNING',
                'title'            => "Finding {$i}",
                'severity'         => 'high',
                'status'           => 'OPEN',
            ]);
        }

        // 1 Needs Revision Test
        Test::create([
            'title'      => 'Revision Needed Assessment',
            'slug'       => 'revision-needed-assessment',
            'status'     => 'needs_revision',
            'created_by' => $this->teacher->id,
        ]);

        // RM Dashboard should show 3 for IRQA Findings
        $dashboardResponse = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.dashboard'));
        $dashboardResponse->assertSee('3');

        // Assessment Approval Center should show Needs Revision count
        $assessmentResponse = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-approval', ['status' => 'needs_revision']));
        $assessmentResponse->assertSee('Revision Needed Assessment');
        $assessmentResponse->assertSee('Needs Revision');
    }

    /**
     * TEST 8: Teacher role boundary enforcement (Teacher cannot access RM dashboard or Assessment Approval).
     */
    public function test_8_teacher_cannot_access_repository_manager_dashboard_or_assessment_approval()
    {
        $dashboardResponse = $this->actingAs($this->teacher)
            ->get(route('admin.repository-manager.dashboard'));
        $dashboardResponse->assertStatus(403);

        $assessmentResponse = $this->actingAs($this->teacher)
            ->get(route('admin.repository-manager.assessment-approval'));
        $assessmentResponse->assertStatus(403);
    }

    /**
     * TEST 9: Back navigation from IRQA Quality Center returns to Repository Manager Dashboard.
     */
    public function test_9_back_navigation_returns_to_repository_manager_dashboard()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.repository-manager.dashboard'));
    }
}
