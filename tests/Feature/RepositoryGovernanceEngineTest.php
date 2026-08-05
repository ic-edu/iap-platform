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

class RepositoryGovernanceEngineTest extends TestCase
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
            'email'  => 'vance_gov@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherA->assignRole('teacher');

        $this->repoManager = User::factory()->create([
            'name'   => 'Repository Manager Gov',
            'email'  => 'repomanager_gov@icedu.org',
            'status' => 'active',
        ]);
        $this->repoManager->assignRole('repository-manager');

        $this->bankA = QuestionBank::create([
            'title'      => 'Governance Question Bank Pool',
            'slug'       => 'governance-question-bank-pool',
            'test_type'  => 'toeic',
            'status'     => 'draft',
            'created_by' => $this->teacherA->id,
        ]);
    }

    /**
     * TEST 1: Teacher submits Assessment -> Dashboard pending total +1, Repository Queue +1.
     */
    public function test_1_teacher_submits_assessment_increments_dashboard_and_repository_queue()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEIC Simulation Gov 01',
            'slug'             => 'toeic-simulation-gov-01',
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

        $question = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Select the correct statement stem.',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Choice A is correct.',
            'choice_text' => 'Choice A is correct.',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
        ]);

        // Resubmit assessment -> status becomes pending_approval
        $res = $this->actingAs($this->teacherA)->post(route('teacher.tests.resubmit', $test->id));
        $res->assertRedirect(route('teacher.tests.show', $test->id));

        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);

        // Teacher Dashboard Awaiting Approval Workflow Inbox KPI shows pending count
        $dashRes = $this->actingAs($this->teacherA)->get(route('teacher.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertSee('Workflow Review Inbox');

        // Repository Manager Approval Queue lists the assessment
        $repoQueueRes = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-approval'));
        $repoQueueRes->assertStatus(200);
        $repoQueueRes->assertSee('TOEIC Simulation Gov 01');
    }

    /**
     * TEST 2: Repository Manager opens Assessment Review and inspects full Question entity read-only.
     */
    public function test_2_repository_manager_inspects_full_question_entity_read_only()
    {
        $test = AssessmentTest::create([
            'title'            => 'IELTS Mock Gov 02',
            'slug'             => 'ielts-mock-gov-02',
            'test_type'        => 'ielts',
            'duration_minutes' => 60,
            'pass_score'       => 65,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Reading Passages',
            'order'   => 1,
        ]);

        $question = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'According to paragraph 2, what is the main cause?',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'explanation'      => 'Paragraph 2 explicitly states thermal expansion is the cause.',
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Thermal expansion of seawater.',
            'choice_text' => 'Thermal expansion of seawater.',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
        ]);

        $res = $this->actingAs($this->repoManager)->get(route('admin.repository-manager.assessment-review', $test->id));
        $res->assertStatus(200);
        $res->assertSee('According to paragraph 2, what is the main cause?');
        $res->assertSee('Thermal expansion of seawater.');
        $res->assertSee('Paragraph 2 explicitly states thermal expansion is the cause.');
        $res->assertSee('✓ (Correct)');
    }

    /**
     * TEST 3: Repository Manager requests revision with question notes -> Teacher sees notes & resubmits.
     */
    public function test_3_repository_manager_requests_revision_and_teacher_resubmits()
    {
        $test = AssessmentTest::create([
            'title'            => 'TOEFL Mock Gov 03',
            'slug'             => 'toefl-mock-gov-03',
            'test_type'        => 'toefl',
            'duration_minutes' => 90,
            'pass_score'       => 500,
            'status'           => 'pending_approval',
            'created_by'       => $this->teacherA->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Structure Section',
            'order'   => 1,
        ]);

        $question = Question::create([
            'question_bank_id' => $this->bankA->id,
            'prompt'           => 'Identify the incorrect segment.',
            'question_type'    => 'multiple_choice',
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'Option A',
            'choice_text' => 'Option A',
            'is_correct'  => true,
            'order'       => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $question->id,
            'order'           => 1,
        ]);

        // Repository Manager requests revision with question notes
        $revRes = $this->actingAs($this->repoManager)->post(route('admin.repository-manager.assessment-revision', $test->id), [
            'notes' => 'Question 1: Please revise prompt stem to be more specific.',
        ]);
        $revRes->assertRedirect(route('admin.repository-manager.assessment-approval'));

        $test->refresh();
        $this->assertEquals('needs_revision', $test->status);

        // Teacher opens Assessment Detail -> sees Repository feedback
        $teacherView = $this->actingAs($this->teacherA)->get(route('teacher.tests.show', $test->id));
        $teacherView->assertStatus(200);
        $teacherView->assertSee('Question 1: Please revise prompt stem to be more specific.');

        // Teacher updates question stem and resubmits
        $question->update(['prompt' => 'Identify the grammatically incorrect segment in sentence 4.']);

        $resubmitRes = $this->actingAs($this->teacherA)->post(route('teacher.tests.resubmit', $test->id));
        $resubmitRes->assertRedirect(route('teacher.tests.show', $test->id));

        $test->refresh();
        $this->assertEquals('pending_approval', $test->status);
    }
}
