<?php

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');
    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');
});

test('ToeicQuestionValidator derives section deterministically', function () {
    expect(ToeicQuestionValidator::deriveSection(1))->toBe('listening')
        ->and(ToeicQuestionValidator::deriveSection(2))->toBe('listening')
        ->and(ToeicQuestionValidator::deriveSection(3))->toBe('listening')
        ->and(ToeicQuestionValidator::deriveSection(4))->toBe('listening')
        ->and(ToeicQuestionValidator::deriveSection(5))->toBe('reading')
        ->and(ToeicQuestionValidator::deriveSection(6))->toBe('reading')
        ->and(ToeicQuestionValidator::deriveSection(7))->toBe('reading');
});

test('ToeicQuestionValidator validates Part 1 Photographs rules', function () {
    // 1. Valid Part 1
    $validData = [
        'part_number'    => 1,
        'prompt'         => 'Look at the photograph and choose the best statement.',
        'difficulty'     => 'medium',
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/part1.mp3',
        'choices'        => ['Statement A', 'Statement B', 'Statement C', 'Statement D'],
        'correct_choice' => 0,
    ];
    $check = ToeicQuestionValidator::check($validData);
    expect($check['is_valid'])->toBeTrue()
        ->and($check['section'])->toBe('listening')
        ->and($check['part_number'])->toBe(1);

    // 2. Missing image
    $missingImg = $validData;
    unset($missingImg['image_url']);
    $checkMissingImg = ToeicQuestionValidator::check($missingImg);
    expect($checkMissingImg['is_valid'])->toBeFalse()
        ->and($checkMissingImg['errors'])->toHaveKey('image_url');

    // 3. Missing audio
    $missingAudio = $validData;
    unset($missingAudio['audio_url']);
    $checkMissingAudio = ToeicQuestionValidator::check($missingAudio);
    expect($checkMissingAudio['is_valid'])->toBeFalse()
        ->and($checkMissingAudio['errors'])->toHaveKey('audio_url');

    // 4. Invalid choice count (3 instead of 4)
    $threeChoices = $validData;
    $threeChoices['choices'] = ['Statement A', 'Statement B', 'Statement C'];
    $checkThreeChoices = ToeicQuestionValidator::check($threeChoices);
    expect($checkThreeChoices['is_valid'])->toBeFalse()
        ->and($checkThreeChoices['errors'])->toHaveKey('choices');
});

test('ToeicQuestionValidator validates Part 2 Question-Response rules', function () {
    // 1. Valid Part 2: audio + exactly 3 choices (A, B, C)
    $validData = [
        'part_number'    => 2,
        'prompt'         => 'Where is the meeting room?',
        'difficulty'     => 'easy',
        'audio_url'      => 'https://example.com/part2.mp3',
        'choices'        => ['On the third floor.', 'At 2 PM.', 'Yes, I did.'],
        'correct_choice' => 0,
    ];
    $check = ToeicQuestionValidator::check($validData);
    expect($check['is_valid'])->toBeTrue()
        ->and($check['section'])->toBe('listening')
        ->and($check['part_number'])->toBe(2);

    // 2. Fourth choice (D) is forbidden
    $fourChoices = $validData;
    $fourChoices['choices'] = ['Option A', 'Option B', 'Option C', 'Option D'];
    $checkFour = ToeicQuestionValidator::check($fourChoices);
    expect($checkFour['is_valid'])->toBeFalse()
        ->and($checkFour['errors'])->toHaveKey('choices')
        ->and($checkFour['errors']['choices'])->toContain('forbidden');

    // 3. 2 choices rejected
    $twoChoices = $validData;
    $twoChoices['choices'] = ['Option A', 'Option B'];
    $checkTwo = ToeicQuestionValidator::check($twoChoices);
    expect($checkTwo['is_valid'])->toBeFalse()
        ->and($checkTwo['errors'])->toHaveKey('choices');

    // 4. Missing audio rejected
    $missingAudio = $validData;
    unset($missingAudio['audio_url']);
    $checkMissingAudio = ToeicQuestionValidator::check($missingAudio);
    expect($checkMissingAudio['is_valid'])->toBeFalse()
        ->and($checkMissingAudio['errors'])->toHaveKey('audio_url');
});

test('ToeicQuestionValidator validates Part 3 Conversations and Part 4 Talks rules', function () {
    foreach ([3, 4] as $part) {
        $validData = [
            'part_number'    => $part,
            'prompt'         => "What are the speakers discussing in Part {$part}?",
            'difficulty'     => 'medium',
            'audio_url'      => "https://example.com/part{$part}.mp3",
            'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_choice' => 1,
        ];
        $check = ToeicQuestionValidator::check($validData);
        expect($check['is_valid'])->toBeTrue()
            ->and($check['section'])->toBe('listening');

        // Missing audio rejected
        $noAudio = $validData;
        unset($noAudio['audio_url']);
        expect(ToeicQuestionValidator::check($noAudio)['is_valid'])->toBeFalse();

        // 3 choices rejected
        $threeChoices = $validData;
        $threeChoices['choices'] = ['Option A', 'Option B', 'Option C'];
        expect(ToeicQuestionValidator::check($threeChoices)['is_valid'])->toBeFalse();
    }
});

test('ToeicQuestionValidator validates Part 5 Incomplete Sentences rules', function () {
    $validData = [
        'part_number'    => 5,
        'prompt'         => 'Ms. Tanaka completed the financial report _____ Tuesday.',
        'difficulty'     => 'hard',
        'choices'        => ['on', 'at', 'in', 'by'],
        'correct_choice' => 3,
    ];
    $check = ToeicQuestionValidator::check($validData);
    expect($check['is_valid'])->toBeTrue()
        ->and($check['section'])->toBe('reading')
        ->and($check['part_number'])->toBe(5);

    // Audio is forbidden in Part 5
    $withAudio = $validData;
    $withAudio['audio_url'] = 'https://example.com/part5.mp3';
    $checkAudio = ToeicQuestionValidator::check($withAudio);
    expect($checkAudio['is_valid'])->toBeFalse()
        ->and($checkAudio['errors'])->toHaveKey('audio_url')
        ->and($checkAudio['errors']['audio_url'])->toContain('does not allow');

    // Missing choice rejected
    $threeChoices = $validData;
    $threeChoices['choices'] = ['on', 'at', 'in'];
    expect(ToeicQuestionValidator::check($threeChoices)['is_valid'])->toBeFalse();
});

test('ToeicQuestionValidator validates Part 6 and Part 7 Passage rules', function () {
    foreach ([6, 7] as $part) {
        $validData = [
            'part_number'    => $part,
            'prompt'         => "What is the purpose of the email in Part {$part}?",
            'difficulty'     => 'medium',
            'passage_text'   => 'Dear Valued Customer, We are writing to notify you of...',
            'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_choice' => 0,
        ];
        $check = ToeicQuestionValidator::check($validData);
        expect($check['is_valid'])->toBeTrue()
            ->and($check['section'])->toBe('reading');

        // Missing passage rejected
        $noPassage = $validData;
        unset($noPassage['passage_text']);
        $checkNoPassage = ToeicQuestionValidator::check($noPassage);
        expect($checkNoPassage['is_valid'])->toBeFalse()
            ->and($checkNoPassage['errors'])->toHaveKey('passage');
    }
});

test('ToeicQuestionValidator enforces difficulty and correct answer', function () {
    $data = [
        'part_number'    => 5,
        'prompt'         => 'Sample prompt.',
        'difficulty'     => '',
        'choices'        => ['A', 'B', 'C', 'D'],
        'correct_choice' => 0,
    ];
    $check = ToeicQuestionValidator::check($data);
    expect($check['is_valid'])->toBeFalse()
        ->and($check['errors'])->toHaveKey('difficulty');

    $noCorrect = [
        'part_number' => 5,
        'prompt'      => 'Sample prompt.',
        'difficulty'  => 'easy',
        'choices'     => [
            ['content' => 'A', 'is_correct' => false],
            ['content' => 'B', 'is_correct' => false],
            ['content' => 'C', 'is_correct' => false],
            ['content' => 'D', 'is_correct' => false],
        ],
    ];
    $checkNoCorrect = ToeicQuestionValidator::check($noCorrect);
    expect($checkNoCorrect['is_valid'])->toBeFalse()
        ->and($checkNoCorrect['errors'])->toHaveKey('correct_choice');
});

test('QuestionBankController storeQuestion enforces TOEIC part validation and persists deterministic section', function () {
    $bank = QuestionBank::create([
        'title'       => 'TOEIC Bank',
        'slug'        => 'toeic-bank-' . uniqid(),
        'test_type'   => TestType::Toeic,
        'status'      => 'draft',
        'created_by'  => $this->teacher->id,
    ]);

    // 1. Invalid Part 1 (missing audio) should fail validation
    $response = $this->actingAs($this->teacher)->post(route('admin.question-banks.store-question', $bank->id), [
        'prompt'         => 'Photo question without audio',
        'part_number'    => 1,
        'difficulty'     => 'medium',
        'image_url'      => 'https://example.com/img.jpg',
        'question_type'  => 'single_choice',
        'choices'        => [
            ['content' => 'A', 'is_correct' => 1],
            ['content' => 'B', 'is_correct' => 0],
            ['content' => 'C', 'is_correct' => 0],
            ['content' => 'D', 'is_correct' => 0],
        ],
    ]);
    $response->assertSessionHasErrors(['audio_url']);

    // 2. Valid Part 2 (audio + 3 choices) persists section=listening and part_number=2
    $responseValid = $this->actingAs($this->teacher)->post(route('admin.question-banks.store-question', $bank->id), [
        'prompt'         => 'Where is the office?',
        'part_number'    => 2,
        'difficulty'     => 'easy',
        'audio_url'      => 'https://example.com/part2.mp3',
        'question_type'  => 'single_choice',
        'choices'        => [
            ['content' => 'On the 2nd floor.', 'is_correct' => 1],
            ['content' => 'At noon.', 'is_correct' => 0],
            ['content' => 'Yes.', 'is_correct' => 0],
        ],
    ]);
    $responseValid->assertRedirect();

    $q = Question::where('question_bank_id', $bank->id)->first();
    expect($q)->not->toBeNull()
        ->and($q->part_number)->toBe(2)
        ->and($q->section->value ?? $q->section)->toBe('listening')
        ->and($q->choices->count())->toBe(3);
});

test('TestBuilderController createAssessmentQuestion validates TOEIC rules and persists section/part', function () {
    $test = Test::create([
        'title'            => 'TOEIC Assessment',
        'slug'             => 'toeic-assessment-' . uniqid(),
        'test_type'        => TestType::Toeic,
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
    ]);
    $section = TestSection::create([
        'test_id'          => $test->id,
        'title'            => 'Reading Section',
        'section_type'     => 'reading',
        'order'            => 1,
        'duration_minutes' => 75,
    ]);

    // 1. Part 5 with audio should fail
    $respFail = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $test->id), [
        'test_section_id' => $section->id,
        'prompt'          => 'Incomplete sentence with audio',
        'part_number'     => 5,
        'difficulty'      => 'hard',
        'audio_url'       => 'https://example.com/fail.mp3',
        'question_type'   => 'multiple_choice',
        'choices'         => ['A', 'B', 'C', 'D'],
        'correct_choice'  => 0,
    ]);
    $respFail->assertSessionHasErrors(['audio_url']);

    // 2. Valid Part 5 persists section=reading and part_number=5
    $respSuccess = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $test->id), [
        'test_section_id' => $section->id,
        'prompt'          => 'Incomplete sentence prompt',
        'part_number'     => 5,
        'difficulty'      => 'medium',
        'question_type'   => 'multiple_choice',
        'choices'         => ['Choice A', 'Choice B', 'Choice C', 'Choice D'],
        'correct_choice'  => 2,
    ]);
    $respSuccess->assertRedirect();

    $createdQ = Question::latest('id')->first();
    expect($createdQ->part_number)->toBe(5)
        ->and($createdQ->section->value ?? $createdQ->section)->toBe('reading')
        ->and($createdQ->choices->count())->toBe(4);
});

test('TestBuilderService validateAssessment validates TOEIC questions with part-aware rules', function () {
    $service = app(TestBuilderService::class);

    $test = Test::create([
        'title'            => 'TOEIC Exam',
        'slug'             => 'toeic-exam-' . uniqid(),
        'test_type'        => TestType::Toeic,
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
    ]);
    $section = TestSection::create([
        'test_id'          => $test->id,
        'title'            => 'Listening Section',
        'section_type'     => 'listening',
        'order'            => 1,
        'duration_minutes' => 45,
    ]);

    // Add an invalid Part 1 question (missing image & audio)
    $q = Question::create([
        'prompt'        => 'Invalid Part 1 Question',
        'part_number'   => 1,
        'section'       => SectionType::Listening,
        'difficulty'    => DifficultyLevel::Medium,
        'question_type' => QuestionType::MultipleChoice,
        'created_by'    => $this->teacher->id,
        'image_url'     => null,
        'audio_url'     => null,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Choice C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'Choice D', 'is_correct' => false]);

    $section->testQuestions()->create(['question_id' => $q->id, 'sort_order' => 1]);

    $valResult = $service->validateAssessment($test);
    expect($valResult['is_valid'])->toBeFalse()
        ->and($valResult['errors'])->not->toBeEmpty();
});

test('Non-TOEIC assessments and questions are not constrained by TOEIC part rules', function () {
    $nonToeicTest = Test::create([
        'title'            => 'General English Test',
        'slug'             => 'general-english-' . uniqid(),
        'test_type'        => TestType::General,
        'duration_minutes' => 60,
        'pass_score'       => 70,
        'created_by'       => $this->teacher->id,
    ]);
    $section = TestSection::create([
        'test_id'          => $nonToeicTest->id,
        'title'            => 'General Section',
        'section_type'     => 'reading',
        'order'            => 1,
        'duration_minutes' => 60,
    ]);

    // General 2-choice true/false question should succeed
    $resp = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $nonToeicTest->id), [
        'test_section_id' => $section->id,
        'prompt'          => 'The earth is round.',
        'question_type'   => 'true_false',
        'difficulty'      => 'easy',
        'choices'         => ['True', 'False'],
        'correct_choice'  => 0,
    ]);
    $resp->assertRedirect();
});
