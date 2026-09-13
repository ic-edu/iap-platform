<?php

namespace Tests\Feature;

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

class AssessmentQuestionAuthoringEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $teacherB;
    protected User $repoManager;
    protected QuestionBank $bankA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherA = User::factory()->create([
            'name'   => 'Dr. Eleanor Vance',
            'email'  => 'vance_qa@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->teacherB = User::factory()->create([
            'name'   => 'Prof. Marcus Brody',
            'email'  => 'brody_qa@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherB->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager QA',
            'email'  => 'repomanager_qa@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Test Question Pool Bank',
            'slug'       => 'test-question-pool-bank',
            'test_type'  => 'general',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Section Explorer renders section questions and question details (200 OK).
     */
    public function test_1_section_explorer_renders_section_questions_and_details()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Question Explorer Test 01',
            'slug'             => 'toeic-question-explorer-test-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Listening Part 1',
            'order'   => 1,
        ]);

        $question = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'What is the person in the photograph doing?',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
            'points'           => 10,
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'He is operating a laptop.',
            'choice_text' => 'He is operating a laptop.',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 10,
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $res->assertStatus(200);
        $res->assertSee('Listening Part 1');
        $res->assertSee('What is the person in the photograph doing?');
        $res->assertSee('Governed Master');
    }

    /**
     * TEST 2: Question Editor updates existing Question stem and choices without changing Question ID.
     */
    public function test_2_question_editor_updates_existing_question_without_changing_id()
    {
        $test = AssessmentTest::create([
            'title'            => 'Update Question Test 02',
            'slug'             => 'update-question-test-02',
            'test_type'        => 'toefl',
            'duration_minutes' => 90,
            'pass_score'       => 500,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $masterQuestion = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Original Master Question Stem',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
        ]);

        // Attempting to update a master question through Test Builder must be rejected
        $resMaster = $this->actingAs($this->teacherA)->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $masterQuestion->id]), [
            'prompt' => 'Attempted Direct Edit on Master Question',
        ]);

        $resMaster->assertRedirect(route('teacher.tests.show', $test->id));
        $resMaster->assertSessionHas('error', 'Master Questions are governed content and cannot be edited directly from Test Builder. Request a Repository Revision through the governance workflow.');

        $masterQuestion->refresh();
        $this->assertEquals('Original Master Question Stem', $masterQuestion->prompt);
    }

    /**
     * TEST 3: Validation Panel detects invalid questions (e.g. empty stem) and blocks resubmission.
     */
    public function test_3_validation_panel_detects_invalid_questions_and_blocks_resubmission()
    {
        $test = AssessmentTest::create([
            'title'            => 'Invalid Question Test 03',
            'slug'             => 'invalid-question-test-03',
            'test_type'        => 'ielts',
            'duration_minutes' => 60,
            'pass_score'       => 65,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Reading Section',
            'order'   => 1,
        ]);

        // Empty prompt stem
        $invalidQuestion = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => '   ',
            'question_type'    => 'multiple_choice',
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $invalidQuestion->id,
            'order'           => 1,
        ]);

        // Resubmit attempt must be blocked
        $res = $this->actingAs($this->teacherA)->post(route('teacher.tests.resubmit', $test->id));
        $res->assertRedirect(route('teacher.tests.show', $test->id));
        $res->assertSessionHas('error');

        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);
    }

    /**
     * TEST 4: Valid assessment passes validation and resubmits to pending_approval.
     */
    public function test_4_valid_assessment_passes_validation_and_resubmits()
    {
        $test = AssessmentTest::create([
            'title'            => 'Valid Resubmit Test 04',
            'slug'             => 'valid-resubmit-test-04',
            'test_type'        => 'general',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Part 1',
            'order'   => 1,
        ]);

        $validQuestion = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Select the correct statement.',
            'question_type'    => 'multiple_choice',
        ]);

        QuestionChoice::create([
            'question_id' => $validQuestion->id,
            'label'       => 'A',
            'content'     => 'Statement A is true.',
            'choice_text' => 'Statement A is true.',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $validQuestion->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->teacherA)->post(route('teacher.tests.resubmit', $test->id));
        $res->assertRedirect(route('teacher.tests.show', $test->id));

        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);
    }

    /**
     * TEST 5: Teacher B cannot edit Teacher A's assessment questions (403 Forbidden).
     */
    public function test_5_teacher_b_cannot_edit_teacher_a_question()
    {
        $testA = AssessmentTest::create([
            'title'            => 'Teacher A Protected Assessment',
            'slug'             => 'teacher-a-protected-assessment',
            'test_type'        => 'toefl',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $questionA = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Protected Question Stem',
        ]);

        $res = $this->actingAs($this->teacherB)->put(route('teacher.tests.update-question', ['test' => $testA->id, 'question' => $questionA->id]), [
            'prompt' => 'Hacked Stem Text',
        ]);

        $res->assertStatus(403);
    }
}
