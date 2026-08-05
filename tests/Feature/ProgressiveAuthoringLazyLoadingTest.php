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

class ProgressiveAuthoringLazyLoadingTest extends TestCase
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
            'email'  => 'vance_lazy@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->teacherB = User::factory()->create([
            'name'   => 'Prof. Marcus Brody',
            'email'  => 'brody_lazy@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherB->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Lazy',
            'email'  => 'repomanager_lazy@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Lazy Question Bank',
            'slug'       => 'lazy-question-bank',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Assessment Detail renders lightweight Revision Summary with Open Question buttons.
     */
    public function test_1_assessment_detail_renders_lightweight_summary()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Lightweight Test 01',
            'slug'             => 'toeic-lightweight-test-01',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Part 1 Listening',
            'order'   => 1,
        ]);

        $question = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Lightweight Question Stem Prompt',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Option A Text',
            'choice_text' => 'Option A Text',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $res->assertStatus(200);
        $res->assertSee('Progressive Revision Summary');
        $res->assertSee('Open Question');
    }

    /**
     * TEST 2: Clicking Open Question lazy-loads ONLY the requested single question.
     */
    public function test_2_open_question_lazy_loads_single_question_payload()
    {
        $test = AssessmentTest::create([
            'title'            => 'Lazy Single Load Test 02',
            'slug'             => 'lazy-single-load-test-02',
            'test_type'        => 'toefl',
            'duration_minutes' => 90,
            'pass_score'       => 500,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $question = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Isolated Question Stem for Lazy Load',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
        ]);

        $res = $this->actingAs($this->teacherA)->get(route('teacher.tests.edit-question', ['test' => $test->id, 'question' => $question->id]));
        $res->assertStatus(200);
        $res->assertSee('Isolated Question Stem for Lazy Load');
        $res->assertSee('Focused Question Authoring Workspace');
    }

    /**
     * TEST 3: Single Question save updates question and returns to Revision Summary.
     */
    public function test_3_single_question_save_updates_and_returns_to_summary()
    {
        $test = AssessmentTest::create([
            'title'            => 'Single Save Test 03',
            'slug'             => 'single-save-test-03',
            'test_type'        => 'ielts',
            'duration_minutes' => 60,
            'pass_score'       => 65,
            'status'           => 'needs_revision',
            'created_by'       => $this->teacherA->id,
        ]);

        $question = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Original Prompt Before Save',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
        ]);

        $res = $this->actingAs($this->teacherA)->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $question->id]), [
            'prompt'         => 'Updated Prompt After Focused Save',
            'question_type'  => 'multiple_choice',
            'difficulty'     => 'hard',
            'choices'        => ['New Choice 1'],
            'correct_choice' => '0',
        ]);

        $res->assertRedirect(route('teacher.tests.show', $test->id));

        $question->refresh();
        $this->assertEquals('Updated Prompt After Focused Save', $question->prompt);
    }

    /**
     * TEST 4: Submit Again is disabled when validation issues exist, and enables when valid.
     */
    public function test_4_submit_again_validation_gating()
    {
        $test = AssessmentTest::create([
            'title'            => 'Gating Test 04',
            'slug'             => 'gating-test-04',
            'test_type'        => 'toeic',
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

        // Question with empty prompt stem -> invalid
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

        $resInvalid = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $resInvalid->assertStatus(200);
        $resInvalid->assertSee('Submit Disabled (Validation Required)');

        // Fix question prompt & choice
        $invalidQuestion->update(['prompt' => 'Fixed Valid Stem Prompt']);
        QuestionChoice::create([
            'question_id' => $invalidQuestion->id,
            'label'       => 'A',
            'content'     => 'Valid Option A',
            'choice_text' => 'Valid Option A',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        $resValid = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $resValid->assertStatus(200);
        $resValid->assertSee('Submit Again for Review');
    }

    /**
     * TEST 5: Teacher B cannot lazy-load or edit Teacher A's question on demand.
     */
    public function test_5_teacher_b_cannot_lazy_load_teacher_a_question()
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
            'prompt'           => 'Teacher A Private Stem',
        ]);

        $resView = $this->actingAs($this->teacherB)->get(route('teacher.tests.edit-question', ['test' => $testA->id, 'question' => $questionA->id]));
        $resView->assertStatus(403);
    }
}
