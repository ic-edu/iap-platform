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
            'name'   => 'Teacher RRWE Test',
            'email'  => 'teacher_rrwe@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager RRWE Test',
            'email'  => 'repomanager_rrwe@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bank = QuestionBank::create([
            'title'       => 'RRWE Test Question Bank 01',
            'slug'        => 'rrwe-test-qb-01',
            'test_type'   => 'toeic',
            'status'      => 'pending_approval',
            'created_by'  => $this->teacher->id,
            'description' => 'Test Repository',
        ]);

        $this->question = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'What is the correct answer?',
            'question_type'    => 'multiple_choice',
            'explanation'      => 'Comprehensive explanation text.',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $this->question->id,
            'label'       => 'A',
            'content'     => 'Option A',
            'is_correct'  => true,
        ]);

        QuestionChoice::create([
            'question_id' => $this->question->id,
            'label'       => 'B',
            'content'     => 'Option B',
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

        $response->assertRedirect(route('admin.repository-manager.questions-approval'));

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

        $this->assertDatabaseHas('repository_revision_items', [
            'question_bank_id' => $this->bank->id,
            'status'           => 'OPEN',
        ]);
    }

    /**
     * TEST 2: Teacher Revision Center displays pending repository revision request.
     */
    public function test_2_teacher_revision_center_displays_pending_revisions()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Missing category tag.',
        ]);

        RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Missing Category association',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.repository-revisions.index'));

        $response->assertStatus(200);
        $response->assertSee($this->bank->title);
        $response->assertSee('Missing category tag.');

        $showResponse = $this->actingAs($this->teacher)->get(route('teacher.repository-revisions.show', $revisionRequest->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Missing Category association');
    }

    /**
     * TEST 3: Teacher resubmitting repository triggers Automatic IRQA Re-Scan and closes fixed findings.
     */
    public function test_3_teacher_resubmission_triggers_automatic_irqa_rescan_and_closes_findings()
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
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Resolved test issue',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $response->assertRedirect(route('teacher.repository-revisions.index'));

        $this->assertDatabaseHas('repository_revision_requests', [
            'id'     => $revisionRequest->id,
            'status' => 'RESUBMITTED',
        ]);

        $this->assertDatabaseHas('question_banks', [
            'id'     => $this->bank->id,
            'status' => 'pending_approval',
        ]);

        $item->refresh();
        $this->assertEquals('CLOSED', $item->status);
    }
}
