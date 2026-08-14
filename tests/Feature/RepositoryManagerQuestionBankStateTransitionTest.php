<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryManagerQuestionBankStateTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create();
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create();
        $this->repoManager->assignRole('repository-manager');
    }

    /** 1. RM can approve a pending_approval QuestionBank. */
    public function test_rm_can_approve_pending_approval_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Pending QB 1',
            'code' => 'QB-PENDING-001',
            'slug' => 'pending-qb-1',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $qb->id), [
                'notes' => 'Approved for release.',
            ]);

        $response->assertStatus(302);
        $this->assertEquals('published', $qb->fresh()->status);
        $this->assertTrue((bool)$qb->fresh()->is_published);
    }

    /** 2. RM can request revision for pending_approval. */
    public function test_rm_can_request_revision_for_pending_approval_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Pending QB 2',
            'code' => 'QB-PENDING-002',
            'slug' => 'pending-qb-2',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $qb->id), [
                'notes' => 'Fix formatting.',
            ]);

        $response->assertStatus(302);
        $this->assertEquals('needs_revision', $qb->fresh()->status);
    }

    /** 3. RM can reject pending_approval. */
    public function test_rm_can_reject_pending_approval_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Pending QB 3',
            'code' => 'QB-PENDING-003',
            'slug' => 'pending-qb-3',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-reject', $qb->id), [
                'notes' => 'Policy violation.',
            ]);

        $response->assertStatus(302);
        $this->assertEquals('archived', $qb->fresh()->status);
    }

    /** 4. RM cannot approve archived. */
    public function test_rm_cannot_approve_archived_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Archived QB 1',
            'code' => 'QB-ARC-001',
            'slug' => 'archived-qb-1',
            'status' => 'archived',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $qb->id), [
                'notes' => 'Attempt illegal approve.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('danger');
        $this->assertEquals('archived', $qb->fresh()->status);
    }

    /** 5. RM cannot request revision for archived. */
    public function test_rm_cannot_request_revision_for_archived_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Archived QB 2',
            'code' => 'QB-ARC-002',
            'slug' => 'archived-qb-2',
            'status' => 'archived',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $qb->id), [
                'notes' => 'Attempt illegal revision.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('danger');
        $this->assertEquals('archived', $qb->fresh()->status);
    }

    /** 6. RM cannot reject archived. */
    public function test_rm_cannot_reject_archived_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Archived QB 3',
            'code' => 'QB-ARC-003',
            'slug' => 'archived-qb-3',
            'status' => 'archived',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-reject', $qb->id), [
                'notes' => 'Attempt illegal reject.',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('danger');
        $this->assertEquals('archived', $qb->fresh()->status);
    }

    /** 7. RM cannot approve draft. */
    public function test_rm_cannot_approve_draft_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Draft QB',
            'code' => 'QB-DFT-001',
            'slug' => 'draft-qb-1',
            'status' => 'draft',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $qb->id));

        $response->assertStatus(302);
        $response->assertSessionHas('danger');
        $this->assertEquals('draft', $qb->fresh()->status);
    }

    /** 8. RM cannot approve published. */
    public function test_rm_cannot_approve_published_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Published QB',
            'code' => 'QB-PUB-001',
            'slug' => 'published-qb-1',
            'status' => 'published',
            'is_published' => true,
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $qb->id));

        $response->assertStatus(302);
        $response->assertSessionHas('danger');
        $this->assertEquals('published', $qb->fresh()->status);
    }

    /** 9. RM cannot approve approved. */
    public function test_rm_cannot_approve_approved_question_bank()
    {
        $qb = QuestionBank::create([
            'title' => 'Approved QB',
            'code' => 'QB-APP-001',
            'slug' => 'approved-qb-1',
            'status' => 'approved',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $qb->id));

        $response->assertStatus(302);
        $response->assertSessionHas('danger');
        $this->assertEquals('approved', $qb->fresh()->status);
    }

    /** 10 & 11. Invalid transition performs ZERO QuestionBank or GovernanceApprovalTask mutation. */
    public function test_invalid_transition_performs_zero_mutation()
    {
        $qb = QuestionBank::create([
            'title' => 'Zero Mutation Arch Bank',
            'code' => 'QB-MUT-001',
            'slug' => 'zero-mutation-arch-bank',
            'status' => 'archived',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $task = GovernanceApprovalTask::create([
            'question_bank_id' => $qb->id,
            'teacher_id' => $this->teacher->id,
            'workflow' => 'APPROVAL',
            'status' => 'OPEN',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $qb->id));

        $response->assertStatus(302);
        $this->assertEquals('archived', $qb->fresh()->status);
        $this->assertEquals('OPEN', $task->fresh()->status);
    }

    /** 12. Validation Workspace hides governance controls for ARCHIVED. */
    public function test_validation_workspace_hides_governance_controls_for_archived()
    {
        $qb = QuestionBank::create([
            'title' => 'Archived UI Test Bank',
            'code' => 'QB-UI-ARC',
            'slug' => 'archived-ui-test-bank',
            'status' => 'archived',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $qb->id));

        $response->assertStatus(200);
        $response->assertSee('Repository Governance Locked');
        $response->assertDontSee('✓ Approve & Publish Repository');
        $response->assertDontSee('⚠️ Request Revision from Author');
        $response->assertDontSee('🚫 Reject & Archive Repository');
    }

    /** 13. Validation Workspace hides governance controls for PUBLISHED. */
    public function test_validation_workspace_hides_governance_controls_for_published()
    {
        $qb = QuestionBank::create([
            'title' => 'Published UI Test Bank',
            'code' => 'QB-UI-PUB',
            'slug' => 'published-ui-test-bank',
            'status' => 'published',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $qb->id));

        $response->assertStatus(200);
        $response->assertSee('Repository Governance Locked');
        $response->assertDontSee('✓ Approve & Publish Repository');
    }

    /** 14. Validation Workspace hides governance controls for DRAFT. */
    public function test_validation_workspace_hides_governance_controls_for_draft()
    {
        $qb = QuestionBank::create([
            'title' => 'Draft UI Test Bank',
            'code' => 'QB-UI-DFT',
            'slug' => 'draft-ui-test-bank',
            'status' => 'draft',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $qb->id));

        $response->assertStatus(200);
        $response->assertSee('Repository Governance Locked');
        $response->assertDontSee('✓ Approve & Publish Repository');
    }

    /** 15. Validation Workspace shows governance controls for PENDING_APPROVAL. */
    public function test_validation_workspace_shows_governance_controls_for_pending_approval()
    {
        $qb = QuestionBank::create([
            'title' => 'Pending UI Test Bank',
            'code' => 'QB-UI-PND',
            'slug' => 'pending-ui-test-bank',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $qb->id));

        $response->assertStatus(200);
        $response->assertDontSee('Repository Governance Locked');
        $response->assertSee('Approve & Publish Repository', false);
        $response->assertSee('Request Revision from Author', false);
        $response->assertSee('Reject & Archive Repository', false);
    }

    /** 16. RM rejection creates notification with rejection reason specifically for the author. */
    public function test_rm_rejection_creates_notification_with_rejection_reason_for_author()
    {
        $unrelatedTeacher = User::factory()->create();
        $unrelatedTeacher->assignRole('teacher');

        $qb = QuestionBank::create([
            'title' => 'Notified Rejection Target Bank',
            'code' => 'QB-NOTIF-REJ',
            'slug' => 'notified-rejection-target-bank',
            'status' => 'pending_approval',
            'created_by' => $this->teacher->id,
            'author_id' => $this->teacher->id,
        ]);

        $rejectionNote = 'Test rejection reason — please revise metadata and question quality.';

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-reject', $qb->id), [
                'notes' => $rejectionNote,
            ]);

        $response->assertStatus(302);
        $this->assertEquals('archived', $qb->fresh()->status);

        // Verify Activity Log
        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_type' => 'QuestionBank',
            'resource_id'   => $qb->id,
            'action'        => 'rejected',
            'approval_note' => $rejectionNote,
            'actor_id'      => $this->teacher->id,
            'reviewer_id'   => $this->repoManager->id,
        ]);

        // Verify Notification for Author
        $notifications = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $this->teacher->id)
            ->get();

        $this->assertCount(1, $notifications);

        $notif = $notifications->first();
        $this->assertEquals('repository_rejected', $notif->type);
        $this->assertEquals('App\Models\User', $notif->notifiable_type);

        $payload = json_decode($notif->data, true);
        $this->assertEquals('Repository Rejected & Archived', $payload['title']);
        $this->assertStringContainsString($rejectionNote, $payload['message']);
        $this->assertEquals($rejectionNote, $payload['rejection_reason']);
        $this->assertEquals($qb->id, $payload['question_bank_id']);
        $this->assertEquals(route('teacher.question-banks.show', $qb->id), $payload['link']);
        $this->assertEquals('HIGH', $payload['priority']);

        // Recipient Safety: Ensure zero notifications were sent to unrelated teacher or RM
        $unrelatedNotifCount = \Illuminate\Support\Facades\DB::table('notifications')
            ->whereIn('notifiable_id', [$unrelatedTeacher->id, $this->repoManager->id])
            ->count();
        $this->assertEquals(0, $unrelatedNotifCount);

        // Verify Notification Center rendering
        $notifCenterResponse = $this->actingAs($this->teacher)
            ->get(route('notifications.index'));
        $notifCenterResponse->assertStatus(200);
        $notifCenterResponse->assertSee('Repository Rejected & Archived');
        $notifCenterResponse->assertSee($rejectionNote);
        $notifCenterResponse->assertSee('Inspect Repository →', false);

        // Verify Click / Destination Resolution
        $readResponse = $this->actingAs($this->teacher)
            ->post(route('notifications.read', $notif->id), ['from' => 'notifications']);
        $readResponse->assertStatus(302);
        $readResponse->assertRedirect(route('teacher.question-banks.show', $qb->id) . '?from=notifications');
    }
}
