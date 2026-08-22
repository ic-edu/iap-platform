<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Engines\ScoringEngine;
use App\Modules\Assessment\Engines\TOEICScoringEngine;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
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
     * 2. Full 100+100 TOEIC assessment scoring: 83 Listening + 78 Reading => 795 total score.
     */
    public function test_full_toeic_scoring_calculates_scaled_score_and_pass_fail(): void
    {
        $test = Test::create([
            'title' => 'Official TOEIC Standard Assessment',
            'slug' => 'official-toeic-standard-' . uniqid(),
            'test_type' => TestType::Toeic,
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
            'slug' => 'toeic-bank-' . uniqid(),
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

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertEquals(795.0, (float) $result['final_score']);
        $this->assertTrue($result['is_passed']); // 795 >= 700
        $this->assertTrue($result['is_full_toeic']);

        // Digital certificate must be issued
        $this->assertDatabaseHas('certificates', [
            'attempt_id' => $attempt->id,
            'user_id' => $this->candidate->id,
        ]);
    }

    /**
     * 3. Practice / UAT / Mini Test does NOT map partial questions (e.g. 3/3) to 990 scaled score.
     */
    public function test_practice_assessment_does_not_scale_to_990(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Mini Practice Test',
            'slug' => 'toeic-mini-practice-' . uniqid(),
            'test_type' => TestType::Toeic,
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 15,
            'pass_score' => 2,
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Part 1: Mini Practice',
            'section_type' => SectionType::Listening,
            'order' => 1,
        ]);

        $bank = QuestionBank::create([
            'title' => 'Practice Bank',
            'slug' => 'practice-bank-' . uniqid(),
            'test_type' => 'toeic',
            'status' => 'published',
            'created_by' => $this->teacher->id,
        ]);

        /** @var AttemptEngine $attemptEngine */
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($test, $this->candidate);

        // 3 questions, all answered correctly
        for ($i = 1; $i <= 3; $i++) {
            $q = Question::create([
                'question_bank_id' => $bank->id,
                'prompt' => "Mini Practice Q#{$i}",
                'section' => SectionType::Listening,
                'question_type' => QuestionType::MultipleChoice,
                'points' => 1,
            ]);
            $c1 = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct Choice', 'is_correct' => true, 'order' => 1]);

            TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => $i]);

            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'selected_choice_id' => $c1->id,
            ]);
        }

        $attemptEngine->submitAttempt($attempt);
        $attempt->refresh();

        // Must NOT be 990 or scaled TOEIC maximum! Must be raw performance (3.0)
        $this->assertNotEquals(990.0, (float) $attempt->total_score);
        $this->assertEquals(3.0, (float) $attempt->total_score);

        $result = app(ResultEngine::class)->generateResult($attempt);
        $this->assertTrue($result['is_practice']);
        $this->assertFalse($result['is_full_toeic']);
        $this->assertEquals('Practice / Raw Score', $result['score_label']);
        $this->assertEquals(3.0, (float) $result['final_score']);
    }

    /**
     * 4. Question Difficulty is metadata only and does not alter raw count scaling.
     */
    public function test_question_difficulty_does_not_affect_toeic_score(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Difficulty Invariant Test',
            'slug' => 'toeic-diff-invariant-' . uniqid(),
            'test_type' => TestType::Toeic,
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
            'slug' => 'diff-bank-' . uniqid(),
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
     * 5. Teacher Question Authoring accepts questions without manual points.
     */
    public function test_teacher_can_create_question_without_manual_points(): void
    {
        $bank = QuestionBank::create([
            'title' => 'Authoring Bank',
            'slug' => 'authoring-bank-' . uniqid(),
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
     * 6. Edge cases: All Incorrect (0 correct), All Correct (100+100), Unanswered.
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
}
