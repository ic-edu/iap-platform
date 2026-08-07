<?php

namespace Tests\Feature;

use App\Models\RepositoryRevisionRequest;
use App\Models\RepositoryRevisionTask;
use App\Models\User;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use Tests\TestCase;

class RepositoryRevisionTaskDeliveryTest extends TestCase
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
            'name'   => 'Teacher Task Delivery Author',
            'email'  => 'teacher_delivery@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Reviewer',
            'email'  => 'repomanager_reviewer@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'High School Chemistry Bank',
            'slug'        => 'high-school-chemistry-bank',
            'test_type'   => 'toeic',
            'status'      => 'pending_approval',
            'created_by'  => $this->teacher->id,
            'description' => 'Chemistry questions for grade 11',
        ]);
    }

    /**
     * TEST: Complete end-to-end task delivery from Repository Manager request to Teacher Dashboard & Revision Center.
     */
    public function test_request_revision_delivers_task_and_notification_to_teacher()
    {
        // 1. Repository Manager requests revision
        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $this->bank->id), [
                'notes' => 'Please add explanation for question #1 and select a category.',
            ]);

        $response->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        // 2. Verify Repository status updated to needs_revision
        $this->assertDatabaseHas('question_banks', [
            'id'     => $this->bank->id,
            'status' => 'needs_revision',
        ]);

        // 3. Verify RepositoryRevisionTask / RepositoryRevisionRequest created with status = OPEN and teacher_id
        $this->assertDatabaseHas('repository_revision_requests', [
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
        ]);

        $task = RepositoryRevisionTask::where('question_bank_id', $this->bank->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals($this->teacher->id, $task->teacher_id);
        $this->assertEquals($this->repoManager->id, $task->requested_by_id);
        $this->assertEquals('OPEN', $task->status);

        // 4. Verify Teacher Notification Created
        $notification = DB::table('notifications')
            ->where('notifiable_id', $this->teacher->id)
            ->where('type', 'repository_revision_requested')
            ->first();

        $this->assertNotNull($notification);
        $notifData = json_decode($notification->data, true);
        $this->assertEquals('Repository Revision Requested', $notifData['title']);
        $this->assertStringContainsString('High School Chemistry Bank', $notifData['message']);

        // 5. Verify Teacher Revision Center displays the task immediately
        $centerResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.index'));

        $centerResponse->assertStatus(200);
        $centerResponse->assertSee('High School Chemistry Bank');
        $centerResponse->assertSee('Open Revision Workspace');

        // 6. Verify Teacher Dashboard shows Repository Revisions counter = 1
        $dashboardResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'));

        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Repository Revisions');
        $dashboardResponse->assertSee('1');
    }
}
