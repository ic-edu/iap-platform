<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentRequestAndAuthoringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rm;
    protected User $teacher1;
    protected User $teacher2;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'super-admin']);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');

        $this->rm = User::factory()->create(['status' => 'active']);
        $this->rm->assignRole('repository-manager');

        $this->teacher1 = User::factory()->create(['status' => 'active']);
        $this->teacher1->assignRole('teacher');

        $this->teacher2 = User::factory()->create(['status' => 'active']);
        $this->teacher2->assignRole('teacher');

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole('super-admin');
    }

    /** 1. Regular Admin can request Assessment. */
    public function test_regular_admin_can_request_assessment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
            'title'              => 'TOEIC Placement for Vocational Tourism',
            'test_type'          => 'toeic',
            'program_context'    => 'Vocational Tourism Placement 2026',
            'required_sections'  => 'Listening and Reading',
            'notes'              => 'Requires 20 high-quality vocational questions.',
            'requested_deadline' => '2026-09-01',
        ]);

        $response->assertRedirect(route('admin.assessment-requests.index'));
        $this->assertDatabaseHas('assessment_requests', [
            'title'        => 'TOEIC Placement for Vocational Tourism',
            'test_type'    => 'toeic',
            'requested_by' => $this->admin->id,
            'status'       => 'pending',
        ]);
    }

    /** 2. Regular Admin cannot directly assign Assessment to Teacher. */
    public function test_regular_admin_cannot_directly_assign_assessment_to_teacher(): void
    {
        $req = AssessmentRequest::create([
            'title'        => 'TOEIC Operational Test',
            'test_type'    => 'toeic',
            'requested_by' => $this->admin->id,
            'status'       => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.repository-manager.assessment-requests.create-draft', $req->id), [
            'teacher_id'       => $this->teacher1->id,
            'title'            => 'Assigned Test',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 700,
        ]);

        $response->assertForbidden();
    }

    /** 3 & 4. RM can create Assessment Draft from request and assign Draft to Teacher. */
    public function test_rm_can_create_assessment_draft_from_request_and_assign_to_teacher(): void
    {
        $req = AssessmentRequest::create([
            'title'        => 'IELTS General Training Assessment',
            'test_type'    => 'ielts',
            'requested_by' => $this->admin->id,
            'status'       => 'pending',
        ]);

        $response = $this->actingAs($this->rm)->post(route('admin.repository-manager.assessment-requests.create-draft', $req->id), [
            'teacher_id'       => $this->teacher1->id,
            'title'            => 'IELTS General Training Assessment Draft',
            'test_type'        => 'ielts',
            'duration_minutes' => 120,
            'pass_score'       => 700,
        ]);

        $response->assertRedirect(route('admin.repository-manager.assessment-requests.index'));
        $this->assertDatabaseHas('assessment_requests', [
            'id'     => $req->id,
            'status' => 'draft_created',
        ]);

        $test = Test::where('assessment_request_id', $req->id)->first();
        $this->assertNotNull($test);
        $this->assertEquals($this->rm->id, $test->created_by);
        $this->assertEquals($this->teacher1->id, $test->assigned_to);
        $this->assertEquals('draft', $test->status);
    }

    /** 5 & 6. Assigned Teacher can access Draft; Unassigned Teacher cannot access Draft. */
    public function test_assigned_teacher_can_access_draft_while_unassigned_teacher_is_forbidden(): void
    {
        $test = Test::create([
            'title'            => 'Assigned Assessment Test',
            'slug'             => 'assigned-assessment-test',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'created_by'       => $this->rm->id,
            'assigned_to'      => $this->teacher1->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);
        TestSection::create(['test_id' => $test->id, 'title' => 'Core Section', 'order' => 1]);

        // Assigned teacher can access
        $resp1 = $this->actingAs($this->teacher1)->get(route('teacher.tests.show', $test->id));
        $resp1->assertOk();

        // Unassigned teacher is forbidden
        $resp2 = $this->actingAs($this->teacher2)->get(route('teacher.tests.show', $test->id));
        $resp2->assertForbidden();
    }

    /** 7 & 8. Teacher can attach Master Question to Draft Assessment without mutating Master Question. */
    public function test_teacher_can_attach_master_question_without_mutating_master_question(): void
    {
        $bank = QuestionBank::create([
            'title'       => 'Institutional Master Question Pool',
            'slug'        => 'institutional-master-question-pool',
            'test_type'   => 'toeic',
            'status'      => 'published',
            'created_by'  => $this->teacher1->id,
        ]);

        $masterQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Original Immutable Master Prompt',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 5,
        ]);
        QuestionChoice::create(['question_id' => $masterQuestion->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $masterQuestion->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);

        $test = Test::create([
            'title'            => 'Vocational Test Draft',
            'slug'             => 'vocational-test-draft',
            'test_type'        => 'toeic',
            'duration_minutes' => 90,
            'pass_score'       => 500,
            'created_by'       => $this->rm->id,
            'assigned_to'      => $this->teacher1->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);
        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Section 1', 'order' => 1]);

        $response = $this->actingAs($this->teacher1)->post(route('teacher.tests.attach-master-question', $test->id), [
            'test_section_id' => $section->id,
            'question_id'     => $masterQuestion->id,
        ]);

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $this->assertDatabaseHas('test_questions', [
            'test_section_id' => $section->id,
            'question_id'     => $masterQuestion->id,
        ]);

        // Verify Master Question in database is untouched
        $masterQuestion->refresh();
        $this->assertEquals('Original Immutable Master Prompt', $masterQuestion->prompt);
        $this->assertEquals($bank->id, $masterQuestion->question_bank_id);
    }

    /** 9 & 10. Teacher can create Assessment-authored Question and edit it. */
    public function test_teacher_can_create_and_edit_assessment_authored_question(): void
    {
        $test = Test::create([
            'title'            => 'Teacher Authored Assessment',
            'slug'             => 'teacher-authored-assessment',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher1->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);
        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Section 1', 'order' => 1]);

        // Create authored question
        $createResp = $this->actingAs($this->teacher1)->post(route('teacher.tests.create-question', $test->id), [
            'test_section_id' => $section->id,
            'prompt'          => 'What is the correct definition of Agile?',
            'question_type'   => 'multiple_choice',
            'difficulty'      => 'medium',
            'points'          => 2,
            'choices'         => ['An iterative approach', 'A waterfall method', 'A hardware tool', 'A database'],
            'correct_choice'  => 0,
        ]);

        $createResp->assertRedirect(route('teacher.tests.show', $test->id));
        $authoredQuestion = Question::whereNull('question_bank_id')->where('prompt', 'What is the correct definition of Agile?')->first();
        $this->assertNotNull($authoredQuestion);

        // Edit authored question
        $editResp = $this->actingAs($this->teacher1)->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $authoredQuestion->id]), [
            'prompt'         => 'Updated Prompt: What is the core philosophy of Agile?',
            'question_type'  => 'multiple_choice',
            'difficulty'     => 'hard',
            'explanation'    => 'Agile values iteration and responding to change.',
            'choices'        => ['An iterative mindset', 'A strict schedule', 'A hardware tool', 'None'],
            'correct_choice' => 0,
        ]);

        $editResp->assertRedirect(route('teacher.tests.show', $test->id));
        $authoredQuestion->refresh();
        $this->assertEquals('Updated Prompt: What is the core philosophy of Agile?', $authoredQuestion->prompt);
        $diffValue = is_object($authoredQuestion->difficulty) ? $authoredQuestion->difficulty->value : $authoredQuestion->difficulty;
        $this->assertEquals('hard', $diffValue);
    }

    /** 11. Teacher cannot edit Master Question directly from Test Builder. */
    public function test_teacher_cannot_edit_master_question_directly_from_test_builder(): void
    {
        $bank = QuestionBank::create([
            'title'       => 'Governed Bank',
            'slug'        => 'governed-bank',
            'test_type'   => 'toeic',
            'status'      => 'published',
            'created_by'  => $this->teacher1->id,
        ]);

        $masterQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Governed Master Question',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);

        $test = Test::create([
            'title'            => 'Test With Master Question',
            'slug'             => 'test-with-master-question',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher1->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        // Attempting to update master question via test builder
        $response = $this->actingAs($this->teacher1)->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $masterQuestion->id]), [
            'prompt' => 'Hacked Master Prompt',
        ]);

        // Blocked with redirect error / not allowed
        $masterQuestion->refresh();
        $this->assertEquals('Governed Master Question', $masterQuestion->prompt);
    }

    /** 12 & 13. Draft with 0 questions remains Draft; Draft with valid questions can submit. */
    public function test_draft_submission_guards(): void
    {
        $test = Test::create([
            'title'            => 'Empty Draft Assessment',
            'slug'             => 'empty-draft-assessment',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher1->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);
        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Section 1', 'order' => 1]);

        // 12. Submitting empty draft fails validation and stays draft
        $emptySubmitResp = $this->actingAs($this->teacher1)->post(route('teacher.tests.resubmit', $test->id));
        $emptySubmitResp->assertRedirect(route('teacher.tests.show', $test->id));
        $test->refresh();
        $this->assertEquals('draft', $test->status);

        // 13. Add valid question, then submission succeeds
        $q = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Valid authored question prompt',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
        \App\Modules\Assessment\Models\TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $q->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $validSubmitResp = $this->actingAs($this->teacher1)->post(route('teacher.tests.resubmit', $test->id));
        $validSubmitResp->assertRedirect(route('teacher.tests.show', $test->id));
        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);
    }

    /** 14. Pending Approval locks Teacher editing. */
    public function test_pending_approval_locks_teacher_editing(): void
    {
        $test = Test::create([
            'title'            => 'Locked Test',
            'slug'             => 'locked-test',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher1->id,
            'status'           => 'pending_approval',
            'is_published'     => false,
        ]);
        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Section 1', 'order' => 1]);

        $response = $this->actingAs($this->teacher1)->post(route('teacher.tests.create-question', $test->id), [
            'test_section_id' => $section->id,
            'prompt'          => 'Another Question',
            'question_type'   => 'multiple_choice',
            'points'          => 1,
        ]);

        $response->assertForbidden();
    }

    /** 15. RM can Approve. */
    public function test_rm_can_approve_assessment(): void
    {
        $test = Test::create([
            'title'            => 'Test To Approve',
            'slug'             => 'test-to-approve',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher1->id,
            'status'           => 'pending_approval',
            'is_published'     => false,
        ]);
        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Section 1', 'order' => 1]);
        $q = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Valid question for approval',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Option A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Option B', 'is_correct' => false]);
        \App\Modules\Assessment\Models\TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

        $response = $this->actingAs($this->rm)->post(route('admin.repository-manager.assessment-approve', $test->id), [
            'notes' => 'Meets institutional quality standards.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.assessment-approval'));
        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertTrue($test->is_published);
    }

    /** 16 & 18. RM can Request Revision -> status becomes NEEDS_REVISION. */
    public function test_rm_can_request_revision_and_status_transitions_to_needs_revision(): void
    {
        $test = Test::create([
            'title'            => 'Test To Revise',
            'slug'             => 'test-to-revise',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher1->id,
            'status'           => 'pending_approval',
            'is_published'     => false,
        ]);

        $response = $this->actingAs($this->rm)->post(route('admin.repository-manager.assessment-revision', $test->id), [
            'notes' => 'Please revise reading passage and check choice labels.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.assessment-approval'));
        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);
        $this->assertFalse($test->is_published);
    }

    /** 17, 19, 20, 21. RM can Send to Archived -> status becomes ARCHIVED, notifies Teacher, retained. */
    public function test_rm_can_send_to_archived_and_notifies_teacher_with_retention(): void
    {
        $test = Test::create([
            'title'            => 'Outdated Assessment Submission',
            'slug'             => 'outdated-assessment-submission',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher1->id,
            'status'           => 'pending_approval',
            'is_published'     => false,
        ]);

        $response = $this->actingAs($this->rm)->post(route('admin.repository-manager.assessment-archive', $test->id), [
            'notes' => 'Program format retired in favor of 2026 standard.',
        ]);

        $response->assertRedirect(route('admin.repository-manager.assessment-approval'));
        $test->refresh();
        $this->assertEquals('archived', $test->status);
        $this->assertFalse($test->is_published);

        // 21. Verify retained in DB (not hard deleted)
        $this->assertDatabaseHas('tests', [
            'id'     => $test->id,
            'status' => 'archived',
        ]);
    }

    /** 22. Teacher can request revision of Master Question via official Repository Revision workflow. */
    public function test_teacher_can_request_revision_of_master_question_via_official_workflow(): void
    {
        $bank = QuestionBank::create([
            'title'       => 'Governed Institutional Bank',
            'slug'        => 'governed-institutional-bank',
            'test_type'   => 'toeic',
            'status'      => 'published',
            'created_by'  => $this->teacher1->id,
        ]);

        $q = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Master prompt with typo',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);

        $response = $this->actingAs($this->teacher1)->post(route('teacher.repository-revisions.request'), [
            'question_bank_id' => $bank->id,
            'question_id'      => $q->id,
            'notes'            => 'Typo in stem prompt word 4.',
        ]);

        $revReq = RepositoryRevisionRequest::where('question_bank_id', $bank->id)->first();
        $this->assertNotNull($revReq);
        $this->assertEquals('OPEN', $revReq->status);
        $this->assertEquals($this->teacher1->id, $revReq->teacher_id);
        $response->assertRedirect(route('teacher.repository-revisions.show', $revReq->id));
    }

    /** 23. Teacher dashboard counts RM-created Assessment assigned to Teacher as Draft. */
    public function test_teacher_dashboard_counts_rm_created_assigned_assessment_as_draft(): void
    {
        Test::create([
            'title'       => 'Assigned Draft Test',
            'slug'        => 'assigned-draft-test',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher1->id,
        ]);

        $workflowService = app(\App\Services\AssessmentWorkflowService::class);
        $metrics = $workflowService->getTeacherMetrics($this->teacher1);
        $this->assertEquals(1, $metrics['draft']);

        $response = $this->actingAs($this->teacher1)->get(route('teacher.dashboard'));
        $response->assertOk();
        $response->assertSee('Assigned Draft Test');
        $response->assertSee('1 assessment draft(s) in progress');
    }

    /** 24. Teacher dashboard counts assigned Assessment as Pending Approval when status = pending_approval. */
    public function test_teacher_dashboard_counts_assigned_assessment_as_pending_approval(): void
    {
        Test::create([
            'title'       => 'Assigned Pending Test',
            'slug'        => 'assigned-pending-test',
            'test_type'   => 'toeic',
            'status'      => 'pending_approval',
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher1->id,
        ]);

        $workflowService = app(\App\Services\AssessmentWorkflowService::class);
        $metrics = $workflowService->getTeacherMetrics($this->teacher1);
        $this->assertEquals(1, $metrics['pending']);

        $inbox = $workflowService->getWorkflowInboxMetrics($this->teacher1);
        $this->assertEquals(1, $inbox['assessments']);
        $this->assertEquals(1, $inbox['total']);

        $response = $this->actingAs($this->teacher1)->get(route('teacher.dashboard'));
        $response->assertOk();
        $response->assertSee('1 assessment(s) awaiting review');
    }

    /** 25. Teacher dashboard counts assigned Assessment as Needs Revision. */
    public function test_teacher_dashboard_counts_assigned_assessment_as_needs_revision(): void
    {
        Test::create([
            'title'       => 'Assigned Revision Test',
            'slug'        => 'assigned-revision-test',
            'test_type'   => 'toeic',
            'status'      => 'needs_revision',
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher1->id,
        ]);

        $workflowService = app(\App\Services\AssessmentWorkflowService::class);
        $metrics = $workflowService->getTeacherMetrics($this->teacher1);
        $this->assertEquals(1, $metrics['needs_revision']);
    }

    /** 26. Teacher dashboard counts assigned Assessment as Approved / Published. */
    public function test_teacher_dashboard_counts_assigned_assessment_as_approved_or_published(): void
    {
        Test::create([
            'title'       => 'Assigned Approved Test',
            'slug'        => 'assigned-approved-test',
            'test_type'   => 'toeic',
            'status'      => 'approved',
            'is_published'=> true,
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher1->id,
        ]);

        $workflowService = app(\App\Services\AssessmentWorkflowService::class);
        $metrics = $workflowService->getTeacherMetrics($this->teacher1);
        $this->assertEquals(1, $metrics['approved']);
    }

    /** 27. Teacher dashboard does NOT count another Teacher's unassigned Assessment. */
    public function test_teacher_dashboard_does_not_count_another_teachers_unassigned_assessment(): void
    {
        Test::create([
            'title'       => 'Teacher 2 Private Draft',
            'slug'        => 'teacher-2-private-draft',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher2->id,
        ]);

        $workflowService = app(\App\Services\AssessmentWorkflowService::class);
        $metrics = $workflowService->getTeacherMetrics($this->teacher1);
        $this->assertEquals(0, $metrics['draft']);

        $response = $this->actingAs($this->teacher1)->get(route('teacher.dashboard'));
        $response->assertOk();
        $response->assertDontSee('Teacher 2 Private Draft');
    }

    /** 28. Existing Teacher-created Assessment metrics continue to work. */
    public function test_existing_teacher_created_assessment_metrics_continue_to_work(): void
    {
        Test::create([
            'title'       => 'Teacher 1 Self-Authored Test',
            'slug'        => 'teacher-1-self-authored-test',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->teacher1->id,
            'assigned_to' => null,
        ]);

        $workflowService = app(\App\Services\AssessmentWorkflowService::class);
        $metrics = $workflowService->getTeacherMetrics($this->teacher1);
        $this->assertEquals(1, $metrics['draft']);

        $response = $this->actingAs($this->teacher1)->get(route('teacher.dashboard'));
        $response->assertOk();
        $response->assertSee('Teacher 1 Self-Authored Test');
    }

    /** 29. Test Builder and Teacher Dashboard return consistent Assessment counts. */
    public function test_test_builder_and_teacher_dashboard_return_consistent_assessment_counts(): void
    {
        Test::create([
            'title'       => 'Draft Alpha',
            'slug'        => 'draft-alpha',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->teacher1->id,
            'assigned_to' => null,
        ]);

        Test::create([
            'title'       => 'Draft Beta',
            'slug'        => 'draft-beta',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher1->id,
        ]);

        Test::create([
            'title'       => 'Draft Gamma (Teacher 2)',
            'slug'        => 'draft-gamma',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher2->id,
        ]);

        $workflowService = app(\App\Services\AssessmentWorkflowService::class);
        $metrics = $workflowService->getTeacherMetrics($this->teacher1);
        $this->assertEquals(2, $metrics['draft']);

        $tbResponse = $this->actingAs($this->teacher1)->get(route('admin.tests.index', ['status' => 'draft']));
        $tbResponse->assertOk();
        $tbResponse->assertSee('Draft Alpha');
        $tbResponse->assertSee('Draft Beta');
        $tbResponse->assertDontSee('Draft Gamma (Teacher 2)');
    }

    /** 30. Draft KPI displays 1 for assigned Assessment. */
    public function test_draft_kpi_displays_1_for_assigned_assessment(): void
    {
        Test::create([
            'title'       => 'TOEIC Listening & Reading for SMK Perhotelan',
            'slug'        => 'toeic-listening-reading-for-smk-perhotelan',
            'test_type'   => 'toeic',
            'status'      => 'draft',
            'created_by'  => $this->rm->id,
            'assigned_to' => $this->teacher1->id,
        ]);

        $response = $this->actingAs($this->teacher1)->get(route('teacher.dashboard'));
        $response->assertOk();
        $response->assertSee('TOEIC Listening & Reading for SMK Perhotelan');
        $response->assertSee('1 assessment draft(s) in progress');
    }
}

