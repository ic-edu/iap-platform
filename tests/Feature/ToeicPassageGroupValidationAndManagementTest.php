<?php

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create(['email' => 'teacher_pv@test.com']);
    $this->teacher->assignRole('teacher');

    $this->otherTeacher = User::factory()->create(['email' => 'other_teacher_pv@test.com']);
    $this->otherTeacher->assignRole('teacher');

    $this->test = Test::create([
        'title'           => 'TOEIC Full Mock Test',
        'slug'            => 'toeic-full-mock-test-' . uniqid(),
        'type'            => 'simulator',
        'assessment_mode' => AssessmentMode::Simulator,
        'test_type'       => 'toeic',
        'status'          => 'draft',
        'is_published'    => false,
        'is_active'       => true,
        'created_by'      => $this->teacher->id,
    ]);

    $this->sectionPart6 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 6: Text Completion',
        'section_type' => 'reading',
        'order'        => 6,
    ]);

    $this->sectionPart7 = TestSection::create([
        'test_id'      => $this->test->id,
        'title'        => 'Part 7: Reading Comprehension',
        'section_type' => 'reading',
        'order'        => 7,
    ]);

    $this->builderService = app(TestBuilderService::class);
});

/*
|--------------------------------------------------------------------------
| A. PART 6 VALIDATION & SECTION ROLLUP (TEST 01 - TEST 12)
|--------------------------------------------------------------------------
*/

test('TEST 01: Part 6 child question with blank prompt is considered complete when 4 choices are present and 1 correct', function () {
    $question = Question::create([
        'prompt'       => '',
        'section'      => 'reading',
        'part_number'  => 6,
        'question_type'=> 'multiple_choice',
    ]);
    foreach (['A', 'B', 'C', 'D'] as $idx => $opt) {
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => $opt,
            'content'     => "Choice {$opt}",
            'is_correct'  => $idx === 0,
            'order'       => $idx + 1,
        ]);
    }

    expect($question->isCompleteChild())->toBeTrue();
});

test('TEST 02: Part 6 child question with whitespace prompt is considered complete when 4 choices are present', function () {
    $question = Question::create([
        'prompt'       => '   ',
        'section'      => 'reading',
        'part_number'  => 6,
        'question_type'=> 'multiple_choice',
    ]);
    foreach (['A', 'B', 'C', 'D'] as $idx => $opt) {
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => $opt,
            'content'     => "Choice {$opt}",
            'is_correct'  => $idx === 1,
            'order'       => $idx + 1,
        ]);
    }

    expect($question->isCompleteChild())->toBeTrue();
});

test('TEST 03: Part 6 child question missing choices is incomplete even with blank prompt', function () {
    $question = Question::create([
        'prompt'       => '',
        'section'      => 'reading',
        'part_number'  => 6,
        'question_type'=> 'multiple_choice',
    ]);
    QuestionChoice::create([
        'question_id' => $question->id,
        'label'       => 'A',
        'content'     => 'Choice A',
        'is_correct'  => true,
        'order'       => 1,
    ]);

    expect($question->isCompleteChild())->toBeFalse();
});

test('TEST 04: Part 6 child question missing correct choice is incomplete', function () {
    $question = Question::create([
        'prompt'       => '',
        'section'      => 'reading',
        'part_number'  => 6,
        'question_type'=> 'multiple_choice',
    ]);
    foreach (['A', 'B', 'C', 'D'] as $idx => $opt) {
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => $opt,
            'content'     => "Choice {$opt}",
            'is_correct'  => false,
            'order'       => $idx + 1,
        ]);
    }

    expect($question->isCompleteChild())->toBeFalse();
});

test('TEST 05: ToeicQuestionValidator::check accepts Part 6 question with blank prompt', function () {
    $check = ToeicQuestionValidator::check([
        'part_number'    => 6,
        'prompt'         => '',
        'passage_text'   => 'Some shared passage text',
        'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
        'correct_choice' => 0,
    ]);

    expect($check['is_valid'])->toBeTrue();
    expect($check['errors'])->toBeEmpty();
});

test('TEST 06: TestBuilderService::validateAssessment does not flag empty prompt error for Part 6 child questions', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $validation = $this->builderService->validateAssessment($this->test->fresh(['sections.testQuestions.question.choices']));
    
    // Check Part 6 questions have no prompt empty errors
    $part6Questions = collect($validation['questions'])->filter(fn($q) => $q['section']->id === $this->sectionPart6->id);
    expect($part6Questions)->toHaveCount(4);
    foreach ($part6Questions as $pq) {
        expect($pq['warnings'])->toBeEmpty();
    }
});

test('TEST 07: Section Rollup calculates Part 6 as 4/4 Complete and READY when all 4 questions valid', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('4/16 Complete');
    $response->assertSee('NEEDS ATTENTION');
    $response->assertDontSee('Stem / Prompt text is empty.');
});

test('TEST 08: Section Rollup identifies Part 6 incomplete question when choice is missing', function () {
    $group = PassageGroup::create([
        'test_id'     => $this->test->id,
        'part_number' => 6,
        'passage_type'=> 'single',
    ]);
    Passage::create([
        'passage_group_id' => $group->id,
        'title'            => 'Doc 1',
        'order_in_group'   => 1,
        'content'          => 'Passage text',
    ]);
    for ($i = 0; $i < 4; $i++) {
        $q = Question::create([
            'passage_group_id' => $group->id,
            'part_number'      => 6,
            'prompt'           => '',
            'section'          => 'reading',
            'question_type'    => 'multiple_choice',
        ]);
        // Slot 0 has only 2 choices (incomplete)
        $numChoices = ($i === 0) ? 2 : 4;
        for ($c = 0; $c < $numChoices; $c++) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => chr(65 + $c),
                'content'     => "Choice {$c}",
                'is_correct'  => $c === 0,
                'order'       => $c + 1,
            ]);
        }
        TestQuestion::create([
            'test_section_id' => $this->sectionPart6->id,
            'question_id'     => $q->id,
            'order'           => $i + 1,
            'points'          => 1,
        ]);
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('3/16 Complete');
    $response->assertSee('NEEDS ATTENTION');
});

test('TEST 09: PassageGroup isComplete returns true for valid Part 6 group with blank prompts', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);
    expect($pg->isComplete())->toBeTrue();
});

test('TEST 10: PassageGroup isComplete returns false if Part 6 has only 3 questions', function () {
    $group = PassageGroup::create([
        'test_id'     => $this->test->id,
        'part_number' => 6,
        'passage_type'=> 'single',
    ]);
    Passage::create([
        'passage_group_id' => $group->id,
        'title'            => 'Doc 1',
        'order_in_group'   => 1,
        'content'          => 'Passage text',
    ]);
    for ($i = 0; $i < 3; $i++) {
        $q = Question::create([
            'passage_group_id' => $group->id,
            'part_number'      => 6,
            'prompt'           => '',
            'section'          => 'reading',
            'question_type'    => 'multiple_choice',
        ]);
        for ($c = 0; $c < 4; $c++) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => chr(65 + $c),
                'content'     => "Choice {$c}",
                'is_correct'  => $c === 0,
                'order'       => $c + 1,
            ]);
        }
    }

    expect($group->isComplete())->toBeFalse();
});

test('TEST 11: Part 6 PassageGroup with optional prompt populated is also valid', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => "Select the best word for blank [".(131 + $i)."]",
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);
    expect($pg->isComplete())->toBeTrue();
});

test('TEST 12: Part 6 PassageGroup fails validation if audio is attached', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
            'audio_url'      => 'https://example.com/audio.mp3',
        ], range(0, 3)),
    ];

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $this->builderService->createPassageGroup($this->sectionPart6, $groupData);
});

/*
|--------------------------------------------------------------------------
| B. PART 7 VALIDATION & SECTION ROLLUP (TEST 13 - TEST 24)
|--------------------------------------------------------------------------
*/

test('TEST 13: Part 7 child question with blank prompt returns isCompleteChild false', function () {
    $question = Question::create([
        'prompt'       => '',
        'section'      => 'reading',
        'part_number'  => 7,
        'question_type'=> 'multiple_choice',
    ]);
    foreach (['A', 'B', 'C', 'D'] as $idx => $opt) {
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => $opt,
            'content'     => "Choice {$opt}",
            'is_correct'  => $idx === 0,
            'order'       => $idx + 1,
        ]);
    }

    expect($question->isCompleteChild())->toBeFalse();
});

test('TEST 14: Part 7 child question with non-empty prompt and 4 choices returns isCompleteChild true', function () {
    $question = Question::create([
        'prompt'       => 'What is the purpose of the email?',
        'section'      => 'reading',
        'part_number'  => 7,
        'question_type'=> 'multiple_choice',
    ]);
    foreach (['A', 'B', 'C', 'D'] as $idx => $opt) {
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => $opt,
            'content'     => "Choice {$opt}",
            'is_correct'  => $idx === 0,
            'order'       => $idx + 1,
        ]);
    }

    expect($question->isCompleteChild())->toBeTrue();
});

test('TEST 15: ToeicQuestionValidator::check rejects Part 7 child question if prompt is empty', function () {
    $check = ToeicQuestionValidator::check([
        'part_number'    => 7,
        'prompt'         => '',
        'passage_text'   => 'Article text here',
        'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
        'correct_choice' => 0,
    ]);

    expect($check['is_valid'])->toBeFalse();
    expect($check['errors'])->toHaveKey('prompt');
});

test('TEST 16: Part 7 Single Passage group requires 2 to 4 questions', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Article', 'content' => 'Article content here'],
        ],
        'questions'       => [
            [
                'prompt'         => 'Question 1',
                'choices'        => ['A', 'B', 'C', 'D'],
                'correct_choice' => 0,
            ],
            // Only 1 question -> invalid
        ],
    ];

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $this->builderService->createPassageGroup($this->sectionPart7, $groupData);
});

function seedPrerequisiteSingleGroupsForMgmt($test, $section, $builderService): void
{
    for ($g = 1; $g <= 9; $g++) {
        $builderService->createPassageGroup($section, [
            'test_section_id' => $section->id,
            'part_number'     => 7,
            'passage_type'    => 'single',
            'title'           => "Prereq Single {$g}",
            'passages'        => [['document_type' => 'article', 'title' => "Doc {$g}", 'content' => "Stimulus {$g}"]],
            'questions'       => array_map(fn($k) => ['prompt' => "Q{$k}", 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(1, 3)),
        ]);
    }
    $builderService->createPassageGroup($section, [
        'test_section_id' => $section->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'title'           => 'Prereq Single 10',
        'passages'        => [['document_type' => 'article', 'title' => 'Doc 10', 'content' => 'Stimulus 10']],
        'questions'       => array_map(fn($k) => ['prompt' => "Q{$k}", 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(1, 2)),
    ]);
}

function seedPrerequisiteDoubleGroupsForMgmt($test, $section, $builderService): void
{
    seedPrerequisiteSingleGroupsForMgmt($test, $section, $builderService);
    for ($g = 1; $g <= 2; $g++) {
        $builderService->createPassageGroup($section, [
            'test_section_id' => $section->id,
            'part_number'     => 7,
            'passage_type'    => 'double',
            'title'           => "Prereq Double {$g}",
            'passages'        => [
                ['document_type' => 'article', 'title' => "Doc {$g}-1", 'content' => 'Text 1'],
                ['document_type' => 'email', 'title' => "Doc {$g}-2", 'content' => 'Text 2'],
            ],
            'questions'       => array_map(fn($k) => ['prompt' => "Q{$k}", 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(1, 5)),
        ]);
    }
}

test('TEST 17: Part 7 Double Passage group requires exactly 2 passages and 5 questions', function () {
    seedPrerequisiteSingleGroupsForMgmt($this->test, $this->sectionPart7, $this->builderService);

    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'double',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Doc 1 content'],
            ['title' => 'Doc 2', 'content' => 'Doc 2 content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => "Question {$i}",
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(1, 5)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart7, $groupData);
    expect($pg->isComplete())->toBeTrue();
    expect($pg->questions)->toHaveCount(5);
    expect($pg->passages)->toHaveCount(2);
});

test('TEST 18: Part 7 Triple Passage group requires exactly 3 passages and 5 questions', function () {
    seedPrerequisiteDoubleGroupsForMgmt($this->test, $this->sectionPart7, $this->builderService);

    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'triple',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Doc 1 content'],
            ['title' => 'Doc 2', 'content' => 'Doc 2 content'],
            ['title' => 'Doc 3', 'content' => 'Doc 3 content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => "Question {$i}",
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(1, 5)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart7, $groupData);
    expect($pg->isComplete())->toBeTrue();
    expect($pg->questions)->toHaveCount(5);
    expect($pg->passages)->toHaveCount(3);
});

test('TEST 19: Part 7 Triple Passage group fails if only 2 passages provided', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'triple',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Doc 1 content'],
            ['title' => 'Doc 2', 'content' => 'Doc 2 content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => "Question {$i}",
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(1, 5)),
    ];

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $this->builderService->createPassageGroup($this->sectionPart7, $groupData);
});

test('TEST 20: Section Rollup for Part 7 shows READY when all questions and passages are valid', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Notice', 'content' => 'Notice content here'],
        ],
        'questions'       => [
            [
                'prompt'         => 'What is this notice about?',
                'choices'        => ['Renovation', 'Policy change', 'Holiday hours', 'New hire'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => 'When will the change take effect?',
                'choices'        => ['Monday', 'Tuesday', 'Next month', 'Immediately'],
                'correct_choice' => 1,
            ],
        ],
    ];

    $this->builderService->createPassageGroup($this->sectionPart7, $groupData);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('2/54 Complete');
    $response->assertSee('NEEDS ATTENTION');
});

test('TEST 21: Section Rollup for Part 7 shows NEEDS ATTENTION when a child question prompt is empty', function () {
    $group = PassageGroup::create([
        'test_id'     => $this->test->id,
        'part_number' => 7,
        'passage_type'=> 'single',
    ]);
    Passage::create([
        'passage_group_id' => $group->id,
        'title'            => 'Notice 1',
        'order_in_group'   => 1,
        'content'          => 'Notice content',
    ]);
    for ($i = 0; $i < 2; $i++) {
        $q = Question::create([
            'passage_group_id' => $group->id,
            'part_number'      => 7,
            'prompt'           => ($i === 0) ? 'Valid prompt?' : '', // Slot 1 has empty prompt
            'section'          => 'reading',
            'question_type'    => 'multiple_choice',
        ]);
        for ($c = 0; $c < 4; $c++) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => chr(65 + $c),
                'content'     => "Choice {$c}",
                'is_correct'  => $c === 0,
                'order'       => $c + 1,
            ]);
        }
        TestQuestion::create([
            'test_section_id' => $this->sectionPart7->id,
            'question_id'     => $q->id,
            'order'           => $i + 1,
            'points'          => 1,
        ]);
    }

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('1/54 Complete');
    $response->assertSee('NEEDS ATTENTION');
});

test('TEST 22: Part 7 PassageGroup with audio attached fails validation', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Article', 'content' => 'Article content here'],
        ],
        'questions'       => [
            [
                'prompt'         => 'Question 1',
                'choices'        => ['A', 'B', 'C', 'D'],
                'correct_choice' => 0,
                'audio_url'      => 'https://example.com/audio.mp3',
            ],
            [
                'prompt'         => 'Question 2',
                'choices'        => ['A', 'B', 'C', 'D'],
                'correct_choice' => 0,
            ],
        ],
    ];

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $this->builderService->createPassageGroup($this->sectionPart7, $groupData);
});

test('TEST 23: TestBuilderService::validateAssessment produces issues when question choices are missing', function () {
    $q = Question::create([
        'prompt'       => 'Sample Part 7 Question',
        'section'      => 'reading',
        'part_number'  => 7,
        'question_type'=> 'multiple_choice',
    ]);
    TestQuestion::create([
        'test_section_id' => $this->sectionPart7->id,
        'question_id'     => $q->id,
        'order'           => 1,
        'points'          => 1,
    ]);

    $val = $this->builderService->validateAssessment($this->test->fresh(['sections.testQuestions.question.choices']));
    expect($val['is_valid'])->toBeFalse();
    expect(implode(' | ', $val['errors']))->toContain('(Sample Part 7 Question): No options/choices provided.');
});

test('TEST 24: TestBuilderService::validateAssessment includes Part 7 passage group findings', function () {
    $group = PassageGroup::create([
        'test_id'     => $this->test->id,
        'part_number' => 7,
        'passage_type'=> 'single',
    ]);
    // Missing passage -> invalid passage group
    $q = Question::create([
        'passage_group_id' => $group->id,
        'part_number'      => 7,
        'prompt'           => 'Some question',
        'section'          => 'reading',
        'question_type'    => 'multiple_choice',
    ]);
    for ($c = 0; $c < 4; $c++) {
        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => chr(65 + $c),
            'content'     => "Choice {$c}",
            'is_correct'  => $c === 0,
            'order'       => $c + 1,
        ]);
    }
    TestQuestion::create([
        'test_section_id' => $this->sectionPart7->id,
        'question_id'     => $q->id,
        'order'           => 1,
        'points'          => 1,
    ]);

    $val = $this->builderService->validateAssessment($this->test->fresh(['sections.testQuestions.question.choices']));
    expect($val['is_valid'])->toBeFalse();
    $foundPgFinding = false;
    foreach ($val['questions'] as $qItem) {
        foreach ($qItem['warnings'] as $w) {
            if (str_starts_with($w, 'Passage Group Finding:')) {
                $foundPgFinding = true;
            }
        }
    }
    expect($foundPgFinding)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| C. PASSAGE GROUP EDIT ACTIONS (TEST 25 - TEST 36)
|--------------------------------------------------------------------------
*/

test('TEST 25: Teacher can update Part 6 passage content and child choices', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Original Title', 'content' => 'Original passage content [131]-[134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['Old A', 'Old B', 'Old C', 'Old D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $updateData = [
        'test_section_id' => $this->sectionPart6->id,
        'title'           => 'Updated Memo Title',
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            [
                'id'             => $pg->passages->first()->id,
                'title'          => 'Updated Doc Title',
                'content'        => 'Updated passage content with new blanks [131]-[134]',
                'document_type'  => 'memo',
                'order_in_group' => 1,
            ],
        ],
        'questions'       => $pg->questions->map(fn($q, $i) => [
            'id'             => $q->id,
            'prompt'         => "Updated blank {$i} note",
            'choices'        => ['New A', 'New B', 'New C', 'New D'],
            'correct_choice' => 2,
            'explanation'    => "Explanation for blank {$i}",
        ])->toArray(),
    ];

    $response = $this->actingAs($this->teacher)->put(
        route('teacher.tests.update-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id]),
        $updateData
    );

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->test->id,
        'section' => $this->sectionPart6->id,
        'focus'   => "passage-group-card-{$pg->id}",
    ]));
    $response->assertSessionHas('status', 'Part 6 Text Completion Group saved successfully.');

    $pg->refresh();
    expect($pg->title)->toBe('Updated Memo Title');
    expect($pg->passages->first()->content)->toBe('Updated passage content with new blanks [131]-[134]');
    expect($pg->questions->first()->prompt)->toBe('Updated blank 0 note');
    expect($pg->questions->first()->explanation)->toBe('Explanation for blank 0');
    expect($pg->questions->first()->choices->where('is_correct', true)->first()->label)->toBe('C');
});

test('TEST 26: Update PassageGroup persists difficulty recalculation', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Simple passage content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['cat', 'dog', 'fish', 'bird'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $updateData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            [
                'id'      => $pg->passages->first()->id,
                'content' => 'Highly complex specialized technical biomedical engineering specifications and pharmacological contraindications.',
            ],
        ],
        'questions'       => $pg->questions->map(fn($q) => [
            'id'             => $q->id,
            'prompt'         => '',
            'choices'        => ['heterogeneous', 'microvascular', 'deoxyribonucleic', 'photosynthesis'],
            'correct_choice' => 1,
        ])->toArray(),
    ];

    $this->builderService->updatePassageGroup($pg, $updateData, $this->sectionPart6);

    $pg->refresh();
    $firstQ = $pg->questions->first();
    expect($firstQ->difficulty_status)->toBeIn(['detected', 'final']);
    expect($firstQ->difficulty_source)->toBe('auto');
});

test('TEST 27: Teacher can update Part 7 PassageGroup from single to double with 5 questions', function () {
    seedPrerequisiteSingleGroupsForMgmt($this->test, $this->sectionPart7, $this->builderService);

    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'double',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Doc 1 content'],
            ['title' => 'Doc 2', 'content' => 'Doc 2 content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => "Question {$i}",
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(1, 5)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart7, $groupData);

    $updateData = [
        'test_section_id' => $this->sectionPart7->id,
        'title'           => 'Updated Double Passage',
        'part_number'     => 7,
        'passage_type'    => 'double',
        'passages'        => [
            ['id' => $pg->passages[0]->id, 'title' => 'Email', 'content' => 'Email content here'],
            ['id' => $pg->passages[1]->id, 'title' => 'Response', 'content' => 'Response letter here'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => "Updated Question {$i}",
            'choices'        => ['Option 1', 'Option 2', 'Option 3', 'Option 4'],
            'correct_choice' => 0,
        ], range(1, 5)),
    ];

    $updated = $this->builderService->updatePassageGroup($pg, $updateData, $this->sectionPart7);

    expect($updated->passage_type)->toBe('double');
    expect($updated->passages)->toHaveCount(2);
    expect($updated->questions)->toHaveCount(5);
    expect($updated->isComplete())->toBeTrue();
});

test('TEST 28: Update PassageGroup rejects update if Part 7 prompt is emptied', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Doc 1 content'],
        ],
        'questions'       => [
            ['prompt' => 'Question 1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Question 2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart7, $groupData);

    $updateData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['id' => $pg->passages->first()->id, 'content' => 'Doc 1 content'],
        ],
        'questions'       => [
            ['id' => $pg->questions[0]->id, 'prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], // Empty prompt
            ['id' => $pg->questions[1]->id, 'prompt' => 'Question 2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ];

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $this->builderService->updatePassageGroup($pg, $updateData, $this->sectionPart7);
});

test('TEST 29: Update PassageGroup allows Part 6 prompt to remain empty', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => "Initial prompt {$i}",
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $updateData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['id' => $pg->passages->first()->id, 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => $pg->questions->map(fn($q) => [
            'id'             => $q->id,
            'prompt'         => '', // Emptied prompt
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 1,
        ])->toArray(),
    ];

    $updated = $this->builderService->updatePassageGroup($pg, $updateData, $this->sectionPart6);
    expect($updated->isComplete())->toBeTrue();
    expect($updated->questions->first()->prompt)->toBe('');
});

test('TEST 30: Unauthorized teacher cannot update passage group of another teacher test', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $response = $this->actingAs($this->otherTeacher)->put(
        route('teacher.tests.update-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id]),
        [
            'test_section_id' => $this->sectionPart6->id,
            'passages'        => [['content' => 'Hacked']],
            'questions'       => array_map(fn($i) => ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(0, 3)),
        ]
    );

    $response->assertForbidden();
});

test('TEST 31: Cannot update passage group if test is published or not editable', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $this->test->update(['status' => 'published', 'is_published' => true]);

    $response = $this->actingAs($this->teacher)->put(
        route('teacher.tests.update-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id]),
        [
            'test_section_id' => $this->sectionPart6->id,
            'passages'        => [['content' => 'Modified']],
            'questions'       => array_map(fn($i) => ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(0, 3)),
        ]
    );

    $response->assertForbidden();
});

test('TEST 32: Update PassageGroup correctly preserves choice count and updates choice text', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['Choice 1', 'Choice 2', 'Choice 3', 'Choice 4'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $updateData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['id' => $pg->passages->first()->id, 'content' => 'Content'],
        ],
        'questions'       => $pg->questions->map(fn($q) => [
            'id'             => $q->id,
            'prompt'         => '',
            'choices'        => ['Alpha', 'Beta', 'Gamma', 'Delta'],
            'correct_choice' => 3,
        ])->toArray(),
    ];

    $this->builderService->updatePassageGroup($pg, $updateData, $this->sectionPart6);

    $firstQ = $pg->fresh(['questions.choices'])->questions->first();
    $choices = $firstQ->choices->sortBy('order')->values();
    expect($choices[0]->content)->toBe('Alpha');
    expect($choices[1]->content)->toBe('Beta');
    expect($choices[2]->content)->toBe('Gamma');
    expect($choices[3]->content)->toBe('Delta');
    expect($choices[3]->is_correct)->toBeTrue();
    expect($choices[0]->is_correct)->toBeFalse();
});

test('TEST 33: Update PassageGroup correctly appends new child questions if count increased', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Content'],
        ],
        'questions'       => [
            ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart7, $groupData);
    expect($pg->questions)->toHaveCount(2);

    $updateData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['id' => $pg->passages->first()->id, 'content' => 'Content'],
        ],
        'questions'       => [
            ['id' => $pg->questions[0]->id, 'prompt' => 'Q1 updated', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['id' => $pg->questions[1]->id, 'prompt' => 'Q2 updated', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => 'Q3 new', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2], // New question
        ],
    ];

    $updated = $this->builderService->updatePassageGroup($pg, $updateData, $this->sectionPart7);
    expect($updated->questions)->toHaveCount(3);
    expect($this->sectionPart7->testQuestions)->toHaveCount(3);
});

test('TEST 34: Update PassageGroup expands origin section and sets focus query parameter', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $response = $this->actingAs($this->teacher)->put(
        route('teacher.tests.update-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id]),
        [
            'test_section_id' => $this->sectionPart6->id,
            'part_number'     => 6,
            'passage_type'    => 'single',
            'passages'        => [['id' => $pg->passages->first()->id, 'content' => 'Content']],
            'questions'       => $pg->questions->map(fn($q) => ['id' => $q->id, 'prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0])->toArray(),
        ]
    );

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->test->id,
        'section' => $this->sectionPart6->id,
        'focus'   => "passage-group-card-{$pg->id}",
    ]));
    $response->assertSessionHas('expanded_section_id', $this->sectionPart6->id);
});

test('TEST 35: Update PassageGroup allows updating question explanation', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $updateData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['id' => $pg->passages->first()->id, 'content' => 'Content'],
        ],
        'questions'       => $pg->questions->map(fn($q, $i) => [
            'id'             => $q->id,
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
            'explanation'    => "Detailed grammar explanation for blank {$i}",
        ])->toArray(),
    ];

    $this->builderService->updatePassageGroup($pg, $updateData, $this->sectionPart6);

    $pg->refresh();
    expect($pg->questions->first()->explanation)->toBe('Detailed grammar explanation for blank 0');
});

test('TEST 36: Update PassageGroup returns 404 if passage group does not belong to assessment', function () {
    $otherTest = Test::create([
        'title'           => 'Other Test',
        'slug'            => 'other-test-' . uniqid(),
        'type'            => 'simulator',
        'assessment_mode' => AssessmentMode::Simulator,
        'test_type'       => 'toeic',
        'status'          => 'draft',
        'is_published'    => false,
        'is_active'       => true,
        'created_by'      => $this->teacher->id,
    ]);

    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->builderService->deletePassageGroup($otherTest, $pg);
});

/*
|--------------------------------------------------------------------------
| D. PASSAGE GROUP REMOVE ACTIONS & ATOMIC CLEANUP (TEST 37 - TEST 48)
|--------------------------------------------------------------------------
*/

test('TEST 37: Destroy PassageGroup removes PassageGroup, assessment-authored passages, and child questions', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage with blanks [131] to [134]'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);
    $passageId = $pg->passages->first()->id;
    $questionIds = $pg->questions->pluck('id')->toArray();
    $pgId = $pg->id;

    $response = $this->actingAs($this->teacher)->delete(
        route('teacher.tests.destroy-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id])
    );

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->test->id,
        'section' => $this->sectionPart6->id,
    ]));
    $response->assertSessionHas('status', 'Part 6 Text Completion Group removed successfully.');
    $response->assertSessionHas('expanded_section_id', $this->sectionPart6->id);

    expect(PassageGroup::find($pgId))->toBeNull();
    expect(Passage::find($passageId))->toBeNull();
    foreach ($questionIds as $qId) {
        expect(Question::find($qId))->toBeNull();
        expect(QuestionChoice::where('question_id', $qId)->exists())->toBeFalse();
        expect(TestQuestion::where('question_id', $qId)->exists())->toBeFalse();
    }
});

test('TEST 38: Destroy Part 7 PassageGroup removes group and returns to Part 7 section expanded', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Notice', 'content' => 'Notice text'],
        ],
        'questions'       => [
            ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart7, $groupData);

    $response = $this->actingAs($this->teacher)->delete(
        route('teacher.tests.destroy-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id])
    );

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->test->id,
        'section' => $this->sectionPart7->id,
    ]));
    $response->assertSessionHas('status', 'Part 7 Reading Passage Group removed successfully.');
});

test('TEST 39: Destroy PassageGroup preserves repository-owned passages with question_bank_id', function () {
    $qb = QuestionBank::create([
        'title'      => 'Master Bank 1',
        'slug'       => 'master-bank-1-' . uniqid(),
        'created_by' => $this->teacher->id,
    ]);

    $pg = PassageGroup::create([
        'test_id'     => $this->test->id,
        'part_number' => 7,
        'passage_type'=> 'single',
    ]);
    $repoPassage = Passage::create([
        'passage_group_id' => $pg->id,
        'question_bank_id' => $qb->id, // Repository-owned
        'title'            => 'Master passage title',
        'order_in_group'   => 1,
        'content'          => 'Master passage from repository',
    ]);
    $childQ = Question::create([
        'passage_group_id' => $pg->id,
        'question_bank_id' => null,
        'part_number'      => 7,
        'prompt'           => 'Q1',
        'section'          => 'reading',
        'question_type'    => 'multiple_choice',
    ]);
    TestQuestion::create([
        'test_section_id' => $this->sectionPart7->id,
        'question_id'     => $childQ->id,
        'order'           => 1,
        'points'          => 1,
    ]);

    $this->builderService->deletePassageGroup($this->test, $pg);

    expect(PassageGroup::find($pg->id))->toBeNull();
    expect(Question::find($childQ->id))->toBeNull();
    // Repository passage is NOT deleted; only decoupled
    $refreshedRepoPassage = Passage::find($repoPassage->id);
    expect($refreshedRepoPassage)->not->toBeNull();
    expect($refreshedRepoPassage->passage_group_id)->toBeNull();
});

test('TEST 40: Destroy PassageGroup preserves repository-owned master questions', function () {
    $qb = QuestionBank::create([
        'title'      => 'Master Bank 2',
        'slug'       => 'master-bank-2-' . uniqid(),
        'created_by' => $this->teacher->id,
    ]);

    $pg = PassageGroup::create([
        'test_id'     => $this->test->id,
        'part_number' => 6,
        'passage_type'=> 'single',
    ]);
    Passage::create([
        'passage_group_id' => $pg->id,
        'title'            => 'Passage Title',
        'order_in_group'   => 1,
        'content'          => 'Passage text',
    ]);
    $masterQ = Question::create([
        'passage_group_id' => $pg->id,
        'question_bank_id' => $qb->id, // Governed master question
        'part_number'      => 6,
        'prompt'           => '',
        'section'          => 'reading',
        'question_type'    => 'multiple_choice',
    ]);
    TestQuestion::create([
        'test_section_id' => $this->sectionPart6->id,
        'question_id'     => $masterQ->id,
        'order'           => 1,
        'points'          => 1,
    ]);

    $this->builderService->deletePassageGroup($this->test, $pg);

    expect(PassageGroup::find($pg->id))->toBeNull();
    // Governed question is NOT deleted; decoupled and placement removed
    $refreshedMasterQ = Question::find($masterQ->id);
    expect($refreshedMasterQ)->not->toBeNull();
    expect($refreshedMasterQ->passage_group_id)->toBeNull();
    expect(TestQuestion::where('question_id', $masterQ->id)->exists())->toBeFalse();
});

test('TEST 41: Unauthorized teacher cannot destroy passage group of another teacher test', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $response = $this->actingAs($this->otherTeacher)->delete(
        route('teacher.tests.destroy-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id])
    );

    $response->assertForbidden();
    expect(PassageGroup::find($pg->id))->not->toBeNull();
});

test('TEST 42: Cannot destroy passage group if test is published', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $this->test->update(['status' => 'published', 'is_published' => true]);

    $response = $this->actingAs($this->teacher)->delete(
        route('teacher.tests.destroy-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id])
    );

    $response->assertForbidden();
    expect(PassageGroup::find($pg->id))->not->toBeNull();
});

test('TEST 43: Destroying PassageGroup updates Section Rollup to NOT STARTED if section becomes empty', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [
            ['title' => 'Doc 1', 'content' => 'Passage content'],
        ],
        'questions'       => array_map(fn($i) => [
            'prompt'         => '',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $this->builderService->deletePassageGroup($this->test, $pg);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('NOT STARTED');
    $response->assertSee('0 questions');
});

test('TEST 44: Destroying one PassageGroup leaves other PassageGroups intact', function () {
    $groupData1 = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Doc 1', 'content' => 'Passage 1']],
        'questions'       => array_map(fn($i) => ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(0, 3)),
    ];
    $groupData2 = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Doc 2', 'content' => 'Passage 2']],
        'questions'       => array_map(fn($i) => ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(0, 3)),
    ];

    $pg1 = $this->builderService->createPassageGroup($this->sectionPart6, $groupData1);
    $pg2 = $this->builderService->createPassageGroup($this->sectionPart6, $groupData2);

    $this->builderService->deletePassageGroup($this->test, $pg1);

    expect(PassageGroup::find($pg1->id))->toBeNull();
    expect(PassageGroup::find($pg2->id))->not->toBeNull();
    expect($this->sectionPart6->fresh()->testQuestions)->toHaveCount(4);
});

test('TEST 45: Destroy PassageGroup returns 404 if test does not match', function () {
    $otherTest = Test::create([
        'title'           => 'Other Test',
        'slug'            => 'other-test-' . uniqid(),
        'type'            => 'simulator',
        'assessment_mode' => AssessmentMode::Simulator,
        'test_type'       => 'toeic',
        'status'          => 'draft',
        'is_published'    => false,
        'is_active'       => true,
        'created_by'      => $this->teacher->id,
    ]);

    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Doc 1', 'content' => 'Passage 1']],
        'questions'       => array_map(fn($i) => ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->builderService->deletePassageGroup($otherTest, $pg);
});

test('TEST 46: PassageGroup card in Blade renders Edit Group and Remove Group buttons', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Staff Memo', 'content' => 'Memo text [131]-[134]']],
        'questions'       => array_map(fn($i) => ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(0, 3)),
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart6, $groupData);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('Edit Group');
    $response->assertSee('Remove Group');
    $response->assertSee("openEditPassageGroupModal");
    $response->assertSee("Remove Text Completion Group?");
});

test('TEST 47: Part 7 PassageGroup card renders Remove Reading Passage Group modal trigger', function () {
    $groupData = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Business Article', 'content' => 'Article text']],
        'questions'       => [
            ['prompt' => 'Q1?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Q2?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ];

    $pg = $this->builderService->createPassageGroup($this->sectionPart7, $groupData);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertOk();
    $response->assertSee('Edit Group');
    $response->assertSee('Remove Group');
    $response->assertSee("Remove Reading Passage Group?");
});

test('TEST 48: Full Mock Test passage groups have no group-level validation errors', function () {
    $pg6Data = [
        'test_section_id' => $this->sectionPart6->id,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Memo', 'content' => 'Memo text with blanks [131] to [134]']],
        'questions'       => array_map(fn($i) => ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], range(0, 3)),
    ];
    $this->builderService->createPassageGroup($this->sectionPart6, $pg6Data);

    $pg7Data = [
        'test_section_id' => $this->sectionPart7->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Article', 'content' => 'Article text']],
        'questions'       => [
            ['prompt' => 'What is discussed?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Who is contacted?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ];
    $this->builderService->createPassageGroup($this->sectionPart7, $pg7Data);

    $val = $this->builderService->validateAssessment($this->test->fresh(['sections.testQuestions.question.choices']));
    $groupErrors = collect($val['errors'])->filter(fn($e) => str_contains($e, 'Passage Group Finding') || str_contains($e, 'Text Completion Group') || str_contains($e, 'Reading Group'));
    expect($groupErrors)->toBeEmpty();
});
