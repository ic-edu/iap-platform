<?php

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\QuestionDifficultyDetectionService;
use App\Services\ToeicQuestionValidator;
use App\Modules\Assessment\Services\TestBuilderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('TEST 01: TOEIC Part 1 validator passes with image, audio, 4 blank transcript choices, and 1 correct choice', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => [
            ['label' => 'A', 'content' => '', 'is_correct' => true],
            ['label' => 'B', 'content' => '', 'is_correct' => false],
            ['label' => 'C', 'content' => '', 'is_correct' => false],
            ['label' => 'D', 'content' => '', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

test('TEST 02: TOEIC Part 1 validator fails when image is missing', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => '',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => ['', '', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('image_url');
});

test('TEST 03: TOEIC Part 1 validator fails when audio is missing', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => '',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => ['', '', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('audio_url');
});

test('TEST 04: TOEIC Part 1 validator fails when choices count is less than 4', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => [
            ['label' => 'A', 'content' => '', 'is_correct' => true],
            ['label' => 'B', 'content' => '', 'is_correct' => false],
            ['label' => 'C', 'content' => '', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('choices');
});

test('TEST 05: TOEIC Part 1 validator fails when choices count is greater than 4', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => [
            ['label' => 'A', 'content' => '', 'is_correct' => true],
            ['label' => 'B', 'content' => '', 'is_correct' => false],
            ['label' => 'C', 'content' => '', 'is_correct' => false],
            ['label' => 'D', 'content' => '', 'is_correct' => false],
            ['label' => 'E', 'content' => '', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('choices');
});

test('TEST 06: TOEIC Part 1 validator fails when no correct answer is selected', function () {
    $data = [
        'part_number' => 1,
        'image_url'   => 'https://example.com/photo.jpg',
        'audio_url'   => 'https://example.com/audio.mp3',
        'prompt'      => '',
        'choices'     => [
            ['label' => 'A', 'content' => '', 'is_correct' => false],
            ['label' => 'B', 'content' => '', 'is_correct' => false],
            ['label' => 'C', 'content' => '', 'is_correct' => false],
            ['label' => 'D', 'content' => '', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('correct_choice');
});

test('TEST 07: TOEIC Part 1 validator fails when multiple correct answers are selected', function () {
    $data = [
        'part_number'     => 1,
        'image_url'       => 'https://example.com/photo.jpg',
        'audio_url'       => 'https://example.com/audio.mp3',
        'prompt'          => '',
        'correct_choices' => [0, 1],
        'choices'         => [
            ['label' => 'A', 'content' => '', 'is_correct' => true],
            ['label' => 'B', 'content' => '', 'is_correct' => true],
            ['label' => 'C', 'content' => '', 'is_correct' => false],
            ['label' => 'D', 'content' => '', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('correct_choice');
});

test('TEST 08: TOEIC Part 1 validator passes with populated statement transcripts', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 2,
        'choices'        => [
            ['label' => 'A', 'content' => 'He is holding a notebook.', 'is_correct' => false],
            ['label' => 'B', 'content' => 'He is typing on a keyboard.', 'is_correct' => false],
            ['label' => 'C', 'content' => 'He is writing on a whiteboard.', 'is_correct' => true],
            ['label' => 'D', 'content' => 'He is sitting on a bench.', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

test('TEST 09: TOEIC Part 1 validator passes with mixed blank and filled transcripts', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => [
            ['label' => 'A', 'content' => 'The desk is empty.', 'is_correct' => true],
            ['label' => 'B', 'content' => '', 'is_correct' => false],
            ['label' => 'C', 'content' => '', 'is_correct' => false],
            ['label' => 'D', 'content' => '', 'is_correct' => false],
        ],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

test('TEST 10: Teacher can author Part 1 question with 4 blank transcripts via controller', function () {
    $teacher = User::factory()->create([
        'status' => 'active',
    ]);
    $teacher->assignRole('teacher');

    $test = Test::create([
        'title'            => 'Part 1 Test',
        'slug'             => 'part-1-test-' . Str::random(6),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_score'       => 750,
        'status'           => 'draft',
        'created_by'       => $teacher->id,
        'assigned_to'      => $teacher->id,
    ]);

    $section = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 1: Photographs',
        'section_type' => 'listening',
        'order'        => 1,
    ]);

    $response = $this->actingAs($teacher)->post(route('teacher.tests.create-question', $test->id), [
        'test_section_id' => $section->id,
        'part_number'     => 1,
        'section'         => 'listening',
        'question_type'   => 'multiple_choice',
        'prompt'          => 'Part 1 Question',
        'image_url'       => 'https://example.com/image.jpg',
        'audio_url'       => 'https://example.com/audio.mp3',
        'correct_choice'  => 1,
        'choices'         => ['', '', '', ''],
    ]);

    $response->assertRedirect();

    $question = Question::where('prompt', 'Part 1 Question')->first();
    expect($question)->not->toBeNull()
        ->and($question->part_number)->toBe(1)
        ->and($question->image_url)->toBe('https://example.com/image.jpg')
        ->and($question->audio_url)->toBe('https://example.com/audio.mp3');

    $choices = $question->choices()->orderBy('label')->get();
    expect($choices)->toHaveCount(4)
        ->and($choices[0]->label)->toBe('A')
        ->and($choices[0]->is_correct)->toBeFalse()
        ->and($choices[1]->label)->toBe('B')
        ->and($choices[1]->is_correct)->toBeTrue()
        ->and($choices[2]->label)->toBe('C')
        ->and($choices[2]->is_correct)->toBeFalse()
        ->and($choices[3]->label)->toBe('D')
        ->and($choices[3]->is_correct)->toBeFalse();
});

test('TEST 11: Teacher can update Part 1 question and clear placeholder text to blank transcripts', function () {
    $teacher = User::factory()->create([
        'status' => 'active',
    ]);
    $teacher->assignRole('teacher');

    $test = Test::create([
        'title'            => 'Part 1 Test 2',
        'slug'             => 'part-1-test-2-' . Str::random(6),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_score'       => 750,
        'status'           => 'draft',
        'created_by'       => $teacher->id,
        'assigned_to'      => $teacher->id,
    ]);

    $section = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 1: Photographs',
        'section_type' => 'listening',
        'order'        => 1,
    ]);

    $question = Question::create([
        'prompt'        => 'Old Prompt',
        'part_number'   => 1,
        'section'       => 'listening',
        'question_type' => 'multiple_choice',
        'image_url'     => 'https://example.com/photo.jpg',
        'audio_url'     => 'https://example.com/audio.mp3',
        'difficulty'    => 'medium',
        'points'        => 1,
    ]);

    // Existing choices had fake "." values
    foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => $lbl,
            'content'     => '.',
            'choice_text' => '.',
            'is_correct'  => $idx === 0,
            'order'       => $idx + 1,
        ]);
    }

    TestQuestion::create([
        'test_id'         => $test->id,
        'test_section_id' => $section->id,
        'question_id'     => $question->id,
        'order'           => 1,
    ]);

    $response = $this->actingAs($teacher)->put(route('teacher.tests.update-question', [
        'test'     => $test->id,
        'question' => $question->id,
    ]), [
        'prompt'         => 'Updated Part 1 Question',
        'part_number'    => 1,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'correct_choice' => 2,
        'choices'        => ['', '', '', ''],
    ]);

    $response->assertRedirect();

    $choices = $question->fresh()->choices()->orderBy('label')->get();
    expect($choices)->toHaveCount(4)
        ->and($choices[0]->content)->toBe('')
        ->and($choices[0]->is_correct)->toBeFalse()
        ->and($choices[2]->content)->toBe('')
        ->and($choices[2]->is_correct)->toBeTrue();
});

test('TEST 12: Part 2 audio-only question contract remains intact with exactly 3 choices', function () {
    $dataValid = [
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => ['', '', ''],
    ];

    $resultValid = ToeicQuestionValidator::check($dataValid);
    expect($resultValid['valid'])->toBeTrue();

    $data4Choices = [
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => ['', '', '', ''],
    ];

    $result4Choices = ToeicQuestionValidator::check($data4Choices);
    expect($result4Choices['valid'])->toBeFalse()
        ->and($result4Choices['errors'])->toHaveKey('choices');
});

test('TEST 13: Part 5 reading question requires non-blank choice text', function () {
    $data = [
        'part_number'    => 5,
        'prompt'         => 'The manager requested _____ the report immediately.',
        'correct_choice' => 0,
        'choices'        => ['', '', '', ''],
    ];

    $result = ToeicQuestionValidator::check($data);
    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('choices');
});

test('TEST 14: Difficulty detector completes with final status for Part 1 with image, audio, and blank transcripts', function () {
    $data = [
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => '',
        'correct_choice' => 0,
        'choices'        => ['', '', '', ''],
    ];

    $result = QuestionDifficultyDetectionService::detect($data, null, 1);
    expect($result['difficulty_status'])->toBe('final')
        ->and($result['difficulty_level'])->toBeIn(['easy', 'medium', 'hard'])
        ->and($result['difficulty_score'])->toBeGreaterThanOrEqual(0);
});

test('TEST 15: Section validation rollup marks Part 1 with 4 blank transcripts as valid and complete', function () {
    $teacher = User::factory()->create([
        'status' => 'active',
    ]);
    $teacher->assignRole('teacher');

    $test = Test::create([
        'title'            => 'Part 1 Test 3',
        'slug'             => 'part-1-test-3-' . Str::random(6),
        'test_type'        => 'general',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_score'       => 750,
        'status'           => 'draft',
        'created_by'       => $teacher->id,
        'assigned_to'      => $teacher->id,
    ]);

    $section = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 1: Photographs',
        'section_type' => 'listening',
        'order'        => 1,
    ]);

    $question = Question::create([
        'prompt'        => 'Photographs Question',
        'part_number'   => 1,
        'section'       => 'listening',
        'question_type' => 'multiple_choice',
        'image_url'     => 'https://example.com/photo.jpg',
        'audio_url'     => 'https://example.com/audio.mp3',
        'difficulty'    => 'medium',
        'points'        => 1,
    ]);

    foreach (['A', 'B', 'C', 'D'] as $idx => $lbl) {
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => $lbl,
            'content'     => '',
            'choice_text' => '',
            'is_correct'  => $idx === 0,
            'order'       => $idx + 1,
        ]);
    }

    TestQuestion::create([
        'test_id'         => $test->id,
        'test_section_id' => $section->id,
        'question_id'     => $question->id,
        'order'           => 1,
    ]);

    $service = app(TestBuilderService::class);
    $validation = $service->validateAssessment($test);

    expect($validation['questions'][0]['warnings'])->toBeEmpty();
    expect($validation['is_valid'])->toBeTrue()
        ->and($validation['errors'])->toBeEmpty();
});
