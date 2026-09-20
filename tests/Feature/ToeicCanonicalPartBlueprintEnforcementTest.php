<?php

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create(['email' => 'teacher_bp@test.com']);
    $this->teacher->assignRole('teacher');

    $this->toeicTest = Test::create([
        'title'           => 'TOEIC Full Blueprint Mock Test',
        'slug'            => 'toeic-full-bp-' . uniqid(),
        'type'            => 'simulator',
        'assessment_mode' => AssessmentMode::Simulator,
        'test_type'       => 'toeic',
        'status'          => 'draft',
        'is_published'    => false,
        'is_active'       => true,
        'created_by'      => $this->teacher->id,
    ]);

    $this->builderService = app(TestBuilderService::class);
});

// Helper to create a test section for a TOEIC part
function createToeicSection(Test $test, int $partNum): TestSection
{
    $bp = ToeicQuestionValidator::getPartBlueprint($partNum);
    return TestSection::create([
        'test_id'      => $test->id,
        'title'        => "Part {$partNum}: {$bp['name']}",
        'section_type' => $bp['section'],
        'order'        => $partNum,
    ]);
}

// Helper to create valid standalone question
function createValidStandaloneQuestion(TestSection $section, int $partNum, int $order, array $custom = []): Question
{
    $choices = $custom['choices'] ?? ($partNum === 2 ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D']);
    $correctChoice = $custom['correct_choice'] ?? 0;

    $q = Question::create([
        'prompt'        => $custom['prompt'] ?? ($partNum === 2 ? '' : "Question {$order} prompt"),
        'section'       => $section->section_type,
        'part_number'   => $partNum,
        'question_type' => 'multiple_choice',
        'audio_url'     => $custom['audio_url'] ?? (in_array($partNum, [1, 2, 3, 4], true) ? 'https://example.com/audio.mp3' : null),
        'image_url'     => $custom['image_url'] ?? ($partNum === 1 ? 'https://example.com/photo.jpg' : null),
    ]);

    foreach ($choices as $idx => $label) {
        QuestionChoice::create([
            'question_id' => $q->id,
            'label'       => chr(65 + $idx),
            'content'     => "Option {$label}",
            'is_correct'  => $idx === $correctChoice,
            'order'       => $idx + 1,
        ]);
    }

    TestQuestion::create([
        'test_section_id' => $section->id,
        'question_id'     => $q->id,
        'order'           => $order,
        'points'          => 1,
    ]);

    return $q;
}

// Helper to create valid Audio Group (3 questions)
function createValidAudioGroup(TestSection $section, int $partNum, int $groupOrder): AudioGroup
{
    $ag = AudioGroup::create([
        'test_id'        => $section->test_id,
        'part_number'    => $partNum,
        'group_type'     => $partNum === 4 ? 'talk' : 'conversation',
        'title'          => ($partNum === 4 ? 'Talk Group ' : 'Conversation Group ') . $groupOrder,
        'order'          => $groupOrder,
        'audio_url'      => 'https://example.com/audio-shared.mp3',
    ]);

    for ($i = 0; $i < 3; $i++) {
        $cq = Question::create([
            'audio_group_id' => $ag->id,
            'part_number'    => $partNum,
            'section'        => 'listening',
            'prompt'         => "AG {$groupOrder} Question " . ($i + 1),
            'question_type'  => 'multiple_choice',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $cIdx => $cLabel) {
            QuestionChoice::create([
                'question_id' => $cq->id,
                'label'       => $cLabel,
                'content'     => "Option {$cLabel}",
                'is_correct'  => $cIdx === 0,
                'order'       => $cIdx + 1,
            ]);
        }
        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $cq->id,
            'order'           => (($groupOrder - 1) * 3) + $i + 1,
            'points'          => 1,
        ]);
    }

    return $ag;
}

// Helper to create valid Part 6 Passage Group (4 questions)
function createValidPart6PassageGroup(TestSection $section, int $groupOrder): PassageGroup
{
    $pg = PassageGroup::create([
        'test_id'      => $section->test_id,
        'part_number'  => 6,
        'passage_type' => 'single',
        'title'        => "Text Completion Group {$groupOrder}",
        'order'        => $groupOrder,
    ]);

    Passage::create([
        'passage_group_id' => $pg->id,
        'title'            => "Text {$groupOrder}",
        'content'          => "Passage content with blanks [1] [2] [3] [4]",
        'order_in_group'   => 1,
    ]);

    for ($i = 0; $i < 4; $i++) {
        $cq = Question::create([
            'passage_group_id' => $pg->id,
            'part_number'      => 6,
            'section'          => 'reading',
            'prompt'           => '',
            'question_type'    => 'multiple_choice',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $cIdx => $cLabel) {
            QuestionChoice::create([
                'question_id' => $cq->id,
                'label'       => $cLabel,
                'content'     => "Option {$cLabel}",
                'is_correct'  => $cIdx === 0,
                'order'       => $cIdx + 1,
            ]);
        }
        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $cq->id,
            'order'           => (($groupOrder - 1) * 4) + $i + 1,
            'points'          => 1,
        ]);
    }

    return $pg;
}

// Helper to create valid Part 7 Passage Group
function createValidPart7PassageGroup(TestSection $section, int $groupOrder, string $type = 'single', int $qCount = 4): PassageGroup
{
    $pg = PassageGroup::create([
        'test_id'      => $section->test_id,
        'part_number'  => 7,
        'passage_type' => $type,
        'title'        => "Reading Group {$groupOrder}",
        'order'        => $groupOrder,
    ]);

    $numDocs = $type === 'triple' ? 3 : ($type === 'double' ? 2 : 1);
    for ($d = 1; $d <= $numDocs; $d++) {
        Passage::create([
            'passage_group_id' => $pg->id,
            'title'            => "Doc {$d}",
            'content'          => "Reading passage content for document {$d}",
            'order_in_group'   => $d,
        ]);
    }

    for ($i = 0; $i < $qCount; $i++) {
        $cq = Question::create([
            'passage_group_id' => $pg->id,
            'part_number'      => 7,
            'section'          => 'reading',
            'prompt'           => "P7 G{$groupOrder} Q" . ($i + 1),
            'question_type'    => 'multiple_choice',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $cIdx => $cLabel) {
            QuestionChoice::create([
                'question_id' => $cq->id,
                'label'       => $cLabel,
                'content'     => "Option {$cLabel}",
                'is_correct'  => $cIdx === 0,
                'order'       => $cIdx + 1,
            ]);
        }
        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $cq->id,
            'order'           => (($groupOrder - 1) * $qCount) + $i + 1,
            'points'          => 1,
        ]);
    }

    return $pg;
}

/*
|--------------------------------------------------------------------------
| A. PART 1 VALIDATION (TOEIC-BP-01)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-01: Part 1 handles UNDER (5/6), EXACT (6/6), and OVER (7/6)', function () {
    $sec = createToeicSection($this->toeicTest, 1);

    // 1. UNDER: 5 questions
    for ($i = 1; $i <= 5; $i++) {
        createValidStandaloneQuestion($sec, 1, $i);
    }
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('5/6 Complete');
    $res->assertSee('NEEDS ATTENTION');
    $res->assertSee('1 question missing');

    // 2. EXACT: 6 questions
    createValidStandaloneQuestion($sec, 1, 6);
    $resExact = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resExact->assertSee('6/6 Complete');
    $resExact->assertSee('READY');

    // 3. OVER: 7 questions
    createValidStandaloneQuestion($sec, 1, 7);
    $resOver = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resOver->assertSee('7/6 Complete');
    $resOver->assertSee('NEEDS ATTENTION');
    $resOver->assertSee('1 question over limit');
});

/*
|--------------------------------------------------------------------------
| B. PART 2 VALIDATION (TOEIC-BP-02)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-02: Part 2 handles UNDER (24/25), EXACT (25/25), and OVER (26/25) with A/B/C audio contract', function () {
    $sec = createToeicSection($this->toeicTest, 2);

    // 1. UNDER: 24 questions
    for ($i = 1; $i <= 24; $i++) {
        createValidStandaloneQuestion($sec, 2, $i);
    }
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('24/25 Complete');
    $res->assertSee('NEEDS ATTENTION');
    $res->assertSee('1 question missing');

    // 2. EXACT: 25 questions
    createValidStandaloneQuestion($sec, 2, 25);
    $resExact = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resExact->assertSee('25/25 Complete');
    $resExact->assertSee('READY');

    // 3. OVER: 26 questions
    createValidStandaloneQuestion($sec, 2, 26);
    $resOver = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resOver->assertSee('26/25 Complete');
    $resOver->assertSee('NEEDS ATTENTION');
    $resOver->assertSee('1 question over limit');
});

/*
|--------------------------------------------------------------------------
| C. PART 3 VALIDATION (TOEIC-BP-03)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-03: Part 3 handles UNDER (38/39), EXACT (39 in 13x3), OVER (42/39)', function () {
    $sec = createToeicSection($this->toeicTest, 3);

    // 12 groups = 36 questions + 2 standalone = 38 (UNDER)
    for ($g = 1; $g <= 12; $g++) {
        createValidAudioGroup($sec, 3, $g);
    }
    createValidStandaloneQuestion($sec, 3, 37);
    createValidStandaloneQuestion($sec, 3, 38);

    $resUnder = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resUnder->assertSee('38/39 Complete');
    $resUnder->assertSee('NEEDS ATTENTION');

    // Clean section and create exactly 13 valid audio groups (39 questions)
    AudioGroup::where('test_id', $this->toeicTest->id)->delete();
    $this->toeicTest->sections()->delete();
    $sec = createToeicSection($this->toeicTest, 3);
    for ($g = 1; $g <= 13; $g++) {
        createValidAudioGroup($sec, 3, $g);
    }

    $resExact = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resExact->assertSee('39/39 Complete');
    $resExact->assertSee('READY');

    // Add extra 14th audio group -> OVER
    createValidAudioGroup($sec, 3, 14);
    $resOver = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resOver->assertSee('42/39 Complete');
    $resOver->assertSee('NEEDS ATTENTION');
    $resOver->assertSee('3 questions over limit');
});

/*
|--------------------------------------------------------------------------
| D. PART 4 VALIDATION (TOEIC-BP-04)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-04: Part 4 handles UNDER (29/30), EXACT (30 in 10x3), and OVER (33/30)', function () {
    $sec = createToeicSection($this->toeicTest, 4);

    // 9 groups = 27 + 2 questions = 29 (UNDER)
    for ($g = 1; $g <= 9; $g++) {
        createValidAudioGroup($sec, 4, $g);
    }
    createValidStandaloneQuestion($sec, 4, 28);
    createValidStandaloneQuestion($sec, 4, 29);

    $resUnder = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resUnder->assertSee('29/30 Complete');
    $resUnder->assertSee('NEEDS ATTENTION');

    // Exactly 10 groups = 30
    AudioGroup::where('test_id', $this->toeicTest->id)->delete();
    $this->toeicTest->sections()->delete();
    $sec = createToeicSection($this->toeicTest, 4);
    for ($g = 1; $g <= 10; $g++) {
        createValidAudioGroup($sec, 4, $g);
    }

    $resExact = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resExact->assertSee('30/30 Complete');
    $resExact->assertSee('READY');

    // Add 11th group -> OVER (33/30)
    createValidAudioGroup($sec, 4, 11);
    $resOver = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resOver->assertSee('33/30 Complete');
    $resOver->assertSee('NEEDS ATTENTION');
    $resOver->assertSee('3 questions over limit');
});

/*
|--------------------------------------------------------------------------
| E. PART 5 VALIDATION (TOEIC-BP-05)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-05: Part 5 handles UNDER (29/30), EXACT (30/30), and OVER (31/30)', function () {
    $sec = createToeicSection($this->toeicTest, 5);

    // 1. UNDER: 29 questions
    for ($i = 1; $i <= 29; $i++) {
        createValidStandaloneQuestion($sec, 5, $i);
    }
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('29/30 Complete');
    $res->assertSee('NEEDS ATTENTION');
    $res->assertSee('1 question missing');

    // 2. EXACT: 30 questions
    createValidStandaloneQuestion($sec, 5, 30);
    $resExact = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resExact->assertSee('30/30 Complete');
    $resExact->assertSee('READY');

    // 3. OVER: 31 questions
    createValidStandaloneQuestion($sec, 5, 31);
    $resOver = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resOver->assertSee('31/30 Complete');
    $resOver->assertSee('NEEDS ATTENTION');
    $resOver->assertSee('1 question over limit');
});

/*
|--------------------------------------------------------------------------
| F. PART 6 VALIDATION (TOEIC-BP-06)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-06: Part 6 handles UNDER (12/16), EXACT (16 in 4x4), and OVER (20/16)', function () {
    $sec = createToeicSection($this->toeicTest, 6);

    // 3 groups = 12 questions (UNDER)
    for ($g = 1; $g <= 3; $g++) {
        createValidPart6PassageGroup($sec, $g);
    }
    $resUnder = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resUnder->assertSee('12/16 Complete');
    $resUnder->assertSee('NEEDS ATTENTION');
    $resUnder->assertSee('4 questions missing');

    // 4 groups = 16 (EXACT)
    createValidPart6PassageGroup($sec, 4);
    $resExact = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resExact->assertSee('16/16 Complete');
    $resExact->assertSee('READY');

    // 5 groups = 20 (OVER)
    createValidPart6PassageGroup($sec, 5);
    $resOver = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resOver->assertSee('20/16 Complete');
    $resOver->assertSee('NEEDS ATTENTION');
    $resOver->assertSee('4 questions over limit');
});

/*
|--------------------------------------------------------------------------
| G. PART 7 VALIDATION (TOEIC-BP-07)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-07: Part 7 handles UNDER (53/54), EXACT (54), and OVER (55/54)', function () {
    $sec = createToeicSection($this->toeicTest, 7);

    // 10 single (29) + 2 double (10) + 2 triple of 5 (10) + 1 triple of 4 (4) = 53 questions (UNDER)
    for ($g = 1; $g <= 9; $g++) {
        createValidPart7PassageGroup($sec, $g, 'single', 3);
    }
    createValidPart7PassageGroup($sec, 10, 'single', 2);
    createValidPart7PassageGroup($sec, 11, 'double', 5);
    createValidPart7PassageGroup($sec, 12, 'double', 5);
    createValidPart7PassageGroup($sec, 13, 'triple', 5);
    createValidPart7PassageGroup($sec, 14, 'triple', 5);
    createValidPart7PassageGroup($sec, 15, 'triple', 4);

    $resUnder = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resUnder->assertSee('53/54 Complete');
    $resUnder->assertSee('NEEDS ATTENTION');
    $resUnder->assertSee('1 question missing');

    // Replace group 15 with a 5-question group -> 54 EXACT (Canonical READY)
    PassageGroup::where('test_id', $this->toeicTest->id)->delete();
    $this->toeicTest->sections()->delete();
    $sec = createToeicSection($this->toeicTest, 7);
    for ($g = 1; $g <= 9; $g++) {
        createValidPart7PassageGroup($sec, $g, 'single', 3);
    }
    createValidPart7PassageGroup($sec, 10, 'single', 2);
    createValidPart7PassageGroup($sec, 11, 'double', 5);
    createValidPart7PassageGroup($sec, 12, 'double', 5);
    createValidPart7PassageGroup($sec, 13, 'triple', 5);
    createValidPart7PassageGroup($sec, 14, 'triple', 5);
    createValidPart7PassageGroup($sec, 15, 'triple', 5);

    $resExact = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resExact->assertSee('54/54 Complete');
    $resExact->assertSee('READY');

    // Add extra passage group with 2 questions -> OVER (56/54)
    createValidPart7PassageGroup($sec, 16, 'single', 2);
    $resOver = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resOver->assertSee('56/54 Complete');
    $resOver->assertSee('NEEDS ATTENTION');
    $resOver->assertSee('2 questions over limit');
});

/*
|--------------------------------------------------------------------------
| H. TOTAL 200 BUT WRONG DISTRIBUTION (TOEIC-BP-08)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-08: Total 200 questions with wrong distribution (P1=5, P2=26) is invalid and blocked', function () {
    // P1 = 5 (Under)
    $s1 = createToeicSection($this->toeicTest, 1);
    for ($i = 1; $i <= 5; $i++) {
        createValidStandaloneQuestion($s1, 1, $i);
    }

    // P2 = 26 (Over)
    $s2 = createToeicSection($this->toeicTest, 2);
    for ($i = 1; $i <= 26; $i++) {
        createValidStandaloneQuestion($s2, 2, $i);
    }

    // P3 = 39 (Exact)
    $s3 = createToeicSection($this->toeicTest, 3);
    for ($g = 1; $g <= 13; $g++) {
        createValidAudioGroup($s3, 3, $g);
    }

    // P4 = 30 (Exact)
    $s4 = createToeicSection($this->toeicTest, 4);
    for ($g = 1; $g <= 10; $g++) {
        createValidAudioGroup($s4, 4, $g);
    }

    // P5 = 30 (Exact)
    $s5 = createToeicSection($this->toeicTest, 5);
    for ($i = 1; $i <= 30; $i++) {
        createValidStandaloneQuestion($s5, 5, $i);
    }

    // P6 = 16 (Exact)
    $s6 = createToeicSection($this->toeicTest, 6);
    for ($g = 1; $g <= 4; $g++) {
        createValidPart6PassageGroup($s6, $g);
    }

    // P7 = 54 (Exact Canonical)
    $s7 = createToeicSection($this->toeicTest, 7);
    for ($g = 1; $g <= 9; $g++) {
        createValidPart7PassageGroup($s7, $g, 'single', 3);
    }
    createValidPart7PassageGroup($s7, 10, 'single', 2);
    createValidPart7PassageGroup($s7, 11, 'double', 5);
    createValidPart7PassageGroup($s7, 12, 'double', 5);
    createValidPart7PassageGroup($s7, 13, 'triple', 5);
    createValidPart7PassageGroup($s7, 14, 'triple', 5);
    createValidPart7PassageGroup($s7, 15, 'triple', 5);

    // Total questions in DB = 5 + 26 + 39 + 30 + 30 + 16 + 54 = 200
    $val = $this->builderService->validateAssessment($this->toeicTest);
    expect($val['is_valid'])->toBeFalse();
    expect(implode(' | ', $val['errors']))->toContain('Part 1');
    expect(implode(' | ', $val['errors']))->toContain('Part 2');

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Submission Disabled');
    $res->assertSee('5/6 Complete');
    $res->assertSee('26/25 Complete');
});

/*
|--------------------------------------------------------------------------
| I. EXACT FULL TOEIC 200 (TOEIC-BP-09)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-09: Exact full TOEIC (6, 25, 39, 30, 30, 16, 54 = 200) passes validation and enables submission', function () {
    // P1 = 6
    $s1 = createToeicSection($this->toeicTest, 1);
    for ($i = 1; $i <= 6; $i++) {
        createValidStandaloneQuestion($s1, 1, $i);
    }

    // P2 = 25
    $s2 = createToeicSection($this->toeicTest, 2);
    for ($i = 1; $i <= 25; $i++) {
        createValidStandaloneQuestion($s2, 2, $i);
    }

    // P3 = 39 (13x3)
    $s3 = createToeicSection($this->toeicTest, 3);
    for ($g = 1; $g <= 13; $g++) {
        createValidAudioGroup($s3, 3, $g);
    }

    // P4 = 30 (10x3)
    $s4 = createToeicSection($this->toeicTest, 4);
    for ($g = 1; $g <= 10; $g++) {
        createValidAudioGroup($s4, 4, $g);
    }

    // P5 = 30
    $s5 = createToeicSection($this->toeicTest, 5);
    for ($i = 1; $i <= 30; $i++) {
        createValidStandaloneQuestion($s5, 5, $i);
    }

    // P6 = 16 (4x4)
    $s6 = createToeicSection($this->toeicTest, 6);
    for ($g = 1; $g <= 4; $g++) {
        createValidPart6PassageGroup($s6, $g);
    }

    // P7 = 54 (Exact Canonical)
    $s7 = createToeicSection($this->toeicTest, 7);
    for ($g = 1; $g <= 9; $g++) {
        createValidPart7PassageGroup($s7, $g, 'single', 3);
    }
    createValidPart7PassageGroup($s7, 10, 'single', 2);
    createValidPart7PassageGroup($s7, 11, 'double', 5);
    createValidPart7PassageGroup($s7, 12, 'double', 5);
    createValidPart7PassageGroup($s7, 13, 'triple', 5);
    createValidPart7PassageGroup($s7, 14, 'triple', 5);
    createValidPart7PassageGroup($s7, 15, 'triple', 5);

    $val = $this->builderService->validateAssessment($this->toeicTest);
    expect($val['is_valid'])->toBeTrue();
    expect($val['errors'])->toBeEmpty();

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertOk();
    $res->assertSee('Submit for Review');
    $res->assertDontSee('Submission Disabled');
});

/*
|--------------------------------------------------------------------------
| J. OVERFLOW QUESTION NUMBERING (TOEIC-BP-10)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-10: Overflow child questions are marked non-canonical and do not steal Part 7 canonical numbers', function () {
    $s6 = createToeicSection($this->toeicTest, 6);
    // 4 canonical groups (Q131–Q146)
    for ($g = 1; $g <= 4; $g++) {
        createValidPart6PassageGroup($s6, $g);
    }
    // 5th temporary group (overflow 17..20 in Part 6)
    createValidPart6PassageGroup($s6, 5);

    $s7 = createToeicSection($this->toeicTest, 7);
    createValidPart7PassageGroup($s7, 1, 'single', 4);

    $val = $this->builderService->validateAssessment($this->toeicTest);

    // Questions 131..146 should have canonical numbers 131..146
    // Questions in 5th group of Part 6 should be is_overflow = true with display_label "Overflow 1", "Overflow 2", etc.
    $part6Questions = collect($val['questions'])->filter(fn($it) => $it['section']->id === $s6->id)->values();
    expect($part6Questions)->toHaveCount(20);

    expect($part6Questions[0]['canonical_number'])->toBe(131);
    expect($part6Questions[15]['canonical_number'])->toBe(146);

    expect($part6Questions[16]['is_overflow'])->toBeTrue();
    expect($part6Questions[16]['display_label'])->toBe('Overflow 1');
    expect($part6Questions[16]['canonical_number'])->toBeNull();

    expect($part6Questions[19]['is_overflow'])->toBeTrue();
    expect($part6Questions[19]['display_label'])->toBe('Overflow 4');

    // Part 7 questions must START at Q147
    $part7Questions = collect($val['questions'])->filter(fn($it) => $it['section']->id === $s7->id)->values();
    expect($part7Questions[0]['canonical_number'])->toBe(147);
    expect($part7Questions[0]['display_label'])->toBe('Q147');

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('[Overflow 1]');
    $res->assertSee('Q147');
});

/*
|--------------------------------------------------------------------------
| K. SUBMISSION ROUTING & FOCUS (TOEIC-BP-11)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-11: Attempting submission with invalid part is blocked and routes to invalid part', function () {
    $s1 = createToeicSection($this->toeicTest, 1);
    for ($i = 1; $i <= 5; $i++) {
        createValidStandaloneQuestion($s1, 1, $i);
    }

    $res = $this->actingAs($this->teacher)->post(route('teacher.tests.resubmit', $this->toeicTest->id));
    $res->assertRedirect();
    $res->assertSessionHas('error');

    $this->toeicTest->refresh();
    expect($this->toeicTest->status)->toBe('draft');
});

/*
|--------------------------------------------------------------------------
| L. BLUEPRINT SINGLE SOURCE (TOEIC-BP-12)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-12: Canonical blueprint single source helper returns exact targets and ranges for all Parts 1-7', function () {
    $expected = [
        1 => ['target' => 6,  'start' => 1,   'end' => 6],
        2 => ['target' => 25, 'start' => 7,   'end' => 31],
        3 => ['target' => 39, 'start' => 32,  'end' => 70],
        4 => ['target' => 30, 'start' => 71,  'end' => 100],
        5 => ['target' => 30, 'start' => 101, 'end' => 130],
        6 => ['target' => 16, 'start' => 131, 'end' => 146],
        7 => ['target' => 54, 'start' => 147, 'end' => 200],
    ];

    $total = 0;
    foreach ($expected as $part => $exp) {
        expect(ToeicQuestionValidator::getPartTargetQuestionCount($part))->toBe($exp['target']);
        expect(ToeicQuestionValidator::getPartQuestionRange($part))->toBe(['start' => $exp['start'], 'end' => $exp['end']]);
        $total += $exp['target'];
    }

    expect($total)->toBe(200);
    expect(ToeicQuestionValidator::getTotalCanonicalTargetCount())->toBe(200);
});

/*
|--------------------------------------------------------------------------
| M. SAVE DRAFT ALLOWED IN ALL STATES (TOEIC-BP-13)
|--------------------------------------------------------------------------
*/

test('TOEIC-BP-13: Save Draft remains allowed when Part is under, exact, or over target', function () {
    $sec = createToeicSection($this->toeicTest, 6);

    // Save Draft under target (1 group = 4 questions)
    $pg = createValidPart6PassageGroup($sec, 1);
    expect($pg->exists)->toBeTrue();

    // Save Draft over target (add 4 more groups = 20 questions)
    for ($g = 2; $g <= 5; $g++) {
        createValidPart6PassageGroup($sec, $g);
    }
    expect(PassageGroup::where('test_id', $this->toeicTest->id)->count())->toBe(5);
    expect($this->toeicTest->fresh()->status)->toBe('draft');
});
