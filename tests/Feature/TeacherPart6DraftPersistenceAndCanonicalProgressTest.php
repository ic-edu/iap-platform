<?php

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\DeliveryUnitBuilder;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Enums\SectionType;
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

    $this->teacher = User::factory()->create();
    $this->teacher->assignRole('teacher');

    // Create TOEIC assessment test
    $this->toeicTest = Test::create([
        'title'            => 'TOEIC Practice Test 2026',
        'slug'             => 'toeic-practice-test-' . uniqid(),
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

    // Create Part 6 Section
    $this->part6Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Part 6: Text Completion',
        'section_type' => SectionType::Reading,
        'order'        => 6,
    ]);
});

function makePart6PassagePayload(string $sectionId, array $questions): array
{
    return [
        'test_section_id' => $sectionId,
        'part_number'     => 6,
        'passage_type'    => 'single',
        'title'           => 'Company Memo on Remote Work',
        'passages'        => [
            [
                'title'         => 'Document 1',
                'document_type' => 'article',
                'order_in_group'=> 1,
                'content'       => "To: All Employees\nFrom: Executive Team\nSubject: Guidelines\n\nPlease find the updated remote work guidelines [131] below. Employees must submit requests [132] their supervisor. [133]. All equipment must be returned [134].",
            ],
        ],
        'questions'       => $questions,
    ];
}

test('P6-PERSIST-01: Partial child question survives server Save Draft and is restored in passage group json', function () {
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Select word for [131]',
            'explanation'    => 'Detailed explanation 1',
            'choices'        => ['provided', 'provides', 'providing', 'provision'],
            'correct_choice' => 0,
        ],
        [
            'prompt'         => 'Select preposition for [132]',
            'explanation'    => 'Partial note',
            'choices'        => ['to', 'with', '', ''],
            'correct_choice' => null,
        ],
        [
            'prompt'         => '',
            'explanation'    => '',
            'choices'        => ['', '', '', ''],
            'correct_choice' => null,
        ],
        [
            'prompt'         => '',
            'explanation'    => '',
            'choices'        => ['', '', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $res = $this->actingAs($this->teacher)->post(route('teacher.tests.create-passage-group', $this->toeicTest->id), $payload);
    $res->assertRedirect();

    $pg = PassageGroup::where('test_id', $this->toeicTest->id)->first();
    expect($pg)->not->toBeNull();

    // Check that exactly 1 Question DB record was created (slot 0)
    expect($pg->questions()->count())->toBe(1);

    // Check draft_slots metadata
    $draftSlots = $pg->context_metadata['draft_slots'] ?? [];
    expect($draftSlots)->toHaveCount(4);
    expect($draftSlots[0]['state'])->toBe('complete');
    expect($draftSlots[0]['question_id'])->toBe($pg->questions()->first()->id);

    expect($draftSlots[1]['state'])->toBe('partial');
    expect($draftSlots[1]['prompt'])->toBe('Select preposition for [132]');
    expect($draftSlots[1]['choices'][0])->toBe('to');
    expect($draftSlots[1]['choices'][1])->toBe('with');

    expect($draftSlots[2]['state'])->toBe('untouched');
    expect($draftSlots[3]['state'])->toBe('untouched');

    // Verify GET response renders the draft correctly
    $viewRes = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $viewRes->assertStatus(200);
    $viewRes->assertSee('Select preposition for [132]');
});

test('P6-PERSIST-02: Persistence survives session clear without client sessionStorage', function () {
    $testBuilder = app(TestBuilderService::class);
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Prompt for [131]',
            'explanation'    => null,
            'choices'        => ['optA', 'optB', 'optC', 'optD'],
            'correct_choice' => 2,
        ],
        [
            'prompt'         => 'Partial prompt for [132]',
            'explanation'    => null,
            'choices'        => ['choice 1', 'choice 2', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $pg = $testBuilder->createPassageGroup($this->part6Section, $payload);

    // Act as other teacher/browser without local storage
    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertStatus(200);
    $res->assertSee('Partial prompt for [132]');
    $res->assertSee('choice 1');
    $res->assertSee('choice 2');
});

test('P6-PERSIST-03: Untouched slot remains empty without fake database records', function () {
    $testBuilder = app(TestBuilderService::class);
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Complete Q1',
            'explanation'    => null,
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 1,
        ],
        [
            'prompt'         => '',
            'explanation'    => '',
            'choices'        => ['', '', '', ''],
            'correct_choice' => null,
        ],
        [
            'prompt'         => '',
            'explanation'    => '',
            'choices'        => ['', '', '', ''],
            'correct_choice' => null,
        ],
        [
            'prompt'         => '',
            'explanation'    => '',
            'choices'        => ['', '', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $pg = $testBuilder->createPassageGroup($this->part6Section, $payload);

    // Only 1 DB record for questions
    expect(Question::where('passage_group_id', $pg->id)->count())->toBe(1);
    expect(QuestionChoice::whereHas('question', fn($q) => $q->where('passage_group_id', $pg->id))->count())->toBe(4);
});

test('P6-PERSIST-04: Transitioning a partial slot to complete updates in place', function () {
    $testBuilder = app(TestBuilderService::class);
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Q1',
            'explanation'    => null,
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ],
        [
            'prompt'         => 'Draft Q2',
            'explanation'    => null,
            'choices'        => ['W', 'X', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $pg = $testBuilder->createPassageGroup($this->part6Section, $payload);
    expect($pg->questions()->count())->toBe(1);

    // Now update Q2 to be complete
    $updatePayload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Q1 Updated',
            'explanation'    => null,
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ],
        [
            'prompt'         => 'Draft Q2 Completed',
            'explanation'    => 'Now complete',
            'choices'        => ['W', 'X', 'Y', 'Z'],
            'correct_choice' => 3,
        ],
        [
            'prompt'         => '',
            'explanation'    => '',
            'choices'        => ['', '', '', ''],
            'correct_choice' => null,
        ],
        [
            'prompt'         => '',
            'explanation'    => '',
            'choices'        => ['', '', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $res = $this->actingAs($this->teacher)->put(
        route('teacher.tests.update-passage-group', ['test' => $this->toeicTest->id, 'passageGroup' => $pg->id]),
        $updatePayload
    );
    $res->assertRedirect();

    $pg->refresh();
    expect($pg->questions()->count())->toBe(2);
    $slots = $pg->context_metadata['draft_slots'];
    expect($slots[0]['state'])->toBe('complete');
    expect($slots[1]['state'])->toBe('complete');
});

test('P6-PERSIST-05: Repeated Save Draft calls are idempotent', function () {
    $testBuilder = app(TestBuilderService::class);
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Q1',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ],
        [
            'prompt'         => 'Partial Q2',
            'choices'        => ['P1', '', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $pg = $testBuilder->createPassageGroup($this->part6Section, $payload);

    // Call update twice with same payload
    $testBuilder->updatePassageGroup($pg, $payload, $this->part6Section);
    $testBuilder->updatePassageGroup($pg, $payload, $this->part6Section);

    $pg->refresh();
    expect($pg->questions()->count())->toBe(1);
    expect($pg->context_metadata['draft_slots'][1]['prompt'])->toBe('Partial Q2');
});

test('P6-PERSIST-06: Multiple partial child questions maintain stable slot positions', function () {
    $testBuilder = app(TestBuilderService::class);
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Q1 Complete',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ],
        [
            'prompt'         => 'Q2 Partial',
            'choices'        => ['P2', '', '', ''],
            'correct_choice' => null,
        ],
        [
            'prompt'         => 'Q3 Complete',
            'choices'        => ['A3', 'B3', 'C3', 'D3'],
            'correct_choice' => 2,
        ],
        [
            'prompt'         => 'Q4 Partial',
            'choices'        => ['P4_A', 'P4_B', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $pg = $testBuilder->createPassageGroup($this->part6Section, $payload);
    $slots = $pg->context_metadata['draft_slots'];

    expect($slots[0]['state'])->toBe('complete');
    expect($slots[1]['state'])->toBe('partial');
    expect($slots[1]['prompt'])->toBe('Q2 Partial');
    expect($slots[2]['state'])->toBe('complete');
    expect($slots[3]['state'])->toBe('partial');
    expect($slots[3]['prompt'])->toBe('Q4 Partial');
});

test('P6-PERSIST-07: Completeness calculation accurately reflects 2/4 complete', function () {
    $testBuilder = app(TestBuilderService::class);
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Q1',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ],
        [
            'prompt'         => 'Q2',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 1,
        ],
        [
            'prompt'         => 'Q3 Partial',
            'choices'        => ['A', '', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $pg = $testBuilder->createPassageGroup($this->part6Section, $payload);
    $check = ToeicQuestionValidator::checkPassageGroup($pg);

    expect($check['is_valid'])->toBeFalse();
    expect($pg->questions()->count())->toBe(2);
});

test('P6-PERSIST-08: Partial child questions are excluded from Candidate Delivery (DeliveryUnitBuilder)', function () {
    $testBuilder = app(TestBuilderService::class);
    $payload = makePart6PassagePayload($this->part6Section->id, [
        [
            'prompt'         => 'Candidate visible Q1',
            'choices'        => ['A', 'B', 'C', 'D'],
            'correct_choice' => 0,
        ],
        [
            'prompt'         => 'Hidden partial Q2',
            'choices'        => ['Draft choice', '', '', ''],
            'correct_choice' => null,
        ],
    ]);

    $pg = $testBuilder->createPassageGroup($this->part6Section, $payload);

    $delivery = DeliveryUnitBuilder::build($this->toeicTest);
    expect($delivery['totalQuestionsCount'])->toBe(1);
    expect($delivery['questions']->first()->prompt)->toBe('Candidate visible Q1');
});

test('P6-PROGRESS-01: Section progress card renders against canonical 16 target (e.g. 10/16 Complete)', function () {
    $testBuilder = app(TestBuilderService::class);

    // Group 1: 4 complete questions
    $g1 = $testBuilder->createPassageGroup($this->part6Section, makePart6PassagePayload($this->part6Section->id, [
        ['prompt' => 'G1 Q1', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G1 Q2', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G1 Q3', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G1 Q4', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
    ]));

    // Group 2: 4 complete questions
    $g2 = $testBuilder->createPassageGroup($this->part6Section, makePart6PassagePayload($this->part6Section->id, [
        ['prompt' => 'G2 Q1', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G2 Q2', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G2 Q3', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G2 Q4', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
    ]));

    // Group 3: 2 complete questions + 2 partial questions (Total complete = 10)
    $g3 = $testBuilder->createPassageGroup($this->part6Section, makePart6PassagePayload($this->part6Section->id, [
        ['prompt' => 'G3 Q1', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G3 Q2', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G3 Q3 Partial', 'choices' => ['A','B','',''], 'correct_choice' => null],
        ['prompt' => 'G3 Q4 Partial', 'choices' => ['A','','',''], 'correct_choice' => null],
    ]));

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertStatus(200);

    // Must see 10/16 Complete
    $res->assertSee('10/16 Complete');
    $res->assertDontSee('10/10 Complete');
    $res->assertSee('Questions 131–146');
    $res->assertSee('• 10 of 16 questions');
});

test('P6-PROGRESS-02: Partial questions do not inflate section progress numerator', function () {
    $testBuilder = app(TestBuilderService::class);

    $testBuilder->createPassageGroup($this->part6Section, makePart6PassagePayload($this->part6Section->id, [
        ['prompt' => 'Q1', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'Q2 Partial', 'choices' => ['A','','',''], 'correct_choice' => null],
        ['prompt' => 'Q3 Partial', 'choices' => ['A','','',''], 'correct_choice' => null],
        ['prompt' => 'Q4 Partial', 'choices' => ['A','','',''], 'correct_choice' => null],
    ]));

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertStatus(200);
    $res->assertSee('1/16 Complete');
});

test('P6-PROGRESS-03: 16/16 complete questions marks Part 6 section READY', function () {
    $testBuilder = app(TestBuilderService::class);

    for ($g = 1; $g <= 4; $g++) {
        $testBuilder->createPassageGroup($this->part6Section, makePart6PassagePayload($this->part6Section->id, [
            ['prompt' => "G{$g} Q1", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
            ['prompt' => "G{$g} Q2", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
            ['prompt' => "G{$g} Q3", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
            ['prompt' => "G{$g} Q4", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ]));
    }

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertStatus(200);
    $res->assertSee('16/16 Complete');
    $res->assertSee('• 16 of 16 questions');
});

test('P6-PROGRESS-04: Incomplete Part 6 (< 16 complete questions) blocks assessment submission', function () {
    $testBuilder = app(TestBuilderService::class);

    // 3 complete groups (12 questions) + 1 group with 3 complete + 1 partial (total 15 complete)
    for ($g = 1; $g <= 3; $g++) {
        $testBuilder->createPassageGroup($this->part6Section, makePart6PassagePayload($this->part6Section->id, [
            ['prompt' => "G{$g} Q1", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
            ['prompt' => "G{$g} Q2", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
            ['prompt' => "G{$g} Q3", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
            ['prompt' => "G{$g} Q4", 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ]));
    }

    $testBuilder->createPassageGroup($this->part6Section, makePart6PassagePayload($this->part6Section->id, [
        ['prompt' => 'G4 Q1', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G4 Q2', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G4 Q3', 'choices' => ['A','B','C','D'], 'correct_choice' => 0],
        ['prompt' => 'G4 Q4 Partial', 'choices' => ['A','B','',''], 'correct_choice' => null],
    ]));

    $res = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $res->assertStatus(200);
    $res->assertSee('Submission Disabled');
    $res->assertSee('15/16 Complete');
});

test('P6-PROGRESS-05: Blueprint helper returns canonical target 16 and range 131-146 for Part 6', function () {
    expect(ToeicQuestionValidator::getPartTargetQuestionCount(6))->toBe(16);
    expect(ToeicQuestionValidator::getPartQuestionRange(6))->toBe(['start' => 131, 'end' => 146]);
    expect(ToeicQuestionValidator::getPartBlueprint(6)['name'])->toBe('Text Completion');
});
