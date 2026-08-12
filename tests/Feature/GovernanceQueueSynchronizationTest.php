<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GovernanceQueueSynchronizationTest extends TestCase
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
            'name'   => 'Gov Sync Teacher',
            'email'  => 'gov_sync_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Gov Sync Repo Manager',
            'email'  => 'gov_sync_repomanager@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'IELTS Speaking Interview & Cue Card Prompts',
            'slug'        => 'ielts-speaking-interview-cue-card-prompts',
            'test_type'   => 'ielts',
            'status'      => 'draft',
            'created_by'  => $this->teacher->id,
            'description' => 'Official institutional question bank repository for IELTS Speaking.',
        ]);
    }

    /**
     * TEST 1: Initial Teacher Submission creates GovernanceApprovalTask and synchronizes RM Dashboard.
     */
    public function test_1_initial_teacher_submission_creates_open_governance_task_and_syncs_rm_dashboard()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $response->assertRedirect(route('admin.question-banks.index'));

        // Assert QuestionBank status
        $this->bank->refresh();
        $this->assertEquals('pending_approval', $this->bank->status);

        // Assert GovernanceApprovalTask exists with status OPEN
        $task = GovernanceApprovalTask::where('question_bank_id', $this->bank->id)
            ->where('status', 'OPEN')
            ->first();

        $this->assertNotNull($task);
        $this->assertEquals('APPROVAL', $task->workflow);
        $this->assertEquals($this->teacher->id, $task->teacher_id);

        // Assert Repository Manager Dashboard shows Governance Queue = 1 and does NOT duplicate Pending Question Banks card
        $rmDashboard = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $rmDashboard->assertStatus(200);
        $rmDashboard->assertSee('Question Bank Governance (1)');
        $rmDashboard->assertDontSee('Pending Question Banks');
        $rmDashboard->assertSee('IELTS Speaking Interview &amp; Cue Card Prompts', false);
    }

    /**
     * TEST 2: Repository Manager can open and view pending submission in questions approval queue.
     */
    public function test_2_repository_manager_can_open_submission_in_questions_approval_queue()
    {
        $this->bank->update(['status' => 'pending_approval']);
        GovernanceApprovalTask::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'workflow'         => 'APPROVAL',
            'status'           => 'OPEN',
            'submitted_at'     => now(),
        ]);

        $response = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.questions-approval'));
        $response->assertStatus(200);
        $response->assertSee('IELTS Speaking Interview &amp; Cue Card Prompts', false);
        $response->assertSee(route('admin.repository-manager.question-bank-validate', $this->bank->id));
    }

    /**
     * TEST 3: Revision request closes approval task and creates revision task for Teacher.
     */
    public function test_3_revision_request_closes_approval_task_and_creates_revision_request()
    {
        $this->bank->update(['status' => 'pending_approval']);
        $task = GovernanceApprovalTask::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'workflow'         => 'APPROVAL',
            'status'           => 'OPEN',
            'submitted_at'     => now(),
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $this->bank->id), [
                'notes' => 'Please revise rubrics and cue card prompts.',
            ]);

        $response->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        // Assert QuestionBank status is needs_revision
        $this->bank->refresh();
        $this->assertEquals('needs_revision', $this->bank->status);

        // Assert GovernanceApprovalTask is COMPLETED
        $task->refresh();
        $this->assertEquals('COMPLETED', $task->status);

        // Assert RepositoryRevisionRequest is OPEN for Teacher
        $revRequest = RepositoryRevisionRequest::where('question_bank_id', $this->bank->id)
            ->where('status', 'OPEN')
            ->first();

        $this->assertNotNull($revRequest);
        $this->assertEquals($this->teacher->id, $revRequest->teacher_id);
    }

    /**
     * TEST 4: Teacher resubmission creates NEW OPEN GovernanceApprovalTask and updates RM queue.
     */
    public function test_4_teacher_resubmission_creates_new_open_governance_task()
    {
        $this->bank->update(['status' => 'needs_revision']);

        $revRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix rubric option 2.',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revRequest->id));

        $response->assertStatus(302);

        // Assert QuestionBank status is pending_approval
        $this->bank->refresh();
        $this->assertEquals('pending_approval', $this->bank->status);

        // Assert NEW GovernanceApprovalTask is OPEN
        $newTask = GovernanceApprovalTask::where('question_bank_id', $this->bank->id)
            ->where('status', 'OPEN')
            ->first();

        $this->assertNotNull($newTask);
        $this->assertEquals('APPROVAL', $newTask->workflow);

        // Assert RM Dashboard reflects Governance Queue = 1
        $rmDashboard = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $rmDashboard->assertStatus(200);
        $rmDashboard->assertSee('Question Bank Governance (1)');
    }

    /**
     * TEST 5 & TEST 6: IRQA re-scan failure notification goes ONLY to Repository Manager.
     */
    public function test_5_and_6_irqa_rescan_failure_notifies_only_repository_manager()
    {
        $this->bank->update(['status' => 'needs_revision']);

        $revRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix quality issues.',
        ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revRequest->id));

        // Inspect notifications table
        $notifsForRM = DB::table('notifications')
            ->where('notifiable_id', $this->repoManager->id)
            ->get();

        $notifsForTeacher = DB::table('notifications')
            ->where('notifiable_id', $this->teacher->id)
            ->get();

        // Repository Manager receives notification
        $this->assertGreaterThan(0, $notifsForRM->count());

        // Teacher receives NO direct IRQA failure notification
        $teacherFailureNotifs = $notifsForTeacher->filter(function ($n) {
            return str_contains($n->type, 'irqa_failed');
        });

        $this->assertEquals(0, $teacherFailureNotifs->count());
    }

    /**
     * TEST 7: Idempotency - duplicate submission calls do not create duplicate OPEN governance tasks.
     */
    public function test_7_idempotency_prevents_duplicate_open_governance_tasks()
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $openTasksCount = GovernanceApprovalTask::where('question_bank_id', $this->bank->id)
            ->where('status', 'OPEN')
            ->count();

        $this->assertEquals(1, $openTasksCount);
    }

    /**
     * TEST 8: Self-healing repair for orphaned pending approval repositories.
     */
    public function test_8_self_healing_repairs_orphaned_pending_approval_repositories()
    {
        // Simulate orphaned repository (status = pending_approval, but no GovernanceApprovalTask exists)
        $this->bank->update(['status' => 'pending_approval']);
        GovernanceApprovalTask::where('question_bank_id', $this->bank->id)->delete();

        $this->assertEquals(0, GovernanceApprovalTask::where('question_bank_id', $this->bank->id)->count());

        // Access Repository Manager Dashboard (Triggers self-healing)
        $rmDashboard = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.dashboard'));
        $rmDashboard->assertStatus(200);

        // Assert GovernanceApprovalTask was auto-healed
        $repairedTask = GovernanceApprovalTask::where('question_bank_id', $this->bank->id)
            ->where('status', 'OPEN')
            ->first();

        $this->assertNotNull($repairedTask);
        $rmDashboard->assertSee('Question Bank Governance (1)');
    }
}
