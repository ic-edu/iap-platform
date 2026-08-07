<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryFinding;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\RepositoryQualityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrqaReviewedIssuesSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected RepositoryQualityService $qualityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'IRQA Teacher Audit',
            'email'  => 'teacher_audit@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'IRQA Manager Audit',
            'email'  => 'manager_audit@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->qualityService = app(RepositoryQualityService::class);
    }

    protected function createValidBank(array $attributes = []): QuestionBank
    {
        $bank = QuestionBank::create(array_merge([
            'title'           => 'Valid Audit Bank',
            'slug'            => 'valid-audit-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'description'     => 'Comprehensive test repository description.',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'acl_category_id' => null,
            'created_by'      => $this->teacher->id,
        ], $attributes));

        foreach (['easy', 'medium', 'hard'] as $diff) {
            Question::create([
                'question_bank_id' => $bank->id,
                'prompt'           => "Audit Question Prompt {$diff}",
                'question_type'    => 'essay',
                'explanation'      => 'Detailed explanation.',
                'difficulty'       => $diff,
                'points'           => 10,
            ]);
        }

        return $bank;
    }

    /**
     * TEST 1 & 6: Reviewed + published + zero findings -> Excluded from Reviewed Issues, classified as Healthy.
     */
    public function test_reviewed_repository_with_zero_findings_excluded_from_reviewed_issues()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Speaking', 'slug' => 'speaking', 'is_active' => true]);
        $bank = $this->createValidBank([
            'title'           => 'IELTS Speaking Interview & Cue Card Prompts',
            'status'          => 'published',
            'acl_category_id' => $category->id,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'approved',
        ]);

        $summary = $this->qualityService->getGlobalQualitySummary();
        $explorerRev = $this->qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);
        $explorerHealthy = $this->qualityService->getExplorerAudits(['filter' => 'healthy']);

        $this->assertEquals(0, $summary['reviewed_issues_count']);
        $this->assertEquals(1, $summary['healthy_count']);
        $this->assertCount(0, $explorerRev['audits']);
        $this->assertCount(1, $explorerHealthy['audits']);
    }

    /**
     * TEST A: Unreviewed repository with OPEN high finding -> Needs Improvement.
     */
    public function test_a_unreviewed_repository_with_open_high_finding_classified_as_needs_improvement()
    {
        $bank = $this->createValidBank([
            'status'          => 'pending_approval',
            'acl_category_id' => null,
        ]);

        $this->qualityService->syncRepositoryFindings($bank);

        $summary = $this->qualityService->getGlobalQualitySummary();
        $explorer = $this->qualityService->getExplorerAudits(['filter' => 'needs_improvement']);

        $this->assertEquals(1, $summary['needs_improvement_count']);
        $this->assertCount(1, $explorer['audits']);
        $this->assertEquals($bank->id, $explorer['audits'][0]['bank_id']);
    }

    /**
     * TEST B & C: Reviewed repository with OPEN high finding / NEEDS_REVISION -> Reviewed Issues, NOT Needs Improvement.
     */
    public function test_b_and_c_reviewed_needs_revision_repository_classified_as_reviewed_issues()
    {
        $bank = $this->createValidBank([
            'status'          => 'needs_revision',
            'acl_category_id' => null,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'repository_manager_requested_revision',
            'approval_note' => 'Please update category.',
        ]);

        $this->qualityService->syncRepositoryFindings($bank);

        $summary = $this->qualityService->getGlobalQualitySummary();
        $explorerNeeds = $this->qualityService->getExplorerAudits(['filter' => 'needs_improvement']);
        $explorerReviewed = $this->qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);

        $this->assertEquals(0, $summary['needs_improvement_count']);
        $this->assertEquals(1, $summary['reviewed_issues_count']);
        $this->assertCount(0, $explorerNeeds['audits']);
        $this->assertCount(1, $explorerReviewed['audits']);
        $this->assertEquals($bank->id, $explorerReviewed['audits'][0]['bank_id']);
    }

    /**
     * TEST D: FIXED finding does not count as active quality issue.
     */
    public function test_d_fixed_finding_does_not_count_as_active_quality_issue()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Grammar', 'slug' => 'grammar', 'is_active' => true]);
        $bank = $this->createValidBank(['acl_category_id' => null]);

        $this->qualityService->syncRepositoryFindings($bank);
        $this->assertEquals(1, RepositoryFinding::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());

        // Fix category
        $bank->acl_category_id = $category->id;
        $bank->save();

        $this->qualityService->syncRepositoryFindings($bank);

        $summary = $this->qualityService->getGlobalQualitySummary();
        $this->assertEquals(0, $summary['needs_improvement_count']);
        $this->assertEquals(1, $summary['healthy_count']);
    }

    /**
     * TEST E: Healthy repository -> Healthy filter.
     */
    public function test_e_healthy_repository_classified_as_healthy()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Vocab', 'slug' => 'vocab', 'is_active' => true]);
        $bank = $this->createValidBank(['acl_category_id' => $category->id]);

        $summary = $this->qualityService->getGlobalQualitySummary();
        $explorer = $this->qualityService->getExplorerAudits(['filter' => 'healthy']);

        $this->assertEquals(1, $summary['healthy_count']);
        $this->assertCount(1, $explorer['audits']);
    }

    /**
     * TEST F: Awaiting approval repository -> Awaiting Approval filter.
     */
    public function test_f_awaiting_approval_repository_classified_as_awaiting_approval()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Listening', 'slug' => 'listening', 'is_active' => true]);
        $bank = $this->createValidBank([
            'status'          => 'pending_approval',
            'acl_category_id' => $category->id,
        ]);

        $summary = $this->qualityService->getGlobalQualitySummary();
        $explorer = $this->qualityService->getExplorerAudits(['filter' => 'awaiting_approval']);

        $this->assertEquals(1, $summary['pending_approval_count']);
        $this->assertCount(1, $explorer['audits']);
    }

    /**
     * TEST G & H: Dashboard KPI counts equal Explorer result counts 1:1.
     */
    public function test_g_and_h_dashboard_counts_match_explorer_counts_exactly()
    {
        $category = \App\Models\AclCategory::create(['name' => 'Reading', 'slug' => 'reading', 'is_active' => true]);

        // 1 Healthy
        $this->createValidBank(['acl_category_id' => $category->id]);

        // 1 Unreviewed Needs Improvement
        $b2 = $this->createValidBank(['acl_category_id' => null, 'status' => 'draft']);
        $this->qualityService->syncRepositoryFindings($b2);

        // 1 Reviewed Issues
        $b3 = $this->createValidBank(['acl_category_id' => null, 'status' => 'needs_revision']);
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $b3->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'repository_manager_requested_revision',
        ]);
        $this->qualityService->syncRepositoryFindings($b3);

        $summary = $this->qualityService->getGlobalQualitySummary();

        $expHealthy = $this->qualityService->getExplorerAudits(['filter' => 'healthy']);
        $expNeeds   = $this->qualityService->getExplorerAudits(['filter' => 'needs_improvement']);
        $expRev     = $this->qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);

        $this->assertEquals(count($expHealthy['audits']), $summary['healthy_count']);
        $this->assertEquals(count($expNeeds['audits']), $summary['needs_improvement_count']);
        $this->assertEquals(count($expRev['audits']), $summary['reviewed_issues_count']);
    }

    /**
     * TEST I: Dashboard KPI links open the correct Explorer filter.
     */
    public function test_i_dashboard_kpi_links_open_correct_explorer_filter()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality'));

        $response->assertStatus(200);
        $response->assertSee(route('admin.academic-library.explorer', ['filter' => 'needs_improvement', 'sort' => 'health_asc']));
        $response->assertSee(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']));
        $response->assertSee('Reviewed Issues →');
    }

    /**
     * TEST J: Assessment Approval Queue remains independent from IRQA.
     */
    public function test_j_assessment_approval_queue_remains_independent_from_irqa()
    {
        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-approval'));

        $response->assertStatus(200);
        $response->assertDontSee('IRQA Repository Explorer');
    }

    /**
     * TEST K, L & TOEIC CASE: TOEIC Vol. 1 classified as Reviewed Issues (YES), Needs Improvement (NO), Findings OPEN, Status NEEDS_REVISION.
     */
    public function test_k_and_l_toeic_vol_1_classified_as_reviewed_issues_not_needs_improvement()
    {
        $toeicBank = QuestionBank::create([
            'title'           => 'TOEIC Official Question Bank Vol. 1',
            'slug'            => 'toeic-official-question-bank-vol-1',
            'test_type'       => 'toeic',
            'description'     => 'Official TOEIC question bank.',
            'current_version' => '1.0',
            'status'          => 'needs_revision',
            'acl_category_id' => null,
            'created_by'      => $this->teacher->id,
        ]);

        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $toeicBank->id,
            'actor_id'      => $this->repoManager->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'repository_manager_requested_revision',
        ]);

        $this->qualityService->syncRepositoryFindings($toeicBank);

        $summary = $this->qualityService->getGlobalQualitySummary();
        $explorerNeeds = $this->qualityService->getExplorerAudits(['filter' => 'needs_improvement']);
        $explorerReviewed = $this->qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);

        // Assert Needs Improvement = 0, Reviewed Issues = 1
        $this->assertEquals(0, $summary['needs_improvement_count']);
        $this->assertEquals(1, $summary['reviewed_issues_count']);
        $this->assertCount(0, $explorerNeeds['audits']);
        $this->assertCount(1, $explorerReviewed['audits']);

        // Assert Validation Workspace renders status NEEDS_REVISION and active OPEN findings
        $validateResponse = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $toeicBank->id));

        $validateResponse->assertStatus(200);
        $validateResponse->assertSee('NEEDS_REVISION');
        $validateResponse->assertSee('Missing Category association');

        // Assert findings remain OPEN (2 findings: Missing Category & Difficulty Unbalanced)
        $this->assertEquals(2, RepositoryFinding::where('question_bank_id', $toeicBank->id)->where('status', 'OPEN')->count());
    }
}
