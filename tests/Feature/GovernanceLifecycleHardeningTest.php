<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\RepositoryQualityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GovernanceLifecycleHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Hardening Teacher',
            'email'  => 'hardening_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Hardening Repo Manager',
            'email'  => 'hardening_repomanager@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: needs_revision => QuestionBank::isRevisionRequested() === true
     */
    public function test_1_needs_revision_status_returns_true_for_is_revision_requested()
    {
        $bank = QuestionBank::create([
            'title'      => 'Test Revision Bank',
            'slug'       => 'test-revision-bank',
            'test_type'  => 'toeic',
            'status'     => 'needs_revision',
            'created_by' => $this->teacher->id,
        ]);

        $this->assertTrue($bank->isRevisionRequested());
    }

    /**
     * TEST 2: draft + ordinary authoring activity log => NOT classified as reviewed_issues
     */
    public function test_2_draft_with_authoring_activity_log_is_not_classified_as_reviewed_issues()
    {
        $bank = QuestionBank::create([
            'title'      => 'Draft Bank with Authoring Logs',
            'slug'       => 'draft-bank-authoring-logs',
            'test_type'  => 'toefl',
            'status'     => 'draft',
            'created_by' => $this->teacher->id,
        ]);

        // Ordinary authoring log
        RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $bank->id,
            'actor_id'      => $this->teacher->id,
            'action'        => 'question_added',
            'approval_note' => 'Added 5 reading questions.',
        ]);

        $qualityService = app(RepositoryQualityService::class);
        $audits = $qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);

        $bankIds = array_column($audits['audits'], 'bank_id');
        $this->assertNotContains($bank->id, $bankIds);
    }

    /**
     * TEST 3: published repository => classified as reviewed_issues
     */
    public function test_3_published_repository_classified_as_reviewed_issues()
    {
        $bank = QuestionBank::create([
            'title'        => 'Published Academic Repository',
            'slug'         => 'published-academic-repository',
            'test_type'    => 'ielts',
            'status'       => 'published',
            'is_published' => true,
            'created_by'   => $this->teacher->id,
        ]);

        $qualityService = app(RepositoryQualityService::class);
        $audits = $qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);

        $bankIds = array_column($audits['audits'], 'bank_id');
        $this->assertContains($bank->id, $bankIds);
    }

    /**
     * TEST 4: needs_revision repository => classified as reviewed_issues
     */
    public function test_4_needs_revision_repository_classified_as_reviewed_issues()
    {
        $bank = QuestionBank::create([
            'title'        => 'Needs Revision Repository',
            'slug'         => 'needs-revision-repository',
            'test_type'    => 'toeic',
            'status'       => 'needs_revision',
            'is_published' => false,
            'created_by'   => $this->teacher->id,
        ]);

        $qualityService = app(RepositoryQualityService::class);
        $audits = $qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);

        $bankIds = array_column($audits['audits'], 'bank_id');
        $this->assertContains($bank->id, $bankIds);
    }

    /**
     * TEST 5: archived/rejected repository => classified as reviewed_issues and archived
     */
    public function test_5_archived_or_rejected_repository_classified_as_reviewed_issues_and_archived()
    {
        $bank = QuestionBank::create([
            'title'        => 'Archived Repository',
            'slug'         => 'archived-repository',
            'test_type'    => 'general',
            'status'       => 'archived',
            'is_published' => false,
            'created_by'   => $this->teacher->id,
        ]);

        $qualityService = app(RepositoryQualityService::class);
        
        $reviewedAudits = $qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);
        $reviewedIds = array_column($reviewedAudits['audits'], 'bank_id');
        $this->assertContains($bank->id, $reviewedIds);

        $archivedAudits = $qualityService->getExplorerAudits(['filter' => 'archived']);
        $archivedIds = array_column($archivedAudits['audits'], 'bank_id');
        $this->assertContains($bank->id, $archivedIds);
    }

    /**
     * TEST 6: pending_approval repository with quality findings => Needs Improvement / Awaiting Approval (NOT Reviewed Issues)
     */
    public function test_6_pending_approval_repository_classified_as_awaiting_approval_not_reviewed_issues()
    {
        $bank = QuestionBank::create([
            'title'        => 'Pending Approval Repository',
            'slug'         => 'pending-approval-repository',
            'test_type'    => 'toeic',
            'status'       => 'pending_approval',
            'is_published' => false,
            'created_by'   => $this->teacher->id,
        ]);

        $qualityService = app(RepositoryQualityService::class);
        
        $awaitingAudits = $qualityService->getExplorerAudits(['filter' => 'awaiting_approval']);
        $awaitingIds = array_column($awaitingAudits['audits'], 'bank_id');
        $this->assertContains($bank->id, $awaitingIds);

        $reviewedAudits = $qualityService->getExplorerAudits(['filter' => 'reviewed_issues']);
        $reviewedIds = array_column($reviewedAudits['audits'], 'bank_id');
        $this->assertNotContains($bank->id, $reviewedIds);
    }

    /**
     * TEST 7: Seeded TOEIC Official Question Bank Vol. 1 => status = needs_revision, is_published = false
     */
    public function test_7_seeded_toeic_official_question_bank_has_canonical_status_and_is_published_false()
    {
        $bank = QuestionBank::create([
            'title'        => 'TOEIC Official Question Bank Vol. 1',
            'slug'         => 'toeic-official-question-bank-vol-1',
            'test_type'    => 'toeic',
            'status'       => 'needs_revision',
            'is_published' => false,
            'created_by'   => $this->teacher->id,
        ]);

        $this->assertEquals('needs_revision', $bank->status);
        $this->assertFalse((bool)$bank->is_published);
        $this->assertTrue($bank->isRevisionRequested());
    }

    /**
     * TEST 8: GovernanceApprovalTask physical database migration table exists and is usable
     */
    public function test_8_governance_approval_tasks_table_exists_and_usable()
    {
        $this->assertTrue(Schema::hasTable('governance_approval_tasks'));

        $bank = QuestionBank::create([
            'title'      => 'Task Bank Test',
            'slug'       => 'task-bank-test',
            'test_type'  => 'toeic',
            'status'     => 'pending_approval',
            'created_by' => $this->teacher->id,
        ]);

        $task = GovernanceApprovalTask::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'workflow'         => 'APPROVAL',
            'status'           => 'OPEN',
            'submitted_at'     => now(),
        ]);

        $this->assertDatabaseHas('governance_approval_tasks', [
            'id'       => $task->id,
            'workflow' => 'APPROVAL',
            'status'   => 'OPEN',
        ]);
    }
}
