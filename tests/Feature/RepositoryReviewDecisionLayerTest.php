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

class RepositoryReviewDecisionLayerTest extends TestCase
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
            'email'  => 'vance_s11_2@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager S11_2',
            'email'  => 'repomanager_s11_2@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Review Decision Layer Bank',
            'slug'       => 'review-decision-layer-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Assessment Review is pure read-only Question Viewer without inline popups.
     */
    public function test_1_decision_panel_separates_review_from_revision()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Review Decision 01',
            'slug'             => 'toeic-review-decision-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Part 1', 'order' => 1]);

        $q1 = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Question 1 Prompt',
            'question_type'    => 'multiple_choice',
        ]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);

        // Open review page -> Pure Read-Only Question Viewer (HOTFIX S11.3.1)
        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);
        $res->assertDontSee('q-rev-modal');
        $res->assertDontSee('Request Revision on Q#');
        $res->assertDontSee('Submit Question Revision');
    }

    /**
     * TEST 2: Teacher Revision Center displays ONLY questions marked Needs Revision.
     */
    public function test_2_teacher_revision_center_displays_only_needs_revision_questions()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEFL Teacher Compatibility 02',
            'slug'             => 'toefl-teacher-compatibility-02',
            'test_type'        => 'toefl',
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create(['test_id' => $test->id, 'title' => 'Structure', 'order' => 1]);

        // Q1 (Reviewed OK)
        $q1 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Perfect Question Stem Q1']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestionReview::create(['test_id' => $test->id, 'question_id' => $q1->id, 'status' => 'reviewed_ok']);

        // Q2 (Needs Revision)
        $q2 = Question::create(['question_bank_id' => $this->bankA->id, 'prompt' => 'Flawed Question Stem Q2']);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q2->id, 'order' => 2]);
        TestQuestionReview::create(['test_id' => $test->id, 'question_id' => $q2->id, 'status' => 'needs_revision', 'field' => 'stem', 'comment' => 'Fix Q2 stem']);

        // Teacher opens Assessment Detail -> sees Q2 revision item ONLY
        $teacherView = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $teacherView->assertStatus(200);
        $teacherView->assertSee('Flawed Question Stem Q2');
        $teacherView->assertSee('Fix Q2 stem');
    }
}
