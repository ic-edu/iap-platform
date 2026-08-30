<?php

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Services\QuestionDifficultyDetectionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');

    $this->test = Test::create([
        'title'       => 'TOEIC Practice Test Alpha',
        'slug'        => 'toeic-practice-test-alpha-' . uniqid(),
        'test_type'   => 'toeic',
        'status'      => 'draft',
        'created_by'  => $this->teacher->id,
        'assigned_to' => $this->teacher->id,
    ]);

    $this->listeningSection = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Listening Comprehension',
        'section_type' => 'listening',
        'order'        => 1,
    ]);

    $this->readingSection = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Reading Comprehension',
        'section_type' => 'reading',
        'order'        => 2,
    ]);
});

test('part 1 detection heuristics lifecycle', function () {
    // 1. Incomplete Draft
    $pendingDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 1,
        'prompt'      => '',
    ]);
    expect($pendingDetect['difficulty_status'])->toBe('pending')
        ->and($pendingDetect['difficulty_level'])->toBe('medium')
        ->and($pendingDetect['difficulty_source'])->toBe('auto');

    // 2. Provisional (Missing audio)
    $provisionalDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 1,
        'prompt'      => 'Look at the photograph and choose the best statement.',
        'image_url'   => 'https://example.com/photo.jpg',
        'choices'     => [
            ['content' => 'A man is sitting.'],
            ['content' => 'A man is standing.'],
            ['content' => 'A man is walking.'],
            ['content' => 'A man is running.'],
        ],
    ]);
    expect($provisionalDetect['difficulty_status'])->toBe('provisional');

    // 3. Final - Easy (Simple short descriptions)
    $easyDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 1,
        'prompt'      => 'Look at the photograph and select the statement that best describes what you see.',
        'image_url'   => 'https://example.com/photo.jpg',
        'audio_url'   => 'https://example.com/part1.mp3',
        'choices'     => [
            ['content' => 'A man is writing a letter.'],
            ['content' => 'A woman is reading a book.'],
            ['content' => 'They are looking at the computer.'],
            ['content' => 'The boy is eating an apple.'],
        ],
    ]);
    expect($easyDetect['difficulty_status'])->toBe('final')
        ->and($easyDetect['difficulty_level'])->toBe('easy')
        ->and($easyDetect['difficulty_score'])->toBeLessThan(40);

    // 4. Final - Hard (Advanced lexical collocations & longer clauses)
    $hardDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 1,
        'prompt'      => 'Look at the photograph and select the statement that best describes what you see.',
        'image_url'   => 'https://example.com/complex_photo.jpg',
        'audio_url'   => 'https://example.com/part1_complex.mp3',
        'choices'     => [
            ['content' => 'Architectural blueprints are being meticulously examined and scrutinized by municipal structural engineers.'],
            ['content' => 'Construction scaffolding is currently undergoing rigorous maintenance and mandatory safety inspections.'],
            ['content' => 'Heavy industrial machinery is actively dismantling obsolete infrastructure throughout the designated work zone.'],
            ['content' => 'Specialized protective equipment has been strategically positioned adjacent to the hazardous perimeter.'],
        ],
    ]);
    expect($hardDetect['difficulty_status'])->toBe('final')
        ->and($hardDetect['difficulty_level'])->toBe('hard')
        ->and($hardDetect['difficulty_score'])->toBeGreaterThanOrEqual(70);
});

test('part 2 detection heuristics', function () {
    // Simple direct Wh- Question
    $easyDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 2,
        'prompt'      => 'Where is the conference room located?',
        'audio_url'   => 'https://example.com/part2.mp3',
        'choices'     => [
            ['content' => 'On the second floor.'],
            ['content' => 'Yes, at 2 PM.'],
            ['content' => 'Mr. Tanaka called.'],
        ],
    ]);
    expect($easyDetect['difficulty_level'])->toBe('easy');

    // Indirect / Suggestion Question
    $hardDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 2,
        'prompt'      => "Why don't we postpone the quarterly financial review until next Wednesday?",
        'audio_url'   => 'https://example.com/part2_indirect.mp3',
        'choices'     => [
            ['content' => 'Well, the regional vice president will be out of town starting Tuesday.'],
            ['content' => 'Yes, yesterday morning.'],
            ['content' => 'The quarterly revenue exceeded our expectations.'],
        ],
    ]);
    expect(['medium', 'hard'])->toContain($hardDetect['difficulty_level']);
});

test('part 5 detection heuristics', function () {
    // Easy Part 5 (Short stem, high-frequency vocabulary)
    $easyDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 5,
        'prompt'      => 'Mr. Henderson will _____ the meeting tomorrow morning.',
        'choices'     => [
            ['content' => 'lead'],
            ['content' => 'leader'],
            ['content' => 'leadership'],
            ['content' => 'leadable'],
        ],
    ]);
    expect($easyDetect['difficulty_level'])->toBe('easy');

    // Hard Part 5 (Long sentence, complex subordination, sophisticated vocabulary)
    $hardDetect = QuestionDifficultyDetectionService::detect([
        'part_number' => 5,
        'prompt'      => 'Notwithstanding the unprecedented fluctuations in global currency valuations, the board of directors unanimously resolved to proceed with the proposed cross-border acquisition, provided that comprehensive due diligence is completed in a _____ manner.',
        'choices'     => [
            ['content' => 'punctual'],
            ['content' => 'punctuality'],
            ['content' => 'punctually'],
            ['content' => 'punctuate'],
        ],
    ]);
    expect($hardDetect['difficulty_level'])->toBe('hard');
});

test('part 7 detection heuristics', function () {
    $hardDetect = QuestionDifficultyDetectionService::detect([
        'part_number'  => 7,
        'passage_type' => 'triple',
        'passage_text' => 'Memo 1: Budget constraints... Email 2: Revised estimates... Schedule 3: Timelines...',
        'prompt'       => 'What is most likely inferred about the third supplier mentioned in the revised memorandum?',
        'choices'      => [
            ['content' => 'It was selected because of superior delivery guarantees.'],
            ['content' => 'It previously submitted an incomplete tender proposal.'],
            ['content' => 'It operates exclusively within the domestic logistics corridor.'],
            ['content' => 'Its pricing strategy aligns with executive sustainability goals.'],
        ],
    ]);
    expect($hardDetect['difficulty_level'])->toBe('hard');
});

test('teacher create assessment question auto detects difficulty and overrides manual input', function () {
    $response = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-question', $this->test->id),
        [
            'test_section_id' => $this->listeningSection->id,
            'part_number'     => 1,
            'question_type'   => 'multiple_choice',
            'prompt'          => 'Look at the photograph and select the statement that best describes what you see.',
            'image_url'       => 'https://example.com/p1_easy.jpg',
            'audio_url'       => 'https://example.com/p1_easy.mp3',
            'difficulty'      => 'hard', // Manual input attempt - MUST BE OVERRIDDEN BY SERVER-SIDE DETECTOR
            'correct_choice'  => '0',
            'choices'         => [
                'A man is holding a cup.',
                'A woman is reading a map.',
                'They are sitting on a bench.',
                'The car is parked by the tree.',
            ],
        ]
    );

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));
    $response->assertSessionHas('status');

    $question = Question::latest('id')->first();
    expect($question)->not->toBeNull();
    $diffVal = is_object($question->difficulty) ? $question->difficulty->value : $question->difficulty;
    expect($diffVal)->toBe('easy')
        ->and($question->difficulty_source)->toBe('auto')
        ->and($question->difficulty_status)->toBe('final')
        ->and($question->difficulty_score)->not->toBeNull()
        ->and($question->difficulty_detected_at)->not->toBeNull();
});

test('teacher update question recomputes auto difficulty', function () {
    $question = Question::create([
        'prompt'            => 'Initial simple prompt',
        'section'           => 'reading',
        'part_number'       => 5,
        'question_type'     => 'multiple_choice',
        'difficulty'        => 'easy',
        'difficulty_source' => 'auto',
        'points'            => 1,
    ]);

    $this->listeningSection->testQuestions()->create([
        'question_id' => $question->id,
        'order'       => 1,
        'points'      => 1,
    ]);

    // Update with complex Part 5 sentence
    $response = $this->actingAs($this->teacher)->put(
        route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $question->id]),
        [
            'part_number'    => 5,
            'question_type'  => 'multiple_choice',
            'difficulty'     => 'easy', // Teacher attempt to manually declare Easy
            'prompt'         => 'Notwithstanding unexpected regulatory impediments, the conglomerate will aggressively accelerate its overseas expansion, ensuring that all regional subsidiaries comply with stringent environmental mandates.',
            'correct_choice' => '0',
            'choices'        => [
                'punctual',
                'punctuality',
                'punctually',
                'punctuate',
            ],
        ]
    );

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));

    $question->refresh();
    $diffVal = is_object($question->difficulty) ? $question->difficulty->value : $question->difficulty;
    expect($diffVal)->toBe('hard')
        ->and($question->difficulty_status)->toBe('final')
        ->and($question->difficulty_source)->toBe('auto');
});

test('teacher ui renders auto difficulty and no manual select', function () {
    // 1. Detail Page (Create Modal)
    $detailResponse = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $detailResponse->assertStatus(200);
    $detailResponse->assertSee('⚡ Question Difficulty');
    $detailResponse->assertSee('create-q-auto-diff-badge');
    $detailResponse->assertDontSee('<select name="difficulty"', false);

    // 2. Question Editor Page
    $question = Question::create([
        'prompt'                 => 'Sample prompt',
        'section'                => 'listening',
        'part_number'            => 1,
        'question_type'          => 'multiple_choice',
        'difficulty'             => 'medium',
        'difficulty_score'       => 50,
        'difficulty_status'      => 'provisional',
        'difficulty_source'      => 'auto',
        'difficulty_detected_at' => now(),
        'points'                 => 1,
    ]);

    $this->listeningSection->testQuestions()->create([
        'question_id' => $question->id,
        'order'       => 1,
        'points'      => 1,
    ]);

    $editorResponse = $this->actingAs($this->teacher)->get(
        route('teacher.tests.edit-question', ['test' => $this->test->id, 'question' => $question->id])
    );
    $editorResponse->assertStatus(200);
    $editorResponse->assertSee('⚡ Auto-Detected Difficulty');
    $editorResponse->assertSee('eq-auto-diff-card');
    $editorResponse->assertDontSee('<select name="difficulty"', false);
});

test('draft safety with auto difficulty', function () {
    $response = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-question', $this->test->id),
        [
            'test_section_id' => $this->listeningSection->id,
            'section'         => 'listening',
            'question_type'   => 'multiple_choice',
            'prompt'          => 'Draft question stem without media yet',
            'correct_choice'  => '0',
            'choices'         => [
                'Choice A',
                'Choice B',
                'Choice C',
                'Choice D',
            ],
        ]
    );

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));
    $response->assertSessionHas('status');

    $question = Question::latest('id')->first();
    $diffVal = is_object($question->difficulty) ? $question->difficulty->value : $question->difficulty;
    expect($question)->not->toBeNull()
        ->and(['final', 'provisional', 'pending'])->toContain($question->difficulty_status)
        ->and($question->difficulty_source)->toBe('auto')
        ->and($diffVal)->not->toBeEmpty();
});
