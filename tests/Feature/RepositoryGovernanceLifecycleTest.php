<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryGovernanceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Lifecycle Teacher',
            'email'  => 'lifecycle_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Lifecycle Repo Manager',
            'email'  => 'lifecycle_repomanager@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'TOEIC Official Question Bank Vol 1',
            'slug'        => 'toeic-official-question-bank-vol-1',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->teacher->id,
            'description' => 'Official institutional TOEIC repository with quality findings.',
        ]);
    }

    /**
     * TEST A: Approve & Publish redirects to Review Completion screen and transitions to Reviewed Issues tab.
     */
    public function test_a_approve_question_bank_shows_completion_screen_and_transitions_to_reviewed_issues()
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $this->bank->id), [
                'notes' => 'Approved for institutional publishing after full audit.',
            ]);

        $response->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        // Follow redirect to completion screen
        $completeView = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.review-complete', $this->bank->id));

        $completeView->assertStatus(200);
        $completeView->assertSee('Done — Governance Review Completed');
        $completeView->assertSee('Status: PUBLISHED');
        $completeView->assertSee('← Back to IRQA Repository Explorer');
        $completeView->assertSee(route('admin.academic-library.explorer'));
        $completeView->assertSee('← Back to Governance Queue');
        $completeView->assertSee(route('admin.repository-manager.questions-approval'));

        // Check IRQA Explorer reviewed_issues (SHOULD contain approved bank)
        $reviewedExplorer = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']));
        $reviewedExplorer->assertSee('TOEIC Official Question Bank Vol 1');
        $reviewedExplorer->assertSee('Decided: PUBLISHED');
    }

    /**
     * TEST B: Request Revision redirects to Review Completion screen and moves to Reviewed Issues tab with NEEDS_REVISION.
     */
    public function test_b_request_revision_shows_completion_screen_and_transitions_to_reviewed_issues()
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $this->bank->id), [
                'notes' => 'Please balance question difficulty.',
            ]);

        $response->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        $completeView = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.review-complete', $this->bank->id));

        $completeView->assertStatus(200);
        $completeView->assertSee('Done — Governance Review Completed');
        $completeView->assertSee('Status: NEEDS REVISION');
        $completeView->assertSee('← Back to IRQA Repository Explorer');
        $completeView->assertSee('← Back to Governance Queue');

        // Check IRQA Explorer reviewed_issues (SHOULD contain bank)
        $reviewedExplorer = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']));
        $reviewedExplorer->assertSee('TOEIC Official Question Bank Vol 1');
        $reviewedExplorer->assertSee('Decided: NEEDS REVISION');
    }

    /**
     * TEST C: Reject & Archive redirects to Review Completion screen and transitions to Reviewed Issues tab with REJECTED/ARCHIVED.
     */
    public function test_c_reject_question_bank_shows_completion_screen_and_transitions_to_reviewed_issues()
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-reject', $this->bank->id), [
                'notes' => 'Rejected due to severe metadata violations.',
            ]);

        $response->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        $completeView = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.review-complete', $this->bank->id));

        $completeView->assertStatus(200);
        $completeView->assertSee('Done — Governance Review Completed');
        $completeView->assertSee('Status: ARCHIVED');
        $completeView->assertSee('← Back to IRQA Repository Explorer');
        $completeView->assertSee('← Back to Governance Queue');

        // Check IRQA Explorer reviewed_issues (SHOULD contain rejected/archived bank)
        $reviewedExplorer = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']));
        $reviewedExplorer->assertSee('TOEIC Official Question Bank Vol 1');
    }

    /**
     * TEST D: Decision sub-filtering under Reviewed Issues.
     */
    public function test_d_decision_subfiltering_under_reviewed_issues()
    {
        $this->bank->update(['status' => 'needs_revision']);
        \App\Models\RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $this->bank->id,
            'actor_id'      => $this->teacher->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'revision_requested',
            'approval_note' => 'Fix questions.',
        ]);

        $publishedFilter = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues', 'decision' => 'published']));
        $publishedFilter->assertDontSee('TOEIC Official Question Bank Vol 1');

        $revisionFilter = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues', 'decision' => 'needs_revision']));
        $revisionFilter->assertSee('TOEIC Official Question Bank Vol 1');
    }

    /**
     * TEST E: Preserved quality findings history remains visible under Reviewed Issues.
     */
    public function test_e_preserved_quality_findings_history_remains_visible()
    {
        $this->bank->update(['status' => 'needs_revision']);
        \App\Models\RepositoryActivityLog::create([
            'resource_type' => 'QuestionBank',
            'resource_id'   => $this->bank->id,
            'actor_id'      => $this->teacher->id,
            'reviewer_id'   => $this->repoManager->id,
            'action'        => 'revision_requested',
            'approval_note' => 'Fix questions.',
        ]);

        $reviewedExplorer = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']));

        $reviewedExplorer->assertStatus(200);
        $reviewedExplorer->assertSee('Detected Issues (Preserved History)');
        $reviewedExplorer->assertSee('Missing Category association');
    }

    /**
     * TEST F: Governance Outcome Navigation Hierarchy.
     * Verifies:
     * 1. Top breadcrumb is restored to '← Back to IRQA Repository Explorer'.
     * 2. Main action button inside card is '← Back to Governance Queue'.
     * 3. Removed duplicate/redundant 'Return to Origin' and 'Open Governance Queue' buttons.
     * 4. Top breadcrumb points to IRQA Explorer while main button points to the Governance Queue.
     */
    public function test_f_governance_outcome_navigation_simplification()
    {
        // 1. Default outcome page
        $completeView = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.review-complete', $this->bank->id));

        $completeView->assertStatus(200);
        // Top breadcrumb
        $completeView->assertSee('← Back to IRQA Repository Explorer');
        $completeView->assertSee(route('admin.academic-library.explorer'));

        // Main action button
        $completeView->assertSee('← Back to Governance Queue');
        $completeView->assertSee(route('admin.repository-manager.questions-approval'));

        // Old redundant buttons removed
        $completeView->assertDontSee('Return to Origin');
        $completeView->assertDontSee('Open Governance Queue');
        $completeView->assertDontSee('🔍 Return to Origin');
        $completeView->assertDontSee('⚡ Open Governance Queue');

        // 2. Contextual Explorer outcome page (from=explorer&filter=reviewed_issues)
        $explorerOutcomeView = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.review-complete', [
                $this->bank->id,
                'from'   => 'explorer',
                'filter' => 'reviewed_issues',
            ]));

        $explorerOutcomeView->assertStatus(200);
        $explorerOutcomeView->assertSee('← Back to IRQA Repository Explorer');
        $explorerOutcomeView->assertSee('← Back to Governance Queue');
        $explorerOutcomeView->assertSee(route('admin.academic-library.explorer', ['filter' => 'reviewed_issues']));
        $explorerOutcomeView->assertDontSee('Return to Origin');
        $explorerOutcomeView->assertDontSee('Open Governance Queue');
    }
}
