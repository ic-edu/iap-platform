<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Services\QuestionDifficultyDetectionService;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');

    $this->test = Test::create([
        'title'      => 'TOEIC Official Practice Exam',
        'slug'       => 'toeic-official-practice-exam-' . uniqid(),
        'type'       => 'simulator',
        'test_type'  => 'toeic',
        'status'     => 'draft',
        'is_active'  => true,
        'created_by' => $this->teacher->id,
    ]);

    $this->part6Section = TestSection::create([
        'test_id'       => $this->test->id,
        'title'         => 'Part 6: Text Completion',
        'part_number'   => 6,
        'section_type'  => 'reading',
        'order'         => 6,
    ]);

    $this->part7Section = TestSection::create([
        'test_id'       => $this->test->id,
        'title'         => 'Part 7: Reading Comprehension',
        'part_number'   => 7,
        'section_type'  => 'reading',
        'order'         => 7,
    ]);

    $this->builderService = app(TestBuilderService::class);
});

/*
|--------------------------------------------------------------------------
| 1. Domain Validation Unit Tests
|--------------------------------------------------------------------------
*/

test('TEST 01: Part 7 Single Passage allows 2 questions', function () {
    $groupData = ['part_number' => 7, 'passage_type' => 'single'];
    $passages = [
        ['title' => 'Doc 1', 'document_type' => 'email', 'content' => 'Dear staff, meeting tomorrow.'],
    ];
    $questions = [
        [
            'prompt' => 'What is the email about?',
            'choices' => ['Staff meeting', 'Office party', 'Salary hike', 'Budget cuts'],
            'correct_choice' => 0,
        ],
        [
            'prompt' => 'When will the meeting take place?',
            'choices' => ['Tomorrow', 'Next week', 'Next month', 'Yesterday'],
            'correct_choice' => 0,
        ],
    ];

    $res = ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions);
    expect($res['is_valid'])->toBeTrue()
        ->and($res['errors'])->toBeEmpty();
});

test('TEST 02: Part 7 Single Passage allows 3 and 4 questions', function () {
    $groupData = ['part_number' => 7, 'passage_type' => 'single'];
    $passages = [
        ['title' => 'Article', 'document_type' => 'article', 'content' => 'Acme Corp reported record profits.'],
    ];

    // 3 questions
    $questions3 = [
        ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
    ];
    expect(ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions3)['is_valid'])->toBeTrue();

    // 4 questions
    $questions4 = array_merge($questions3, [
        ['prompt' => 'Q4', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
    ]);
    expect(ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions4)['is_valid'])->toBeTrue();
});

test('TEST 03: Part 7 Single Passage rejects fewer than 2 or more than 4 questions', function () {
    $groupData = ['part_number' => 7, 'passage_type' => 'single'];
    $passages = [
        ['title' => 'Doc 1', 'document_type' => 'memo', 'content' => 'Memo text'],
    ];

    // 1 question -> invalid
    $questions1 = [
        ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];
    $res1 = ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions1);
    expect($res1['is_valid'])->toBeFalse()
        ->and(implode(' ', $res1['errors']))->toContain('2 to 4 questions');

    // 5 questions -> invalid for single
    $questions5 = [
        ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q4', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q5', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];
    $res5 = ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions5);
    expect($res5['is_valid'])->toBeFalse()
        ->and(implode(' ', $res5['errors']))->toContain('2 to 4 questions');
});

test('TEST 04: Part 7 Double Passage requires exactly 2 documents and exactly 5 questions', function () {
    $groupData = ['part_number' => 7, 'passage_type' => 'double'];
    $passages = [
        ['title' => 'Doc 1', 'document_type' => 'advertisement', 'content' => 'Conference Schedule'],
        ['title' => 'Doc 2', 'document_type' => 'email', 'content' => 'Registration Confirmation'],
    ];
    $questions5 = [
        ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
        ['prompt' => 'Q4', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ['prompt' => 'Q5', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];

    // Valid double passage
    $res = ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions5);
    expect($res['is_valid'])->toBeTrue();

    // Invalid: 1 document
    $res1Doc = ToeicQuestionValidator::checkPassageGroup($groupData, [$passages[0]], $questions5);
    expect($res1Doc['is_valid'])->toBeFalse()
        ->and(implode(' ', $res1Doc['errors']))->toContain('exactly 2 passages');

    // Invalid: 4 questions
    $res4Qs = ToeicQuestionValidator::checkPassageGroup($groupData, $passages, array_slice($questions5, 0, 4));
    expect($res4Qs['is_valid'])->toBeFalse()
        ->and(implode(' ', $res4Qs['errors']))->toContain('exactly 5 questions');
});

test('TEST 05: Part 7 Triple Passage requires exactly 3 documents and exactly 5 questions', function () {
    $groupData = ['part_number' => 7, 'passage_type' => 'triple'];
    $passages = [
        ['title' => 'Doc 1', 'document_type' => 'advertisement', 'content' => 'Job listing'],
        ['title' => 'Doc 2', 'document_type' => 'email', 'content' => 'Application email'],
        ['title' => 'Doc 3', 'document_type' => 'letter', 'content' => 'Recommendation letter'],
    ];
    $questions5 = [
        ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q4', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q5', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];

    // Valid triple passage
    expect(ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions5)['is_valid'])->toBeTrue();

    // Invalid: 2 documents
    $res2Docs = ToeicQuestionValidator::checkPassageGroup($groupData, array_slice($passages, 0, 2), $questions5);
    expect($res2Docs['is_valid'])->toBeFalse()
        ->and(implode(' ', $res2Docs['errors']))->toContain('exactly 3 passages');
});

test('TEST 06: Part 7 requires child question prompt whereas Part 6 allows blank prompt', function () {
    // Part 7 with blank prompt -> invalid
    $p7Group = ['part_number' => 7, 'passage_type' => 'single'];
    $p7Passages = [['title' => 'Doc 1', 'content' => 'Passage text']];
    $p7Questions = [
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Valid Prompt', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];
    $resP7 = ToeicQuestionValidator::checkPassageGroup($p7Group, $p7Passages, $p7Questions);
    expect($resP7['is_valid'])->toBeFalse()
        ->and(implode(' ', $resP7['errors']))->toContain('Part 7 requires a question prompt');

    // Part 6 with blank prompt -> valid
    $p6Group = ['part_number' => 6, 'passage_type' => 'single'];
    $p6Passages = [['title' => 'Doc 1', 'content' => 'Passage with blank [131] and [132] and [133] and [134]']];
    $p6Questions = [
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];
    $resP6 = ToeicQuestionValidator::checkPassageGroup($p6Group, $p6Passages, $p6Questions);
    expect($resP6['is_valid'])->toBeTrue();
});

test('TEST 07: Part 7 supports image-only, text-only, and text-plus-image stimulus modes', function () {
    $groupData = ['part_number' => 7, 'passage_type' => 'single'];

    // Image-only document
    $imagePassages = [
        [
            'title'         => 'Schedule Table',
            'document_type' => 'schedule',
            'content'       => '',
            'image_url'     => 'https://example.com/schedule.png',
        ],
    ];
    $questions = [
        ['prompt' => 'What time does the train depart?', 'choices' => ['8 AM', '9 AM', '10 AM', '11 AM'], 'correct_choice' => 0],
        ['prompt' => 'Where is the destination?', 'choices' => ['Boston', 'New York', 'Chicago', 'Denver'], 'correct_choice' => 1],
    ];
    $resImg = ToeicQuestionValidator::checkPassageGroup($groupData, $imagePassages, $questions);
    expect($resImg['is_valid'])->toBeTrue();

    // Text + Image document
    $textImgPassages = [
        [
            'title'         => 'Advertisement & Map',
            'document_type' => 'advertisement',
            'content'       => 'Grand Opening Sale! Check out the location map below.',
            'image_url'     => 'https://example.com/map.jpg',
        ],
    ];
    $resTextImg = ToeicQuestionValidator::checkPassageGroup($groupData, $textImgPassages, $questions);
    expect($resTextImg['is_valid'])->toBeTrue();

    // Neither text nor image -> invalid
    $emptyPassages = [
        [
            'title'          => 'Empty Doc',
            'document_type'  => 'article',
            'content'        => '',
            'image_url'      => null,
            'media_asset_id' => null,
        ],
    ];
    $resEmpty = ToeicQuestionValidator::checkPassageGroup($groupData, $emptyPassages, $questions);
    expect($resEmpty['is_valid'])->toBeFalse()
        ->and(implode(' ', $resEmpty['errors']))->toContain('must contain text or an attached visual document');
});

/*
|--------------------------------------------------------------------------
| 2. Controller & Service Creation & Update Feature Tests
|--------------------------------------------------------------------------
*/

test('TEST 08: Teacher can create Part 7 Single passage group with 2 questions via HTTP', function () {
    $payload = [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'title'           => 'Customer Survey Results',
        'passages'        => [
            [
                'document_type' => 'article',
                'title'         => 'Survey Findings',
                'content'       => 'Overall satisfaction increased by 15% this quarter.',
            ],
        ],
        'questions'       => [
            [
                'prompt'         => 'What is the main subject of the report?',
                'correct_choice' => 0,
                'choices'        => ['Customer satisfaction', 'Quarterly tax return', 'Employee hiring', 'Product recall'],
                'explanation'    => 'The text states overall satisfaction increased.',
            ],
            [
                'prompt'         => 'By how much did satisfaction increase?',
                'correct_choice' => 2,
                'choices'        => ['5%', '10%', '15%', '20%'],
            ],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-passage-group', $this->test->id), $payload);

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));
    $response->assertSessionHas('status');

    $this->assertDatabaseHas('passage_groups', [
        'test_id'      => $this->test->id,
        'part_number'  => 7,
        'passage_type' => 'single',
        'title'        => 'Customer Survey Results',
    ]);

    $this->assertDatabaseHas('passages', [
        'document_type' => 'article',
        'title'         => 'Survey Findings',
        'content'       => 'Overall satisfaction increased by 15% this quarter.',
    ]);

    $this->assertDatabaseCount('questions', 2);
    $this->assertDatabaseCount('test_questions', 2);
});

test('TEST 09: Teacher can create Part 7 Double passage group with visual document stimulus', function () {
    $mediaAsset = MediaAsset::create([
        'uploaded_by'   => $this->teacher->id,
        'filename'      => 'schedule_chart.png',
        'original_name' => 'schedule_chart.png',
        'path'          => 'media/schedule_chart.png',
        'mime_type'     => 'image/png',
        'type'          => 'image',
        'size'          => 1024,
    ]);

    $payload = [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'double',
        'title'           => 'Training Workshop Schedule & Email',
        'passages'        => [
            [
                'document_type'  => 'schedule',
                'title'          => 'Workshop Schedule Table',
                'content'        => '',
                'media_asset_id' => $mediaAsset->id,
                'image_url'      => $mediaAsset->url,
            ],
            [
                'document_type'  => 'email',
                'title'          => 'Follow-up Email',
                'content'        => 'Please make sure to register for Room B sessions in advance.',
            ],
        ],
        'questions'       => [
            ['prompt' => 'Q1: What is the topic of the schedule?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Q2: When does Room B session begin?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => 'Q3: Why did the sender write the email?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => 'Q4: What are attendees advised to do?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
            ['prompt' => 'Q5: What is suggested about Room B?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-passage-group', $this->test->id), $payload);

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));

    $pg = PassageGroup::where('test_id', $this->test->id)->first();
    expect($pg)->not->toBeNull()
        ->and($pg->passage_type)->toBe('double')
        ->and($pg->passages)->toHaveCount(2)
        ->and($pg->questions)->toHaveCount(5);

    $firstDoc = $pg->passages->where('order_in_group', 1)->first();
    expect($firstDoc->document_type)->toBe('schedule')
        ->and($firstDoc->media_asset_id)->toBe($mediaAsset->id)
        ->and($firstDoc->getEffectiveImageUrl())->not->toBeEmpty();
});

test('TEST 10: Teacher can update passage group and clean up orphaned passages and questions', function () {
    // First create a Triple passage group with 3 docs and 5 questions
    $pg = $this->builderService->createPassageGroup($this->part7Section, [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'triple',
        'title'           => 'Initial Triple Set',
        'passages'        => [
            ['document_type' => 'notice', 'title' => 'Doc 1', 'content' => 'Text 1'],
            ['document_type' => 'email', 'title' => 'Doc 2', 'content' => 'Text 2'],
            ['document_type' => 'invoice', 'title' => 'Doc 3', 'content' => 'Text 3'],
        ],
        'questions'       => [
            ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => 'Q4', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
            ['prompt' => 'Q5', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ],
    ]);

    expect($pg->passages)->toHaveCount(3)
        ->and($pg->questions)->toHaveCount(5);

    // Update down to Single passage group with 2 questions
    $updatePayload = [
        'part_number'  => 7,
        'passage_type' => 'single',
        'title'        => 'Updated to Single Set',
        'passages'     => [
            ['document_type' => 'notice', 'title' => 'Doc 1 (Updated)', 'content' => 'Text 1 updated'],
        ],
        'questions'    => [
            ['prompt' => 'Q1 (Updated)', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 1],
            ['prompt' => 'Q2 (Updated)', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 2],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->put(route('teacher.tests.update-passage-group', ['test' => $this->test->id, 'passageGroup' => $pg->id]), $updatePayload);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    // Verify orphan passages (2 and 3) and questions (3, 4, 5) were removed
    $pg->refresh();
    expect($pg->passage_type)->toBe('single')
        ->and($pg->title)->toBe('Updated to Single Set')
        ->and($pg->passages)->toHaveCount(1)
        ->and($pg->questions)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| 3. Difficulty Detection & Candidate Delivery Views Tests
|--------------------------------------------------------------------------
*/

test('TEST 11: QuestionDifficultyDetectionService handles Part 7 with visual documents', function () {
    $data = [
        'part_number'    => 7,
        'passage_type'   => 'double',
        'image_url'      => 'https://example.com/invoice.png',
        'media_asset_id' => 'some-uuid',
        'prompt'         => 'What is suggested about the discount on the invoice?',
        'choices'        => [
            'It applies only to online orders.',
            'It expired last week.',
            'It requires a membership coupon.',
            'It is non-refundable.',
        ],
        'correct_choice' => 0,
    ];

    $detect = QuestionDifficultyDetectionService::detect($data);
    expect($detect)->toHaveKey('difficulty_level')
        ->and($detect)->toHaveKey('difficulty_score')
        ->and($detect['difficulty_status'])->toBe('final')
        ->and($detect['difficulty_factors']['reasons'])->toContain('Double passage cross-referencing requirement');
});

test('TEST 12: Assessment Preview renders visual document images and text cleanly', function () {
    $pg = $this->builderService->createPassageGroup($this->part7Section, [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'title'           => 'Advertisement Analysis',
        'passages'        => [
            [
                'document_type' => 'advertisement',
                'title'         => 'Summer Sale Flyer',
                'content'       => 'Special discounts available this weekend.',
                'image_url'     => 'https://example.com/flyer.png',
            ],
        ],
        'questions'       => [
            ['prompt' => 'What is featured on the flyer?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'When is the discount valid?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ],
    ]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.tests.preview', $this->test->id));

    $response->assertOk();
    $response->assertSee('https://example.com/flyer.png');
    $response->assertSee('Special discounts available this weekend.');
});

test('TEST 13: Part 6 remains strictly constrained to exactly 4 child questions', function () {
    $groupData = ['part_number' => 6, 'passage_type' => 'single'];
    $passages = [['title' => 'Doc 1', 'content' => 'Passage with blanks [131]-[134]']];

    // 3 questions -> invalid for Part 6
    $questions3 = [
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ];
    $res3 = ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions3);
    expect($res3['is_valid'])->toBeFalse()
        ->and(implode(' ', $res3['errors']))->toContain('Part 6 Text Completion group requires exactly 4 questions');

    // 4 questions -> valid
    $questions4 = array_merge($questions3, [
        ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
    ]);
    $res4 = ToeicQuestionValidator::checkPassageGroup($groupData, $passages, $questions4);
    expect($res4['is_valid'])->toBeTrue();
});

test('TEST 14: Teacher can create Part 7 Single with 3 questions and 4 questions via HTTP', function () {
    // 3 questions
    $payload3 = [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'title'           => '3 Question Single Set',
        'passages'        => [
            ['document_type' => 'memo', 'title' => 'Office Memo', 'content' => 'Please note the new security policy.'],
        ],
        'questions'       => [
            ['prompt' => 'What is the memo about?', 'choices' => ['Security', 'Budget', 'Hiring', 'Lunch'], 'correct_choice' => 0],
            ['prompt' => 'Who should follow the policy?', 'choices' => ['All staff', 'Managers only', 'Interns', 'Visitors'], 'correct_choice' => 0],
            ['prompt' => 'When does it take effect?', 'choices' => ['Immediately', 'Next month', 'Next year', 'Never'], 'correct_choice' => 0],
        ],
    ];

    $res3 = $this->actingAs($this->teacher)->post(route('teacher.tests.create-passage-group', $this->test->id), $payload3);
    $res3->assertRedirect(route('teacher.tests.show', $this->test->id));
    $this->assertDatabaseHas('passage_groups', ['title' => '3 Question Single Set']);

    // 4 questions
    $payload4 = [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'single',
        'title'           => '4 Question Single Set',
        'passages'        => [
            ['document_type' => 'email', 'title' => 'Client Inquiry', 'content' => 'Inquiry regarding product availability.'],
        ],
        'questions'       => [
            ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => 'Q4', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ];

    $res4 = $this->actingAs($this->teacher)->post(route('teacher.tests.create-passage-group', $this->test->id), $payload4);
    $res4->assertRedirect(route('teacher.tests.show', $this->test->id));
    $this->assertDatabaseHas('passage_groups', ['title' => '4 Question Single Set']);
});

test('TEST 15: Teacher can create Part 7 Triple passage group via HTTP', function () {
    $payload = [
        'test_section_id' => $this->part7Section->id,
        'part_number'     => 7,
        'passage_type'    => 'triple',
        'title'           => 'Conference Registration & Logistics Set',
        'passages'        => [
            ['document_type' => 'advertisement', 'title' => 'Conference Brochure', 'content' => 'Annual Tech Expo 2026.'],
            ['document_type' => 'schedule', 'title' => 'Event Timetable', 'content' => 'Keynote at 9 AM, Workshop at 11 AM.'],
            ['document_type' => 'email', 'title' => 'Confirmation Email', 'content' => 'Thank you for registering.'],
        ],
        'questions'       => [
            ['prompt' => 'What is the main event?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => 'When is the keynote?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => 'Who sent the email?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => 'What is required for entry?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
            ['prompt' => 'What discount is mentioned?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-passage-group', $this->test->id), $payload);

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));

    $pg = PassageGroup::where('title', 'Conference Registration & Logistics Set')->first();
    expect($pg)->not->toBeNull()
        ->and($pg->passage_type)->toBe('triple')
        ->and($pg->passages)->toHaveCount(3)
        ->and($pg->questions)->toHaveCount(5);
});

test('TEST 16: Supported document types are properly stored and validated', function () {
    $types = ['email', 'memo', 'notice', 'advertisement', 'article', 'letter', 'chat', 'schedule', 'form', 'invoice', 'webpage', 'message', 'other'];

    foreach ($types as $type) {
        $res = ToeicQuestionValidator::checkPassageGroup(
            ['part_number' => 7, 'passage_type' => 'single'],
            [['document_type' => $type, 'title' => "Doc {$type}", 'content' => 'Sample content']],
            [
                ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ]
        );
        expect($res['is_valid'])->toBeTrue();
    }
});
