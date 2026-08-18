<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Engines\ScoringEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringFoundationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name' => 'Teacher Author',
            'email' => 'teacher_author@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->candidate = User::factory()->create([
            'name' => 'Candidate Student',
            'email' => 'candidate_student@test.com',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');
    }

    /**
     * Helper to create test with sections and questions.
     */
    protected function createFullTest(ScoringMethod $method, int $passScore = 50): array
    {
        $bank = \App\Modules\QuestionBank\Models\QuestionBank::create([
            'title' => 'Test Question Bank for Scoring',
            'slug' => 'test-bank-scoring-' . uniqid(),
            'test_type' => 'general',
            'status' => 'published',
            'is_published' => true,
            'created_by' => $this->teacher->id,
        ]);

        $test = Test::create([
            'title' => 'Test Assessment ' . $method->value,
            'slug' => 'test-assessment-' . $method->value . '-' . uniqid(),
            'test_type' => TestType::General,
            'scoring_method' => $method,
            'duration_minutes' => 60,
            'pass_score' => $passScore,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Section 1: General Core',
            'order' => 1,
        ]);

        // Objective Question (Multiple Choice, 50 points)
        $qObj = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'What is the capital of Indonesia?',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 50,
            'created_by' => $this->teacher->id,
        ]);
        $c1 = QuestionChoice::create(['question_id' => $qObj->id, 'label' => 'A', 'content' => 'Jakarta', 'is_correct' => true, 'order' => 1]);
        $c2 = QuestionChoice::create(['question_id' => $qObj->id, 'label' => 'B', 'content' => 'Surabaya', 'is_correct' => false, 'order' => 2]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $qObj->id,
            'order' => 1,
            'points' => 50,
        ]);

        // Subjective Question (Essay, 50 points)
        $qSub = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'Discuss the socioeconomic impact of sustainable energy in ASEAN.',
            'question_type' => QuestionType::Essay,
            'points' => 50,
            'reference_answer' => 'Model essay on renewable investment and employment generation.',
            'created_by' => $this->teacher->id,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $qSub->id,
            'order' => 2,
            'points' => 50,
        ]);

        return [$test, $qObj, $c1, $c2, $qSub];
    }

    /**
     * 1. Existing automatic test defaults to automatic.
     */
    public function test_existing_test_defaults_to_automatic_scoring_method()
    {
        $test = Test::create([
            'title' => 'Legacy Test',
            'slug' => 'legacy-test-' . uniqid(),
            'test_type' => TestType::General,
            'duration_minutes' => 30,
            'pass_score' => 60,
            'created_by' => $this->teacher->id,
        ]);

        $this->assertEquals(ScoringMethod::Automatic, $test->scoring_method);
        $this->assertTrue($test->isAutomatic());
        $this->assertFalse($test->requiresEvaluation());
    }

    /**
     * 2. Automatic attempt still scores immediately on submission.
     * 3. Automatic attempt reaches final result.
     * 4. Automatic certificate behavior remains unchanged.
     */
    public function test_automatic_attempt_scores_immediately_and_issues_certificate_when_passed()
    {
        [$test, $qObj, $c1, $c2, $qSub] = $this->createFullTest(ScoringMethod::Automatic, 50);

        /** @var AttemptEngine $attemptEngine */
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        $this->assertEquals(EvaluationStatus::NotRequired, $attempt->evaluation_status);
        $this->assertEquals(AttemptStatus::InProgress, $attempt->status);

        // Candidate answers correctly on objective question
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $qObj->id,
            'selected_choice_id' => $c1->id,
        ]);

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
        $this->assertEquals(EvaluationStatus::NotRequired, $attempt->evaluation_status);
        $this->assertEquals(50.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertTrue($result['is_passed']);
        $this->assertFalse($result['is_pending_evaluation']);
        $this->assertEquals('Submitted & Completed', $result['completion_status']);

        // Certificate must be issued for passed automatic test
        $this->assertDatabaseHas('certificates', [
            'attempt_id' => $attempt->id,
            'user_id' => $this->candidate->id,
        ]);
    }

    /**
     * 5. Human attempt becomes pending_evaluation.
     * 6. Human attempt does NOT issue certificate on submission.
     */
    public function test_human_attempt_becomes_pending_evaluation_and_defers_certificate()
    {
        [$test, $qObj, $c1, $c2, $qSub] = $this->createFullTest(ScoringMethod::Human, 50);

        /** @var AttemptEngine $attemptEngine */
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        $this->assertEquals(EvaluationStatus::PendingEvaluation, $attempt->evaluation_status);

        // Candidate submits essay
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $qSub->id,
            'text_response' => 'Comprehensive candidate analysis of renewable energy transitions.',
        ]);

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
        $this->assertEquals(EvaluationStatus::PendingEvaluation, $attempt->evaluation_status);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertTrue($result['is_pending_evaluation']);
        $this->assertFalse($result['is_passed']);
        $this->assertEquals('Pending Evaluation', $result['grade']);
        $this->assertEquals('Awaiting Examiner Evaluation', $result['completion_status']);

        // Certificate must NOT be issued yet
        $this->assertDatabaseMissing('certificates', [
            'attempt_id' => $attempt->id,
        ]);
    }

    /**
     * 7. Hybrid attempt becomes pending_evaluation.
     * 8. Hybrid attempt preserves automatic objective score.
     * 9. Hybrid attempt does NOT issue final certificate yet.
     * 10. Pending human evaluation is not presented as fully completed.
     */
    public function test_hybrid_attempt_scores_objective_preserves_subjective_and_defers_certificate()
    {
        [$test, $qObj, $c1, $c2, $qSub] = $this->createFullTest(ScoringMethod::Hybrid, 50);

        /** @var AttemptEngine $attemptEngine */
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        $this->assertEquals(EvaluationStatus::PendingEvaluation, $attempt->evaluation_status);

        // Candidate answers objective question correctly
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $qObj->id,
            'selected_choice_id' => $c1->id,
        ]);

        // Candidate writes essay response
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $qSub->id,
            'text_response' => 'In-depth essay text describing the economic multiplier effect.',
        ]);

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        // 7. Status is pending_evaluation
        $this->assertEquals(EvaluationStatus::PendingEvaluation, $attempt->evaluation_status);

        // 8. Objective score (50.0) is preserved and auto-scored
        $this->assertEquals(50.0, (float) $attempt->total_score);

        $ansObj = Answer::where('attempt_id', $attempt->id)->where('question_id', $qObj->id)->first();
        $this->assertTrue($ansObj->is_correct);
        $this->assertEquals(50.0, (float) $ansObj->score_earned);

        $ansSub = Answer::where('attempt_id', $attempt->id)->where('question_id', $qSub->id)->first();
        $this->assertNull($ansSub->is_correct); // not false, waiting for human evaluation
        $this->assertEquals('In-depth essay text describing the economic multiplier effect.', $ansSub->text_response);

        // 9. Certificate is NOT issued yet (even though total_score >= pass_score)
        $this->assertDatabaseMissing('certificates', [
            'attempt_id' => $attempt->id,
        ]);

        // 10. Result presentation reflects pending evaluation
        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertTrue($result['is_pending_evaluation']);
        $this->assertFalse($result['is_passed']);
        $this->assertEquals('Pending Evaluation', $result['grade']);
        $this->assertEquals('Awaiting Examiner Evaluation', $result['completion_status']);
    }

    /**
     * 11. Test Builder Controller persists scoring_method configuration.
     */
    public function test_test_builder_stores_and_updates_scoring_method()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.tests.store'), [
                'title' => 'IELTS Academic Writing Test',
                'test_type' => 'ielts',
                'scoring_method' => 'human',
                'duration_minutes' => 60,
                'pass_score' => 65,
            ]);

        $response->assertRedirect();

        $test = Test::where('title', 'IELTS Academic Writing Test')->first();
        $this->assertNotNull($test);
        $this->assertEquals(ScoringMethod::Human, $test->scoring_method);
        $this->assertTrue($test->isHuman());

        // Update to hybrid
        $updateResp = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update', $test->id), [
                'title' => 'IELTS Academic Combined Test',
                'test_type' => 'ielts',
                'scoring_method' => 'hybrid',
                'duration_minutes' => 90,
                'pass_score' => 70,
            ]);

        $updateResp->assertRedirect();
        $test->refresh();
        $this->assertEquals(ScoringMethod::Hybrid, $test->scoring_method);
        $this->assertTrue($test->isHybrid());
    }
}
