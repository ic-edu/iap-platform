<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Engines\ReviewEngine;
use App\Modules\Assessment\Engines\TOEICScoringEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TOEICScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $candidate;

    protected TOEICScoringEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->engine = app(TOEICScoringEngine::class);

        $this->teacher = User::factory()->create([
            'name' => 'TOEIC Teacher',
            'email' => 'toeic_teacher@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->candidate = User::factory()->create([
            'name' => 'TOEIC Candidate',
            'email' => 'toeic_candidate@test.com',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');
    }

    /**
     * 1. Test exact conversion table values against required test specifications.
     */
    public function test_conversion_table_mappings(): void
    {
        // 0 correct
        $this->assertSame(0, $this->engine->convertListeningScore(0));
        $this->assertSame(0, $this->engine->convertReadingScore(0));

        // 16 correct
        $this->assertSame(5, $this->engine->convertListeningScore(16));
        $this->assertSame(5, $this->engine->convertReadingScore(16));

        // 17 correct
        $this->assertSame(10, $this->engine->convertListeningScore(17));
        $this->assertSame(5, $this->engine->convertReadingScore(17));

        // 25 correct
        $this->assertSame(50, $this->engine->convertListeningScore(25));
        $this->assertSame(30, $this->engine->convertReadingScore(25));

        // 50 correct
        $this->assertSame(230, $this->engine->convertListeningScore(50));
        $this->assertSame(185, $this->engine->convertReadingScore(50));

        // 70 correct
        $this->assertSame(360, $this->engine->convertListeningScore(70));
        $this->assertSame(310, $this->engine->convertReadingScore(70));

        // 75 correct
        $this->assertSame(395, $this->engine->convertListeningScore(75));
        $this->assertSame(335, $this->engine->convertReadingScore(75));

        // 83 correct
        $this->assertSame(440, $this->engine->convertListeningScore(83));
        $this->assertSame(390, $this->engine->convertReadingScore(83));

        // 78 correct reading
        $this->assertSame(355, $this->engine->convertReadingScore(78));

        // 90 correct
        $this->assertSame(480, $this->engine->convertListeningScore(90));
        $this->assertSame(435, $this->engine->convertReadingScore(90));

        // 93 correct
        $this->assertSame(495, $this->engine->convertListeningScore(93));
        $this->assertSame(455, $this->engine->convertReadingScore(93));

        // 100 correct (Maximum)
        $this->assertSame(495, $this->engine->convertListeningScore(100));
        $this->assertSame(495, $this->engine->convertReadingScore(100));

        // Boundary clamping (> 100)
        $this->assertSame(495, $this->engine->convertListeningScore(110));
        $this->assertSame(495, $this->engine->convertReadingScore(110));
    }

    /**
     * 2. Full 100+100 Mock Test assessment scoring: 83 Listening + 78 Reading => 795 Institutional Scaled Score.
     */
    public function test_full_mock_test_scoring_calculates_institutional_scaled_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Full Mock Test 01',
            'slug' => 'toeic-full-mock-test-01-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest, // Internal enum represents Mock Test
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 120,
            'pass_score' => 700,
            'status' => 'published',
            'is_published' => true,
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Comprehension',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Comprehension',
            'section_type' => SectionType::Reading,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'TOEIC Bank',
            'slug' => 'toeic-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        /** @var AttemptEngine $attemptEngine */
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        // Build 100 Listening questions (83 correct, 17 incorrect)
        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "Listening Question #{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'difficulty' => DifficultyLevel::Medium,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            $cWrong = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Wrong', 'is_correct' => false, 'order' => 2]);

            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i, 'points' => 1]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'selected_choice_id' => ($i <= 83) ? $cCorrect->id : $cWrong->id,
            ]);
        }

        // Build 100 Reading questions (78 correct, 22 incorrect)
        for ($j = 1; $j <= 100; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "Reading Question #{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'difficulty' => DifficultyLevel::Hard,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            $cWrong = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Wrong', 'is_correct' => false, 'order' => 2]);

            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j, 'points' => 1]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'selected_choice_id' => ($j <= 78) ? $cCorrect->id : $cWrong->id,
            ]);
        }

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        // 83 correct listening => 440
        // 78 correct reading => 355
        // Total => 795
        $this->assertEquals(795.0, (float) $attempt->total_score);

        $sectionScores = $attempt->section_scores;
        $this->assertEquals(83, $sectionScores['listening']['correct']);
        $this->assertEquals(440, $sectionScores['listening']['score']);
        $this->assertEquals(78, $sectionScores['reading']['correct']);
        $this->assertEquals(355, $sectionScores['reading']['score']);
        $this->assertTrue($sectionScores['is_full_toeic']);
        $this->assertFalse($sectionScores['is_practice']);
        $this->assertEquals('Scaled Score', $sectionScores['score_label']);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertEquals(795.0, (float) $result['final_score']);
        $this->assertTrue($result['is_passed']); // 795 >= 700
        $this->assertTrue($result['is_full_toeic']);
        $this->assertEquals('Scaled Score', $result['score_label']);

        // Digital certificate is decoupled from raw attempt submission in Phase 3
        $this->assertDatabaseMissing('certificates', [
            'attempt_id' => $attempt->id,
            'user_id' => $this->candidate->id,
        ]);
    }

    /**
     * 3. Simulator with 40 questions returns raw Practice Score and does NOT scale to 990.
     */
    public function test_simulator_with_40_questions_returns_practice_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC 40-Question Simulator',
            'slug' => 'toeic-40-simulator-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 45,
            'pass_score' => 20,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening & Reading Overview',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Simulator Bank',
            'slug' => 'sim-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        // 40 questions, candidate gets 28 correct
        for ($i = 1; $i <= 40; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "Sim Q#{$i}",
                'section' => ($i <= 20) ? SectionType::Listening : SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            $cWrong = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Wrong', 'is_correct' => false, 'order' => 2]);

            TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => $i]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'selected_choice_id' => ($i <= 28) ? $cCorrect->id : $cWrong->id,
            ]);
        }

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(28.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertTrue($result['is_practice']);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertEquals('Practice Score', $result['score_label']);
        $this->assertEquals('simulator', $result['toeic_breakdown']['assessment_mode']);
    }

    /**
     * 4. Simulator with 50 questions returns raw Practice Score and does NOT scale to 990.
     */
    public function test_simulator_with_50_questions_returns_practice_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC 50-Question Diagnostic Simulator',
            'slug' => 'toeic-50-simulator-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 50,
            'pass_score' => 25,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Diagnostic Core',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Diagnostic Bank',
            'slug' => 'diag-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        // 50 questions, all 50 correct
        for ($i = 1; $i <= 50; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "Diagnostic Q#{$i}",
                'section' => ($i <= 25) ? SectionType::Listening : SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);

            TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => $i]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'selected_choice_id' => $cCorrect->id,
            ]);
        }

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        // 50 correct in a 50-question simulator must NOT be converted to 990 or scaled TOEIC max
        $this->assertEquals(50.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertTrue($result['is_practice']);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertEquals('Practice Score', $result['score_label']);
    }

    /**
     * 5. Short Mock Test with < 200 questions returns Practice / Raw Score.
     */
    public function test_short_mock_test_returns_practice_raw_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Short Mock Assessment',
            'slug' => 'toeic-short-mock-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 30,
            'pass_score' => 5,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Mini Mock Section',
            'section_type' => SectionType::Reading,
            'order' => 1,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Mini Mock Bank',
            'slug' => 'mini-mock-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        for ($i = 1; $i <= 10; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "Short Mock Q#{$i}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);

            TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => $i]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'selected_choice_id' => $c->id,
            ]);
        }

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(10.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertTrue($result['is_practice']);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertEquals('Practice / Raw Score', $result['score_label']);
    }

    /**
     * 6. Question Difficulty is metadata only and does not alter raw count scaling.
     */
    public function test_question_difficulty_does_not_affect_toeic_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Difficulty Invariant Test',
            'slug' => 'toeic-diff-invariant-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 60,
            'pass_score' => 1,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Part 5',
            'section_type' => SectionType::Reading,
            'order' => 1,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Diff Bank',
            'slug' => 'diff-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        // Easy question vs Hard question both contribute exactly 1 correct count
        $qEasy = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'Easy Question',
            'section' => SectionType::Reading,
            'difficulty' => DifficultyLevel::Easy,
            'points' => 999, // Legacy points value must be ignored
        ]);
        $cEasy = QuestionChoice::create(['question_id' => $qEasy->id, 'label' => 'A', 'content' => 'Ans', 'is_correct' => true, 'order' => 1]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $qEasy->id,
            'selected_choice_id' => $cEasy->id,
        ]);

        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(1.0, (float) $attempt->total_score);
    }

    /**
     * 7. Teacher Question Authoring accepts questions without manual points.
     */
    public function test_teacher_can_create_question_without_manual_points(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Authoring Bank',
            'slug' => 'authoring-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'draft',
            'created_by' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->post(route('admin.question-banks.store-question', $bank->id), [
            'prompt' => 'What time does the conference start?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'choices' => [
                ['label' => 'A', 'content' => 'At 9:00 AM'],
                ['label' => 'B', 'content' => 'In the lobby'],
            ],
            'correct_choice' => '0',
            // Notice: no manual 'points' provided in request
        ]);

        $response->assertRedirect();
        $q = Question::where('prompt', 'What time does the conference start?')->first();
        $this->assertNotNull($q);
        $this->assertEquals(DifficultyLevel::Easy, $q->difficulty);
        $this->assertEquals(1, $q->points); // Defaults safely to 1
    }

    /**
     * 8. Edge cases: All Incorrect (0 correct), All Correct (100+100), Unanswered.
     */
    public function test_edge_cases_all_incorrect_and_all_correct(): void
    {
        // 0 correct
        $evalZero = $this->engine->convertListeningScore(0) + $this->engine->convertReadingScore(0);
        $this->assertSame(0, $evalZero);

        // 100 + 100 correct (Maximum 990)
        $evalMax = $this->engine->convertListeningScore(100) + $this->engine->convertReadingScore(100);
        $this->assertSame(990, $evalMax);
    }

    /**
     * 9. Mock Test with 150 Listening + 50 Reading (200 total) is NOT full TOEIC and does not scale to 990.
     */
    public function test_mock_test_with_150_listening_and_50_reading_is_not_full_toeic(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Asymmetric Mock 150L 50R',
            'slug' => 'toeic-asym-150-50-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 100,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section',
            'section_type' => SectionType::Reading,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Asym Bank',
            'slug' => 'asym-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        // 150 Listening questions
        for ($i = 1; $i <= 150; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L Q#{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        // 50 Reading questions
        for ($j = 1; $j <= 50; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R Q#{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(200.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertTrue($result['is_practice']);
        $this->assertEquals('Practice / Raw Score', $result['score_label']);
    }

    /**
     * 10. Mock Test with 50 Listening + 150 Reading (200 total) is NOT full TOEIC and does not scale to 990.
     */
    public function test_mock_test_with_50_listening_and_150_reading_is_not_full_toeic(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Asymmetric Mock 50L 150R',
            'slug' => 'toeic-asym-50-150-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 100,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section',
            'section_type' => SectionType::Reading,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Asym Bank 2',
            'slug' => 'asym-bank-2-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        // 50 Listening questions
        for ($i = 1; $i <= 50; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L Q#{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        // 150 Reading questions
        for ($j = 1; $j <= 150; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R Q#{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(200.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertTrue($result['is_practice']);
        $this->assertEquals('Practice / Raw Score', $result['score_label']);
    }

    /**
     * 11. Mock Test with 200 Listening + 0 Reading is NOT full TOEIC.
     */
    public function test_mock_test_with_200_listening_and_0_reading_is_not_full_toeic(): void
    {
        $test = Test::create([
            'title' => 'TOEIC 200L 0R Mock',
            'slug' => 'toeic-200l-0r-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 100,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section Only',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $bank = QuestionBank::create([
            'title' => 'L200 Bank',
            'slug' => 'l200-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        for ($i = 1; $i <= 200; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L Q#{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(200.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertTrue($result['is_practice']);
        $this->assertEquals('Practice / Raw Score', $result['score_label']);
    }

    /**
     * 12. Mock Test with 0 Listening + 200 Reading is NOT full TOEIC.
     */
    public function test_mock_test_with_0_listening_and_200_reading_is_not_full_toeic(): void
    {
        $test = Test::create([
            'title' => 'TOEIC 0L 200R Mock',
            'slug' => 'toeic-0l-200r-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 100,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section Only',
            'section_type' => SectionType::Reading,
            'order' => 1,
        ]);

        $bank = QuestionBank::create([
            'title' => 'R200 Bank',
            'slug' => 'r200-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        for ($i = 1; $i <= 200; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R Q#{$i}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $i]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(200.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertTrue($result['is_practice']);
        $this->assertEquals('Practice / Raw Score', $result['score_label']);
    }

    /**
     * 13. Simulator with 100 Listening + 100 Reading is STILL Practice Score.
     */
    public function test_simulator_with_100_listening_and_100_reading_is_still_practice_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC 200-Question Simulator',
            'slug' => 'toeic-200-sim-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 120,
            'pass_score' => 100,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section',
            'section_type' => SectionType::Reading,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Sim 200 Bank',
            'slug' => 'sim-200-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        // 100 Listening questions
        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L Sim Q#{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        // 100 Reading questions
        for ($j = 1; $j <= 100; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R Sim Q#{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j]);
            Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $c->id]);
        }

        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        $this->assertEquals(200.0, (float) $attempt->total_score);
        $this->assertNotEquals(990.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertTrue($result['is_practice']);
        $this->assertEquals('Practice Score', $result['score_label']);
    }

    /**
     * 14. Full Real Test TOEIC with 0 answers evaluates as full TOEIC with 200 denominator and 0 scaled score.
     */
    public function test_full_real_test_toeic_with_zero_answers_evaluates_as_full_toeic_and_zero_scaled_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Full Real Test 200Q',
            'slug' => 'toeic-full-real-test-200q-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 650,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 45,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section',
            'section_type' => SectionType::Reading,
            'duration_minutes' => 75,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Full Bank',
            'slug' => 'full-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        // 100 Listening questions in blueprint
        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L Question #{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);
        }

        // 100 Reading questions in blueprint
        for ($j = 1; $j <= 100; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R Question #{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j]);
        }

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        // 0 answers submitted
        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        // Evaluates score = 0, listening = 0, reading = 0, total_questions = 200
        $this->assertEquals(0.0, (float) $attempt->total_score);

        $sectionScores = $attempt->section_scores;
        $this->assertEquals(0, $sectionScores['listening']['correct']);
        $this->assertEquals(100, $sectionScores['listening']['total']);
        $this->assertEquals(0, $sectionScores['listening']['score']);
        $this->assertEquals(0, $sectionScores['reading']['correct']);
        $this->assertEquals(100, $sectionScores['reading']['total']);
        $this->assertEquals(0, $sectionScores['reading']['score']);
        $this->assertTrue($sectionScores['is_full_toeic']);
        $this->assertFalse($sectionScores['is_practice']);
        $this->assertEquals('Scaled Score', $sectionScores['score_label']);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertEquals(0.0, (float) $result['final_score']);
        $this->assertFalse($result['is_passed']); // 0 < 650
        $this->assertTrue($result['is_full_toeic']);
        $this->assertFalse($result['is_practice']);
        $this->assertEquals('Scaled Score', $result['score_label']);
        $this->assertEquals(200, $result['total_questions']);
        $this->assertEquals(0, $result['correct_count']);
        $this->assertEquals(0, $result['answered_questions']);
        $this->assertEquals(200, $result['unanswered_questions']);
    }

    /**
     * 15. Full Real Test TOEIC partially answered maintains 200 denominator and full TOEIC classification.
     */
    public function test_full_real_test_toeic_with_partial_answers_retains_full_toeic_status_and_200_denominator(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Full Real Test Partial Answers',
            'slug' => 'toeic-full-partial-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 650,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 45,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section',
            'section_type' => SectionType::Reading,
            'duration_minutes' => 75,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Full Bank Partial',
            'slug' => 'full-bank-part-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        // 100 Listening questions in blueprint, candidate answers 10 (8 correct, 2 wrong)
        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L Question #{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            $cWrong = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false, 'order' => 2]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);

            if ($i <= 10) {
                Answer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $q->id,
                    'selected_choice_id' => ($i <= 8) ? $cCorrect->id : $cWrong->id,
                ]);
            }
        }

        // 100 Reading questions in blueprint, candidate answers 5 (4 correct, 1 wrong)
        for ($j = 1; $j <= 100; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R Question #{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            $cWrong = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false, 'order' => 2]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j]);

            if ($j <= 5) {
                Answer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $q->id,
                    'selected_choice_id' => ($j <= 4) ? $cCorrect->id : $cWrong->id,
                ]);
            }
        }

        app(AttemptEngine::class)->submitAttempt($attempt);
        $attempt->refresh();

        // 8 Listening correct -> 5
        // 4 Reading correct -> 5
        // Total score = 10
        $this->assertEquals(10.0, (float) $attempt->total_score);

        $sectionScores = $attempt->section_scores;
        $this->assertEquals(8, $sectionScores['listening']['correct']);
        $this->assertEquals(100, $sectionScores['listening']['total']);
        $this->assertEquals(5, $sectionScores['listening']['score']);
        $this->assertEquals(4, $sectionScores['reading']['correct']);
        $this->assertEquals(100, $sectionScores['reading']['total']);
        $this->assertEquals(5, $sectionScores['reading']['score']);
        $this->assertTrue($sectionScores['is_full_toeic']);
        $this->assertFalse($sectionScores['is_practice']);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertEquals(10.0, (float) $result['final_score']);
        $this->assertTrue($result['is_full_toeic']);
        $this->assertFalse($result['is_practice']);
        $this->assertEquals(200, $result['total_questions']);
        $this->assertEquals(12, $result['correct_count']);
        $this->assertEquals(15, $result['answered_questions']);
        $this->assertEquals(185, $result['unanswered_questions']);
    }

    /**
     * 16. Timeout-expired full TOEIC with zero answers renders Institutional Scaled Score and Decision UI.
     */
    public function test_timeout_expired_full_toeic_with_zero_answers_renders_institutional_scaled_score_in_review(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Mock Test Group - UAT Class 9A',
            'slug' => 'toeic-mock-test-uat-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 650,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 45,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section',
            'section_type' => SectionType::Reading,
            'duration_minutes' => 75,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'UAT Bank',
            'slug' => 'uat-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L UAT Q#{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);
        }

        for ($j = 1; $j <= 100; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R UAT Q#{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j]);
        }

        $assignment = CandidateTestAssignment::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 0,
            'assigned_at' => now(),
        ]);

        $attempt = app(AttemptEngine::class)->startAttempt($test, $this->candidate);

        // Simulate timeout expiration
        app(AttemptEngine::class)->expireAttempt($attempt);
        $attempt->refresh();

        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Must display Scaled Score treatment
        $this->assertStringContainsString('Scaled Score', $content);
        $this->assertStringContainsString('0 <span class="text-sm font-normal text-slate-500 dark:text-slate-400">/ 990</span>', $content);
        $this->assertStringContainsString('0 / 100 correct', $content);
        $this->assertStringContainsString('0 <span class="text-xs font-normal text-slate-500 dark:text-slate-400">/ 495</span>', $content);
        $this->assertStringContainsString('This score is generated from an independently prepared Mock Test and does not indicate affiliation with, endorsement by, or production by any third-party test provider.', $content);

        // Must NOT display Practice Score Mode notice
        $this->assertStringNotContainsString('Practice Score Mode:', $content);
        $this->assertStringNotContainsString('Simulator results are evaluated by accuracy', $content);

        // Must preserve Attempt #1 Decision UI
        $this->assertStringContainsString('Mock Test Result Decision', $content);
        $this->assertStringContainsString('Option A', $content);
        $this->assertStringContainsString('Finalize Result &amp; Release Final Score', $content);
        $this->assertStringContainsString('Option B', $content);
        $this->assertStringContainsString('Retry Second Attempt', $content);

        // Must display Correct (0), Incorrect (0), Unanswered (200) without leaking questions
        $this->assertStringContainsString('Correct Answers', $content);
        $this->assertStringContainsString('Incorrect Answers', $content);
        $this->assertStringContainsString('Unanswered', $content);
        $this->assertStringContainsString('200', $content);
        $this->assertStringContainsString('No review required', $content);
        $this->assertStringContainsString('Question-level review is not available for secure Mock Tests', $content);
        $this->assertStringNotContainsString('Detailed Question Review', $content);
    }

    /**
     * 17. Canonical result semantics: test 200Q across zero-answered, partial-answered, and fully-answered.
     */
    public function test_canonical_result_semantics_across_zero_partial_and_full_answers(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Full Blueprint Semantics Test',
            'slug' => 'toeic-full-semantics-'.uniqid(),
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 650,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $listeningSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 45,
            'order' => 1,
        ]);

        $readingSection = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Reading Section',
            'section_type' => SectionType::Reading,
            'duration_minutes' => 75,
            'order' => 2,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Semantics Bank',
            'slug' => 'semantics-bank-'.uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $allQuestions = [];
        $choicesMap = [];

        for ($i = 1; $i <= 100; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "L Question #{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            $cWrong = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false, 'order' => 2]);
            TestQuestion::create(['test_section_id' => $listeningSection->id, 'question_id' => $q->id, 'order' => $i]);
            $allQuestions[] = $q;
            $choicesMap[$q->id] = ['correct' => $cCorrect, 'wrong' => $cWrong];
        }

        for ($j = 1; $j <= 100; $j++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "R Question #{$j}",
                'section' => SectionType::Reading,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $cCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true, 'order' => 1]);
            $cWrong = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false, 'order' => 2]);
            TestQuestion::create(['test_section_id' => $readingSection->id, 'question_id' => $q->id, 'order' => $j]);
            $allQuestions[] = $q;
            $choicesMap[$q->id] = ['correct' => $cCorrect, 'wrong' => $cWrong];
        }

        // Case 1: 200Q / 0 answered -> correct = 0, incorrect = 0, unanswered = 200
        $attemptZero = app(AttemptEngine::class)->startAttempt($test, $this->candidate);
        app(AttemptEngine::class)->submitAttempt($attemptZero);
        $attemptZero->refresh();

        $resultZero = app(ResultEngine::class)->generateResult($attemptZero);
        $this->assertEquals(200, $resultZero['total_questions']);
        $this->assertEquals(0, $resultZero['answered_questions']);
        $this->assertEquals(0, $resultZero['correct_count']);
        $this->assertEquals(0, $resultZero['incorrect_count']);
        $this->assertEquals(0, $resultZero['incorrect_answers']);
        $this->assertEquals(200, $resultZero['unanswered_questions']);

        $summaryZero = app(ReviewEngine::class)->getReviewSummary($attemptZero);
        $this->assertEquals(0, $summaryZero['correct_answers']);
        $this->assertEquals(0, $summaryZero['incorrect_answers']);
        $this->assertEquals(200, $summaryZero['unanswered_questions']);

        // Case 2: 200Q / 150 answered / 120 correct -> correct = 120, incorrect = 30, unanswered = 50
        $attemptPartial = app(AttemptEngine::class)->startAttempt($test, $this->candidate);
        for ($k = 0; $k < 150; $k++) {
            $q = $allQuestions[$k];
            $isCorrectAnswer = ($k < 120);
            Answer::create([
                'attempt_id' => $attemptPartial->id,
                'question_id' => $q->id,
                'selected_choice_id' => $isCorrectAnswer ? $choicesMap[$q->id]['correct']->id : $choicesMap[$q->id]['wrong']->id,
            ]);
        }
        app(AttemptEngine::class)->submitAttempt($attemptPartial);
        $attemptPartial->refresh();

        $resultPartial = app(ResultEngine::class)->generateResult($attemptPartial);
        $this->assertEquals(200, $resultPartial['total_questions']);
        $this->assertEquals(150, $resultPartial['answered_questions']);
        $this->assertEquals(120, $resultPartial['correct_count']);
        $this->assertEquals(30, $resultPartial['incorrect_count']);
        $this->assertEquals(30, $resultPartial['incorrect_answers']);
        $this->assertEquals(50, $resultPartial['unanswered_questions']);

        $summaryPartial = app(ReviewEngine::class)->getReviewSummary($attemptPartial);
        $this->assertEquals(120, $summaryPartial['correct_answers']);
        $this->assertEquals(30, $summaryPartial['incorrect_answers']);
        $this->assertEquals(50, $summaryPartial['unanswered_questions']);

        // Case 3: 200Q / all answered (200) / 150 correct -> correct = 150, incorrect = 50, unanswered = 0
        $attemptFull = app(AttemptEngine::class)->startAttempt($test, $this->candidate);
        for ($k = 0; $k < 200; $k++) {
            $q = $allQuestions[$k];
            $isCorrectAnswer = ($k < 150);
            Answer::create([
                'attempt_id' => $attemptFull->id,
                'question_id' => $q->id,
                'selected_choice_id' => $isCorrectAnswer ? $choicesMap[$q->id]['correct']->id : $choicesMap[$q->id]['wrong']->id,
            ]);
        }
        app(AttemptEngine::class)->submitAttempt($attemptFull);
        $attemptFull->refresh();

        $resultFull = app(ResultEngine::class)->generateResult($attemptFull);
        $this->assertEquals(200, $resultFull['total_questions']);
        $this->assertEquals(200, $resultFull['answered_questions']);
        $this->assertEquals(150, $resultFull['correct_count']);
        $this->assertEquals(50, $resultFull['incorrect_count']);
        $this->assertEquals(50, $resultFull['incorrect_answers']);
        $this->assertEquals(0, $resultFull['unanswered_questions']);

        $summaryFull = app(ReviewEngine::class)->getReviewSummary($attemptFull);
        $this->assertEquals(150, $summaryFull['correct_answers']);
        $this->assertEquals(50, $summaryFull['incorrect_answers']);
        $this->assertEquals(0, $summaryFull['unanswered_questions']);
    }
}
