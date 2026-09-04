<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Engines\TOEICScoringEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorScoringThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $this->candidate = User::factory()->create();
        $this->candidate->assignRole('student');

        $this->teacher = User::factory()->create();
        $this->teacher->assignRole('teacher');
    }

    /**
     * Helper to create a test with sections, questions, and attempt.
     */
    protected function createSimulatorAttempt(int $totalQuestions, int $correctCount, string $testType = 'toeic', int $passScore = 700): Attempt
    {
        $test = Test::create([
            'title'            => 'Simulator Test Fixture',
            'slug'             => 'simulator-test-' . uniqid(),
            'test_type'        => $testType,
            'assessment_mode'  => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score'       => $passScore,
            'scoring_method'   => 'automatic',
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Practice Section',
            'section_type' => SectionType::Listening,
            'order'        => 1,
        ]);

        $attempt = Attempt::create([
            'test_id'           => $test->id,
            'user_id'           => $this->candidate->id,
            'attempt_token'     => 'sim-tok-' . uniqid(),
            'status'            => AttemptStatus::Submitted,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at'        => now()->subMinutes(30),
            'submitted_at'      => now(),
        ]);

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $q = Question::create([
                'prompt'        => "Question {$i}",
                'question_type' => QuestionType::MultipleChoice,
                'points'        => 1,
                'section'       => SectionType::Listening,
            ]);

            TestQuestion::create([
                'test_section_id' => $section->id,
                'question_id'     => $q->id,
                'order'           => $i,
                'points'          => 1,
            ]);

            $cCorrect = QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => 'A',
                'content'     => 'Correct Choice',
                'is_correct'  => true,
            ]);

            $cWrong = QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => 'B',
                'content'     => 'Wrong Choice',
                'is_correct'  => false,
            ]);

            $isCorrect = ($i <= $correctCount);
            Answer::create([
                'attempt_id'         => $attempt->id,
                'question_id'        => $q->id,
                'selected_choice_id' => $isCorrect ? $cCorrect->id : $cWrong->id,
                'is_correct'         => $isCorrect,
                'score_earned'       => $isCorrect ? 1 : 0,
            ]);
        }

        $attempt->update(['total_score' => $correctCount]);

        return $attempt;
    }

    /**
     * TEST SCORE-01: Simulator 0/68 -> 0% -> FAILED.
     */
    public function test_score_01_simulator_zero_correct_fails()
    {
        $attempt = $this->createSimulatorAttempt(68, 0);

        $result = app(ResultEngine::class)->generateResult($attempt);

        $this->assertEquals(0, $result['correct_count']);
        $this->assertEquals(68, $result['total_questions']);
        $this->assertEquals(0.0, $result['percentage']);
        $this->assertFalse($result['is_passed']);
        $this->assertTrue($result['is_practice']);
    }

    /**
     * TEST SCORE-02: Simulator 50/68 -> 73.53% -> FAILED (Boundary < 75%).
     */
    public function test_score_02_simulator_below_75_percent_fails()
    {
        $attempt = $this->createSimulatorAttempt(68, 50);

        $result = app(ResultEngine::class)->generateResult($attempt);

        $this->assertEquals(50, $result['correct_count']);
        $this->assertEquals(68, $result['total_questions']);
        $this->assertEquals(73.53, $result['percentage']);
        $this->assertFalse($result['is_passed']);
    }

    /**
     * TEST SCORE-03: Simulator exactly 75.00% and 75.01% -> PASSED.
     */
    public function test_score_03_simulator_exact_75_percent_passes()
    {
        // 75/100 = 75.00% -> PASSED
        $attempt = $this->createSimulatorAttempt(100, 75);
        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertEquals(75.0, $result['percentage']);
        $this->assertTrue($result['is_passed']);

        // 74/100 = 74.00% (< 75%) -> FAILED
        $attemptLow = $this->createSimulatorAttempt(100, 74);
        $resultLow = app(ResultEngine::class)->generateResult($attemptLow);
        $this->assertEquals(74.0, $resultLow['percentage']);
        $this->assertFalse($resultLow['is_passed']);

        // 76/100 = 76.00% (> 75%) -> PASSED
        $attemptHigh = $this->createSimulatorAttempt(100, 76);
        $resultHigh = app(ResultEngine::class)->generateResult($attemptHigh);
        $this->assertEquals(76.0, $resultHigh['percentage']);
        $this->assertTrue($resultHigh['is_passed']);
    }

    /**
     * TEST SCORE-04: Current UAT specimen 66/68 -> 97.06% -> PASSED.
     */
    public function test_score_04_simulator_66_of_68_uat_specimen_passes()
    {
        $attempt = $this->createSimulatorAttempt(68, 66);

        $result = app(ResultEngine::class)->generateResult($attempt);

        $this->assertEquals(66, $result['correct_count']);
        $this->assertEquals(68, $result['total_questions']);
        $this->assertEquals(97.06, $result['percentage']);
        $this->assertEquals(66.0, $result['final_score']);
        $this->assertEquals('A+', $result['grade']);
        $this->assertTrue($result['is_passed']);
        $this->assertTrue($result['is_practice']);
    }

    /**
     * TEST SCORE-05: Simulator 68/68 -> 100% -> PASSED.
     */
    public function test_score_05_simulator_perfect_score_passes()
    {
        $attempt = $this->createSimulatorAttempt(68, 68);

        $result = app(ResultEngine::class)->generateResult($attempt);

        $this->assertEquals(100.0, $result['percentage']);
        $this->assertTrue($result['is_passed']);
    }

    /**
     * TEST SCORE-06: Pass Threshold: 75% copy rendered on result page; 700 not shown as pass threshold.
     */
    public function test_score_06_simulator_result_view_renders_75_percent_threshold()
    {
        $attempt = $this->createSimulatorAttempt(68, 66, 'toeic', 700);

        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.review', $attempt));

        $response->assertStatus(200);
        $response->assertSee('RESULT: PASSED');
        $response->assertDontSee('RESULT: FAILED');
        $response->assertSee('Pass Threshold: 75%');
        $response->assertDontSee('Pass Threshold: 700');
        $response->assertSee('Practice Score');
        $response->assertSee('97.06%');
        $response->assertSee('Grade A+');
    }

    /**
     * TEST SCORE-07: Mock Test does NOT use Simulator 75% pass policy.
     */
    public function test_score_07_mock_test_does_not_use_simulator_75_percent_policy()
    {
        // Create full TOEIC Mock Test (RealTest mode) with 100 Listening + 100 Reading questions
        $mockTest = Test::create([
            'title'            => 'Full TOEIC Institutional Mock Test',
            'slug'             => 'mock-toeic-' . uniqid(),
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'scoring_method'   => 'automatic',
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $secL = TestSection::create([
            'test_id'      => $mockTest->id,
            'title'        => 'Listening Section',
            'section_type' => SectionType::Listening,
            'order'        => 1,
        ]);

        $secR = TestSection::create([
            'test_id'      => $mockTest->id,
            'title'        => 'Reading Section',
            'section_type' => SectionType::Reading,
            'order'        => 2,
        ]);

        $attempt = Attempt::create([
            'test_id'           => $mockTest->id,
            'user_id'           => $this->candidate->id,
            'attempt_token'     => 'mock-tok-' . uniqid(),
            'status'            => AttemptStatus::Submitted,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at'        => now()->subMinutes(120),
            'submitted_at'      => now(),
        ]);

        // 100 Listening questions: 70 correct
        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'prompt'        => "L{$i}",
                'question_type' => QuestionType::MultipleChoice,
                'points'        => 1,
                'section'       => SectionType::Listening,
            ]);
            TestQuestion::create(['test_section_id' => $secL->id, 'question_id' => $q->id, 'order' => $i]);
            $isCorrect = ($i <= 70);
            Answer::create([
                'attempt_id'  => $attempt->id,
                'question_id' => $q->id,
                'is_correct'  => $isCorrect,
            ]);
        }

        // 100 Reading questions: 60 correct
        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'prompt'        => "R{$i}",
                'question_type' => QuestionType::MultipleChoice,
                'points'        => 1,
                'section'       => SectionType::Reading,
            ]);
            TestQuestion::create(['test_section_id' => $secR->id, 'question_id' => $q->id, 'order' => $i]);
            $isCorrect = ($i <= 60);
            Answer::create([
                'attempt_id'  => $attempt->id,
                'question_id' => $q->id,
                'is_correct'  => $isCorrect,
            ]);
        }

        // Total correct = 130 / 200 (65% accuracy)
        // Scaled score: L70 -> 360, R60 -> 255. Total = 615 / 990.
        // Pass score is 700. Since 615 < 700, this MUST FAIL.
        $result = app(ResultEngine::class)->generateResult($attempt);

        $this->assertEquals(615.0, $result['final_score']);
        $this->assertEquals(700.0, $result['pass_score']);
        $this->assertFalse($result['is_passed']);
        $this->assertFalse($result['is_practice']);
        $this->assertTrue($result['is_full_toeic']);

        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.review', $attempt));

        $response->assertStatus(200);
        $response->assertSee('RESULT: FAILED');
        $response->assertSee('Pass Threshold: 700');
    }

    /**
     * TEST SCORE-08 & 09: Non-TOEIC Simulator tests use universal 75% threshold.
     */
    public function test_score_08_and_09_general_simulator_uses_75_percent_threshold()
    {
        // 80/100 on General Simulator -> PASSED
        $genPass = $this->createSimulatorAttempt(100, 80, 'general', 90);
        $resPass = app(ResultEngine::class)->generateResult($genPass);
        $this->assertTrue($resPass['is_passed']);

        // 70/100 on General Simulator -> FAILED
        $genFail = $this->createSimulatorAttempt(100, 70, 'general', 60);
        $resFail = app(ResultEngine::class)->generateResult($genFail);
        $this->assertFalse($resFail['is_passed']);
    }
}
