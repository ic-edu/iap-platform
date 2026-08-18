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
            'status'      => 'needs_revision',
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
        $this->bank->status = 'pending_approval';
        $this->bank->save();

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

        // 2. Repository Description finding renders Question Bank Workspace link & label
        $response->assertSee(route('admin.question-banks.show', $this->bank->id), false);
        $response->assertSee('from=revision_task', false);
        $response->assertSee('revision_request_id=' . $revisionRequest->id, false);
        $response->assertSee('🛠 Edit Repository Details →');
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

    /**
     * TEST 12: PENDING_APPROVAL repository findings route to read-only repository view with "View Repository" action.
     */
    public function test_12_pending_approval_repository_findings_route_to_read_only_view_and_show_view_repository_label()
    {
        $this->bank->status = 'pending_approval';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'RESUBMITTED',
            'notes'            => 'Awaiting review.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question wording feedback',
            'status'                         => 'CLOSED',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revisionRequest->id));

        $response->assertStatus(200);

        // Action button MUST be "👁 View Repository →" and MUST NOT be Focused Question Editor
        $response->assertSee('👁 View Repository →', false);
        $response->assertDontSee('🛠 Open Focused Question Editor →', false);

        // Target URL MUST be admin.question-banks.show with revision context
        $response->assertSee(route('admin.question-banks.show', [$this->bank->id]), false);
        $response->assertSee('from=revision_task', false);
        $response->assertSee('revision_request_id=' . $revisionRequest->id, false);
    }

    /**
     * TEST 13: NEEDS_REVISION + OPEN revision finding routes to Focused Question Editor.
     */
    public function test_13_needs_revision_repository_findings_route_to_focused_editor()
    {
        $this->bank->status = 'needs_revision';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix question items.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question choice missing',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revisionRequest->id));

        $response->assertStatus(200);

        // Action button MUST be "🛠 Open Focused Question Editor →"
        $response->assertSee('🛠 Open Focused Question Editor →', false);

        $expectedUrl = route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]);
        $response->assertSee($expectedUrl);
    }

    /**
     * TEST 14: Direct access to edit-question on a locked PENDING_APPROVAL repository cleanly redirects to read-only view.
     */
    public function test_14_direct_access_to_editor_on_locked_pending_approval_repository_is_blocked()
    {
        $this->bank->status = 'pending_approval';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'RESUBMITTED',
            'notes'            => 'Awaiting review.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question choice missing',
            'status'                         => 'CLOSED',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $item->id]));

        $response->assertRedirect(route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
        ]));
        $response->assertSessionHas('info');
    }

    /**
     * TEST 15: Direct POST to update-question on a locked PENDING_APPROVAL repository is blocked.
     */
    public function test_15_direct_update_to_locked_pending_approval_repository_is_blocked()
    {
        $this->bank->status = 'pending_approval';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'RESUBMITTED',
            'notes'            => 'Awaiting review.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question choice missing',
            'status'                         => 'CLOSED',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.update-question', [$revisionRequest->id, $item->id]), [
                'question_id' => $this->question->id,
                'prompt'      => 'Illegal modification attempt while locked',
            ]);

        $response->assertRedirect(route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
        ]));
        $response->assertSessionHas('error');
    }

    /**
     * TEST 16: Revision resubmit cannot proceed while any finding is OPEN.
     */
    public function test_16_revision_cannot_be_resubmitted_while_any_finding_is_open()
    {
        $this->bank->status = 'needs_revision';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix issues.',
        ]);

        $itemOpen = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Repository Description',
            'status'                         => 'OPEN',
        ]);

        $itemClosed = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question explanation needs detail',
            'status'                         => 'CLOSED',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify status has NOT changed
        $this->assertEquals('needs_revision', $this->bank->fresh()->status);
        $this->assertEquals('OPEN', $revisionRequest->fresh()->status);
    }

    /**
     * TEST 17: All findings CLOSED allows resubmission and transitions QuestionBank to pending_approval.
     */
    public function test_17_all_findings_closed_allows_resubmission_and_transitions_bank_to_pending_approval()
    {
        $this->bank->status = 'needs_revision';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix issues.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question choice missing',
            'status'                         => 'CLOSED',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.resubmit', $revisionRequest->id));

        $response->assertRedirect(route('teacher.repository-revisions.index'));
        $this->assertEquals('pending_approval', $this->bank->fresh()->status);
        $this->assertEquals('RESUBMITTED', $revisionRequest->fresh()->status);
    }

    /**
     * TEST 18: Updating repository metadata auto-closes matching metadata finding when resolved.
     */
    public function test_18_metadata_update_automatically_closes_matching_metadata_finding()
    {
        $this->bank->status = 'needs_revision';
        $this->bank->description = null;
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Provide repository description.',
        ]);

        $metaItem = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Repository Description',
            'status'                         => 'OPEN',
        ]);

        // Submit metadata update with description populated
        $response = $this->actingAs($this->teacher)
            ->put(route('admin.question-banks.update', $this->bank->id), [
                'title'       => $this->bank->title,
                'test_type'   => 'toeic',
                'description' => 'Comprehensive description for academic testing repository.',
            ]);

        $response->assertRedirect(route('admin.question-banks.show', [$this->bank->id]));

        // Finding MUST be automatically closed
        $this->assertEquals('CLOSED', $metaItem->fresh()->status);
    }

    /**
     * TEST 19: Locked repository displays SUBMITTED — AWAITING REVIEW badge for remaining findings.
     */
    public function test_19_locked_repository_displays_submitted_awaiting_review_badge()
    {
        $this->bank->status = 'pending_approval';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'RESUBMITTED',
            'notes'            => 'Awaiting governance review.',
        ]);

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Repository Description',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revisionRequest->id));

        $response->assertStatus(200);

        // MUST display SUBMITTED — AWAITING REVIEW and NOT actionable OPEN
        $response->assertSee('SUBMITTED — AWAITING REVIEW', false);
        $response->assertDontSee('background:rgba(244,63,94,.2);color:#fb7185;', false);
    }

    /**
     * TEST 20: Direct submitForApproval route is blocked if active revision request has open findings.
     */
    public function test_20_submit_for_approval_route_is_blocked_if_active_revision_has_open_findings()
    {
        $this->bank->status = 'needs_revision';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Must fix finding first.',
        ]);

        $this->question->choices()->delete();

        $item = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question choice missing',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $this->bank->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Status MUST remain needs_revision
        $this->assertEquals('needs_revision', $this->bank->fresh()->status);
        $this->assertEquals('OPEN', $revisionRequest->fresh()->status);
    }

    /**
     * TEST 21: Repository-level findings route to Question Bank Workspace (Test Builder) while question findings route to Focused Editor.
     */
    public function test_21_repository_level_findings_map_to_question_bank_workspace()
    {
        $this->bank->status = 'needs_revision';
        $this->bank->save();

        $revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Fix repository and questions.',
        ]);

        $itemDesc = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Repository Description',
            'status'                         => 'OPEN',
        ]);

        $itemVer = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Version',
            'status'                         => 'OPEN',
        ]);

        $itemCat = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing Category association',
            'status'                         => 'OPEN',
        ]);

        $itemCount = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Insufficient question count (1 questions in repository)',
            'status'                         => 'OPEN',
        ]);

        $itemQuestion = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revisionRequest->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question 1 explanation needs more detail.',
            'status'                         => 'OPEN',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revisionRequest->id));

        $response->assertStatus(200);

        // 1-4. All repository-level findings route to Question Bank Workspace (admin.question-banks.show)
        $response->assertSee(route('admin.question-banks.show', $this->bank->id), false);
        $response->assertSee('from=revision_task', false);
        $response->assertSee('revision_request_id=' . $revisionRequest->id, false);
        $response->assertSee('🛠 Edit Repository Details →');

        // 5. Question-level finding routes to Focused Question Editor
        $expectedEditorUrl = route('teacher.repository-revisions.edit-question', [$revisionRequest->id, $itemQuestion->id]);
        $response->assertSee($expectedEditorUrl, false);
        $response->assertSee('🛠 Open Focused Question Editor →');

        // 6. Navigate to Question Bank Workspace and verify contextual back link and authoring controls
        $expectedWorkspaceUrl = route('admin.question-banks.show', [
            $this->bank->id,
            'from'                => 'revision_task',
            'revision_request_id' => $revisionRequest->id,
        ]);
        $workspaceResponse = $this->actingAs($this->teacher)
            ->get($expectedWorkspaceUrl);

        $workspaceResponse->assertStatus(200);
        $workspaceResponse->assertSee('← Back to Revision Task');
        $workspaceResponse->assertSee('✏ Edit Bank Details');
        $workspaceResponse->assertSee('📥 Bulk Import');
        $workspaceResponse->assertSee('+ Add Question');
        $workspaceResponse->assertSee('🚀 Submit for Approval');
    }

    /**
     * TEST 22: Teacher can filter Question Banks by needs_revision in authoring workspace.
     */
    public function test_22_teacher_can_filter_question_banks_by_needs_revision()
    {
        $revBank = QuestionBank::create([
            'title'       => 'Needs Revision Filter Unique Test Bank',
            'slug'        => 'needs-rev-filter-' . uniqid(),
            'test_type'   => 'toeic',
            'status'      => 'needs_revision',
            'created_by'  => $this->teacher->id,
            'description' => 'Test bank needing revision',
        ]);

        $draftBank = QuestionBank::create([
            'title'       => 'Draft Unique Filter Test Bank',
            'slug'        => 'draft-bank-' . uniqid(),
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->teacher->id,
            'description' => 'Draft test bank',
        ]);

        $publishedBank = QuestionBank::create([
            'title'       => 'Published Unique Filter Test Bank',
            'slug'        => 'pub-bank-' . uniqid(),
            'test_type'   => 'toeic',
            'status'      => 'published',
            'created_by'  => $this->teacher->id,
            'description' => 'Published test bank',
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.question-banks.index', ['status' => 'needs_revision']));

        $response->assertStatus(200);

        // MUST see needs_revision bank and badge
        $response->assertSee('Needs Revision Filter Unique Test Bank');
        $response->assertSee('Needs Revision');

        // MUST NOT see draft or published banks in the filtered result
        $response->assertDontSee('Draft Unique Filter Test Bank');
        $response->assertDontSee('Published Unique Filter Test Bank');
    }

    /**
     * TEST 23: Complete synchronization of Revision Task Card and Workspace status presentation.
     */
    public function test_23_revision_status_synchronization_across_card_and_workspace()
    {
        // ── SCENARIO A: Revision in progress with open findings (OPEN / needs_revision)
        $this->bank->status = 'needs_revision';
        $this->bank->save();
        $this->question->explanation = null;
        $this->question->save();

        $revReq = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->bank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Please revise Question 1.',
        ]);

        $itemOpen = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revReq->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'question_warning',
            'feedback'                       => 'Question 1 explanation missing.',
            'status'                         => 'OPEN',
        ]);

        // Verify Index Card
        $indexResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('OPEN');
        $indexResponse->assertSee('1</strong> Findings Remaining', false);
        $indexResponse->assertDontSee('READY FOR RESUBMISSION');
        $indexResponse->assertDontSee('RESUBMITTED — AWAITING REVIEW');

        // Verify Detail Workspace
        $showResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revReq->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Status: OPEN');
        $showResponse->assertSee('Actionable Quality Findings (1 remaining)');
        $showResponse->assertSee('Resubmit Disabled (1 Remaining)');
        $showResponse->assertDontSee('Status: RESUBMITTED — AWAITING REVIEW');

        // ── SCENARIO B: All findings resolved (READY FOR RESUBMISSION)
        $this->question->explanation = 'Explanation provided.';
        $this->question->save();
        $itemOpen->status = 'CLOSED';
        $itemOpen->save();

        // Verify Index Card
        $indexResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('READY FOR RESUBMISSION');
        $indexResponse->assertSee('0</strong> Findings Remaining', false);
        $indexResponse->assertSee('All findings resolved. Ready for resubmission.');
        $indexResponse->assertDontSee('Actionable Issues');
        $indexResponse->assertDontSee('RESUBMITTED — AWAITING REVIEW');

        // Verify Detail Workspace
        $showResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revReq->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Status: READY FOR RESUBMISSION');
        $showResponse->assertSee('All Findings Resolved (1)');
        $showResponse->assertSee('Resubmit Repository &amp; Trigger IRQA Re-Scan', false);
        $showResponse->assertDontSee('Status: RESUBMITTED — AWAITING REVIEW');

        // ── SCENARIO C: After Resubmission (RESUBMITTED / pending_approval)
        $revReq->status = 'RESUBMITTED';
        $revReq->save();
        $this->bank->status = 'pending_approval';
        $this->bank->save();

        // Create an unclosed finding to verify it renders as SUBMITTED — AWAITING REVIEW
        $itemAwaiting = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revReq->id,
            'question_bank_id'               => $this->bank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Historical finding under review',
            'status'                         => 'OPEN',
        ]);

        // Verify Index Card
        $indexResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('RESUBMITTED — AWAITING REVIEW');
        $indexResponse->assertSee('Repository has been resubmitted and is awaiting governance review.');
        $indexResponse->assertDontSee('Findings Remaining');
        $indexResponse->assertDontSee('Actionable Issues');
        $indexResponse->assertDontSee('READY FOR RESUBMISSION');
        $indexResponse->assertDontSee('All Findings Resolved — Ready For Resubmission');

        // Verify Detail Workspace
        $showResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revReq->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Status: RESUBMITTED — AWAITING REVIEW');
        $showResponse->assertSee('Repository locked while awaiting governance review.');
        $showResponse->assertSee('Repository Findings (2)');
        $showResponse->assertSee('SUBMITTED — AWAITING REVIEW');
        $showResponse->assertSee('Repository Locked For Governance Approval');
        $showResponse->assertDontSee('Status: OPEN');
        $showResponse->assertDontSee('Actionable Quality Findings');
        $showResponse->assertDontSee('Ready to Resubmit Repository?');
    }

    /**
     * TEST 24: Question count revision finding auto-closure and submit guard integrity.
     */
    public function test_24_question_count_revision_finding_auto_closure_and_submit_guard()
    {
        // 1. Setup repository with 0 questions in needs_revision state
        $emptyBank = QuestionBank::create([
            'title'           => 'Zero Question Auto-Closure Bank',
            'slug'            => 'zero-question-auto-closure-bank-' . uniqid(),
            'test_type'       => 'general',
            'status'          => 'needs_revision',
            'created_by'      => $this->teacher->id,
            'description'     => 'Valid Description',
            'current_version' => '1.0',
        ]);

        $revReq = RepositoryRevisionRequest::create([
            'question_bank_id' => $emptyBank->id,
            'teacher_id'       => $this->teacher->id,
            'requested_by_id'  => $this->repoManager->id,
            'status'           => 'OPEN',
            'notes'            => 'Please add questions and address metadata.',
        ]);

        // Finding #1: Description (Already satisfied by Valid Description)
        $itemDesc = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revReq->id,
            'question_bank_id'               => $emptyBank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Missing metadata: Repository Description',
            'status'                         => 'OPEN',
        ]);

        // Finding #4: Insufficient question count
        $itemCount = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revReq->id,
            'question_bank_id'               => $emptyBank->id,
            'question_id'                    => null,
            'finding_type'                   => 'quality_warning',
            'feedback'                       => 'Insufficient question count (0 questions in repository)',
            'status'                         => 'OPEN',
        ]);

        // Initial check: Description finding auto-closes, but question count remains OPEN
        app(\App\Services\RepositoryQualityService::class)->reconcileRevisionItems($emptyBank);
        $this->assertEquals('CLOSED', $itemDesc->fresh()->status);
        $this->assertEquals('OPEN', $itemCount->fresh()->status);

        // Submit remains blocked because Finding #4 is OPEN
        $blockedSubmit = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $emptyBank->id));
        $blockedSubmit->assertRedirect();
        $blockedSubmit->assertSessionHas('error');
        $this->assertEquals('needs_revision', $emptyBank->fresh()->status);
        $this->assertEquals('OPEN', $revReq->fresh()->status);

        // Revision Workspace confirms Finding #4 is OPEN and Resubmit is disabled
        $showResponse = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revReq->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Status: OPEN');
        $showResponse->assertSee('Resubmit Disabled (1 Remaining)');

        // 2. Teacher adds a question to satisfy the threshold
        $addResponse = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.store-question', $emptyBank->id), [
                'prompt'        => 'What is the capital of Indonesia?',
                'question_type' => 'multiple_choice',
                'difficulty'    => 'medium',
                'points'        => 1,
                'explanation'   => 'Jakarta / Nusantara is the capital.',
                'choices'       => [
                    ['label' => 'A', 'content' => 'Jakarta'],
                    ['label' => 'B', 'content' => 'Bandung'],
                    ['label' => 'C', 'content' => 'Surabaya'],
                ],
                'correct_choice' => '0',
            ]);
        $addResponse->assertRedirect();

        // 3. Finding #4 is now automatically CLOSED!
        $this->assertEquals(1, $emptyBank->questions()->count());
        $this->assertEquals('CLOSED', $itemCount->fresh()->status);

        // Revision Workspace now confirms all findings resolved & Resubmit is available
        $showResponseAfter = $this->actingAs($this->teacher)
            ->get(route('teacher.repository-revisions.show', $revReq->id));
        $showResponseAfter->assertStatus(200);
        $showResponseAfter->assertSee('Status: READY FOR RESUBMISSION');
        $showResponseAfter->assertSee('All Findings Resolved (2)');
        $showResponseAfter->assertSee('Resubmit Repository &amp; Trigger IRQA Re-Scan', false);

        // 4. Submit for Approval now successfully proceeds
        $successfulSubmit = $this->actingAs($this->teacher)
            ->post(route('admin.question-banks.submit', $emptyBank->id));
        $successfulSubmit->assertRedirect();
        $this->assertEquals('pending_approval', $emptyBank->fresh()->status);
        $this->assertEquals('RESUBMITTED', $revReq->fresh()->status);
    }
}
