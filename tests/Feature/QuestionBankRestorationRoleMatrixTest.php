<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\RepositoryActivityLog;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionBankRestorationRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerTeacher;
    protected User $otherTeacher;
    protected User $repoManager;
    protected User $superAdmin;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ownerTeacher = User::factory()->create(['name' => 'Owner Teacher', 'email' => 'owner_teacher@icedu.org']);
        $this->ownerTeacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['name' => 'Other Teacher', 'email' => 'other_teacher@icedu.org']);
        $this->otherTeacher->assignRole('teacher');

        $this->repoManager = User::factory()->create(['name' => 'Repository Manager', 'email' => 'repo_manager@icedu.org']);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin', 'email' => 'super_admin@icedu.org']);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create(['name' => 'Operational Admin', 'email' => 'admin@icedu.org']);
        $this->admin->assignRole('admin');
    }

    protected function createBank(array $attributes = []): QuestionBank
    {
        return QuestionBank::create(array_merge([
            'title'        => 'Matrix Test Bank',
            'slug'         => 'matrix-test-bank-' . uniqid(),
            'code'         => 'QB-MTX-' . strtoupper(substr(uniqid(), 0, 4)),
            'test_type'    => 'toeic',
            'description'  => 'Role matrix governance test repository.',
            'status'       => 'archived',
            'created_by'   => $this->ownerTeacher->id,
            'is_published' => false,
        ], $attributes));
    }

    /** 1. Teacher owner can request restore. */
    public function test_teacher_owner_can_request_restore(): void
    {
        $bank = $this->createBank(['status' => 'archived']);

        $res = $this->actingAs($this->ownerTeacher)
            ->post(route('admin.question-banks.request-restore', $bank->id), [
                'reason' => 'Need updated questions for new term',
            ]);

        $res->assertStatus(302);
        $this->assertEquals('pending_restore_approval', $bank->fresh()->status);
        $this->assertEquals(1, GovernanceApprovalTask::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
    }

    /** 2. RM cannot request restore. */
    public function test_rm_cannot_request_restore(): void
    {
        $bank = $this->createBank(['status' => 'archived']);

        $res = $this->actingAs($this->repoManager)
            ->post(route('admin.question-banks.request-restore', $bank->id), [
                'reason' => 'RM restore attempt',
            ]);

        $res->assertStatus(403);
        $this->assertEquals('archived', $bank->fresh()->status);
    }

    /** 3. SA can approve restore. */
    public function test_super_admin_can_approve_restore(): void
    {
        $bank = $this->createBank(['status' => 'pending_restore_approval']);
        GovernanceApprovalTask::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->ownerTeacher->id,
            'workflow'         => 'APPROVAL',
            'status'           => 'OPEN',
            'submitted_at'     => now(),
        ]);

        $res = $this->actingAs($this->superAdmin)
            ->post(route('admin.question-banks.approve-restore', $bank->id));

        $res->assertStatus(302);
        $this->assertEquals('approved', $bank->fresh()->status);
        $this->assertFalse((bool) $bank->fresh()->is_published);
        $this->assertEquals(1, GovernanceApprovalTask::where('question_bank_id', $bank->id)->where('status', 'COMPLETED')->count());

        // Teacher author notified
        $notif = DB::table('notifications')->where('notifiable_id', $this->ownerTeacher->id)->where('type', 'question_bank_restoration_approved')->first();
        $this->assertNotNull($notif);
    }

    /** 4. SA can reject restore. */
    public function test_super_admin_can_reject_restore(): void
    {
        $bank = $this->createBank(['status' => 'pending_restore_approval']);

        $res = $this->actingAs($this->superAdmin)
            ->post(route('admin.question-banks.reject-restore', $bank->id), [
                'reason' => 'Outdated curriculum',
            ]);

        $res->assertStatus(302);
        $this->assertEquals('archived', $bank->fresh()->status);
        $this->assertFalse((bool) $bank->fresh()->is_published);
    }

    /** 5. RM cannot act while status = pending_restore_approval. */
    public function test_rm_cannot_act_while_status_is_pending_restore_approval(): void
    {
        $bank = $this->createBank(['status' => 'pending_restore_approval']);

        // RM approve attempt
        $resApprove = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $bank->id));
        $resApprove->assertStatus(302);
        $resApprove->assertSessionHas('danger');

        // RM revision attempt
        $resRevision = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), ['notes' => 'Revision note']);
        $resRevision->assertStatus(302);
        $resRevision->assertSessionHas('danger');

        // Status remains unmutated
        $this->assertEquals('pending_restore_approval', $bank->fresh()->status);
    }

    /** 6. After SA approval, RM can publish restored APPROVED repository. */
    public function test_after_sa_approval_rm_can_publish_restored_approved_repository(): void
    {
        $bank = $this->createBank(['status' => 'approved', 'is_published' => false]);

        $res = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $bank->id), [
                'notes' => 'Institutional publishing after restoration review',
            ]);

        $res->assertRedirect();
        $this->assertEquals('published', $bank->fresh()->status);
        $this->assertTrue((bool) $bank->fresh()->is_published);

        // Teacher notified of publication
        $notif = DB::table('notifications')->where('notifiable_id', $this->ownerTeacher->id)->where('type', 'question_bank_published')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('published', $notif->data);
    }

    /** 7. After SA approval, RM can request revision on restored APPROVED repository. */
    public function test_after_sa_approval_rm_can_request_revision(): void
    {
        $bank = $this->createBank(['status' => 'approved', 'is_published' => false]);

        $res = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), [
                'notes' => 'Please update reading passages 3 and 4 before publishing.',
            ]);

        $res->assertStatus(302);
        $this->assertEquals('needs_revision', $bank->fresh()->status);
        $this->assertFalse((bool) $bank->fresh()->is_published);
    }

    /** 8. RM revision produces Teacher revision task. */
    public function test_rm_revision_produces_teacher_revision_task(): void
    {
        $bank = $this->createBank(['status' => 'approved', 'is_published' => false]);

        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), [
                'notes' => 'Needs listening audio link update',
            ]);

        $revisionRequest = RepositoryRevisionRequest::where('question_bank_id', $bank->id)->latest()->first();
        $this->assertNotNull($revisionRequest);
        $this->assertEquals('OPEN', $revisionRequest->status);
        $this->assertEquals($this->ownerTeacher->id, $revisionRequest->teacher_id);
        $this->assertEquals('Needs listening audio link update', $revisionRequest->notes);

        // Teacher receives notification
        $notif = DB::table('notifications')->where('notifiable_id', $this->ownerTeacher->id)->where('type', 'repository_revision_requested')->first();
        $this->assertNotNull($notif);
    }

    /** 9. Teacher can resubmit after RM revision. */
    public function test_teacher_can_resubmit_after_rm_revision(): void
    {
        $bank = $this->createBank(['status' => 'needs_revision', 'is_published' => false]);

        $res = $this->actingAs($this->ownerTeacher)
            ->post(route('admin.question-banks.submit', $bank->id));

        $res->assertRedirect();
        $this->assertEquals('pending_approval', $bank->fresh()->status);
    }

    /** 10. Regular Admin cannot publish Question Bank. */
    public function test_regular_admin_cannot_publish_question_bank(): void
    {
        $bank = $this->createBank(['status' => 'approved', 'is_published' => false]);

        // Attempt via PublicationOperationController route
        $resPub1 = $this->actingAs($this->admin)
            ->post(route('admin.publications.question-banks.publish', $bank->id));
        $resPub1->assertStatus(403);

        // Attempt via QuestionBankController route
        $resPub2 = $this->actingAs($this->admin)
            ->post(route('admin.question-banks.publish', $bank->id));
        $resPub2->assertStatus(403);

        // Status remains approved (not published)
        $this->assertEquals('approved', $bank->fresh()->status);
        $this->assertFalse((bool) $bank->fresh()->is_published);
    }

    /** 11. Regular Admin cannot access Question Bank publication queue. */
    public function test_regular_admin_cannot_access_question_bank_publication_queue(): void
    {
        $res = $this->actingAs($this->admin)
            ->get(route('admin.publications.question-banks'));

        $res->assertStatus(403);
    }

    /** 12. Regular Admin does not receive repository restoration/publication governance notifications. */
    public function test_regular_admin_does_not_receive_repository_notifications(): void
    {
        $bank = $this->createBank(['status' => 'pending_approval']);

        // Super Admin approves initial submission
        $this->actingAs($this->superAdmin)
            ->post(route('admin.approvals.question-banks.approve', $bank->id));

        // Admin received 0 notifications
        $this->assertEquals(0, DB::table('notifications')->where('notifiable_id', $this->admin->id)->count());

        // RM received notification
        $rmNotif = DB::table('notifications')->where('notifiable_id', $this->repoManager->id)->first();
        $this->assertNotNull($rmNotif);
    }

    /** 13. RM can still perform normal Question Bank governance for pending_approval repositories. */
    public function test_rm_can_perform_normal_governance_for_pending_approval(): void
    {
        $bank = $this->createBank(['status' => 'pending_approval']);

        $res = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $bank->id), [
                'notes' => 'Quality verified and approved for publishing',
            ]);

        $res->assertRedirect();
        $this->assertEquals('published', $bank->fresh()->status);
        $this->assertTrue((bool) $bank->fresh()->is_published);
    }

    /** 14. Existing normal Question Bank workflow is not regressed. */
    public function test_existing_normal_question_bank_workflow_is_not_regressed(): void
    {
        // 1. Teacher creates draft
        $bank = QuestionBank::create([
            'title'       => 'Normal IELTS Bank',
            'slug'        => 'normal-ielts-bank',
            'created_by'  => $this->ownerTeacher->id,
            'test_type'   => 'ielts',
            'status'      => 'draft',
        ]);

        // 2. Teacher submits
        $this->actingAs($this->ownerTeacher)->post(route('admin.question-banks.submit', $bank->id));
        $this->assertEquals('pending_approval', $bank->fresh()->status);

        // 3. RM requests revision
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-bank-revision', $bank->id), ['notes' => 'Add 2 more questions']);
        $this->assertEquals('needs_revision', $bank->fresh()->status);

        // 4. Teacher resubmits
        $this->actingAs($this->ownerTeacher)->post(route('admin.question-banks.submit', $bank->id));
        $this->assertEquals('pending_approval', $bank->fresh()->status);

        // 5. RM approves and publishes
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-bank-approve', $bank->id));
        $this->assertEquals('published', $bank->fresh()->status);
        $this->assertTrue((bool) $bank->fresh()->is_published);
    }

    /** 15. Archived repository remains protected from direct publish. */
    public function test_archived_repository_remains_protected_from_direct_publish(): void
    {
        $bank = $this->createBank(['status' => 'archived']);

        // RM cannot publish archived bank
        $resRm = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $bank->id));
        $resRm->assertStatus(302);
        $resRm->assertSessionHas('danger');

        // Admin cannot publish archived bank
        $resAdmin = $this->actingAs($this->admin)
            ->post(route('admin.publications.question-banks.publish', $bank->id));
        $resAdmin->assertStatus(403);

        $this->assertEquals('archived', $bank->fresh()->status);
        $this->assertFalse((bool) $bank->fresh()->is_published);
    }
}
