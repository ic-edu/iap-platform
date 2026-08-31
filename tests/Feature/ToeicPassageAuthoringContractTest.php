<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\QuestionDifficultyDetectionService;
use App\Services\ToeicQuestionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

use Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Assessment\Enums\AssessmentMode;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create([
        'email' => 'teacher_passage_contract@test.com',
    ]);
    $this->teacher->assignRole('teacher');

    $this->test = Test::create([
        'title'            => 'TOEIC Passage Contract Assessment',
        'slug'             => 'toeic-passage-contract-assessment-' . uniqid(),
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

    $this->part5Section = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 5: Incomplete Sentences',
        'section_type' => 'reading',
        'order'        => 5,
    ]);

    $this->part6Section = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 6: Text Completion',
        'section_type' => 'reading',
        'order'        => 6,
    ]);

    $this->part7Section = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 7: Reading Comprehension',
        'section_type' => 'reading',
        'order'        => 7,
    ]);
});

// ==========================================
// PART 6 TESTS (TEST 01 - TEST 10)
// ==========================================

test('TEST 01 - Part 6 PassageGroup modal prompt field is optional', function () {
    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $response->assertStatus(200);
    $response->assertSee('Question Note / Blank Context (Optional)');
    $response->assertDontSee('Question 1 Prompt / Blank Stem *');
});

test('TEST 02 - Blank child prompt passes request validation', function () {
    $payload = [
        'test_section_id'  => $this->part6Section->id,
        'title'            => 'Part 6 Memo With Blank Prompts',
        'part_number'      => 6,
        'passage_type'     => 'single',
        'passages'         => [
            [
                'title'         => 'Office Memo',
                'content'       => 'Please note that the annual meeting is scheduled for Friday [131]. All staff should arrive [132] time. We look forward to your [133]. Please notify your [134].',
                'document_type' => 'memo',
                'order_in_group'=> 1,
            ],
        ],
        'questions'        => [
            [
                'prompt'         => '',
                'choices'        => ['morning', 'afternoon', 'evening', 'night'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => '',
                'choices'        => ['in', 'on', 'at', 'by'],
                'correct_choice' => 1,
            ],
            [
                'prompt'         => '',
                'choices'        => ['participation', 'participate', 'participating', 'participant'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => '',
                'choices'        => ['supervisor', 'supervise', 'supervising', 'supervision'],
                'correct_choice' => 0,
            ],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-passage-group', $this->test->id), $payload);

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));
    $this->assertDatabaseHas('passage_groups', [
        'test_id'     => $this->test->id,
        'part_number' => 6,
    ]);

    $this->assertDatabaseCount('questions', 4);
});

test('TEST 03 - Blank child prompt passes TOEIC Part 6 domain validation', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['content' => 'Passage text with [131] [132] [133] [134] blanks.'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    expect($result['is_valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

test('TEST 04 - Four children still required', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [['content' => 'Passage text']],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    expect($result['is_valid'])->toBeTrue();
    expect($result['question_count'])->toBe(4);
});

test('TEST 05 - Four choices each still required', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [['content' => 'Passage text']],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C'], 'correct_choice' => 0], // Only 3 choices
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    expect($result['is_valid'])->toBeFalse();
    expect($result['errors'])->toHaveKey('question_1_choices');
});

test('TEST 06 - One correct answer each still required', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [['content' => 'Passage text']],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => null], // No correct choice
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    expect($result['is_valid'])->toBeFalse();
    expect($result['errors'])->toHaveKey('question_1_correct_choice');
});

test('TEST 07 - Missing passage still fails', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    expect($result['is_valid'])->toBeFalse();
    expect($result['errors'])->toHaveKey('passage_count');
});

test('TEST 08 - 3-child Part 6 group fails', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [['content' => 'Passage text']],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
        ],
    ]);

    expect($result['is_valid'])->toBeFalse();
    expect($result['errors'])->toHaveKey('question_count');
});

test('TEST 09 - 5-child Part 6 group fails', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [['content' => 'Passage text']],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ],
    ]);

    expect($result['is_valid'])->toBeFalse();
    expect($result['errors'])->toHaveKey('question_count');
});

test('TEST 10 - Section rollup counts valid blank-prompt child as complete', function () {
    $pg = PassageGroup::create([
        'test_id'         => $this->test->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'title'           => 'Rollup Test Group',
    ]);

    Passage::create([
        'passage_group_id' => $pg->id,
        'test_id'          => $this->test->id,
        'order_in_group'   => 1,
        'document_type'    => 'article',
        'title'            => 'Document 1',
        'content'          => 'Passage text with [131] [132] [133] [134] blanks.',
    ]);

    for ($i = 0; $i < 4; $i++) {
        $q = Question::create([
            'passage_group_id' => $pg->id,
            'prompt'           => '',
            'section'          => 'reading',
            'part_number'      => 6,
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $cIdx => $opt) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $opt,
                'content'     => "Option {$opt}",
                'is_correct'  => $cIdx === 0,
            ]);
        }

        TestQuestion::create([
            'test_id'         => $this->test->id,
            'test_section_id' => $this->part6Section->id,
            'question_id'     => $q->id,
            'order'           => $i + 1,
        ]);
    }

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $response->assertStatus(200);
    $response->assertSee('4 / 4 Complete');
    $response->assertSee('🟢 VALID');
});

// ==========================================
// PART 7 TESTS (TEST 11 - TEST 14)
// ==========================================

test('TEST 11 - Part 7 child question prompt remains required', function () {
    $result = ToeicQuestionValidator::check([
        'part_number'   => 7,
        'prompt'        => '',
        'passage_text'  => 'Valid passage text',
        'choices'       => ['A', 'B', 'C', 'D'],
        'correct_choice'=> 0,
    ]);

    expect($result['is_valid'])->toBeFalse();
    expect($result['errors'])->toHaveKey('prompt');
    expect($result['errors']['prompt'])->toContain('Part 7 requires a question prompt');
});

test('TEST 12 - Part 7 missing question prompt fails', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'single',
        'passages'     => [['content' => 'Passage text']],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], // Blank prompt
            ['prompt' => 'What is suggested?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ]);

    expect($result['is_valid'])->toBeFalse();
    expect($result['errors'])->toHaveKey('question_1_prompt');
});

test('TEST 13 - Part 7 valid prompt passes', function () {
    $result = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'single',
        'passages'     => [['content' => 'Passage text']],
        'questions'    => [
            ['prompt' => 'Why did the writer send the email?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'What will happen on Monday?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ]);

    expect($result['is_valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

test('TEST 14 - Part 7 PassageGroup relationship remains unchanged', function () {
    $payload = [
        'test_section_id'  => $this->part7Section->id,
        'title'            => 'Part 7 Single Passage Reading',
        'part_number'      => 7,
        'passage_type'     => 'single',
        'passages'         => [
            [
                'title'         => 'Advertisement',
                'content'       => 'Grand Opening Sale! All items 20% off this weekend.',
                'document_type' => 'advertisement',
                'order_in_group'=> 1,
            ],
        ],
        'questions'        => [
            [
                'prompt'         => 'What is the advertisement about?',
                'choices'        => ['A sale', 'A new store', 'A hiring event', 'A closing sale'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => 'How much discount is offered?',
                'choices'        => ['10%', '20%', '30%', '50%'],
                'correct_choice' => 1,
            ],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-passage-group', $this->test->id), $payload);

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));
    $this->assertDatabaseHas('passage_groups', [
        'test_id'     => $this->test->id,
        'part_number' => 7,
    ]);
});

// ==========================================
// CTA TESTS (TEST 15 - TEST 21)
// ==========================================

test('TEST 15 - Part 6 does NOT render + Add Question', function () {
    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $content = $response->getContent();
    $sec6Id = "section-card-{$this->part6Section->id}";
    $sec6Pos = strpos($content, $sec6Id);
    $sec7Id = "section-card-{$this->part7Section->id}";
    $sec7Pos = strpos($content, $sec7Id);
    $part6Block = substr($content, $sec6Pos, $sec7Pos - $sec6Pos);

    expect($part6Block)->not->toContain('+ Add Question');
});

test('TEST 16 - Part 6 renders + Add Passage Group', function () {
    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $content = $response->getContent();
    $sec6Id = "section-card-{$this->part6Section->id}";
    $sec6Pos = strpos($content, $sec6Id);
    $sec7Id = "section-card-{$this->part7Section->id}";
    $sec7Pos = strpos($content, $sec7Id);
    $part6Block = substr($content, $sec6Pos, $sec7Pos - $sec6Pos);

    expect($part6Block)->toContain('+ Add Passage Group');
});

test('TEST 17 - Part 7 does NOT render + Add Question', function () {
    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $content = $response->getContent();
    $sec7Id = "section-card-{$this->part7Section->id}";
    $sec7Pos = strpos($content, $sec7Id);
    $part7Block = substr($content, $sec7Pos);

    expect($part7Block)->not->toContain('+ Add Question');
});

test('TEST 18 - Part 7 renders + Add Passage Group where supported', function () {
    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $content = $response->getContent();
    $sec7Id = "section-card-{$this->part7Section->id}";
    $sec7Pos = strpos($content, $sec7Id);
    $part7Block = substr($content, $sec7Pos);

    expect($part7Block)->toContain('+ Add Passage Group');
});

test('TEST 19 - Part 5 still renders Add Question', function () {
    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $content = $response->getContent();
    $sec5Id = "section-card-{$this->part5Section->id}";
    $sec5Pos = strpos($content, $sec5Id);
    $sec6Id = "section-card-{$this->part6Section->id}";
    $sec6Pos = strpos($content, $sec6Id);
    $part5Block = substr($content, $sec5Pos, $sec6Pos - $sec5Pos);

    expect($part5Block)->toContain('+ Add Question');
});

test('TEST 20 - Global Add New Question cannot create standalone Part 6 question', function () {
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-question', $this->test->id), [
            'test_section_id' => $this->part6Section->id,
            'part_number'     => 6,
            'question_type'   => 'multiple_choice',
            'prompt'          => 'Standalone question attempting to bypass rule',
            'choices'         => ['A', 'B', 'C', 'D'],
            'correct_choice'  => 0,
        ]);

    $response->assertSessionHasErrors(['part_number']);
});

test('TEST 21 - Global Add New Question cannot create standalone Part 7 question', function () {
    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-question', $this->test->id), [
            'test_section_id' => $this->part7Section->id,
            'part_number'     => 7,
            'question_type'   => 'multiple_choice',
            'prompt'          => 'Standalone question attempting to bypass rule',
            'choices'         => ['A', 'B', 'C', 'D'],
            'correct_choice'  => 0,
        ]);

    $response->assertSessionHasErrors(['part_number']);
});

// ==========================================
// LEGACY TESTS (TEST 22 - TEST 23)
// ==========================================

test('TEST 22 - Existing legacy standalone Part 6 Question still renders safely', function () {
    $q = Question::create([
        'passage_group_id' => null,
        'prompt'           => 'Legacy standalone Part 6 question prompt',
        'section'          => 'reading',
        'part_number'      => 6,
        'question_type'    => 'multiple_choice',
        'points'           => 1,
    ]);

    foreach (['A', 'B', 'C', 'D'] as $cIdx => $opt) {
        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => $opt,
            'content'     => "Option {$opt}",
            'is_correct'  => $cIdx === 0,
        ]);
    }

    TestQuestion::create([
        'test_id'         => $this->test->id,
        'test_section_id' => $this->part6Section->id,
        'question_id'     => $q->id,
        'order'           => 1,
    ]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $response->assertStatus(200);
    $response->assertSee('Legacy standalone Part 6 question prompt');
});

test('TEST 23 - Existing legacy standalone Part 7 Question still renders safely', function () {
    $q = Question::create([
        'passage_group_id' => null,
        'prompt'           => 'Legacy standalone Part 7 question prompt',
        'section'          => 'reading',
        'part_number'      => 7,
        'question_type'    => 'multiple_choice',
        'points'           => 1,
    ]);

    foreach (['A', 'B', 'C', 'D'] as $cIdx => $opt) {
        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => $opt,
            'content'     => "Option {$opt}",
            'is_correct'  => $cIdx === 0,
        ]);
    }

    TestQuestion::create([
        'test_id'         => $this->test->id,
        'test_section_id' => $this->part7Section->id,
        'question_id'     => $q->id,
        'order'           => 1,
    ]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.show', $this->test->id));

    $response->assertStatus(200);
    $response->assertSee('Legacy standalone Part 7 question prompt');
});

// ==========================================
// AUTO DIFFICULTY TESTS (TEST 24 - TEST 26)
// ==========================================

test('TEST 24 - Part 6 blank prompt does not crash difficulty detection', function () {
    $detection = QuestionDifficultyDetectionService::detect([
        'part_number'  => 6,
        'prompt'       => '',
        'passage_text' => 'Passage text',
        'choices'      => ['word1', 'word2', 'word3', 'word4'],
    ]);

    expect($detection)->toHaveKeys(['difficulty_level', 'difficulty_score', 'difficulty_status']);
    expect($detection['difficulty_level'])->toBeIn(['easy', 'medium', 'hard']);
});

test('TEST 25 - Part 6 difficulty can use available passage/choice context', function () {
    // Whole sentence insertion item (long choices)
    $detectionLong = QuestionDifficultyDetectionService::detect([
        'part_number'  => 6,
        'prompt'       => '',
        'passage_text' => 'Passage with context',
        'choices'      => [
            'We are pleased to inform you about the update',
            'Please let us know if you have questions',
            'The management will review all applications next week',
            'Thank you for your continued support of our mission',
        ],
    ]);

    expect($detectionLong['difficulty_status'])->toBe('final');
    expect($detectionLong['difficulty_factors']['reasons'])->toContain('Whole-sentence insertion item requiring context flow synthesis');
});

test('TEST 26 - Part 7 difficulty unchanged', function () {
    $detection = QuestionDifficultyDetectionService::detect([
        'part_number'  => 7,
        'prompt'       => 'What is suggested about the company in the article?',
        'passage_text' => 'Article text',
        'passage_type' => 'double',
        'choices'      => ['It is expanding', 'It is closing', 'It is merging', 'It is hiring'],
    ]);

    expect($detection['difficulty_status'])->toBe('final');
    expect($detection['difficulty_factors']['reasons'])->toContain('Double passage cross-referencing requirement');
});

// ==========================================
// CANDIDATE TESTS (TEST 27 - TEST 29)
// ==========================================

test('TEST 27 - Part 6 blank prompt does not expose empty mandatory question heading', function () {
    $pg = PassageGroup::create([
        'test_id'         => $this->test->id,
        'test_section_id' => $this->part6Section->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'title'           => 'Preview Part 6 Group',
    ]);

    Passage::create([
        'passage_group_id' => $pg->id,
        'test_id'          => $this->test->id,
        'order_in_group'   => 1,
        'document_type'    => 'article',
        'title'            => 'Document 1',
        'content'          => 'Passage text with [131] [132] [133] [134] blanks.',
    ]);

    for ($i = 0; $i < 4; $i++) {
        $q = Question::create([
            'passage_group_id' => $pg->id,
            'prompt'           => '',
            'section'          => 'reading',
            'part_number'      => 6,
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $cIdx => $opt) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $opt,
                'content'     => "Part6 Choice {$opt} for Blank {$i}",
                'is_correct'  => $cIdx === 0,
            ]);
        }

        TestQuestion::create([
            'test_id'         => $this->test->id,
            'test_section_id' => $this->part6Section->id,
            'question_id'     => $q->id,
            'order'           => $i + 1,
        ]);
    }

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.preview', $this->test->id));

    $response->assertStatus(200);
    $response->assertDontSee('(No stem prompt)');
});

test('TEST 28 - Part 6 choices remain candidate-visible', function () {
    $pg = PassageGroup::create([
        'test_id'         => $this->test->id,
        'test_section_id' => $this->part6Section->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'title'           => 'Preview Part 6 Choices Group',
    ]);

    Passage::create([
        'passage_group_id' => $pg->id,
        'test_id'          => $this->test->id,
        'order_in_group'   => 1,
        'document_type'    => 'article',
        'title'            => 'Document 1',
        'content'          => 'Passage text with [131] [132] [133] [134] blanks.',
    ]);

    for ($i = 0; $i < 4; $i++) {
        $q = Question::create([
            'passage_group_id' => $pg->id,
            'prompt'           => '',
            'section'          => 'reading',
            'part_number'      => 6,
            'question_type'    => 'multiple_choice',
            'points'           => 1,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $cIdx => $opt) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $opt,
                'content'     => "UniqueOption_{$opt}_Q{$i}",
                'is_correct'  => $cIdx === 0,
            ]);
        }

        TestQuestion::create([
            'test_id'         => $this->test->id,
            'test_section_id' => $this->part6Section->id,
            'question_id'     => $q->id,
            'order'           => $i + 1,
        ]);
    }

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.preview', $this->test->id));

    $response->assertStatus(200);
    $response->assertSee('UniqueOption_A_Q0');
    $response->assertSee('UniqueOption_B_Q0');
    $response->assertSee('UniqueOption_C_Q0');
    $response->assertSee('UniqueOption_D_Q0');
});

test('TEST 29 - Part 7 question prompts remain candidate-visible', function () {
    $pg = PassageGroup::create([
        'test_id'         => $this->test->id,
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'title'           => 'Preview Part 7 Prompt Group',
    ]);

    Passage::create([
        'passage_group_id' => $pg->id,
        'test_id'          => $this->test->id,
        'order_in_group'   => 1,
        'document_type'    => 'article',
        'title'            => 'Document 1',
        'content'          => 'Passage text for comprehension.',
    ]);

    $q = Question::create([
        'passage_group_id' => $pg->id,
        'prompt'           => 'What is the main topic of the article?',
        'section'          => 'reading',
        'part_number'      => 7,
        'question_type'    => 'multiple_choice',
        'points'           => 1,
    ]);

    foreach (['A', 'B', 'C', 'D'] as $cIdx => $opt) {
        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => $opt,
            'content'     => "Part7 Choice {$opt}",
            'is_correct'  => $cIdx === 0,
        ]);
    }

    TestQuestion::create([
        'test_id'         => $this->test->id,
        'test_section_id' => $this->part7Section->id,
        'question_id'     => $q->id,
        'order'           => 1,
    ]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.preview', $this->test->id));

    $response->assertStatus(200);
    $response->assertSee('What is the main topic of the article?');
});
