<?php

namespace Tests\Feature;

use App\Models\TestQuestionReview;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuredQuestionReviewEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $repoManager;
    protected QuestionBank $bankA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance',
            'email'  => 'vance_s11_1@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager S11_1',
            'email'  => 'repomanager_s11_1@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Structured Question Review Bank',
            'slug'       => 'structured-question-review-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Repository Manager flags Q2 Needs Revision -> Teacher sees targeted Q2 revision only.
     */
    public function test_1_repository_manager_flags_question_and_teacher_sees_targeted_revision()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Structured Review 01',
            'slug'             => 'toeic-structured-review-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Listening Section',
            'order'   => 1,
        ]);

        // Q1 (Valid)
        $q1 = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Valid Prompt Stem Q1',
            'question_type'    => 'multiple_choice',
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Q2 (Requires Revision)
        $q2 = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Flawed Prompt Stem Q2',
            'question_type'    => 'multiple_choice',
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q2->id, 'order' => 2]);

        // Repository Manager flags Q2 Needs Revision
        $resRev = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => $q2->id]), [
            'field'    => 'stem',
            'comment'  => 'Prompt stem Q2 is ambiguous, please clarify context.',
            'severity' => 'warning',
        ]);
        $resRev->assertRedirect();

        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);

        // Teacher opens Assessment Detail -> sees Q2 feedback
        $teacherView = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $teacherView->assertStatus(200);
        $teacherView->assertSee('Prompt stem Q2 is ambiguous, please clarify context.');
    }

    /**
     * TEST 2: Approve button enabled immediately under Review by Exception, disabled if flagged.
     */
    public function test_2_approve_disabled_when_flagged_questions_exist()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEFL Approval Guard 02',
            'slug'             => 'toefl-approval-guard-02',
            'test_type'        => 'toefl',
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Reading', 'order' => 1]);

        $q1 = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Prompt Q1',
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Flag Q1
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => $q1->id]), [
            'field'    => 'stem',
            'comment'  => 'Fix stem',
            'severity' => 'warning',
        ]);

        // Attempt approve while Q1 is flagged -> MUST BE BLOCKED
        $resApproveBlocked = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $resApproveBlocked->assertRedirect();
        $resApproveBlocked->assertSessionHas('error');

        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);

        // Clear flag Q1
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-review-ok', ['test' => $test->id, 'question' => $q1->id]));

        // Approve attempt -> SUCCESS under Review by Exception
        $resApproveSuccess = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $resApproveSuccess->assertRedirect(route('admin.repository-manager.assessment-review', $test->id));

        $test->refresh();
        $this->assertEquals('approved', $test->status);
        $this->assertFalse((bool) $test->is_published);
    }
}
