<?php

namespace Tests\Feature;

use App\Models\GovernanceApprovalTask;
use App\Models\RepositoryRevisionRequest;
use App\Models\RepositoryRevisionTask;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use Tests\TestCase;

class GovernanceApprovalTaskWorkflowTest extends TestCase
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
            'name'   => 'Teacher Submission Author',
            'email'  => 'teacher_sub@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Governance',
            'email'  => 'repomanager_gov@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'IELTS Speaking Interview & Cue Card Prompts',
            'slug'        => 'ielts-speaking-interview-cue-cards',
            'test_type'   => 'ielts',
            'status'      => 'draft',
            'created_by'  => $this->teacher->id,
            'description' => 'IELTS speaking cue card prompts and rubric',
        ]);
    }

    /**
     * TEST 1: Teacher submission creates GovernanceApprovalTask and Repository Manager Notification.
     */
    public function test_teacher_submit_creates_governance_approval_task_and_notification()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $response->assertRedirect(route('admin.question-banks.index'));

        // 1. Verify Bank Status updated to pending_approval
        $this->assertDatabaseHas('question_banks', [
            'id'     => $this->bank->id,
            'status' => 'pending_approval',
        ]);

        // 2. Verify GovernanceApprovalTask created with status = OPEN
        $this->assertDatabaseHas('governance_approval_tasks', [
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'workflow'         => 'APPROVAL',
            'status'           => 'OPEN',
        ]);

        $task = GovernanceApprovalTask::where('question_bank_id', $this->bank->id)->first();
        $this->assertNotNull($task);

        // 3. Verify Repository Manager Notification Created
        $notification = DB::table('notifications')
            ->where('notifiable_id', $this->repoManager->id)
            ->where('type', 'repository_submitted_for_approval')
            ->first();

        $this->assertNotNull($notification);
        $notifData = json_decode($notification->data, true);
        $this->assertEquals('New Repository Submitted', $notifData['title']);
        $this->assertStringContainsString('IELTS Speaking Interview', $notifData['message']);

        // 4. Verify Repository Manager Dashboard shows task in Governance Queue
        $dashboardResponse = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.dashboard'));

        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('IELTS Speaking Interview');
        $dashboardResponse->assertSee('Validation Workspace');
    }

    /**
     * TEST 2: Repository Manager approval closes GovernanceApprovalTask.
     */
    public function test_repository_manager_approval_completes_governance_approval_task()
    {
        // Submit first
        $this->actingAs($this->teacher)->post(route('admin.question-banks.submit', $this->bank->id));

        // Approve bank
        $approveResponse = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $this->bank->id), [
                'notes' => 'Approved for institutional repository.',
            ]);

        $approveResponse->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        // Verify task updated to COMPLETED
        $this->assertDatabaseHas('governance_approval_tasks', [
            'question_bank_id' => $this->bank->id,
            'status'           => 'COMPLETED',
        ]);
    }

    /**
     * TEST 3: Repository Manager revision request closes approval task and creates revision task.
     */
    public function test_repository_manager_revision_request_transitions_approval_task_to_revision_task()
    {
        // Submit first
        $this->actingAs($this->teacher)->post(route('admin.question-banks.submit', $this->bank->id));

        // Request revision
        $revisionResponse = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $this->bank->id), [
                'notes' => 'Please add explanation for question #1.',
            ]);

        $revisionResponse->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        // Verify Approval Task COMPLETED
        $this->assertDatabaseHas('governance_approval_tasks', [
            'question_bank_id' => $this->bank->id,
            'status'           => 'COMPLETED',
        ]);

        // Verify Revision Task Created
        $this->assertDatabaseHas('repository_revision_requests', [
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
        ]);
    }
}
