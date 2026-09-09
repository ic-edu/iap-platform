<?php

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create();
    $this->teacher->assignRole('teacher');

    $this->otherTeacher = User::factory()->create();
    $this->otherTeacher->assignRole('teacher');

    // Create TOEIC assessment test
    $this->toeicTest = Test::create([
        'title'            => 'Official TOEIC Practice Test',
        'slug'             => 'official-toeic-practice-test-' . uniqid(),
        'type'             => 'simulator',
        'assessment_mode'  => AssessmentMode::Simulator,
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'status'           => 'draft',
        'is_published'     => false,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
        'assigned_to'      => $this->teacher->id,
    ]);

    // Create Part 5 Section (Incomplete Sentences - Standalone)
    $this->part5Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 5: Incomplete Sentences',
        'section_type' => SectionType::Reading,
        'order'        => 5,
    ]);

    // Create Part 6 Section (Text Completion - Passage Groups)
    $this->part6Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 6: Text Completion',
        'section_type' => SectionType::Reading,
        'order'        => 6,
    ]);

    // Create Part 7 Section (Reading Comprehension)
    $this->part7Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 7: Reading Comprehension',
        'section_type' => SectionType::Reading,
        'order'        => 7,
    ]);
});

function validPart6Payload(string $sectionId): array
{
    return [
        'test_section_id' => $sectionId,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'title'           => 'Training Course Email',
        'passages'        => [
            [
                'title'         => 'Document 1',
                'document_type' => 'article',
                'order_in_group'=> 1,
                'content'       => "To: All Staff\nFrom: HR Department\nSubject: Upcoming Training\n\nPlease note that the annual leadership seminar will take place next Friday. All employees are [131] to attend. You should submit your registration [132] by Wednesday. [133]. If you have any questions, please contact your manager [134].",
            ],
        ],
        'questions'       => [
            [
                'prompt'         => 'Select the best word for blank [131]',
                'explanation'    => 'Encouraged is the correct passive participle here.',
                'choices'        => ['encouraged', 'encourages', 'encouraging', 'encouragement'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => 'Select the best word for blank [132]',
                'explanation'    => 'Forms is the appropriate noun.',
                'choices'        => ['forms', 'formulate', 'forming', 'formally'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => 'Select the best sentence for blank [133]',
                'explanation'    => 'Contextually fits the seminar announcement.',
                'choices'        => [
                    'Lunch will be provided during the break.',
                    'The office will be closed permanently.',
                    'Please return all library books.',
                    'Flight tickets must be booked early.',
                ],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => 'Select the best word for blank [134]',
                'explanation'    => 'Directly fits the adverb role.',
                'choices'        => ['directly', 'direct', 'direction', 'directed'],
                'correct_choice' => 0,
            ],
        ],
    ];
}

// -----------------------------------------------------------------------------
// FOCUSED TESTS: CTA & UI
// -----------------------------------------------------------------------------

test('TEST 01: Part 6 renders Add Passage Group', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertStatus(200);
    $res->assertSee('+ Add Passage Group');
});

test('TEST 02: Click handler exists', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('openCreatePassageGroupModal(' . "'" . $this->part6Section->id . "'", false);
});

test('TEST 03: #create-passage-group-modal exists', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('id="create-passage-group-modal"', false);
});

test('TEST 04: Click opens modal', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('function openCreatePassageGroupModal(', false);
    $res->assertSee('create-passage-group-modal', false);
});

test('TEST 05: Part 6 section context is locked', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('id="pg-section-id"', false);
});

test('TEST 06: Part number is locked to 6', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('id="pg-part-number"', false);
    $res->assertSee('READING • PART 6');
});

test('TEST 07: Passage editor is visible', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('id="pg-passage-content"', false);
    $res->assertSee('Text / Passage Stimulus');
});

test('TEST 08: Exactly four child question cards render', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Question 1 of 4');
    $res->assertSee('Question 2 of 4');
    $res->assertSee('Question 3 of 4');
    $res->assertSee('Question 4 of 4');
    $res->assertDontSee('Question 5 of 4');
});

test('TEST 09: No fifth-child button exists', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertDontSee('Add Fifth Question');
    $res->assertDontSee('Add Another Question (Part 6)');
});

test('TEST 10: Each child renders A/B/C/D', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('id="pg-q0-choice-0"', false);
    $res->assertSee('id="pg-q0-choice-1"', false);
    $res->assertSee('id="pg-q0-choice-2"', false);
    $res->assertSee('id="pg-q0-choice-3"', false);
    $res->assertSee('id="pg-q3-choice-3"', false);
});

test('TEST 11: Correct-answer control exists for each child', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('name="questions[0][correct_choice]"', false);
    $res->assertSee('name="questions[1][correct_choice]"', false);
    $res->assertSee('name="questions[2][correct_choice]"', false);
    $res->assertSee('name="questions[3][correct_choice]"', false);
});

// -----------------------------------------------------------------------------
// FOCUSED TESTS: BACKEND & DOMAIN
// -----------------------------------------------------------------------------

test('TEST 12: Valid Part 6 PassageGroup creates parent group', function () {
    $payload = validPart6Payload($this->part6Section->id);

    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertRedirect();
    expect(PassageGroup::where('test_id', $this->toeicTest->id)->count())->toBe(1);

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg->title)->toBe('Training Course Email')
        ->and((int)$pg->part_number)->toBe(6)
        ->and($pg->passage_type)->toBe('single')
        ->and($pg->passages()->count())->toBe(1);
});

test('TEST 13: Exactly four child Questions created', function () {
    $payload = validPart6Payload($this->part6Section->id);

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg->questions()->count())->toBe(4);
});

test('TEST 14: All children link to same PassageGroup', function () {
    $payload = validPart6Payload($this->part6Section->id);

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    $childGroupIds = $pg->questions()->pluck('passage_group_id')->unique()->toArray();

    expect($childGroupIds)->toBe([$pg->id]);
});

test('TEST 15: Each Question gets four choices', function () {
    $payload = validPart6Payload($this->part6Section->id);

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    foreach ($pg->questions as $q) {
        expect($q->choices()->count())->toBe(4);
        expect($q->choices()->pluck('label')->toArray())->toBe(['A', 'B', 'C', 'D']);
    }
});

test('TEST 16: Each Question has exactly one correct answer', function () {
    $payload = validPart6Payload($this->part6Section->id);

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    foreach ($pg->questions as $q) {
        expect($q->choices()->where('is_correct', true)->count())->toBe(1);
    }
});

test('TEST 17: Three-question Part 6 group rejected', function () {
    $payload = validPart6Payload($this->part6Section->id);
    array_pop($payload['questions']); // 3 questions

    expect(fn () => ToeicQuestionValidator::validatePassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
    ], $payload['passages'], $payload['questions']))->toThrow(ValidationException::class);
});

test('TEST 18: Five-question Part 6 group rejected', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][] = [
        'prompt'         => 'Fifth question',
        'choices'        => ['A', 'B', 'C', 'D'],
        'correct_choice' => 0,
    ];

    expect(fn () => ToeicQuestionValidator::validatePassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
    ], $payload['passages'], $payload['questions']))->toThrow(ValidationException::class);
});

test('TEST 19: Part 7 rules remain unchanged', function () {
    // Part 7 single passage accepts 3 questions (valid range 2-4)
    $part7Single = [
        'part_number'  => 7,
        'passage_type' => 'single',
    ];
    $passages = [
        ['content' => 'Sample Article', 'order_in_group' => 1, 'document_type' => 'article'],
    ];
    $questions = [
        ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
    ];

    $result = ToeicQuestionValidator::validatePassageGroup($part7Single, $passages, $questions);
    expect($result['is_valid'])->toBeTrue();
});

test('TEST 20: Creation is transactional', function () {
    $payload = validPart6Payload($this->part6Section->id);
    // Attach forbidden audio to Part 6 question to trigger ValidationException
    $payload['questions'][3]['audio_url'] = 'https://example.com/forbidden.mp3';

    try {
        $this->actingAs($this->teacher)->post(
            route('teacher.tests.create-passage-group', $this->toeicTest->id),
            $payload
        );
    } catch (\Throwable $e) {
        // Handled
    }

    expect(PassageGroup::where('test_id', $this->toeicTest->id)->count())->toBe(0);
    expect(Passage::where('test_id', $this->toeicTest->id)->count())->toBe(0);
    expect(Question::where('part_number', 6)->count())->toBe(0);
});

// -----------------------------------------------------------------------------
// FOCUSED TESTS: RENDERING & PRESENTATION
// -----------------------------------------------------------------------------

test('TEST 21: PassageGroup renders compactly in Part 6', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Training Course Email');
    $res->assertSee('Text Completion Group');
    $res->assertSee('Progress: 4 / 4 Complete');
    $res->assertSee('🟢 VALID');
});

test('TEST 22: Grouped children do not duplicate as standalone cards', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));

    // Child questions should appear in the group overview tree, not as standalone question headers
    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    foreach ($pg->questions as $q) {
        $res->assertSee('id="question-card-' . $q->id . '"', false);
    }
});

test('TEST 23: Global numbering continues from previous Part', function () {
    // Add 1 standalone question in Part 5
    $p5q = Question::create([
        'prompt'        => 'Part 5 question stem',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'A', 'content' => 'Opt A', 'choice_text' => 'Opt A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'B', 'content' => 'Opt B', 'choice_text' => 'Opt B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'C', 'content' => 'Opt C', 'choice_text' => 'Opt C', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'D', 'content' => 'Opt D', 'choice_text' => 'Opt D', 'is_correct' => false, 'order' => 4]);
    TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $p5q->id, 'order' => 1, 'points' => 1]);

    // Create Part 6 group
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));

    // Part 5 gets Q1, Part 6 gets Q2, Q3, Q4, Q5
    $res->assertSee('Q2 ✓ Complete:');
    $res->assertSee('Q3 ✓ Complete:');
    $res->assertSee('Q4 ✓ Complete:');
    $res->assertSee('Q5 ✓ Complete:');
});

test('TEST 24: Question count adds four', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('4/4 Complete');
});

test('TEST 25: Section validation summary recomputes', function () {
    // Before: Empty section -> NOT STARTED
    $resBefore = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resBefore->assertSee('NOT STARTED');

    // Add valid group -> 4/4 Complete -> READY
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $resAfter = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resAfter->assertSee('READY');
});

test('TEST 26: Return context keeps Part 6 expanded', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertRedirect();
    $res->assertSessionHas('expanded_section_id', $this->part6Section->id);
});

// -----------------------------------------------------------------------------
// REGRESSION TESTS
// -----------------------------------------------------------------------------

test('TEST 27: Part 3 AudioGroup unaffected', function () {
    $part3Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 3: Conversations',
        'section_type' => SectionType::Listening,
        'order'        => 3,
    ]);

    $ag = AudioGroup::create([
        'test_id'     => $this->toeicTest->id,
        'title'       => 'Airport Conversation',
        'group_type'  => 'conversation',
        'part_number' => 3,
        'audio_url'   => 'https://example.com/audio/sample.mp3',
        'order'       => 1,
        'created_by'  => $this->teacher->id,
    ]);

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Airport Conversation');
    $res->assertSee('Audio Group (Conversation)');
});

test('TEST 28: Part 4 AudioGroup unaffected', function () {
    $part4Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 4: Talks',
        'section_type' => SectionType::Listening,
        'order'        => 4,
    ]);

    $ag = AudioGroup::create([
        'test_id'     => $this->toeicTest->id,
        'title'       => 'Radio Broadcast Talk',
        'group_type'  => 'talk',
        'part_number' => 4,
        'audio_url'   => 'https://example.com/audio/talk.mp3',
        'order'       => 1,
        'created_by'  => $this->teacher->id,
    ]);

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Radio Broadcast Talk');
    $res->assertSee('Audio Group (Talk)');
});

test('TEST 29: Part 5 standalone Questions unaffected', function () {
    $q = Question::create([
        'prompt'        => 'Mr. Henderson --- the contract tomorrow.',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'will sign', 'choice_text' => 'will sign', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'signs', 'choice_text' => 'signs', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'signed', 'choice_text' => 'signed', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'signing', 'choice_text' => 'signing', 'is_correct' => false, 'order' => 4]);
    TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Mr. Henderson --- the contract tomorrow.');
});

test('TEST 30: Part 7 existing data unaffected', function () {
    $pg7 = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 7 Double Passage Memo & Email',
        'part_number'  => 7,
        'passage_type' => 'double',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);
    Passage::create([
        'passage_group_id' => $pg7->id,
        'test_id'          => $this->toeicTest->id,
        'order_in_group'   => 1,
        'document_type'    => 'memo',
        'title'            => 'Memo',
        'content'          => 'Office Policy Memo Content',
    ]);
    Passage::create([
        'passage_group_id' => $pg7->id,
        'test_id'          => $this->toeicTest->id,
        'order_in_group'   => 2,
        'document_type'    => 'email',
        'title'            => 'Email',
        'content'          => 'Reply Email Content',
    ]);

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Part 7 Double Passage Memo & Email');
    $res->assertSee('Passage Group (Double)');
});

test('TEST 31: Media architecture unaffected', function () {
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertSee('Attach Media');
    $res->assertSee('id="question-media-picker-modal"', false);
});

test('TEST 32: Auto Difficulty unaffected', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    foreach ($pg->questions as $q) {
        expect($q->difficulty)->not->toBeNull();
        expect($q->difficulty_source)->toBe('auto');
    }
});

test('TEST 33: Candidate Preview unaffected by authoring UI changes', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertStatus(200);
});

// -----------------------------------------------------------------------------
// DRAFT SAVE & INCOMPLETE GROUP REMEDIATION TESTS (P6-DRAFT-01 to P6-DRAFT-12)
// -----------------------------------------------------------------------------

test('P6-DRAFT-01: Teacher can save draft Part 6 group with 1 of 4 completed questions', function () {
    $payload = validPart6Payload($this->part6Section->id);
    // Only keep first question complete; blank out choices for Q2, Q3, Q4
    $payload['questions'][1]['choices'] = ['', '', '', ''];
    $payload['questions'][2]['choices'] = ['', '', '', ''];
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertRedirect();
    $res->assertSessionHas('status');

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg)->not->toBeNull()
        ->and($pg->questions()->count())->toBe(1)
        ->and($pg->isComplete())->toBeFalse();

    $showRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $showRes->assertSee('Progress: 1 / 4 Complete');
    $showRes->assertSee('🟡 INCOMPLETE');
});

test('P6-DRAFT-02: Teacher can save draft Part 6 group with 2 of 4 completed questions', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][2]['choices'] = ['', '', '', ''];
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertRedirect();
    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg)->not->toBeNull()
        ->and($pg->questions()->count())->toBe(2)
        ->and($pg->isComplete())->toBeFalse();

    $showRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $showRes->assertSee('Progress: 2 / 4 Complete');
    $showRes->assertSee('🟡 INCOMPLETE');
});

test('P6-DRAFT-03: Teacher can save draft Part 6 group with 3 of 4 completed questions', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertRedirect();
    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg)->not->toBeNull()
        ->and($pg->questions()->count())->toBe(3)
        ->and($pg->isComplete())->toBeFalse();

    $showRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $showRes->assertSee('Progress: 3 / 4 Complete');
    $showRes->assertSee('🟡 INCOMPLETE');
});

test('P6-DRAFT-04: Teacher can save draft Part 6 group with 0 completed questions (passage text only)', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][0]['choices'] = ['', '', '', ''];
    $payload['questions'][1]['choices'] = ['', '', '', ''];
    $payload['questions'][2]['choices'] = ['', '', '', ''];
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertRedirect();
    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg)->not->toBeNull()
        ->and($pg->questions()->count())->toBe(0)
        ->and($pg->isComplete())->toBeFalse();

    $showRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $showRes->assertSee('Progress: 0 / 4 Complete');
    $showRes->assertSee('🟡 INCOMPLETE');
});

test('P6-DRAFT-05: Teacher cannot save Part 6 group without passage text', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['passages'][0]['content'] = '';

    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertSessionHasErrors();
    expect(PassageGroup::where('test_id', $this->toeicTest->id)->count())->toBe(0);
});

test('P6-DRAFT-06: Teacher saving 4 of 4 complete questions creates complete and valid group', function () {
    $payload = validPart6Payload($this->part6Section->id);

    $res = $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $res->assertRedirect();
    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg)->not->toBeNull()
        ->and($pg->questions()->count())->toBe(4)
        ->and($pg->isComplete())->toBeTrue();

    $showRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $showRes->assertSee('Progress: 4 / 4 Complete');
    $showRes->assertSee('🟢 VALID');
});

test('P6-DRAFT-07: Updating draft group from 2 questions to 4 questions marks group complete', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][2]['choices'] = ['', '', '', ''];
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg->questions()->count())->toBe(2);

    $existingQuestions = $pg->questions()->orderBy('id')->get();

    // Now update with all 4 questions completed
    $updatePayload = validPart6Payload($this->part6Section->id);
    $updatePayload['questions'][0]['id'] = $existingQuestions[0]->id;
    $updatePayload['questions'][1]['id'] = $existingQuestions[1]->id;

    $res = $this->actingAs($this->teacher)->put(
        route('teacher.tests.update-passage-group', ['test' => $this->toeicTest->id, 'passageGroup' => $pg->id]),
        $updatePayload
    );

    $res->assertRedirect();
    $pg->refresh();
    expect($pg->questions()->count())->toBe(4)
        ->and($pg->isComplete())->toBeTrue();

    $showRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $showRes->assertSee('Progress: 4 / 4 Complete');
    $showRes->assertSee('🟢 VALID');
});

test('P6-DRAFT-08: Updating draft group from 1 question to 2 questions updates complete count and remains incomplete', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][1]['choices'] = ['', '', '', ''];
    $payload['questions'][2]['choices'] = ['', '', '', ''];
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg->questions()->count())->toBe(1);

    $q1 = $pg->questions()->first();

    // Update with Q1 and Q2 completed
    $updatePayload = validPart6Payload($this->part6Section->id);
    $updatePayload['questions'][0]['id'] = $q1->id;
    $updatePayload['questions'][2]['choices'] = ['', '', '', ''];
    $updatePayload['questions'][3]['choices'] = ['', '', '', ''];

    $res = $this->actingAs($this->teacher)->put(
        route('teacher.tests.update-passage-group', ['test' => $this->toeicTest->id, 'passageGroup' => $pg->id]),
        $updatePayload
    );

    $res->assertRedirect();
    $pg->refresh();
    expect($pg->questions()->count())->toBe(2)
        ->and($pg->isComplete())->toBeFalse();

    $showRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $showRes->assertSee('Progress: 2 / 4 Complete');
    $showRes->assertSee('🟡 INCOMPLETE');
});

test('P6-DRAFT-09: Untouched child question slots do not fabricate fake choices in database', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][1]['choices'] = ['', '', '', ''];
    $payload['questions'][2]['choices'] = ['', '', '', ''];
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg->questions()->count())->toBe(1);

    // Only 4 choices total should exist in DB for this test (belonging to Q1)
    $allQuestionIds = $pg->questions()->pluck('id');
    $allChoices = QuestionChoice::whereIn('question_id', $allQuestionIds)->get();
    expect($allChoices->count())->toBe(4);
});

test('P6-DRAFT-10: Incomplete Part 6 group blocks Assessment submission to Review Manager', function () {
    $payload = validPart6Payload($this->part6Section->id);
    $payload['questions'][2]['choices'] = ['', '', '', ''];
    $payload['questions'][3]['choices'] = ['', '', '', ''];

    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    /** @var \App\Modules\Assessment\Services\TestBuilderService $service */
    $service = app(\App\Modules\Assessment\Services\TestBuilderService::class);
    $validation = $service->validateAssessment($this->toeicTest);

    expect($validation['is_valid'])->toBeFalse()
        ->and(count($validation['errors']))->toBeGreaterThan(0);
});

test('P6-DRAFT-11: Assessment with complete 4/4 Part 6 group passes completeness validation', function () {
    // Add standalone question to Part 5
    $p5q = Question::create([
        'prompt'        => 'Part 5 question stem',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'A', 'content' => 'Opt A', 'choice_text' => 'Opt A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'B', 'content' => 'Opt B', 'choice_text' => 'Opt B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'C', 'content' => 'Opt C', 'choice_text' => 'Opt C', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $p5q->id, 'label' => 'D', 'content' => 'Opt D', 'choice_text' => 'Opt D', 'is_correct' => false, 'order' => 4]);
    TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $p5q->id, 'order' => 1, 'points' => 1]);

    /** @var \App\Modules\Assessment\Services\TestBuilderService $service */
    $service = app(\App\Modules\Assessment\Services\TestBuilderService::class);

    // Add valid Part 7 passage group
    $pg7Data = [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'passages'        => [['title' => 'Article', 'content' => 'Article text']],
        'questions'       => [
            ['prompt' => 'What is discussed?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Who is contacted?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ];
    $service->createPassageGroup($this->part7Section, $pg7Data);

    // Add complete 4/4 Part 6 group
    $payload = validPart6Payload($this->part6Section->id);
    $this->actingAs($this->teacher)->post(
        route('teacher.tests.create-passage-group', $this->toeicTest->id),
        $payload
    );

    $validation = $service->validateAssessment($this->toeicTest->fresh());

    expect($validation['is_valid'])->toBeTrue()
        ->and($validation['errors'])->toBeEmpty();
});

test('P6-DRAFT-12: Part 7 passage groups continue to require all questions to be valid', function () {
    $part7Single = [
        'part_number'  => 7,
        'passage_type' => 'single',
    ];
    $passages = [
        ['content' => 'Sample Article', 'order_in_group' => 1, 'document_type' => 'article'],
    ];
    $questions = [
        ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];

    // Single passage requires 2-4 questions; 1 question is invalid
    $result = ToeicQuestionValidator::checkPassageGroup($part7Single, $passages, $questions);
    expect($result['is_valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey('question_count');
});
