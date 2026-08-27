<?php

namespace Tests\Feature;

use App\Models\RepositoryActivityLog;
use App\Models\RepositoryFinding;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryCreationRoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'Boundary Teacher',
            'email'  => 'boundary_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Boundary Manager',
            'email'  => 'boundary_manager@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * TEST 1: Teacher can access repository/question bank workspace page.
     */
    public function test_1_teacher_can_access_question_bank_workspace()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.question-banks.index'));

        $response->assertStatus(200);
    }

    /**
     * TEST 2 & 3: Teacher can successfully create a repository with created_by = Teacher ID.
     */
    public function test_2_and_3_teacher_can_create_repository_and_sets_created_by()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.store'), [
                'title'       => 'Teacher Authored Repository',
                'test_type'   => 'toeic',
                'description' => 'Created by teacher.',
            ]);

        $response->assertStatus(302);

        $bank = QuestionBank::where('title', 'Teacher Authored Repository')->first();
        $this->assertNotNull($bank);
        $this->assertEquals($this->teacher->id, $bank->created_by);
        $this->assertEquals('draft', is_object($bank->status) ? $bank->status->value : $bank->status);
    }

    /**
     * TEST 4, 5 & 6: Repository Manager cannot create a repository via POST request and receives 403 Forbidden.
     */
    public function test_4_5_and_6_repo_manager_cannot_create_repository()
    {
        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.question-banks.store'), [
                'title'       => 'Unauthorized RM Repository',
                'test_type'   => 'toeic',
                'description' => 'Attempted by RM.',
            ]);

        $response->assertStatus(403);

        $bank = QuestionBank::where('title', 'Unauthorized RM Repository')->first();
        $this->assertNull($bank);
    }

    /**
     * TEST 7: Repository Manager can still access Validation Workspace.
     */
    public function test_7_repo_manager_can_access_validation_workspace()
    {
        $bank = QuestionBank::create([
            'title'           => 'Governance Audit Bank',
            'slug'            => 'gov-audit-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.question-bank-validate', $bank->id));

        $response->assertStatus(200);
    }

    /**
     * TEST 8: Repository Manager can still request revision.
     */
    public function test_8_repo_manager_can_request_revision()
    {
        $bank = QuestionBank::create([
            'title'           => 'Revision Target Bank',
            'slug'            => 'rev-target-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), [
                'notes' => 'Please fix question alignment.',
            ]);

        $response->assertStatus(302);

        $bank->refresh();
        $this->assertEquals('needs_revision', is_object($bank->status) ? $bank->status->value : $bank->status);

        $revReq = RepositoryRevisionRequest::where('question_bank_id', $bank->id)->first();
        $this->assertNotNull($revReq);
        $this->assertEquals($this->teacher->id, $revReq->teacher_id);
        $this->assertEquals($this->repoManager->id, $revReq->requested_by_id);
    }

    /**
     * TEST 9: Repository Manager can still approve/reject.
     */
    public function test_9_repo_manager_can_approve_repository()
    {
        $bank = QuestionBank::create([
            'title'           => 'Approve Target Bank',
            'slug'            => 'app-target-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $bank->id), [
                'decision_notes' => 'Approved by governance.',
            ]);

        $response->assertStatus(302);

        $bank->refresh();
        $this->assertEquals('published', is_object($bank->status) ? $bank->status->value : $bank->status);
        $this->assertEquals($this->teacher->id, $bank->created_by);
    }

    /**
     * TEST 10 & 11: Teacher can see revision request and resubmit.
     */
    public function test_10_and_11_teacher_can_process_revision_and_resubmit()
    {
        $bank = QuestionBank::create([
            'title'           => 'Teacher Revision Bank',
            'slug'            => 'teacher-rev-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'needs_revision',
            'created_by'      => $this->teacher->id,
        ]);

        $question = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Sample prompt',
            'question_type'    => 'essay',
            'explanation'      => 'Explanation',
            'difficulty'       => 'medium',
            'points'           => 5,
        ]);

        $revReq = RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix item',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revReq->id,
            'question_bank_id'               => $bank->id,
            'question_id'                    => $question->id,
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Fix item',
            'status'                         => 'CLOSED',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revReq->id));

        $response->assertStatus(200);

        $resubmitResponse = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revReq->id));

        $resubmitResponse->assertStatus(302);

        $bank->refresh();
        $revReq->refresh();

        $this->assertEquals('pending_approval', is_object($bank->status) ? $bank->status->value : $bank->status);
        $this->assertEquals('RESUBMITTED', $revReq->status);
        $this->assertEquals($this->teacher->id, $bank->created_by);
    }

    /**
     * TEST 12, 13 & 14: RM review, approval, and revision operations NEVER alter created_by.
     */
    public function test_12_13_and_14_rm_actions_do_not_alter_created_by()
    {
        $bank = QuestionBank::create([
            'title'           => 'Ownership Protection Bank',
            'slug'            => 'owner-prot-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        // RM requests revision
        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), ['notes' => 'Revision note']);

        $bank->refresh();
        $this->assertEquals($this->teacher->id, $bank->created_by);

        // Teacher resubmits for approval
        $bank->update(['status' => 'pending_approval']);

        // RM approves
        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-approve', $bank->id), ['decision_notes' => 'Approve note']);

        $bank->refresh();
        $this->assertEquals($this->teacher->id, $bank->created_by);
    }

    /**
     * TEST 15: No IRQA functionality regression occurs during governance operations.
     */
    public function test_15_irqa_functionality_remains_intact()
    {
        $bank = QuestionBank::create([
            'title'           => 'IRQA Intact Test Bank',
            'slug'            => 'irqa-intact-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'needs_revision',
            'created_by'      => $this->teacher->id,
        ]);

        RepositoryFinding::create([
            'question_bank_id' => $bank->id,
            'finding_code'     => 'IRQA_WARN',
            'title'            => 'Missing Category association',
            'description'      => 'Missing Category association',
            'severity'         => 'high',
            'status'           => 'OPEN',
        ]);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.academic-library.quality', ['from' => 'dashboard']));

        $response->assertStatus(200);
        $this->assertStringContainsString('IRQA', $response->getContent());
    }

    /**
     * TEST 16: QuestionBankSeeder guarantees creator has teacher role and never resolves to RM.
     */
    public function test_16_question_bank_seeder_assigns_teacher_role_creator()
    {
        $this->seed(\App\Modules\QuestionBank\Database\Seeders\QuestionBankSeeder::class);

        $teacher = User::where('email', 'teacher@icedu.org')->first();
        $this->assertNotNull($teacher);
        $this->assertTrue($teacher->hasRole('teacher'));
        $this->assertFalse($teacher->hasRole('repository-manager'));
    }

    /**
     * TEST 17: RepositoryRevisionRequest recipient teacher_id matches repository author, requested_by_id matches RM.
     */
    public function test_17_revision_request_recipient_reconciliation()
    {
        $bank = QuestionBank::create([
            'title'           => 'Reconciliation Bank',
            'slug'            => 'reconcile-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), ['notes' => 'Revision note']);

        $revReq = RepositoryRevisionRequest::where('question_bank_id', $bank->id)->first();
        $this->assertNotNull($revReq);
        $this->assertEquals($this->teacher->id, $revReq->teacher_id);
        $this->assertEquals($this->repoManager->id, $revReq->requested_by_id);

        $dashboardResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'));
        $dashboardResponse->assertStatus(200);

        $revisionCenterResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.index'));
        $revisionCenterResponse->assertStatus(200);
        $this->assertStringContainsString('Reconciliation Bank', $revisionCenterResponse->getContent());
    }

    /**
     * TEST 18: RM repeating Request Revision reuses active request and prevents duplicate active tasks.
     */
    public function test_18_repeated_request_revision_reuses_active_request()
    {
        $bank = QuestionBank::create([
            'title'           => 'Idempotency Target Bank',
            'slug'            => 'idempotency-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'pending_approval',
            'created_by'      => $this->teacher->id,
        ]);

        // First revision request
        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), [
                'notes' => 'First revision note',
            ]);

        $activeCountInitial = RepositoryRevisionRequest::where('question_bank_id', $bank->id)
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->count();
        $this->assertEquals(1, $activeCountInitial);

        $initialRequest = RepositoryRevisionRequest::where('question_bank_id', $bank->id)
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->first();

        // Second repeated revision request for the SAME bank while OPEN
        $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-bank-revision', $bank->id), [
                'notes' => 'Second updated revision note',
            ]);

        $activeCountAfterSecond = RepositoryRevisionRequest::where('question_bank_id', $bank->id)
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->count();
        $this->assertEquals(1, $activeCountAfterSecond);

        $reusedRequest = RepositoryRevisionRequest::where('question_bank_id', $bank->id)
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->first();

        // Assert request ID was reused and notes updated
        $this->assertEquals($initialRequest->id, $reusedRequest->id);
        $this->assertEquals('Second updated revision note', $reusedRequest->notes);

        // Assert Teacher Revision Center shows EXACTLY ONE active task card for this bank
        $revisionCenterResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.index'));
        $revisionCenterResponse->assertStatus(200);

        // Count occurrences of card ID/title for this bank in Revision Center content
        $content = $revisionCenterResponse->getContent();
        $this->assertEquals(1, substr_count($content, 'Idempotency Target Bank'));
    }

    /**
     * TEST 19: Focused editor correctly renders DifficultyLevel Enum and balanced difficulty closes finding #2.
     */
    public function test_19_focused_editor_renders_difficulty_enum_correctly_and_balances_finding()
    {
        $bank = QuestionBank::create([
            'title'           => 'Enum Render Test Bank',
            'slug'            => 'enum-render-bank-' . uniqid(),
            'test_type'       => 'toeic',
            'current_version' => '1.0',
            'status'          => 'needs_revision',
            'created_by'      => $this->teacher->id,
        ]);

        $q1 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Prompt 1',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 5,
        ]);

        $q2 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Prompt 2',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 5,
        ]);

        $revReq = RepositoryRevisionRequest::create([
            'question_bank_id' => $bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Balance difficulty',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revReq->id,
            'question_bank_id'               => $bank->id,
            'question_id'                    => $q1->id,
            'finding_type'                   => 'quality_warning',
            'severity'                       => 'high',
            'feedback'                       => 'Repository needs more balanced question difficulty (currently 100% medium)',
            'status'                         => 'OPEN',
        ]);

        // 1. Verify Focused Editor HTML renders 'selected' on option value="medium" (NOT easy)
        $editorResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revReq->id, $item->id]));

        $editorResponse->assertStatus(200);
        $editorResponse->assertSee('<option value="medium" selected', false);

        // 2. Update Question #1 to 'easy' via updateQuestion endpoint
        $updateResponse = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.update-question', [$revReq->id, $item->id]), [
                'question_id'    => $q1->id,
                'prompt'         => $q1->prompt,
                'question_type'  => 'multiple_choice',
                'difficulty'     => 'easy',
                'points'         => 5,
            ]);

        $updateResponse->assertStatus(302);

        $q1->refresh();
        $this->assertEquals('easy', is_object($q1->difficulty) ? $q1->difficulty->value : $q1->difficulty);

        // Assert item status updated to CLOSED
        $item->refresh();
        $this->assertEquals('CLOSED', $item->status);
    }
}
