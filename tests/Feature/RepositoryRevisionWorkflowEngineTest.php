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
use Tests\TestCase;

class RepositoryRevisionWorkflowEngineTest extends TestCase
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
            'name'   => 'Teacher RRWE Enterprise Test',
            'email'  => 'teacher_rrwe_ent@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager RRWE Enterprise Test',
            'email'  => 'repomanager_rrwe_ent@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'RRWE Enterprise Test Bank',
            'slug'        => 'rrwe-ent-test-bank',
            'test_type'   => 'toeic',
            'status'      => 'pending_approval',
            'created_by'  => $this->teacher->id,
            'description' => 'Test Repository Enterprise',
        ]);

        $this->question = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'What is the capital of France?',
            'question_type'    => 'multiple_choice',
            'explanation'      => 'Detailed explanation provided.',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $this->question->id,
            'label'       => 'A',
            'content'     => 'Paris',
            'is_correct'  => true,
        ]);

        QuestionChoice::create([
            'question_id' => $this->question->id,
            'label'       => 'B',
            'content'     => 'London',
            'is_correct'  => false,
        ]);
    }

    /**
     * TEST 1: Repository Manager requesting revision creates RepositoryRevisionRequest and Items.
     */
    public function test_1_repository_manager_request_revision_creates_governance_entities()
    {
        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $this->bank->id), [
                'notes' => 'Please add category association and double check questions.',
            ]);

        $response->assertRedirect(route('admin.repository-manager.review-complete', $this->bank->id));

        $this->assertDatabaseHas('question_banks', [
            'id'     => $this->bank->id,
            'status' => 'needs_revision',
        ]);

        $this->assertDatabaseHas('repository_revision_requests', [
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
        ]);
    }

    /**
     * TEST 2: Focused Question Editor Mode renders Repository Revision Mode banner.
     */
    public function test_2_focused_question_editor_renders_revision_mode_banner()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Missing category tag.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Missing Category association',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]));

        $response->assertStatus(200);
        $response->assertSee('Focused Repository Revision Mode');
        $response->assertSee('Missing Category association');

        $this->assertDatabaseHas('repository_activity_logs', [
            'resource_id' => (string) $this->question->id,
            'action'      => 'teacher_edited_question',
        ]);
    }

    /**
     * TEST 3: Auto validate blocks resubmit if open findings exist, and allows when all closed.
     */
    public function test_3_auto_validate_blocks_resubmit_when_open_findings_exist()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Review required.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Unresolved test issue',
            'status'                         => 'OPEN',
        ]);

        // Attempt resubmit with OPEN item -> Should be blocked
        $blockedResponse = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $blockedResponse->assertSessionHas('error');

        // Mark item CLOSED -> Should succeed
        $item->status = 'CLOSED';
        $item->save();

        $successResponse = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $successResponse->assertRedirect(route('teacher.repository-revisions.index'));
        $successResponse->assertSessionHas('success', 'Repository successfully resubmitted. Waiting Repository Manager review.');
    }
}
