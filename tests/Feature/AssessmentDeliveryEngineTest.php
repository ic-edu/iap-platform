<?php

use App\Models\User;
use App\Modules\Assessment\Engines\AssessmentEngine;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\AutoSaveEngine;
use App\Modules\Assessment\Engines\NavigationEngine;
use App\Modules\Assessment\Engines\RandomizationEngine;
use App\Modules\Assessment\Engines\ReviewEngine;
use App\Modules\Assessment\Engines\ScoringEngine;
use App\Modules\Assessment\Engines\TimerEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Events\RuleViolationDetected;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('timer engine calculates remaining seconds accurately', function () {
    $engine = new TimerEngine;
    $user = User::factory()->create();
    $test = Test::create([
        'title' => 'Timer Test',
        'slug' => 'timer-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'created_by' => $user->id,
    ]);
    $attempt = Attempt::create([
        'test_id' => $test->id,
        'user_id' => $user->id,
        'started_at' => now()->subMinutes(10),
        'status' => AttemptStatus::InProgress,
    ]);

    $remaining = $engine->getRemainingSeconds($attempt);
    expect($remaining)->toBeGreaterThan(2900)->toBeLessThanOrEqual(3000);
    expect($engine->isExpired($attempt))->toBeFalse();
});

test('timer engine handles unstarted attempt without error', function () {
    $engine = new TimerEngine;
    $attempt = new Attempt(['started_at' => null]);

    expect($engine->getRemainingSeconds($attempt))->toBe(0);
});

test('timer engine identifies expired attempt', function () {
    $engine = new TimerEngine;
    $user = User::factory()->create();
    $test = Test::create([
        'title' => 'Expired Test',
        'slug' => 'expired-test',
        'test_type' => TestType::General,
        'duration_minutes' => 30,
        'pass_score' => 70,
        'created_by' => $user->id,
    ]);
    $attempt = Attempt::create([
        'test_id' => $test->id,
        'user_id' => $user->id,
        'started_at' => now()->subMinutes(40),
        'status' => AttemptStatus::InProgress,
    ]);

    expect($engine->isExpired($attempt))->toBeTrue();
    expect($engine->getRemainingSeconds($attempt))->toBe(0);
});

test('randomization engine shuffles questions deterministically', function () {
    $engine = new RandomizationEngine;
    $user = User::factory()->create();
    $test = Test::create([
        'title' => 'Shuffle Test',
        'slug' => 'shuffle-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'shuffle_questions' => true,
        'created_by' => $user->id,
    ]);

    $bank = QuestionBank::create([
        'title' => 'Bank',
        'slug' => 'bank',
        'test_type' => TestType::General,
        'created_by' => $user->id,
    ]);

    $attempt = Attempt::create([
        'test_id' => $test->id,
        'user_id' => $user->id,
        'seed' => 'static_seed_123',
        'status' => AttemptStatus::InProgress,
    ]);

    $q1 = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Q1', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 5]);
    $q2 = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Q2', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 5]);
    $q3 = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Q3', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 5]);

    $questions = collect([$q1, $q2, $q3]);

    $shuffled1 = $engine->getShuffledQuestions($attempt, $questions);
    $shuffled2 = $engine->getShuffledQuestions($attempt, $questions);

    expect($shuffled1->pluck('id')->toArray())->toBe($shuffled2->pluck('id')->toArray());
});

test('randomization engine shuffles choices deterministically', function () {
    $engine = new RandomizationEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Choice Shuffle', 'slug' => 'choice-shuffle', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'shuffle_choices' => true, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'seed' => 'choice_seed_456', 'status' => AttemptStatus::InProgress]);

    $c1 = (object) ['id' => 'c1', 'label' => 'A'];
    $c2 = (object) ['id' => 'c2', 'label' => 'B'];
    $c3 = (object) ['id' => 'c3', 'label' => 'C'];
    $choices = collect([$c1, $c2, $c3]);

    $shuffled1 = $engine->getShuffledChoices($attempt, 'q_100', $choices);
    $shuffled2 = $engine->getShuffledChoices($attempt, 'q_100', $choices);

    expect($shuffled1->pluck('id')->toArray())->toBe($shuffled2->pluck('id')->toArray());
});

test('navigation engine toggles question flag and review later', function () {
    $engine = new NavigationEngine;
    $user = User::factory()->create();
    $test = Test::create([
        'title' => 'Nav Test',
        'slug' => 'nav-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'created_by' => $user->id,
    ]);
    $attempt = Attempt::create([
        'test_id' => $test->id,
        'user_id' => $user->id,
        'status' => AttemptStatus::InProgress,
    ]);

    $engine->toggleFlag($attempt, 'q_ulid_1');
    $attempt->refresh();
    expect($attempt->flagged_questions)->toContain('q_ulid_1');

    $engine->toggleFlag($attempt, 'q_ulid_1');
    $attempt->refresh();
    expect($attempt->flagged_questions)->not()->toContain('q_ulid_1');

    $engine->toggleReviewLater($attempt, 'q_ulid_2');
    $attempt->refresh();
    expect($attempt->review_later_questions)->toContain('q_ulid_2');
});

test('navigation engine sets current question ID', function () {
    $engine = new NavigationEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Nav 2', 'slug' => 'nav-2', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::InProgress]);

    $engine->setCurrentQuestion($attempt, 'target_q_123');
    $attempt->refresh();

    expect($attempt->current_question_id)->toBe('target_q_123');
});

test('auto save engine updates candidate answer response', function () {
    $engine = new AutoSaveEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Save Test', 'slug' => 'save-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $bank = QuestionBank::create([
        'title' => 'Bank',
        'slug' => 'bank',
        'test_type' => TestType::General,
        'created_by' => $user->id,
    ]);

    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::InProgress]);
    $question = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Prompt', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 5]);
    $choice = QuestionChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'Choice Content', 'is_correct' => true]);

    $answer = $engine->saveAnswer($attempt, $question->id, $choice->id);

    expect($answer->selected_choice_id)->toBe($choice->id);
    expect(Answer::where('attempt_id', $attempt->id)->count())->toBe(1);
});

test('scoring engine evaluates multiple choice answers correctly', function () {
    $scoring = new ScoringEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Score Test', 'slug' => 'score-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $bank = QuestionBank::create([
        'title' => 'Scoring Bank',
        'slug' => 'scoring-bank',
        'created_by' => $user->id,
        'test_type' => TestType::General,
    ]);

    $q = Question::create([
        'question_bank_id' => $bank->id,
        'prompt' => 'What is 2+2?',
        'section' => SectionType::Reading,
        'question_type' => QuestionType::MultipleChoice,
        'points' => 10,
    ]);

    $correctChoice = QuestionChoice::create([
        'question_id' => $q->id,
        'label' => 'A',
        'content' => '4',
        'is_correct' => true,
    ]);

    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::InProgress]);
    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $q->id,
        'selected_choice_id' => $correctChoice->id,
    ]);

    $totalScore = $scoring->evaluateAttempt($attempt);

    expect($totalScore)->toBe(10.0);
    expect($attempt->total_score)->toBe(10.0);
});

test('scoring engine assigns zero for incorrect choices', function () {
    $scoring = new ScoringEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Zero Score', 'slug' => 'zero-score', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $bank = QuestionBank::create(['title' => 'Bank', 'slug' => 'bank', 'test_type' => TestType::General, 'created_by' => $user->id]);

    $q = Question::create(['question_bank_id' => $bank->id, 'prompt' => '2+2?', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 10]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '4', 'is_correct' => true]);
    $wrongChoice = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '5', 'is_correct' => false]);

    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::InProgress]);
    Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'selected_choice_id' => $wrongChoice->id]);

    $score = $scoring->evaluateAttempt($attempt);
    expect($score)->toBe(0.0);
});

test('scoring engine evaluates short answer exact match correctly', function () {
    $scoring = new ScoringEngine;
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Short Answer Test', 'slug' => 'short-answer-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $bank = QuestionBank::create(['title' => 'Bank', 'slug' => 'bank', 'test_type' => TestType::General, 'created_by' => $user->id]);

    $q = Question::create([
        'question_bank_id' => $bank->id,
        'prompt' => 'Capital of France?',
        'section' => SectionType::Reading,
        'question_type' => QuestionType::ShortAnswer,
        'points' => 15,
    ]);

    QuestionChoice::create([
        'question_id' => $q->id,
        'label' => 'Ans',
        'content' => 'Paris',
        'is_correct' => true,
    ]);

    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::InProgress]);
    Answer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $q->id,
        'text_response' => 'paris',
    ]);

    $totalScore = $scoring->evaluateAttempt($attempt);

    expect($totalScore)->toBe(15.0);
});

test('review engine generates summary payload based on test settings', function () {
    $engine = new ReviewEngine;
    $user = User::factory()->create();
    $test = Test::create([
        'title' => 'Pass Test',
        'slug' => 'pass-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 50,
        'created_by' => $user->id,
    ]);

    $attempt = Attempt::create([
        'test_id' => $test->id,
        'user_id' => $user->id,
        'status' => AttemptStatus::Submitted,
        'total_score' => 80.0,
        'submitted_at' => now(),
    ]);

    $summary = $engine->getReviewSummary($attempt);

    expect($summary['is_passed'])->toBeTrue();
    expect($summary['total_score'])->toBe(80.0);
});

test('attempt engine handles complete lifecycle from start to submit', function () {
    $scoring = new ScoringEngine;
    $attemptEngine = new AttemptEngine($scoring);
    $user = User::factory()->create();
    $test = Test::create([
        'title' => 'Lifecycle Test',
        'slug' => 'lifecycle-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'created_by' => $user->id,
    ]);

    $attempt = $attemptEngine->startAttempt($test, $user);
    expect($attempt->status)->toBe(AttemptStatus::InProgress);

    $attemptEngine->resumeAttempt($attempt);
    expect($attempt->status)->toBe(AttemptStatus::InProgress);

    $submitted = $attemptEngine->submitAttempt($attempt);
    expect($submitted->status)->toBe(AttemptStatus::Submitted);
    expect($submitted->submitted_at)->not()->toBeNull();
});

test('attempt engine handles cancel and expire lifecycle', function () {
    $scoring = new ScoringEngine;
    $attemptEngine = new AttemptEngine($scoring);
    $user = User::factory()->create();
    $test = Test::create(['title' => 'Expire Test', 'slug' => 'expire-test', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);

    $attempt = $attemptEngine->startAttempt($test, $user);
    $expired = $attemptEngine->expireAttempt($attempt);

    expect($expired->status)->toBe(AttemptStatus::Expired);

    $cancelled = $attemptEngine->cancelAttempt($attempt);
    expect($cancelled->status)->toBe(AttemptStatus::Cancelled);
});

test('assessment engine orchestrates candidate test attempt', function () {
    $timer = new TimerEngine;
    $random = new RandomizationEngine;
    $nav = new NavigationEngine;
    $save = new AutoSaveEngine;
    $scoring = new ScoringEngine;
    $review = new ReviewEngine;
    $attemptEng = new AttemptEngine($scoring);

    $orchestrator = new AssessmentEngine($attemptEng, $timer, $nav, $save, $random, $scoring, $review);
    $user = User::factory()->create();
    $test = Test::create([
        'title' => 'Orchestrator Test',
        'slug' => 'orchestrator-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'created_by' => $user->id,
    ]);

    $attempt = $orchestrator->startAttempt($test, $user);
    expect($attempt->status)->toBe(AttemptStatus::InProgress);

    $resumed = $orchestrator->resumeAttempt($attempt);
    expect($resumed->id)->toBe($attempt->id);

    $cancel = $orchestrator->cancelAttempt($attempt);
    expect($cancel->status)->toBe(AttemptStatus::Cancelled);
});

test('candidate portal dashboard displays candidate metrics', function () {
    $user = User::factory()->create();
    $user->assignRole('student');

    $response = $this->actingAs($user)->get(route('candidate.portal'));

    $response->assertStatus(200);
    $response->assertSee('Student Portal Dashboard');
});

test('candidate portal dashboard shows ongoing attempt alert', function () {
    $user = User::factory()->create();
    $user->assignRole('student');

    $test = Test::create(['title' => 'Active Exam', 'slug' => 'active-exam', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'is_published' => true, 'created_by' => $user->id]);
    Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'started_at' => now(), 'status' => AttemptStatus::InProgress]);

    $response = $this->actingAs($user)->get(route('candidate.portal'));

    $response->assertStatus(200);
    $response->assertSee('Ongoing Session');
});

test('candidate can view available tests and my attempts', function () {
    $user = User::factory()->create();
    $user->assignRole('student');

    $responseTests = $this->actingAs($user)->get(route('candidate.available-tests'));
    $responseTests->assertStatus(200);

    $responseAttempts = $this->actingAs($user)->get(route('candidate.my-attempts'));
    $responseAttempts->assertStatus(200);
});

test('candidate can start a test and access cbt exam interface', function () {
    $user = User::factory()->create();
    $user->assignRole('student');

    $test = Test::create([
        'title' => 'CBT Published Test',
        'slug' => 'cbt-published-test',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 70,
        'is_published' => true,
        'created_by' => $user->id,
    ]);

    $startResp = $this->actingAs($user)->post(route('candidate.tests.start', $test));
    $startResp->assertRedirect();

    $attempt = Attempt::where('user_id', $user->id)->first();
    expect($attempt)->not()->toBeNull();

    $examResp = $this->actingAs($user)->get(route('candidate.exam', $attempt));
    $examResp->assertStatus(200);
    $examResp->assertSee('CBT Examination Session');
});

test('candidate exam autosave endpoint updates response', function () {
    $user = User::factory()->create();
    $user->assignRole('student');

    $test = Test::create(['title' => 'CBT Save', 'slug' => 'cbt-save', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $bank = QuestionBank::create([
        'title' => 'Bank',
        'slug' => 'bank',
        'test_type' => TestType::General,
        'created_by' => $user->id,
    ]);

    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::InProgress]);
    $question = Question::create(['question_bank_id' => $bank->id, 'prompt' => 'Prompt', 'section' => SectionType::Reading, 'question_type' => QuestionType::MultipleChoice, 'points' => 5]);

    $response = $this->actingAs($user)->postJson(route('candidate.exam.autosave', $attempt), [
        'question_id' => $question->id,
        'answer_text' => 'Sample short answer candidate response',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'saved']);
});

test('candidate anti cheating violation event increments violations count', function () {
    $user = User::factory()->create();
    $test = Test::create(['title' => 'CBT Violate', 'slug' => 'cbt-violate', 'test_type' => TestType::General, 'duration_minutes' => 60, 'pass_score' => 70, 'created_by' => $user->id]);
    $attempt = Attempt::create(['test_id' => $test->id, 'user_id' => $user->id, 'status' => AttemptStatus::InProgress]);

    event(new RuleViolationDetected($attempt, 'window_blur'));

    $attempt->refresh();
    expect($attempt->violations_count)->toBe(1);
});

test('candidate can submit exam and view review page', function () {
    $user = User::factory()->create();
    $user->assignRole('student');

    $test = Test::create([
        'title' => 'Final Exam',
        'slug' => 'final-exam',
        'test_type' => TestType::General,
        'duration_minutes' => 60,
        'pass_score' => 50,
        'created_by' => $user->id,
    ]);

    $attempt = Attempt::create([
        'user_id' => $user->id,
        'test_id' => $test->id,
        'status' => AttemptStatus::InProgress,
    ]);

    $submitResp = $this->actingAs($user)->post(route('candidate.exam.submit', $attempt));
    $submitResp->assertRedirect(route('candidate.review', $attempt));

    $reviewResp = $this->actingAs($user)->get(route('candidate.review', $attempt));
    $reviewResp->assertStatus(200);
    $reviewResp->assertSee('Final Test Score');
});
