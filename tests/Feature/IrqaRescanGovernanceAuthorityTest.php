<?php

namespace Tests\Feature;

use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IrqaRescanGovernanceAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;
    protected QuestionBank $bank;
    protected Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Teacher IRQA Authority Test',
            'email'  => 'teacher_irqa_auth@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager IRQA Authority Test',
            'email'  => 'repomanager_irqa_auth@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'IRQA Authority Test Bank',
            'slug'        => 'irqa-auth-test-bank',
            'test_type'   => 'toeic',
            'status'      => 'needs_revision',
            'created_by'  => $this->teacher->id,
            'description' => 'Test Repository for IRQA Authority',
        ]);

        $this->question = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Which option is correct?',
            'question_type'    => 'multiple_choice',
            'explanation'      => 'Detailed explanation provided.',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $this->question->id,
            'label'       => 'A',
            'content'     => 'Option A',
            'is_correct'  => true,
        ]);
    }

    /**
     * TEST 1: Resubmission notifies Repository Manager of IRQA status and Teacher only of reception.
     */
    public function test_1_resubmission_never_sends_direct_irqa_failure_to_teacher()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Quality fixes required.',
        ]);

        RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Missing Category association',
            'status'                         => 'CLOSED',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $response->assertRedirect(route('teacher.repository-revisions.index'));
        $response->assertSessionHas('success', 'Repository successfully resubmitted. Waiting Repository Manager review.');

        // Verify Teacher Notification (Neutral only)
        $teacherNotif = DB::table('notifications')
            ->where('notifiable_id', $this->teacher->id)
            ->where('type', 'repository_resubmission_received')
            ->first();

        $this->assertNotNull($teacherNotif);
        $this->assertStringContainsString('Waiting Repository Manager review', $teacherNotif->data);

        // Verify Repository Manager Notification (IRQA Report Destination)
        $rmNotif = DB::table('notifications')
            ->where('notifiable_id', $this->repoManager->id)
            ->first();

        $this->assertNotNull($rmNotif);

        // Verify Governance Audit Logs
        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_id' => $this->bank->id,
            'action'      => 'teacher_resubmitted_repository',
        ]);

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_id' => $this->bank->id,
            'action'      => 'irqa_scan_started',
        ]);

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_id' => $this->bank->id,
            'action'      => 'irqa_scan_completed',
        ]);
    }
}
