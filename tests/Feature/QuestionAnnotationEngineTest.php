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

class QuestionAnnotationEngineTest extends TestCase
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
            'email'  => 'vance_s11_4@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager S11_4',
            'email'  => 'repomanager_s11_4@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Annotation Engine Bank',
            'slug'       => 'annotation-engine-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Question Review State persistence (Default OK vs Flagged Revision / Critical Issue).
     */
    public function test_1_question_review_state_persistence_and_annotation()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Annotation Test 01',
            'slug'             => 'toeic-annotation-test-01',
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

        // Mark Q1 Reviewed OK / Clear flag (Default OK)
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-review-ok', ['test' => $test->id, 'question' => $q1->id]));

        // Annotate Q2 with Critical Issue
        $this->actingAs($this->repoManager)->post(route('admin.repository-manager.question-request-revision', ['test' => $test->id, 'question' => $q2->id]), [
            'status'   => 'critical_issue',
            'field'    => 'choices',
            'comment'  => 'Answer key option C is missing or invalid.',
            'severity' => 'critical',
        ]);

        $rev1 = TestQuestionReview::where('test_id', $test->id)->where('question_id', $q1->id)->first();
        $rev2 = TestQuestionReview::where('test_id', $test->id)->where('question_id', $q2->id)->first();

        // Under Review by Exception, Q1 has no flag record (implicitly Default OK)
        $this->assertNull($rev1);
        
        // Q2 has a critical_issue flag record
        $this->assertNotNull($rev2);
        $this->assertEquals('critical_issue', $rev2->status);
        $this->assertEquals('choices', $rev2->field);
        $this->assertEquals('Answer key option C is missing or invalid.', $rev2->comment);
    }

    /**
     * TEST 2: Question Navigator renders status colors and workspace displays inline review panel.
     */
    public function test_2_workspace_renders_question_navigator_and_inline_review_panel()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Annotation Workspace 02',
            'slug'             => 'toeic-annotation-workspace-02',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Reading', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Question 1 Stem']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        TestQuestionReview::create(['test_id' => $test->id, 'question_id' => $q1->id, 'status' => 'critical_issue', 'field' => 'stem', 'comment' => 'Stem ambiguous']);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);

        // Verify Question Navigator widget & Inline Review Panel
        $res->assertSee('Question Navigator');
        $res->assertSee('🔴');
        $res->assertSee('Q1');
        $res->assertSee('Annotation Workspace');
        $res->assertSee('Stem ambiguous');
        $res->assertDontSee('q-rev-modal');
    }

    /**
     * TEST 3: Assessment Revision Guard disables return button when no questions are annotated for revision.
     */
    public function test_3_assessment_revision_guard_disables_button_when_no_questions_flagged()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEFL Revision Guard 03',
            'slug'             => 'toefl-revision-guard-03',
            'test_type'        => 'toefl',
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Structure', 'order' => 1]);
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Clean Question 1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);
        $res->assertSee('0 Questions Flagged — No revisions needed');
    }
}
