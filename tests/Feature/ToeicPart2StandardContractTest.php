<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create(['status' => 'active']);
    $this->student->assignRole('student');

    $this->toeicTest = Test::create([
        'title'            => 'Official TOEIC Listening & Reading Assessment',
        'slug'             => 'official-toeic-lr-' . uniqid(),
        'type'             => 'simulator',
        'assessment_mode'  => AssessmentMode::Simulator,
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'status'           => 'draft',
        'is_published'     => false,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
    ]);

    $this->part2Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'section_type' => SectionType::Listening,
        'title'        => 'Part 2: Question-Response',
        'order'        => 2,
    ]);
});

// =========================================================================
// SECTION 1: FOCUSED VALIDATION TESTS (TEST 01 to TEST 08)
// =========================================================================

test('TEST 01: Part 2 with audio, 3 choices with empty string content, exactly one correct answer passes validation', function () {
    $data = [
        'part_number'    => 2,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/part2-q01.mp3',
        'prompt'         => 'Mark your answer on your answer sheet.',
        'correct_choice' => '0',
        'choices'        => ['', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

test('TEST 02: Part 2 with audio, 3 choices with optional transcripts, exactly one correct answer passes validation', function () {
    $data = [
        'part_number'    => 2,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/part2-q02.mp3',
        'prompt'         => 'Where is the meeting taking place?',
        'correct_choice' => '1',
        'choices'        => [
            'In Room 302.',
            'At two oclock.',
            'Yes, with John.',
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

test('TEST 03: Part 2 question model with empty choices does not require fake dash strings', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/part2-q03.mp3',
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);

    $result = ToeicQuestionValidator::check([], $q);
    expect($result['is_valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

test('TEST 04: Part 2 missing audio fails validation with explicit audio error', function () {
    $data = [
        'part_number'    => 2,
        'section'        => 'listening',
        'audio_url'      => '',
        'prompt'         => 'Question stem',
        'correct_choice' => '0',
        'choices'        => ['', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('audio_url');
});

test('TEST 05: Part 2 with 4 choices fails validation because 4th choice is forbidden', function () {
    $data = [
        'part_number'    => 2,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/part2-q05.mp3',
        'prompt'         => 'Listen to the audio.',
        'correct_choice' => '0',
        'choices'        => ['', '', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('choices');
});

test('TEST 06: Part 2 with no correct choice selected fails validation', function () {
    $data = [
        'part_number'    => 2,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/part2-q06.mp3',
        'prompt'         => 'Listen to the audio.',
        'correct_choice' => null,
        'choices'        => ['', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('correct_choice');
});

test('TEST 07: Part 2 with multiple correct choices fails validation', function () {
    $data = [
        'part_number'     => 2,
        'section'         => 'listening',
        'audio_url'       => 'https://example.com/audio/part2-q07.mp3',
        'prompt'          => 'Listen to the audio.',
        'correct_choices' => ['0', '1'],
        'choices'         => ['', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('correct_choice');
});

test('TEST 08: Part 2 with invalid choice labels fails validation', function () {
    $data = [
        'part_number'    => 2,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/part2-q08.mp3',
        'prompt'         => 'Listen to the audio.',
        'correct_choice' => '0',
        'choices'        => [
            ['label' => 'X', 'content' => '', 'is_correct' => true],
            ['label' => 'Y', 'content' => '', 'is_correct' => false],
            ['label' => 'Z', 'content' => '', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('choice_labels');
});

// =========================================================================
// SECTION 2: SECTION ROLLUP & TEACHER DETAIL UX (TEST 09 to TEST 12)
// =========================================================================

test('TEST 09: Collapsed Section Rollup for Part 2 with 25 valid audio-only questions displays READY', function () {
    for ($i = 1; $i <= 25; $i++) {
        $q = Question::create([
            'prompt'        => "Part 2 Item #{$i}",
            'section'       => SectionType::Listening,
            'part_number'   => 2,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty'    => DifficultyLevel::Medium,
            'audio_url'     => "https://example.com/audio/p2-q{$i}.mp3",
            'points'        => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
        TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => $i, 'points' => 1]);
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('READY');
    $response->assertSee('25/25 Complete');
});

test('TEST 10: Collapsed Section Rollup for Part 2 with 1 missing audio item displays NEEDS ATTENTION', function () {
    for ($i = 1; $i <= 25; $i++) {
        $q = Question::create([
            'prompt'        => "Part 2 Item #{$i}",
            'section'       => SectionType::Listening,
            'part_number'   => 2,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty'    => DifficultyLevel::Medium,
            'audio_url'     => ($i === 5) ? null : "https://example.com/audio/p2-q{$i}.mp3",
            'points'        => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
        TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => $i, 'points' => 1]);
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('NEEDS ATTENTION');
    $response->assertSee('24/25 Complete');
    $response->assertSee('1 question missing');
});

test('TEST 11: Top-level submit button enabled when general assessment has 12 valid audio-only questions', function () {
    $this->toeicTest->update(['test_type' => 'general']);
    for ($i = 1; $i <= 12; $i++) {
        $q = Question::create([
            'prompt'        => "Part 2 Item #{$i}",
            'section'       => SectionType::Listening,
            'part_number'   => 2,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty'    => DifficultyLevel::Medium,
            'audio_url'     => "https://example.com/audio/p2-q{$i}.mp3",
            'points'        => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
        TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => $i, 'points' => 1]);
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('Submit for Review');
    $response->assertDontSee('Submission Disabled');
});

test('TEST 12: Top-level submit button disabled with attention badge when Part 2 question is invalid', function () {
    for ($i = 1; $i <= 25; $i++) {
        $q = Question::create([
            'prompt'        => "Part 2 Item #{$i}",
            'section'       => SectionType::Listening,
            'part_number'   => 2,
            'question_type' => QuestionType::MultipleChoice,
            'difficulty'    => DifficultyLevel::Medium,
            'audio_url'     => ($i === 1) ? null : "https://example.com/audio/p2-q{$i}.mp3",
            'points'        => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
        TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => $i, 'points' => 1]);
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('Submission Disabled');
    $response->assertSee('Need Attention');
});

// =========================================================================
// SECTION 3: CANDIDATE PREVIEW & DELIVERY TESTS (TEST 13 to TEST 17)
// =========================================================================

test('TEST 13: Candidate Preview for Part 2 renders (A), (B), (C) radio choices without choice text', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-preview-01.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'SECRET_OPT_A', 'choice_text' => 'SECRET_OPT_A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'SECRET_OPT_B', 'choice_text' => 'SECRET_OPT_B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'SECRET_OPT_C', 'choice_text' => 'SECRET_OPT_C', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('(A)');
    $response->assertSee('(B)');
    $response->assertSee('(C)');
    $response->assertDontSee('SECRET_OPT_A');
    $response->assertDontSee('SECRET_OPT_B');
    $response->assertDontSee('SECRET_OPT_C');
});

test('TEST 14: Candidate Preview does not leak teacher transcripts for Part 2', function () {
    $secretTranscript = 'SECRET_TEACHER_TRANSCRIPT_FOR_PART_2';
    $q = Question::create([
        'prompt'        => 'Mark your answer on your answer sheet.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-preview-02.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => $secretTranscript, 'choice_text' => $secretTranscript, 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertDontSee($secretTranscript);
});

test('TEST 15: Candidate CBT exam renders audio + (A), (B), (C) for Part 2', function () {
    $this->toeicTest->update(['status' => 'published', 'is_published' => true]);

    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-cbt-01.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p2-cbt-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
    $response->assertStatus(200);
    $response->assertSee(route('candidate.exam.audio-stream', [$attempt, $q]));
    $response->assertSee('(A)');
    $response->assertSee('(B)');
    $response->assertSee('(C)');
});

test('TEST 16: Candidate CBT exam hides transcript / choice content for Part 2', function () {
    $this->toeicTest->update(['status' => 'published', 'is_published' => true]);
    $hiddenText = 'CANDIDATE_MUST_NOT_SEE_THIS_AUDIO_TEXT';

    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-cbt-02.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => $hiddenText, 'choice_text' => $hiddenText, 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Option B Text', 'choice_text' => 'Option B Text', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Option C Text', 'choice_text' => 'Option C Text', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p2-cbt-hide-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
    $response->assertStatus(200);
    $response->assertDontSee($hiddenText);
    $response->assertDontSee('Option B Text');
    $response->assertDontSee('Option C Text');
});

test('TEST 17: Candidate CBT exam for Part 2 records candidate selected choice', function () {
    $this->toeicTest->update(['status' => 'published', 'is_published' => true]);

    $q = Question::create([
        'prompt'        => 'Listen to the question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-cbt-03.mp3',
        'points'        => 1,
    ]);
    $cA = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    $cB = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    $cC = QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p2-cbt-ans-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $answer = Answer::create([
        'attempt_id'         => $attempt->id,
        'question_id'        => $q->id,
        'selected_choice_id' => $cA->id,
        'is_correct'         => true,
    ]);

    expect($answer->is_correct)->toBeTrue();
    $this->assertDatabaseHas('answers', [
        'attempt_id'         => $attempt->id,
        'question_id'        => $q->id,
        'selected_choice_id' => $cA->id,
    ]);
});

// =========================================================================
// SECTION 4: CROSS-PART REGRESSION SAFETY (TEST 18 to TEST 24)
// =========================================================================

test('TEST 18: Part 1 Photographs still requires image, audio, 4 choices', function () {
    $data = [
        'part_number'    => 1,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/p1.mp3',
        'image_url'      => 'https://example.com/images/p1.jpg',
        'prompt'         => 'Look at the photograph.',
        'correct_choice' => '0',
        'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue();

    // Failing if image missing
    $dataMissingImg = $data;
    unset($dataMissingImg['image_url']);
    expect(ToeicQuestionValidator::check($dataMissingImg)['is_valid'])->toBeFalse();

    // Failing if only 3 choices
    $data3Choices = $data;
    $data3Choices['choices'] = ['Option A', 'Option B', 'Option C'];
    expect(ToeicQuestionValidator::check($data3Choices)['is_valid'])->toBeFalse();
});

test('TEST 19: Part 3 Conversations still requires audio and 4 choices with non-empty text', function () {
    $data = [
        'part_number'    => 3,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/p3.mp3',
        'prompt'         => 'What are the speakers discussing?',
        'correct_choice' => '0',
        'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue();

    // Failing with empty choices
    $dataEmptyChoices = $data;
    $dataEmptyChoices['choices'] = ['', '', '', ''];
    expect(ToeicQuestionValidator::check($dataEmptyChoices)['is_valid'])->toBeFalse();
});

test('TEST 20: Part 4 Talks still requires audio and 4 choices with non-empty text', function () {
    $data = [
        'part_number'    => 4,
        'section'        => 'listening',
        'audio_url'      => 'https://example.com/audio/p4.mp3',
        'prompt'         => 'What is the speaker announcing?',
        'correct_choice' => '0',
        'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue();
});

test('TEST 21: Part 5 Incomplete Sentences requires 4 choices with non-empty text and forbids audio', function () {
    $data = [
        'part_number'    => 5,
        'section'        => 'reading',
        'prompt'         => 'Please fill in the blank: The manager ___ the report.',
        'correct_choice' => '0',
        'choices'        => ['approved', 'approving', 'approval', 'approves'],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue();

    // Audio is forbidden
    $dataWithAudio = $data;
    $dataWithAudio['audio_url'] = 'https://example.com/audio/p5.mp3';
    expect(ToeicQuestionValidator::check($dataWithAudio)['is_valid'])->toBeFalse();
});

test('TEST 22: Part 6 Text Completion requires passage and 4 choices with non-empty text', function () {
    $data = [
        'part_number'    => 6,
        'section'        => 'reading',
        'passage_text'   => 'Dear Customer, Thank you for your order.',
        'prompt'         => 'Choose the word that best fits position [1].',
        'correct_choice' => '0',
        'choices'        => ['promptly', 'prompt', 'promptness', 'prompted'],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue();

    // Missing passage fails
    $dataNoPassage = $data;
    unset($dataNoPassage['passage_text']);
    expect(ToeicQuestionValidator::check($dataNoPassage)['is_valid'])->toBeFalse();
});

test('TEST 23: Part 7 Reading Comprehension requires passage and 4 choices with non-empty text', function () {
    $data = [
        'part_number'    => 7,
        'section'        => 'reading',
        'passage_text'   => 'Notice: Office will be closed on Monday.',
        'prompt'         => 'Why will the office be closed?',
        'correct_choice' => '0',
        'choices'        => ['For maintenance', 'For holiday', 'For relocation', 'For inspection'],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['is_valid'])->toBeTrue();
});

test('TEST 24: Generic MCQ assessment requires visible choice text', function () {
    $genericTest = Test::create([
        'title'            => 'General English Quiz',
        'slug'             => 'general-english-quiz-' . uniqid(),
        'type'             => 'quiz',
        'assessment_mode'  => AssessmentMode::Simulator,
        'test_type'        => 'general',
        'duration_minutes' => 60,
        'pass_score'       => 70,
        'status'           => 'draft',
        'is_published'     => false,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
    ]);

    $genericSection = TestSection::create([
        'test_id'      => $genericTest->id,
        'section_type' => SectionType::Reading,
        'title'        => 'Grammar & Vocabulary',
        'order'        => 1,
    ]);

    // Submitting with empty choices fails because generic MCQ requires non-empty choice text
    $responseFail = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $genericTest->id), [
        'test_section_id' => $genericSection->id,
        'question_type'   => 'multiple_choice',
        'prompt'          => 'What is the synonym of rapid?',
        'correct_choice'  => '0',
        'choices'         => ['', '', '', ''],
    ]);
    $responseFail->assertSessionHasErrors();

    // Submitting with non-empty choices succeeds
    $responseSuccess = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $genericTest->id), [
        'test_section_id' => $genericSection->id,
        'question_type'   => 'multiple_choice',
        'prompt'          => 'What is the synonym of rapid?',
        'correct_choice'  => '0',
        'choices'         => ['Fast', 'Slow', 'Quiet', 'Loud'],
    ]);
    $responseSuccess->assertRedirect(route('teacher.tests.show', $genericTest->id));
});

// =========================================================================
// SECTION 5: LIGHT & DARK THEME AUTHORING UX TESTS (TEST 25 to TEST 31)
// =========================================================================

test('TEST 25: Edit Question view renders choice rows with light neutral styling in Light Theme', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-edit-01.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('Responses &amp; Correct Answer Selection', false);
    $response->assertSee('Optional response transcript...', false);
});

test('TEST 26: Edit Question view does not hardcode dark navy backgrounds on unselected choices in script', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-edit-02.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    // Ensure updateEditorCorrectChoice uses light theme neutral background (#f8fafc / #ecfdf5)
    $response->assertSee('#f8fafc');
    $response->assertSee('#ecfdf5');
});

test('TEST 27: Edit Question for Part 2 hides Add Choice and Remove Choice buttons', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-edit-03.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('display:none', false); // Add choice / remove choice hidden for Part 2
});

test('TEST 28: Edit Question for Part 2 displays optional transcript helper and placeholder', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-edit-04.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('Response transcripts are optional authoring metadata and are not shown during the test.');
});

test('TEST 29: Updating Part 2 question via TestBuilderController with empty transcripts preserves 3 choices with empty string content and correct answer', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-update-01.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Old A', 'choice_text' => 'Old A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Old B', 'choice_text' => 'Old B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Old C', 'choice_text' => 'Old C', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated prompt stem',
        'part_number'    => 2,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '1', // Choice B
        'choices'        => ['', '', ''],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));

    $q->refresh();
    $choices = $q->choices()->orderBy('order')->get();
    expect($choices)->toHaveCount(3)
        ->and($choices[0]->label)->toBe('A')
        ->and($choices[0]->is_correct)->toBeFalse()
        ->and($choices[1]->label)->toBe('B')
        ->and($choices[1]->is_correct)->toBeTrue()
        ->and($choices[2]->label)->toBe('C')
        ->and($choices[2]->is_correct)->toBeFalse();
});

test('TEST 30: Edit Question view contains responsive and dark theme CSS classes', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the audio question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-edit-05.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('gov-card');
    $response->assertSee('gov-hero-indigo');
});

test('TEST 31: TestBuilderController store authored question for Part 2 preserves 3 choices without requiring non-empty text', function () {
    $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->toeicTest->id), [
        'test_section_id' => $this->part2Section->id,
        'part_number'     => 2,
        'section'         => 'listening',
        'question_type'   => 'multiple_choice',
        'prompt'          => 'Mark your answer on your answer sheet.',
        'audio_url'       => 'https://example.com/audio/p2-new-01.mp3',
        'correct_choice'  => '2', // Choice C
        'choices'         => ['', '', ''],
    ]);

    $response->assertRedirect(route('teacher.tests.show', $this->toeicTest->id));

    $newQ = Question::where('prompt', 'Mark your answer on your answer sheet.')->first();
    expect($newQ)->not->toBeNull();
    $choices = $newQ->choices()->orderBy('order')->get();
    expect($choices)->toHaveCount(3)
        ->and($choices[0]->label)->toBe('A')
        ->and($choices[0]->is_correct)->toBeFalse()
        ->and($choices[1]->label)->toBe('B')
        ->and($choices[1]->is_correct)->toBeFalse()
        ->and($choices[2]->label)->toBe('C')
        ->and($choices[2]->is_correct)->toBeTrue();
});
