<?php

namespace Tests\Feature;

use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\ScoringEngine;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterQuestionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected QuestionBank $masterBank;
    protected Question $masterQuestion;
    protected QuestionChoice $choiceA;
    protected QuestionChoice $choiceB;
    protected QuestionChoice $choiceC;
    protected QuestionChoice $choiceD;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name' => 'Governed Teacher',
            'email' => 'teacher_gov@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create([
            'name' => 'Candidate Student',
            'email' => 'student_gov@test.com',
            'status' => 'active',
        ]);
        $this->student->assignRole('student');

        // Create published institutional Master Question Bank
        $this->masterBank = QuestionBank::create([
            'title' => 'TOEIC Institutional Master Bank',
            'slug' => 'toeic-master-bank-' . uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'is_published' => true,
            'created_by' => $this->teacher->id,
        ]);

        // Create master question
        $this->masterQuestion = Question::create([
            'question_bank_id' => $this->masterBank->id,
            'prompt' => 'Official Master Question Stem: What is the capital of France?',
            'question_type' => QuestionType::MultipleChoice,
            'difficulty' => DifficultyLevel::Easy,
            'points' => 10,
            'explanation' => 'Paris is the capital of France.',
            'created_by' => $this->teacher->id,
        ]);

        $this->choiceA = QuestionChoice::create(['question_id' => $this->masterQuestion->id, 'label' => 'A', 'content' => 'Paris', 'is_correct' => true, 'order' => 1]);
        $this->choiceB = QuestionChoice::create(['question_id' => $this->masterQuestion->id, 'label' => 'B', 'content' => 'London', 'is_correct' => false, 'order' => 2]);
        $this->choiceC = QuestionChoice::create(['question_id' => $this->masterQuestion->id, 'label' => 'C', 'content' => 'Berlin', 'is_correct' => false, 'order' => 3]);
        $this->choiceD = QuestionChoice::create(['question_id' => $this->masterQuestion->id, 'label' => 'D', 'content' => 'Madrid', 'is_correct' => false, 'order' => 4]);
    }

    private function createTestWithStatus(string $status): Test
    {
        $test = Test::create([
            'title' => 'Assessment ' . ucfirst($status),
            'slug' => 'test-' . $status . '-' . uniqid(),
            'test_type' => TestType::Toeic,
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 60,
            'pass_score' => 70,
            'status' => $status,
            'is_published' => ($status === 'published'),
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Core Section',
            'order' => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $this->masterQuestion->id,
            'order' => 1,
            'points' => 10,
        ]);

        return $test;
    }

    /**
     * 1. Teacher can add Master Question reference to DRAFT Assessment.
     */
    public function test_1_teacher_can_add_master_question_to_draft_assessment()
    {
        $test = $this->createTestWithStatus('draft');
        $section = $test->sections->first();

        // Create a second master question
        $secondMasterQ = Question::create([
            'question_bank_id' => $this->masterBank->id,
            'prompt' => 'Second Master Question Stem',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
            'created_by' => $this->teacher->id,
        ]);

        /** @var TestBuilderService $service */
        $service = app(TestBuilderService::class);
        $tq = $service->assignQuestionToSection($section, $secondMasterQ->id, 2, 15);

        $this->assertNotNull($tq);
        $this->assertEquals($section->id, $tq->test_section_id);
        $this->assertEquals($secondMasterQ->id, $tq->question_id);
        $this->assertEquals(2, $tq->order);
        $this->assertEquals(15, $tq->points);

        // Verify no duplicate/forked question was created in questions table
        $this->assertEquals(2, Question::where('question_bank_id', $this->masterBank->id)->count());
    }

    /**
     * 2. Teacher can remove Master Question reference from DRAFT Assessment.
     */
    public function test_2_teacher_can_remove_master_question_from_draft_assessment()
    {
        $test = $this->createTestWithStatus('draft');
        $section = $test->sections->first();

        $tq = TestQuestion::where('test_section_id', $section->id)
            ->where('question_id', $this->masterQuestion->id)
            ->first();

        $this->assertNotNull($tq);
        $tq->delete();

        $this->assertDatabaseMissing('test_questions', ['id' => $tq->id]);
        // Master Question in bank must remain intact
        $this->assertDatabaseHas('questions', ['id' => $this->masterQuestion->id]);
    }

    /**
     * 3. Teacher can reorder Questions in DRAFT Assessment.
     */
    public function test_3_teacher_can_reorder_questions_in_draft_assessment()
    {
        $test = $this->createTestWithStatus('draft');
        $section = $test->sections->first();

        $tq = TestQuestion::where('test_section_id', $section->id)->first();
        $tq->update(['order' => 5, 'points' => 25]);

        $tq->refresh();
        $this->assertEquals(5, $tq->order);
        $this->assertEquals(25, $tq->points);
    }

    /**
     * 4. Teacher CANNOT edit Master Question content from Test Builder (prompt stem).
     */
    public function test_4_teacher_cannot_edit_master_question_stem_from_test_builder()
    {
        $test = $this->createTestWithStatus('draft');

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $this->masterQuestion->id]), [
                'prompt' => 'Illegally Mutated Master Question Stem Text',
                'question_type' => 'multiple_choice',
            ]);

        $response->assertRedirect(route('teacher.tests.show', $test->id));
        $response->assertSessionHas('error', 'Master Questions are governed content and cannot be edited directly from Test Builder. Request a Repository Revision through the governance workflow.');

        $this->masterQuestion->refresh();
        $this->assertEquals('Official Master Question Stem: What is the capital of France?', $this->masterQuestion->prompt);
    }

    /**
     * 5. Teacher CANNOT modify Master Question choices from Test Builder.
     */
    public function test_5_teacher_cannot_modify_master_question_choices_from_test_builder()
    {
        $test = $this->createTestWithStatus('draft');

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $this->masterQuestion->id]), [
                'prompt' => $this->masterQuestion->prompt,
                'choices' => ['Hacked Choice 1', 'Hacked Choice 2'],
                'correct_choice' => '0',
            ]);

        $response->assertRedirect(route('teacher.tests.show', $test->id));

        $this->choiceA->refresh();
        $this->assertEquals('Paris', $this->choiceA->content);
        $this->assertEquals('London', $this->choiceB->fresh()->content);
    }

    /**
     * 6. Teacher CANNOT change Master Question correct answer from Test Builder.
     */
    public function test_6_teacher_cannot_change_master_question_correct_answer_from_test_builder()
    {
        $test = $this->createTestWithStatus('draft');

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $this->masterQuestion->id]), [
                'prompt' => $this->masterQuestion->prompt,
                'choices' => ['Paris', 'London', 'Berlin', 'Madrid'],
                'correct_choice' => '1', // Attempting to make London correct
            ]);

        $response->assertRedirect(route('teacher.tests.show', $test->id));

        $this->choiceA->refresh();
        $this->assertTrue($this->choiceA->is_correct);
        $this->assertFalse($this->choiceB->fresh()->is_correct);
    }

    /**
     * 7. Teacher CANNOT modify questions in PENDING_APPROVAL Assessment.
     */
     public function test_7_teacher_cannot_modify_questions_in_pending_approval_assessment()
     {
         $test = $this->createTestWithStatus('pending_approval');

         $response = $this->actingAs($this->teacher)
             ->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $this->masterQuestion->id]), [
                 'prompt' => 'Attempted Edit on Pending Assessment Question',
             ]);

         $response->assertStatus(403);
     }

    /**
     * 8. Teacher CANNOT modify questions in APPROVED Assessment.
     */
    public function test_8_teacher_cannot_modify_questions_in_approved_assessment()
    {
        $test = $this->createTestWithStatus('approved');

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $this->masterQuestion->id]), [
                'prompt' => 'Attempted Edit on Approved Test Question',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 9. Teacher CANNOT modify questions in PUBLISHED Assessment.
     */
    public function test_9_teacher_cannot_modify_questions_in_published_assessment()
    {
        $test = $this->createTestWithStatus('published');

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $test->id, 'question' => $this->masterQuestion->id]), [
                'prompt' => 'Attempted Edit on Published Test Question',
            ]);

        $response->assertStatus(403);
    }

    /**
     * 10. Official Repository Revision route remains functional for Master Question content changes.
     */
    public function test_10_official_repository_revision_route_remains_functional()
    {
        // Create an official Repository Revision Request for the bank
        $revRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->masterBank->id,
            'teacher_id' => $this->teacher->id,
            'requested_by_id' => 1,
            'status' => 'OPEN',
            'notes' => 'Please update Paris explanation and refine wording.',
        ]);

        $revItem = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $revRequest->id,
            'question_bank_id' => $this->masterBank->id,
            'question_id' => $this->masterQuestion->id,
            'status' => 'OPEN',
            'feedback' => 'Refine explanation text.',
        ]);

        // Put master question bank in needs_revision state for authoring revision
        $this->masterBank->update(['status' => 'needs_revision']);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.repository-revisions.update-question', [
                'revisionRequest' => $revRequest->id,
                'item' => $revItem->id,
            ]), [
                'prompt' => 'Updated via Official Revision: What is the capital of France?',
                'explanation' => 'Paris is the official capital and largest city of France.',
                'choices' => [
                    $this->choiceA->id => ['content' => 'Paris, France', 'label' => 'A'],
                    $this->choiceB->id => ['content' => 'London, UK', 'label' => 'B'],
                ],
                'correct_choice_id' => $this->choiceA->id,
            ]);

        $response->assertRedirect();

        $this->masterQuestion->refresh();
        $this->assertEquals('Updated via Official Revision: What is the capital of France?', $this->masterQuestion->prompt);
        $this->assertEquals('Paris is the official capital and largest city of France.', $this->masterQuestion->explanation);
        $this->assertEquals('Paris, France', $this->choiceA->fresh()->content);
    }

    /**
     * 11. Master Question remains unchanged after rejected Test Builder JSON/AJAX mutation attempt.
     */
    public function test_11_master_question_remains_unchanged_after_ajax_mutation_attempt()
    {
        $test = $this->createTestWithStatus('draft');

        $response = $this->actingAs($this->teacher)
            ->json('PUT', route('teacher.tests.update-question', ['test' => $test->id, 'question' => $this->masterQuestion->id]), [
                'prompt' => 'AJAX Attempted Stem Mutation',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Master Questions are governed content and cannot be edited directly from Test Builder. Request a Repository Revision through the governance workflow.',
        ]);

        $this->masterQuestion->refresh();
        $this->assertEquals('Official Master Question Stem: What is the capital of France?', $this->masterQuestion->prompt);
    }

    /**
     * 12. Existing automatic scoring behavior remains intact when Assessment uses Master Questions.
     */
    public function test_12_existing_automatic_scoring_behavior_remains_intact()
    {
        $test = $this->createTestWithStatus('published');

        /** @var AttemptEngine $attemptEngine */
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->student);

        // Candidate selects Choice A (Paris - Correct)
        /** @var \App\Modules\Assessment\Engines\AutoSaveEngine $autoSaveEngine */
        $autoSaveEngine = app(\App\Modules\Assessment\Engines\AutoSaveEngine::class);
        $autoSaveEngine->saveAnswer($attempt, (string) $this->masterQuestion->id, (string) $this->choiceA->id);

        $attemptEngine->submitAttempt($attempt);

        $attempt->refresh();
        $this->assertEquals(10.0, (float) $attempt->total_score);

        $answer = $attempt->answers->first();
        $this->assertNotNull($answer);
        $this->assertTrue($answer->is_correct);
        $this->assertEquals(10.0, (float) $answer->score_earned);
    }
}
