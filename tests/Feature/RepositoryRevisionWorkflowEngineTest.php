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

    /**
     * TEST 4: Finding Routing Matrix in Revision Task View.
     * - Question-linked finding (question_id != null) -> Focused Question Editor
     * - Repository Description finding (question_id == null) -> Repository Details (action=edit_metadata)
     * - Version finding (question_id == null) -> Repository Details (action=edit_metadata)
     * - Category finding (question_id == null) -> Repository Details (action=edit_category)
     * - Insufficient question count finding (question_id == null) -> Add Questions (action=add_question)
     */
    public function test_4_finding_routing_matrix_in_revision_task_view()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Multiple governance findings need attention.',
        ]);

        // Item 1: Question-linked finding
        $itemQuestion = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question 1 is missing explanation.',
            'status'                         => 'OPEN',
        ]);

        // Item 2: Repository Description finding
        $itemDesc = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Repository Description',
            'status'                         => 'OPEN',
        ]);

        // Item 3: Version finding
        $itemVer = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Version',
            'status'                         => 'OPEN',
        ]);

        // Item 4: Category finding
        $itemCat = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing Category association',
            'status'                         => 'OPEN',
        ]);

        // Item 5: Question count finding
        $itemCount = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Insufficient question count (0 questions in repository)',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revisionRequest->id));

        $response->assertStatus(200);

        // 1. Question finding renders Focused Question Editor link & label
        $response->assertSee(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $itemQuestion->id]));
        $response->assertSee('🛠 Open Focused Question Editor →');

        // 2. Repository Description finding renders Edit Repository Details link & label (action=edit_metadata)
        $response->assertSee(route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
            'action'              => 'edit_metadata',
        ]));
        $response->assertSee('🛠 Edit Repository Details →');

        // 3. Category finding renders Edit Repository Details link (action=edit_category)
        $response->assertSee(route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
            'action'              => 'edit_category',
        ]));

        // 4. Question count finding renders Add / Manage Questions link & label (action=add_question)
        $response->assertSee(route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
            'action'              => 'add_question',
        ]));
        $response->assertSee('➕ Add / Manage Questions →');
    }

    /**
     * TEST 5: Backend Safety Guard — Direct hit to editQuestion with null question_id cleanly redirects without 500 TypeError.
     */
    public function test_5_edit_question_safely_redirects_when_question_id_is_null()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Missing repository description.',
        ]);

        $itemNoQuestion = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Repository Description',
            'status'                         => 'OPEN',
        ]);

        // Attempt direct access to edit-question route with null question item
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $itemNoQuestion->id]));

        // MUST NOT 500 error; MUST cleanly redirect to repository authoring workspace
        $response->assertRedirect(route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
            'action'              => 'edit_metadata',
        ]));
        $response->assertSessionHas('info');
    }

    /**
     * TEST 6: Contextual Back navigation from Question Bank workspace returns to Revision Task.
     */
    public function test_6_back_navigation_from_question_bank_workspace_returns_to_revision_task()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix repository details.',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('← Back to Revision Task');
        $response->assertSee(route('teacher.repository-revisions.show', $revisionRequest->id));
    }

    /**
     * TEST 7: Essay question can be saved without answer choices or correct answer, and closes finding.
     */
    public function test_7_essay_question_can_save_without_answer_choices_or_correct_answer()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Revise essay prompt and explanation.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question is missing a designated correct answer choice',
            'status'                         => 'OPEN',
        ]);

        // Save as ESSAY with no choices submitted
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.update-question', [$revisionRequest->id, $item->id]), [
                'question_id'            => $this->question->id,
                'prompt'                 => 'Discuss the economic impacts of global trade.',
                'question_type'          => 'essay',
                'explanation'            => 'Grading is based on clarity, structure, and supporting evidence.',
                'difficulty'             => 'hard',
                'points'                 => 10,
                'reference_answer_text'  => 'Model essay outline...',
            ]);

        $response->assertRedirect(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]));
        $this->assertDatabaseHas('questions', [
            'id'            => $this->question->id,
            'question_type' => 'essay',
            'prompt'        => 'Discuss the economic impacts of global trade.',
        ]);

        // Verify choices were deleted for essay
        $this->assertEquals(0, $this->question->fresh()->choices()->count());

        // Verify item is marked CLOSED
        $this->assertEquals('CLOSED', $item->fresh()->status);
    }

    /**
     * TEST 8: MCQ question still requires answer choices and correct answer in live validation checklist.
     */
    public function test_8_mcq_still_requires_answer_choices_and_correct_answer()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix choices.',
        ]);

        // Question with no choices
        $qNoChoices = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'MCQ without choices',
            'question_type'    => 'multiple_choice',
            'explanation'      => 'Some explanation',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $qNoChoices->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question has no answer choices attached',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]));

        $response->assertStatus(200);
        // Live validation checklist must show Answer Choices and Correct Answer as required for MCQ
        $response->assertSee('Answer Choices');
        $response->assertSee('Correct Answer');
        $response->assertSee('✖ Answer Choices');
        $response->assertSee('✖ Correct Answer');
    }

    /**
     * TEST 9: Essay live validation checklist does NOT include or fail answer-choice / correct-answer checks.
     */
    public function test_9_essay_live_validation_checklist_does_not_fail_choice_checks()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Check essay checklist.',
        ]);

        $essayQ = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Write an essay comparing two literary works.',
            'question_type'    => 'essay',
            'explanation'      => 'Clear rubric provided.',
            'difficulty'       => 'hard',
            'points'           => 5,
        ]);

        // Category set on bank
        $category = \App\Models\AclCategory::create([
            'name'      => 'Literature & Composition',
            'slug'      => 'literature-composition-' . uniqid(),
            'test_type' => 'general',
            'is_active' => true,
        ]);
        $this->bank->acl_category_id = $category->id;
        $this->bank->save();

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $essayQ->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Prompt phrasing improvement',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]));

        $response->assertStatus(200);
        // Answer Choices & Correct Answer are not in the checklist
        $response->assertDontSee('✖ Answer Choices');
        $response->assertDontSee('✖ Correct Answer');
        $response->assertSee('✔ 100% Quality Standards Satisfied');
    }

    /**
     * TEST 10: Valid explanation synchronizes checklist PASS and hides field-level warning.
     */
    public function test_10_valid_explanation_synchronizes_checklist_pass_and_hides_field_warning()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Provide detailed explanation.',
        ]);

        $q = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Explain the process of cellular respiration.',
            'question_type'    => 'essay',
            'explanation'      => 'Detailed pedagogical explanation covering glycolysis and Krebs cycle.',
            'difficulty'       => 'medium',
            'points'           => 5,
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $q->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question is missing detailed explanation',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]));

        $response->assertStatus(200);

        // Checklist MUST show Explanation as Passed (fre-val-badge--pass)
        $response->assertSeeInOrder(['id="val-badge-explanation"', 'fre-val-badge--pass', 'Explanation'], false);

        // Field level action-required warning MUST be hidden
        $response->assertSee('id="explanation-action-required"', false);
        $response->assertSee('display:none', false);

        // Explanation section MUST NOT have fre-highlight class
        $response->assertDontSee('id="explanation-section" class="fre-section fre-highlight"', false);
    }

    /**
     * TEST 11: Empty / invalid explanation synchronizes checklist FAIL and shows field-level warning.
     */
    public function test_11_empty_explanation_synchronizes_checklist_fail_and_shows_field_warning()
    {
        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Missing explanation.',
        ]);

        $q = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Question without explanation',
            'question_type'    => 'essay',
            'explanation'      => '',
            'difficulty'       => 'medium',
            'points'           => 5,
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $q->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question is missing detailed explanation',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]));

        $response->assertStatus(200);

        // Checklist MUST show Explanation as Failed (fre-val-badge--fail)
        $response->assertSeeInOrder(['id="val-badge-explanation"', 'fre-val-badge--fail', 'Explanation'], false);

        // Field level warning MUST be shown (fre-highlight class on explanation-section)
        $response->assertSee('id="explanation-section" class="fre-section fre-highlight"', false);
        $response->assertSee('Action Required: Provide Explanation');
    }
}
