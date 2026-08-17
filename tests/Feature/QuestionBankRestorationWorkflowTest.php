<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\RepositoryActivityLog;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use Tests\TestCase;

class QuestionBankRestorationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerTeacher;
    protected User $otherTeacher;
    protected User $repoManager;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ownerTeacher = User::factory()->create(['name' => 'Owner Teacher', 'email' => 'owner_teacher@icedu.org']);
        $this->ownerTeacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create(['name' => 'Other Teacher', 'email' => 'other_teacher@icedu.org']);
        $this->otherTeacher->assignRole('teacher');

        $this->repoManager = User::factory()->create(['name' => 'Repo Manager', 'email' => 'repo_manager@icedu.org']);
        $this->repoManager->assignRole('repository-manager');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin', 'email' => 'super_admin@icedu.org']);
        $this->superAdmin->assignRole('super-admin');
    }

    protected function createBank(array $attributes = []): QuestionBank
    {
        return QuestionBank::create(array_merge([
            'title'           => 'Restoration Test Bank',
            'slug'            => 'restoration-test-bank-' . uniqid(),
            'code'            => 'QB-RST-' . strtoupper(substr(uniqid(), 0, 4)),
            'test_type'       => 'toeic',
            'description'     => 'Restoration workflow test repository.',
            'status'          => 'archived',
            'created_by'      => $this->ownerTeacher->id,
            'is_published'    => false,
        ], $attributes));
    }

    /** 1 & 5 & 6 & 7 & 8: Owner Teacher can request restore from archived, updating status to pending_restore_approval, persisting reason, creating task, and notifying Super Admin. */
    public function test_owner_teacher_can_request_restore_from_archived()
    {
        $bank = $this->createBank(['status' => 'archived']);

        $response = $this->actingAs($this->ownerTeacher)
            ->post(route('admin.question-banks.request-restore', $bank->id), [
                'reason' => 'Need to update questions for upcoming semester',
            ]);

        $response->assertStatus(302);
        $bank->refresh();

        $this->assertEquals('pending_restore_approval', $bank->status);

        // Verify task creation
        $this->assertEquals(1, GovernanceApprovalTask::where('question_bank_id', $bank->id)->where('workflow', 'APPROVAL')->where('status', 'OPEN')->count());

        // Verify activity log note persistence
        $log = RepositoryActivityLog::where('resource_id', $bank->id)->where('action', 'restore_requested')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('Need to update questions for upcoming semester', $log->approval_note);

        // Verify Super Admin notification
        $notification = DB::table('notifications')->where('notifiable_id', $this->superAdmin->id)->where('type', 'question_bank_restoration_requested')->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Need to update questions for upcoming semester', $notification->data);
    }

    /** 2: Non-owner Teacher cannot request restore. */
    public function test_non_owner_teacher_cannot_request_restore()
    {
        $bank = $this->createBank(['status' => 'archived']);

        $response = $this->actingAs($this->otherTeacher)
            ->post(route('admin.question-banks.request-restore', $bank->id), [
                'reason' => 'Unauthorized attempt',
            ]);

        $response->assertStatus(403);
        $this->assertEquals('archived', $bank->fresh()->status);
    }

    /** 3: Repository Manager cannot request restore. */
    public function test_repository_manager_cannot_request_restore()
    {
        $bank = $this->createBank(['status' => 'archived']);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.question-banks.request-restore', $bank->id), [
                'reason' => 'RM attempt',
            ]);

        $response->assertStatus(403);
        $this->assertEquals('archived', $bank->fresh()->status);
    }

    /** 4: Non-archived repository cannot request restore. */
    public function test_non_archived_repository_cannot_request_restore()
    {
        $nonArchivedStatuses = ['draft', 'published', 'approved', 'pending_approval', 'needs_revision', 'pending_restore_approval'];

        foreach ($nonArchivedStatuses as $status) {
            $bank = $this->createBank(['status' => $status]);

            $response = $this->actingAs($this->ownerTeacher)
                ->post(route('admin.question-banks.request-restore', $bank->id), [
                    'reason' => 'Invalid source state attempt',
                ]);

            $response->assertStatus(302);
            $response->assertSessionHas('danger');
            $this->assertEquals($status, $bank->fresh()->status);
        }
    }

    /** 9 & 10 & 11 & 12: Super Admin can approve pending restore, resulting in approved, resolving task, and notifying author. */
    public function test_super_admin_can_approve_pending_restore()
    {
        $bank = $this->createBank(['status' => 'pending_restore_approval']);
        GovernanceApprovalTask::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->ownerTeacher->id,
            'workflow'         => 'APPROVAL',
            'status'           => 'OPEN',
            'submitted_at'     => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.question-banks.approve-restore', $bank->id));

        $response->assertStatus(302);
        $bank->refresh();

        $this->assertEquals('approved', $bank->status);
        $this->assertFalse((bool)$bank->is_published);

        // Task resolved
        $this->assertEquals(0, GovernanceApprovalTask::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
        $this->assertEquals(1, GovernanceApprovalTask::where('question_bank_id', $bank->id)->where('status', 'COMPLETED')->count());

        // Author notified
        $notification = DB::table('notifications')->where('notifiable_id', $this->ownerTeacher->id)->where('type', 'question_bank_restoration_approved')->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Repository Restoration Approved', $notification->data);
    }

    /** 13 & 14 & 15 & 16 & 17: Super Admin can reject pending restore, returning to archived, persisting reason, resolving task, and notifying author. */
    public function test_super_admin_can_reject_pending_restore()
    {
        $bank = $this->createBank(['status' => 'pending_restore_approval']);
        GovernanceApprovalTask::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->ownerTeacher->id,
            'workflow'         => 'APPROVAL',
            'status'           => 'OPEN',
            'submitted_at'     => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.question-banks.reject-restore', $bank->id), [
                'reason' => 'Repository content is obsolete',
            ]);

        $response->assertStatus(302);
        $bank->refresh();

        $this->assertEquals('archived', $bank->status);
        $this->assertFalse((bool)$bank->is_published);

        // Task resolved as REJECTED
        $this->assertEquals(0, GovernanceApprovalTask::where('question_bank_id', $bank->id)->where('status', 'OPEN')->count());
        $this->assertEquals(1, GovernanceApprovalTask::where('question_bank_id', $bank->id)->where('status', 'REJECTED')->count());

        // Activity log persists rejection reason
        $log = RepositoryActivityLog::where('resource_id', $bank->id)->where('action', 'restore_rejected')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('Repository content is obsolete', $log->approval_note);

        // Author notified with rejection reason
        $notification = DB::table('notifications')->where('notifiable_id', $this->ownerTeacher->id)->where('type', 'question_bank_restoration_rejected')->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Repository content is obsolete', $notification->data);
    }

    /** 18: Super Admin cannot approve a repository not in pending_restore_approval status. */
    public function test_super_admin_cannot_approve_repository_not_in_pending_restore_approval()
    {
        $invalidStatuses = ['archived', 'draft', 'published', 'approved', 'pending_approval'];

        foreach ($invalidStatuses as $status) {
            $bank = $this->createBank(['status' => $status]);

            $response = $this->actingAs($this->superAdmin)
                ->post(route('admin.question-banks.approve-restore', $bank->id));

            $response->assertStatus(302);
            $response->assertSessionHas('danger');
            $this->assertEquals($status, $bank->fresh()->status);
        }
    }

    /** 19: Repository Manager cannot bypass restoration or mutate archived/pending_restore repositories. */
    public function test_repository_manager_cannot_bypass_restoration()
    {
        $archivedBank = $this->createBank(['status' => 'archived']);
        $pendingRestoreBank = $this->createBank(['status' => 'pending_restore_approval']);

        foreach ([$archivedBank, $pendingRestoreBank] as $bank) {
            $initialStatus = $bank->status;

            // RM Approve attempt
            $resp1 = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-bank-approve', $bank->id));
            $resp1->assertStatus(302);
            $resp1->assertSessionHas('danger');

            // RM Revision attempt
            $resp2 = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-bank-revision', $bank->id), ['notes' => 'RM notes']);
            $resp2->assertStatus(302);
            $resp2->assertSessionHas('danger');

            // RM Reject attempt
            $resp3 = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-bank-reject', $bank->id), ['notes' => 'RM notes']);
            $resp3->assertStatus(302);
            $resp3->assertSessionHas('danger');

            // RM Restore approve attempt
            $resp4 = $this->actingAs($this->repoManager)->post(route('admin.question-banks.approve-restore', $bank->id));
            $resp4->assertStatus(403);

            // Verify status was never mutated by RM
            $this->assertEquals($initialStatus, $bank->fresh()->status);
        }
    }

    /** 20: pending_restore_approval renders correctly as "Pending Restore". */
    public function test_pending_restore_approval_renders_correctly_as_pending_restore()
    {
        $bank = $this->createBank(['status' => 'pending_restore_approval']);

        $responseIndex = $this->actingAs($this->superAdmin)->get(route('admin.question-banks.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Pending Restore');

        $responseDash = $this->actingAs($this->ownerTeacher)->get(route('teacher.dashboard'));
        $responseDash->assertStatus(200);
        $responseDash->assertSee('Pending Restore');
    }

    /** 21: Archived Teacher UI shows Request Restore. */
    public function test_archived_teacher_ui_shows_request_restore()
    {
        $bank = $this->createBank(['status' => 'archived']);

        $responseIndex = $this->actingAs($this->ownerTeacher)->get(route('admin.question-banks.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Request Restore');

        $responseDash = $this->actingAs($this->ownerTeacher)->get(route('teacher.dashboard'));
        $responseDash->assertStatus(200);
        $responseDash->assertSee('Request Restore');
    }

    /** 22: Pending Restore Teacher UI does not show Request Restore. */
    public function test_pending_restore_teacher_ui_does_not_show_request_restore()
    {
        $bank = $this->createBank(['status' => 'pending_restore_approval']);

        $responseIndex = $this->actingAs($this->ownerTeacher)->get(route('admin.question-banks.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertDontSee('Request Restore');

        $responseDash = $this->actingAs($this->ownerTeacher)->get(route('teacher.dashboard'));
        $responseDash->assertStatus(200);
        $responseDash->assertDontSee('Request Restore');
    }

    /** 23: Request Restore UI does not contain native browser dialogs (prompt/confirm) and uses IAP inline confirmation panel. */
    public function test_request_restore_ui_does_not_contain_native_browser_dialogs_and_uses_iap_confirmation()
    {
        $bank = $this->createBank(['status' => 'archived']);

        foreach ([route('admin.question-banks.index'), route('teacher.dashboard')] as $route) {
            $response = $this->actingAs($this->ownerTeacher)->get($route);
            $response->assertStatus(200);
            $content = $response->getContent();

            $this->assertStringNotContainsString('window.prompt', $content);
            $this->assertStringNotContainsString('window.confirm', $content);
            $this->assertStringNotContainsString('prompt(', $content);
            $this->assertStringNotContainsString('confirm(', $content);
            $this->assertStringContainsString('inline-restore-panel', $content);
        }
    }
}
