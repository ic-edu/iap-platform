<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentEmptySubmissionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $admin;
    protected User $superAdmin;
    protected User $repoManager;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'repository-manager', 'guard_name' => 'web']);

        $this->teacher = User::factory()->create(['name' => 'Teacher User']);
        $this->teacher->assignRole('teacher');

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin User']);
        $this->superAdmin->assignRole('super-admin');

        $this->repoManager = User::factory()->create(['name' => 'Repo Manager User']);
        $this->repoManager->assignRole('repository-manager');
    }

    /**
     * Helper to create a base draft test with 1 section and 0 questions.
     */
    protected function createEmptyDraftTest(): Test
    {
        $test = Test::create([
            'title'            => 'Empty Draft Assessment',
            'slug'             => 'empty-draft-assessment',
            'test_type'        => 'toefl',
            'scoring_method'   => 'automatic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacher->id,
        ]);

        TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 1: General Core',
            'order'   => 1,
        ]);

        return $test;
    }

    /**
     * Helper to create a valid question with choices and correct answer.
     */
    protected function createValidQuestion(): Question
    {
        $bank = QuestionBank::create([
            'title'       => 'Valid Core Bank',
            'slug'        => 'valid-core-bank',
            'test_type'   => 'toefl',
            'status'      => 'published',
            'created_by'  => $this->teacher->id,
            'description' => 'Valid bank',
        ]);

        $question = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'What is the standard frequency of AC power in North America?',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'difficulty'       => 'medium',
            'explanation'      => 'In North America, AC electrical power is distributed at 60 Hz.',
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => '50 Hz',
            'choice_text' => '50 Hz',
            'is_correct'  => false,
            'order'       => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'B',
            'content'     => '60 Hz',
            'choice_text' => '60 Hz',
            'is_correct'  => true,
            'order'       => 2,
        ]);

        return $question;
    }

    /**
     * SCENARIO 1: Empty assessment cannot be submitted via Teacher resubmit.
     */
    public function test_1_empty_assessment_cannot_be_submitted_via_teacher_resubmit(): void
    {
        $test = $this->createEmptyDraftTest();

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.resubmit', $test->id));

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Assessment cannot be submitted with 0 questions', session('error'));

        $test->refresh();
        $this->assertEquals('draft', $test->status);
    }

    /**
     * SCENARIO 2: Empty assessment cannot be submitted via admin.tests.submit-approval.
     */
    public function test_2_empty_assessment_cannot_be_submitted_via_admin_submit_approval(): void
    {
        $test = $this->createEmptyDraftTest();

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.tests.submit-approval', $test->id));

        $response->assertRedirect(route('admin.tests.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Assessment cannot be submitted with 0 questions', session('error'));

        $test->refresh();
        $this->assertEquals('draft', $test->status);
    }

    /**
     * SCENARIO 3: Empty assessment cannot be submitted via repository-manager submit.
     */
    public function test_3_empty_assessment_cannot_be_submitted_via_repository_manager_submit(): void
    {
        $test = $this->createEmptyDraftTest();

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.tests.submit', $test->id));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Assessment cannot be submitted with 0 questions', session('error'));

        $test->refresh();
        $this->assertEquals('draft', $test->status);
    }

    /**
     * SCENARIO 4: Empty assessment remains draft after failed submission attempts.
     */
    public function test_4_empty_assessment_remains_draft(): void
    {
        $test = $this->createEmptyDraftTest();
        $this->assertEquals('draft', $test->status);

        $this->actingAs($this->teacher)->post(route('teacher.tests.resubmit', $test->id));
        $this->assertEquals('draft', $test->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.tests.submit-approval', $test->id));
        $this->assertEquals('draft', $test->fresh()->status);
    }

    /**
     * SCENARIO 5: Assessment with an empty section is rejected.
     */
    public function test_5_empty_section_is_rejected(): void
    {
        $test = $this->createEmptyDraftTest();
        $sec1 = $test->sections->first();

        $question = $this->createValidQuestion();
        TestQuestion::create([
            'test_section_id' => $sec1->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        // Add a 2nd empty section
        TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 2: Listening Comprehension',
            'order'   => 2,
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.resubmit', $test->id));

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $response->assertSessionHas('error');
        $this->assertStringContainsString("Section 'Section 2: Listening Comprehension' has no questions assigned", session('error'));

        $this->assertEquals('draft', $test->fresh()->status);
    }

    /**
     * SCENARIO 6: Assessment with >=1 valid question can be submitted.
     */
    public function test_6_assessment_with_valid_questions_can_be_submitted(): void
    {
        $test = $this->createEmptyDraftTest();
        $sec1 = $test->sections->first();

        $question = $this->createValidQuestion();
        TestQuestion::create([
            'test_section_id' => $sec1->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.resubmit', $test->id));

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $response->assertSessionHas('status', 'Assessment resubmitted successfully.');

        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);
    }

    /**
     * SCENARIO 7: Existing per-question validation still works (e.g. no correct answer).
     */
    public function test_7_existing_per_question_validation_still_works(): void
    {
        $test = $this->createEmptyDraftTest();
        $sec1 = $test->sections->first();

        $bank = QuestionBank::create([
            'title'      => 'Invalid Bank',
            'slug'       => 'invalid-bank',
            'test_type'  => 'toefl',
            'status'     => 'published',
            'created_by' => $this->teacher->id,
        ]);

        // Question with no correct answer
        $invalidQ = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Invalid question without correct choice?',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'difficulty'       => 'easy',
        ]);

        QuestionChoice::create([
            'question_id' => $invalidQ->id,
            'label'       => 'A',
            'content'     => 'Choice A',
            'is_correct'  => false,
            'order'       => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $sec1->id,
            'question_id'     => $invalidQ->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $test->refresh();

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.resubmit', $test->id));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('No correct answer option selected', session('error'));
        $this->assertEquals('draft', $test->fresh()->status);
    }

    /**
     * SCENARIO 8: Submit button disabled for zero-question assessment in UI.
     */
    public function test_8_submit_button_disabled_for_zero_question_assessment(): void
    {
        $test = $this->createEmptyDraftTest();

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.tests.show', $test->id));

        $response->assertStatus(200);
        $response->assertSee('Submission Disabled (Validation Required)');
        $response->assertSee('Assessment cannot be submitted with 0 questions. Please add at least one question.');
    }

    /**
     * SCENARIO 9: Governance queue does not normally receive zero-question submissions.
     */
    public function test_9_governance_queue_does_not_normally_receive_zero_question_submissions(): void
    {
        $test = $this->createEmptyDraftTest();

        // Attempt submission
        $this->actingAs($this->teacher)->post(route('teacher.tests.resubmit', $test->id));

        // Review queue query only includes pending/pending_approval
        $queueCount = Test::whereIn('status', ['pending', 'pending_approval'])->count();
        $this->assertEquals(0, $queueCount);

        $response = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-approval'));

        $response->assertStatus(200);
        $response->assertDontSee($test->title);
    }

    /**
     * SCENARIO 10: Approve remains unavailable for zero-question assessment.
     */
    public function test_10_approve_remains_unavailable_for_zero_question_assessment(): void
    {
        $test = $this->createEmptyDraftTest();
        // Force status to pending_approval as if it was a legacy record
        $test->update(['status' => 'pending_approval']);

        // Repository Manager Review UI
        $viewResponse = $this->actingAs($this->repoManager)
            ->get(route('admin.repository-manager.assessment-review', $test->id));

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Incomplete Assessment: Contains 0 questions');
        $viewResponse->assertSee('disabled');

        // Repository Manager direct Approve attempt
        $approveResponse = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id));

        $approveResponse->assertSessionHas('error', 'Cannot approve empty assessment with 0 questions.');
        $this->assertEquals('pending_approval', $test->fresh()->status);
    }

    /**
     * SCENARIO 11: Request Revision behavior remains correct for valid flagged assessments.
     */
    public function test_11_request_revision_behavior_remains_correct_for_valid_flagged_assessments(): void
    {
        $test = $this->createEmptyDraftTest();
        $sec1 = $test->sections->first();
        $question = $this->createValidQuestion();

        TestQuestion::create([
            'test_section_id' => $sec1->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $test->update(['status' => 'pending_approval']);

        // Flag question for revision
        $flagRes = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => $question->id]), [
                'field'    => 'stem',
                'comment'  => 'Please clarify prompt phrasing.',
                'severity' => 'warning',
            ]);

        $flagRes->assertRedirect();
        $this->assertEquals('needs_revision', $test->fresh()->status);
    }

    /**
     * SCENARIO 12: Existing assessment approval tests remain green.
     */
    public function test_12_valid_assessment_can_be_approved_by_repository_manager(): void
    {
        $test = $this->createEmptyDraftTest();
        $sec1 = $test->sections->first();
        $question = $this->createValidQuestion();

        TestQuestion::create([
            'test_section_id' => $sec1->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $test->update(['status' => 'pending_approval']);

        $approveRes = $this->actingAs($this->repoManager)
            ->post(route('admin.repository-manager.assessment-approve', $test->id), [
                'notes' => 'Quality standards verified.',
            ]);

        $approveRes->assertRedirect(route('admin.repository-manager.assessment-approval'));
        $approveRes->assertSessionHas('success');

        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertTrue((bool) $test->is_published);
    }
}
