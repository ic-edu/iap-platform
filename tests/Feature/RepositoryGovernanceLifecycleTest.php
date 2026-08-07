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
        $completeView->assertSee(route('admin.academic-library.explorer'));

        // Check IRQA Explorer needs_improvement (should NOT contain approved bank)
        $unreviewedExplorer = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'needs_improvement']));
        $unreviewedExplorer->assertDontSee('TOEIC Official Question Bank Vol 1');

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

        // Check IRQA Explorer needs_improvement (should NOT contain bank awaiting teacher revision)
        $unreviewedExplorer = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.explorer', ['filter' => 'needs_improvement']));
        $unreviewedExplorer->assertDontSee('TOEIC Official Question Bank Vol 1');

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
}
