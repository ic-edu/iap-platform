<?php

namespace Tests\Feature;

use App\Models\TestQuestionReview;
use App\Models\User;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositorySmartReviewEngineTest extends TestCase
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
            'email'  => 'vance_s11_5@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager S11_5',
            'email'  => 'repomanager_s11_5@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Smart Review Engine Bank',
            'slug'       => 'smart-review-engine-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Implicit Approval (Review by Exception). Perfect assessment is approved immediately with 0 flagged questions.
     */
    public function test_1_implicit_approval_allows_instant_approval_when_zero_questions_flagged()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Smart Review 01',
            'slug'             => 'toeic-smart-review-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening', 'order' => 1]);

        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1 Stem']);
        $q2 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 2 Stem']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q2->id, 'order' => 2]);

        // Open review page -> Approve Assessment enabled immediately (0 flagged questions)
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);
        $res->assertSee('Review by Exception Mode');
        $res->assertSee('🟢 Default OK');

        // Execute Instant Approval without clicking Reviewed OK on every question
        $approveRes = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-approve', $test->id));
        $approveRes->assertRedirect();
        
        $this->assertEquals('approved', $test->fresh()->status);
        $this->assertFalse((bool) $test->fresh()->is_published);
    }

    /**
     * TEST 2: Flagging a question disables Approve Assessment and enables Request Revision with badge.
     */
    public function test_2_flagging_question_disables_approval_and_enables_revision_request()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Smart Review 02',
            'slug'             => 'toeic-smart-review-02',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1 Stem']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Flag Q1 via AJAX
        $ajaxRes = $this->actingAs($this->repoManager)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => $q1->id]), [
                'field'    => 'choices',
                'comment'  => 'Choice B is duplicate',
                'severity' => 'warning',
            ]);

        $ajaxRes->assertStatus(200);
        $ajaxRes->assertJson([
            'success'             => true,
            'message'             => 'Review saved.',
            'flagged_count'       => 1,
            'is_allowed'          => false,
            'is_revision_allowed' => true,
        ]);

        // Verify HTML review page reflects 1 Questions Flagged
        $viewRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $viewRes->assertStatus(200);
        $viewRes->assertSee('1 Questions Flagged');
        $viewRes->assertSee('Choice B is duplicate');
    }

    /**
     * TEST 3: Clearing a flag restores Default OK state and enables Instant Approval.
     */
    public function test_3_clearing_flag_restores_default_ok_and_enables_approval()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Smart Review 03',
            'slug'             => 'toeic-smart-review-03',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Listening', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1 Stem']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestionReview::create(['test_id' => $test->id, 'question_id' => $q1->id, 'status' => 'needs_revision', 'field' => 'stem', 'comment' => 'Ambiguous stem']);

        // Clear flag via AJAX
        $clearRes = $this->actingAs($this->repoManager)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.repository-manager.question-review-ok', ['test' => $test->id, 'question' => $q1->id]));

        $clearRes->assertStatus(200);
        $clearRes->assertJson([
            'success'             => true,
            'message'             => 'Flag cleared — Question marked OK.',
            'flagged_count'       => 0,
            'is_allowed'          => true,
            'is_revision_allowed' => false,
        ]);
    }
}
