<?php

use App\Models\MediaAsset;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
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
    $this->candidate = User::factory()->create(['status' => 'active']);
    $this->candidate->assignRole('student');

    $this->bank = QuestionBank::create([
        'title'      => 'TOEIC Reading Master Bank',
        'slug'       => 'toeic-reading-master-bank-' . uniqid(),
        'test_type'  => TestType::Toeic,
        'category'   => 'English',
        'status'     => 'draft',
        'created_by' => $this->teacher->id,
    ]);

    $this->test = Test::create([
        'title'       => 'TOEIC Official Reading Practice',
        'slug'        => 'toeic-official-reading-practice-' . uniqid(),
        'type'        => 'simulator',
        'test_type'   => 'toeic',
        'status'      => 'draft',
        'is_active'   => true,
        'created_by'  => $this->teacher->id,
    ]);

    $this->section = TestSection::create([
        'test_id'     => $this->test->id,
        'title'       => 'Reading Section',
        'order'       => 1,
        'description' => 'Directions for Reading Section',
    ]);
});

test('1. Part 6: 1 passage + 4 questions (valid)', function () {
    $passages = [
        [
            'order_in_group' => 1,
            'document_type'  => 'memo',
            'title'          => 'Office Memorandum',
            'content'        => 'To all staff: Please review the updated schedule.',
        ],
    ];

    $questions = [];
    for ($i = 1; $i <= 4; $i++) {
        $questions[] = [
            'prompt'         => "Text completion question #{$i}",
            'difficulty'     => 'medium',
            'correct_choice' => 0,
            'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
        ];
    }

    $check = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
    ], $passages, $questions);

    expect($check['is_valid'])->toBeTrue();
    expect($check['errors'])->toBeEmpty();
    expect($check['part_number'])->toBe(6);
    expect($check['passage_type'])->toBe('single');
    expect($check['passage_count'])->toBe(1);
    expect($check['question_count'])->toBe(4);
});

test('2. Part 6: invalid group (e.g. 1/2/3/5 questions, or missing passage) rejected', function () {
    // 3 questions instead of 4
    $passages = [
        [
            'order_in_group' => 1,
            'document_type'  => 'email',
            'title'          => 'Email to Vendor',
            'content'        => 'Dear Vendor, thank you for your prompt reply.',
        ],
    ];

    $threeQuestions = [];
    for ($i = 1; $i <= 3; $i++) {
        $threeQuestions[] = [
            'prompt'         => "Question #{$i}",
            'difficulty'     => 'easy',
            'correct_choice' => 0,
            'choices'        => ['A', 'B', 'C', 'D'],
        ];
    }

    $check = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
    ], $passages, $threeQuestions);

    expect($check['is_valid'])->toBeFalse();
    expect($check['errors'])->toHaveKey('question_count');

    // Missing passage
    $fourQuestions = $threeQuestions;
    $fourQuestions[] = [
        'prompt'         => 'Question #4',
        'difficulty'     => 'medium',
        'correct_choice' => 1,
        'choices'        => ['A', 'B', 'C', 'D'],
    ];

    $checkNoPassage = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
    ], [], $fourQuestions);

    expect($checkNoPassage['is_valid'])->toBeFalse();
    expect($checkNoPassage['errors'])->toHaveKey('passage_count');
});

test('3. Part 7 Single: valid group (1 passage, 2–4 questions)', function () {
    $passage = [
        [
            'order_in_group' => 1,
            'document_type'  => 'advertisement',
            'title'          => 'Job Advertisement',
            'content'        => 'Seeking experienced Senior Software Engineer in Tokyo.',
        ],
    ];

    foreach ([2, 3, 4] as $qCount) {
        $questions = [];
        for ($i = 1; $i <= $qCount; $i++) {
            $questions[] = [
                'prompt'         => "Part 7 Single question #{$i}",
                'difficulty'     => 'medium',
                'correct_choice' => 0,
                'choices'        => ['Choice A', 'Choice B', 'Choice C', 'Choice D'],
            ];
        }

        $check = ToeicQuestionValidator::checkPassageGroup([
            'part_number'  => 7,
            'passage_type' => 'single',
        ], $passage, $questions);

        expect($check['is_valid'])->toBeTrue("Part 7 Single with {$qCount} questions should be valid.");
    }

    // 1 question on single passage should be invalid
    $oneQuestion = [
        [
            'prompt'         => 'Part 7 Single question 1',
            'difficulty'     => 'medium',
            'correct_choice' => 0,
            'choices'        => ['A', 'B', 'C', 'D'],
        ],
    ];
    $check1 = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'single',
    ], $passage, $oneQuestion);
    expect($check1['is_valid'])->toBeFalse();
    expect($check1['errors'])->toHaveKey('question_count');
});

test('4. Part 7 Double: valid group (2 passages, 5 questions)', function () {
    $passages = [
        [
            'order_in_group' => 1,
            'document_type'  => 'email',
            'title'          => 'Inquiry Email',
            'content'        => 'Inquiry regarding product availability.',
        ],
        [
            'order_in_group' => 2,
            'document_type'  => 'letter',
            'title'          => 'Response Letter',
            'content'        => 'Thank you for your letter of January 15.',
        ],
    ];

    $questions = [];
    for ($i = 1; $i <= 5; $i++) {
        $questions[] = [
            'prompt'         => "Double passage question #{$i}",
            'difficulty'     => 'medium',
            'correct_choice' => 2,
            'choices'        => ['Option 1', 'Option 2', 'Option 3', 'Option 4'],
        ];
    }

    $check = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'double',
    ], $passages, $questions);

    expect($check['is_valid'])->toBeTrue();
    expect($check['errors'])->toBeEmpty();
    expect($check['passage_count'])->toBe(2);
    expect($check['question_count'])->toBe(5);
});

test('5. Part 7 Triple: valid group (3 passages, 5 questions)', function () {
    $passages = [
        [
            'order_in_group' => 1,
            'document_type'  => 'schedule',
            'title'          => 'Conference Schedule',
            'content'        => '9:00 AM Keynote Speech\n10:30 AM Workshop',
        ],
        [
            'order_in_group' => 2,
            'document_type'  => 'email',
            'title'          => 'Registration Confirmation',
            'content'        => 'Your conference badge has been reserved.',
        ],
        [
            'order_in_group' => 3,
            'document_type'  => 'notice',
            'title'          => 'Venue Map Notice',
            'content'        => 'Parking is available at North Garage.',
        ],
    ];

    $questions = [];
    for ($i = 1; $i <= 5; $i++) {
        $questions[] = [
            'prompt'         => "Triple passage question #{$i}",
            'difficulty'     => 'hard',
            'correct_choice' => 1,
            'choices'        => ['Ans A', 'Ans B', 'Ans C', 'Ans D'],
        ];
    }

    $check = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'triple',
    ], $passages, $questions);

    expect($check['is_valid'])->toBeTrue();
    expect($check['errors'])->toBeEmpty();
    expect($check['passage_count'])->toBe(3);
    expect($check['question_count'])->toBe(5);
});

test('6. Invalid double/triple passage counts rejected', function () {
    // Double with 1 passage (invalid)
    $passages1 = [
        ['order_in_group' => 1, 'document_type' => 'article', 'title' => 'Doc 1', 'content' => 'Content 1'],
    ];
    $q5 = [];
    for ($i = 1; $i <= 5; $i++) {
        $q5[] = ['prompt' => "Q{$i}", 'difficulty' => 'easy', 'correct_choice' => 0, 'choices' => ['A', 'B', 'C', 'D']];
    }
    $checkDouble1 = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'double',
    ], $passages1, $q5);
    expect($checkDouble1['is_valid'])->toBeFalse();
    expect($checkDouble1['errors'])->toHaveKey('passage_count');

    // Double with 4 questions (invalid)
    $passages2 = [
        ['order_in_group' => 1, 'document_type' => 'article', 'title' => 'Doc 1', 'content' => 'Content 1'],
        ['order_in_group' => 2, 'document_type' => 'memo', 'title' => 'Doc 2', 'content' => 'Content 2'],
    ];
    $q4 = array_slice($q5, 0, 4);
    $checkDoubleQ4 = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'double',
    ], $passages2, $q4);
    expect($checkDoubleQ4['is_valid'])->toBeFalse();
    expect($checkDoubleQ4['errors'])->toHaveKey('question_count');

    // Triple with 2 passages (invalid)
    $checkTriple2 = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 7,
        'passage_type' => 'triple',
    ], $passages2, $q5);
    expect($checkTriple2['is_valid'])->toBeFalse();
    expect($checkTriple2['errors'])->toHaveKey('passage_count');
});

test('7. Passage order persists (1, 2, 3)', function () {
    $group = PassageGroup::create([
        'question_bank_id' => $this->bank->id,
        'title'            => 'Part 7 Triple Test Group',
        'part_number'      => 7,
        'passage_type'     => 'triple',
        'order'            => 1,
        'created_by'       => $this->teacher->id,
    ]);

    $p2 = Passage::create([
        'passage_group_id' => $group->id,
        'question_bank_id' => $this->bank->id,
        'order_in_group'   => 2,
        'document_type'    => 'memo',
        'title'            => 'Second Document',
        'content'          => 'Memo body',
    ]);
    $p1 = Passage::create([
        'passage_group_id' => $group->id,
        'question_bank_id' => $this->bank->id,
        'order_in_group'   => 1,
        'document_type'    => 'email',
        'title'            => 'First Document',
        'content'          => 'Email body',
    ]);
    $p3 = Passage::create([
        'passage_group_id' => $group->id,
        'question_bank_id' => $this->bank->id,
        'order_in_group'   => 3,
        'document_type'    => 'notice',
        'title'            => 'Third Document',
        'content'          => 'Notice body',
    ]);

    $retrievedPassages = $group->passages;
    expect($retrievedPassages)->toHaveCount(3);
    expect($retrievedPassages[0]->order_in_group)->toBe(1);
    expect($retrievedPassages[0]->title)->toBe('First Document');
    expect($retrievedPassages[1]->order_in_group)->toBe(2);
    expect($retrievedPassages[1]->title)->toBe('Second Document');
    expect($retrievedPassages[2]->order_in_group)->toBe(3);
    expect($retrievedPassages[2]->title)->toBe('Third Document');
});

test('8. Questions remain linked to passage_group_id', function () {
    $group = PassageGroup::create([
        'question_bank_id' => $this->bank->id,
        'title'            => 'Part 6 Text Completion',
        'part_number'      => 6,
        'passage_type'     => 'single',
        'order'            => 1,
        'created_by'       => $this->teacher->id,
    ]);

    $passage = Passage::create([
        'passage_group_id' => $group->id,
        'question_bank_id' => $this->bank->id,
        'order_in_group'   => 1,
        'document_type'    => 'article',
        'title'            => 'Text Completion Article',
        'content'          => 'Article with blanks [1] [2] [3] [4].',
    ]);

    for ($i = 1; $i <= 4; $i++) {
        Question::create([
            'question_bank_id' => $this->bank->id,
            'passage_group_id' => $group->id,
            'prompt'           => "Blank [{$i}] completion",
            'section'          => SectionType::Reading,
            'part_number'      => 6,
            'question_type'    => QuestionType::MultipleChoice,
            'difficulty'       => DifficultyLevel::Medium,
            'points'           => 1,
        ]);
    }

    expect($group->questions)->toHaveCount(4);
    foreach ($group->questions as $q) {
        expect($q->passage_group_id)->toBe($group->id);
        expect($q->passageGroup->id)->toBe($group->id);
        $effectivePassages = $q->getEffectivePassages();
        expect($effectivePassages)->toHaveCount(1);
        expect($effectivePassages->first()->title)->toBe('Text Completion Article');
    }
});

test('9. Question Bank passage group authoring & storage via controller', function () {
    $passages = [
        [
            'document_type'  => 'advertisement',
            'title'          => 'Product Flyer',
            'content'        => 'Special discount on enterprise software subscriptions.',
            'order_in_group' => 1,
        ],
        [
            'document_type'  => 'email',
            'title'          => 'Customer Inquiry',
            'content'        => 'Could you clarify the volume discount terms?',
            'order_in_group' => 2,
        ],
    ];

    $questions = [];
    for ($i = 1; $i <= 5; $i++) {
        $questions[] = [
            'prompt'         => "Double Passage Q{$i}",
            'difficulty'     => 'medium',
            'explanation'    => "Explanation for Q{$i}",
            'correct_choice' => 1,
            'choices'        => [
                ['content' => 'Choice Alpha'],
                ['content' => 'Choice Beta (Correct)'],
                ['content' => 'Choice Gamma'],
                ['content' => 'Choice Delta'],
            ],
        ];
    }

    $response = $this->actingAs($this->teacher)
        ->post(route('admin.question-banks.store-passage-group', $this->bank), [
            'title'        => 'Part 7 Double Passage Group (Flyer + Email)',
            'part_number'  => 7,
            'passage_type' => 'double',
            'passages'     => $passages,
            'questions'    => $questions,
        ]);

    $response->assertRedirect(route('admin.question-banks.show', $this->bank));
    $this->assertDatabaseHas('passage_groups', [
        'question_bank_id' => $this->bank->id,
        'part_number'      => 7,
        'passage_type'     => 'double',
    ]);
    $this->assertDatabaseCount('passages', 2);
    $this->assertDatabaseCount('questions', 5);
    $this->assertDatabaseCount('question_choices', 20);
});

test('10. Assessment Builder passage group authoring & storage', function () {
    $passages = [
        [
            'document_type'  => 'article',
            'title'          => 'Business Report',
            'content'        => 'Quarterly revenue expanded by 14% year over year.',
            'order_in_group' => 1,
        ],
    ];

    $questions = [];
    for ($i = 1; $i <= 3; $i++) {
        $questions[] = [
            'prompt'         => "Single Passage Q{$i}",
            'difficulty'     => 'easy',
            'correct_choice' => 0,
            'choices'        => [
                'Answer A',
                'Answer B',
                'Answer C',
                'Answer D',
            ],
        ];
    }

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-passage-group', $this->test), [
            'test_section_id' => $this->section->id,
            'title'           => 'Assessment Part 7 Single Group',
            'part_number'     => 7,
            'passage_type'    => 'single',
            'passages'        => $passages,
            'questions'       => $questions,
        ]);

    $response->assertRedirect(route('teacher.tests.show', $this->test));
    $this->assertDatabaseHas('passage_groups', [
        'test_id'      => $this->test->id,
        'part_number'  => 7,
        'passage_type' => 'single',
    ]);

    $this->assertDatabaseCount('test_questions', 3);
});

test('11. Repository Revision preserves group and passage links on question edit', function () {
    $group = PassageGroup::create([
        'question_bank_id' => $this->bank->id,
        'title'            => 'Preserved Group',
        'part_number'      => 6,
        'passage_type'     => 'single',
        'order'            => 1,
        'created_by'       => $this->teacher->id,
    ]);

    $passage = Passage::create([
        'passage_group_id' => $group->id,
        'question_bank_id' => $this->bank->id,
        'order_in_group'   => 1,
        'document_type'    => 'memo',
        'title'            => 'Important Memo',
        'content'          => 'Office policy memo.',
    ]);

    $question = Question::create([
        'question_bank_id' => $this->bank->id,
        'passage_group_id' => $group->id,
        'prompt'           => 'Original Prompt',
        'section'          => SectionType::Reading,
        'part_number'      => 6,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
        'points'           => 1,
    ]);

    QuestionChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $question->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $question->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $question->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    $repoManager = User::factory()->create(['status' => 'active']);
    $repoManager->assignRole('repository-manager');

    $revRequest = RepositoryRevisionRequest::create([
        'question_bank_id' => $this->bank->id,
        'teacher_id'       => $this->teacher->id,
        'requested_by_id'  => $repoManager->id,
        'status'           => 'OPEN',
    ]);

    $revItem = RepositoryRevisionItem::create([
        'repository_revision_request_id' => $revRequest->id,
        'question_bank_id'               => $this->bank->id,
        'question_id'                    => $question->id,
        'finding_type'                   => 'prompt',
        'feedback'                       => 'Clarify prompt',
        'status'                         => 'OPEN',
    ]);

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.repository-revisions.update-question', [$revRequest->id, $revItem->id]), [
            'question_id'      => $question->id,
            'prompt'           => 'Revised Question Prompt',
            'part_number'      => 6,
            'difficulty'       => 'medium',
            'passage_group_id' => $group->id,
            'choices'          => [
                ['content' => 'A', 'is_correct' => 1],
                ['content' => 'B', 'is_correct' => 0],
                ['content' => 'C', 'is_correct' => 0],
                ['content' => 'D', 'is_correct' => 0],
            ],
        ]);

    $question->refresh();
    expect($question->prompt)->toBe('Revised Question Prompt');
    expect($question->passage_group_id)->toBe($group->id);
});

test('12. Split-screen data/render contract in candidate exam view', function () {
    $group = PassageGroup::create([
        'test_id'      => $this->test->id,
        'title'        => 'Marketing Strategy Double Passage',
        'part_number'  => 7,
        'passage_type' => 'double',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    $pass1 = Passage::create([
        'passage_group_id' => $group->id,
        'test_id'          => $this->test->id,
        'order_in_group'   => 1,
        'document_type'    => 'email',
        'title'            => 'Email from Director',
        'content'          => 'Please find our international marketing proposal attached.',
    ]);

    $pass2 = Passage::create([
        'passage_group_id' => $group->id,
        'test_id'          => $this->test->id,
        'order_in_group'   => 2,
        'document_type'    => 'article',
        'title'            => 'Market Analysis',
        'content'          => 'Overseas market demand increased by 25%.',
    ]);

    $q = Question::create([
        'passage_group_id' => $group->id,
        'prompt'           => 'What is the main topic of the director\'s email?',
        'section'          => SectionType::Reading,
        'part_number'      => 7,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
        'points'           => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'International marketing proposal', 'choice_text' => 'International marketing proposal', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Hiring budget cuts', 'choice_text' => 'Hiring budget cuts', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Office relocation', 'choice_text' => 'Office relocation', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'Annual conference schedule', 'choice_text' => 'Annual conference schedule', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create([
        'test_section_id' => $this->section->id,
        'question_id'     => $q->id,
        'order'           => 1,
        'points'          => 1,
    ]);

    $attempt = Attempt::create([
        'test_id'       => $this->test->id,
        'user_id'       => $this->candidate->id,
        'attempt_token' => 'pass-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->candidate)
        ->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('Part 7 Double Passage');
    $response->assertSee('Email from Director');
    $response->assertSee('Market Analysis');
    $response->assertSee('Please find our international marketing proposal attached.');
    $response->assertSee('What is the main topic of the director\'s email?');
    $response->assertSee('International marketing proposal');
});

test('13. Multi-passage selection tabs render in candidate exam', function () {
    $group = PassageGroup::create([
        'test_id'      => $this->test->id,
        'title'        => 'Hospitality Triple Passage',
        'part_number'  => 7,
        'passage_type' => 'triple',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->test->id, 'order_in_group' => 1, 'document_type' => 'schedule', 'title' => 'Event Schedule', 'content' => 'Event timeline']);
    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->test->id, 'order_in_group' => 2, 'document_type' => 'memo', 'title' => 'Catering Memo', 'content' => 'Catering menu details']);
    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->test->id, 'order_in_group' => 3, 'document_type' => 'notice', 'title' => 'Guest Notice', 'content' => 'Guest parking info']);

    $q = Question::create([
        'passage_group_id' => $group->id,
        'prompt'           => 'Which document contains catering menu details?',
        'section'          => SectionType::Reading,
        'part_number'      => 7,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
        'points'           => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Catering Memo', 'choice_text' => 'Catering Memo', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Schedule', 'choice_text' => 'Schedule', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Notice', 'choice_text' => 'Notice', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'None', 'choice_text' => 'None', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create([
        'test_section_id' => $this->section->id,
        'question_id'     => $q->id,
        'order'           => 1,
        'points'          => 1,
    ]);

    $attempt = Attempt::create([
        'test_id'       => $this->test->id,
        'user_id'       => $this->candidate->id,
        'attempt_token' => 'triple-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->candidate)
        ->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('passage-doc-tab');
    $response->assertSee('Event Schedule');
    $response->assertSee('Catering Memo');
    $response->assertSee('Guest Notice');
});

test('14. Audio forbidden in Part 6 & Part 7', function () {
    $passages = [
        ['order_in_group' => 1, 'document_type' => 'article', 'title' => 'Article', 'content' => 'Sample article content'],
    ];

    $questionsWithAudio = [
        [
            'prompt'         => 'Part 6 question with audio',
            'difficulty'     => 'medium',
            'audio_url'      => 'https://example.com/forbidden.mp3',
            'correct_choice' => 0,
            'choices'        => ['A', 'B', 'C', 'D'],
        ],
        [
            'prompt'         => 'Part 6 question 2',
            'difficulty'     => 'medium',
            'correct_choice' => 0,
            'choices'        => ['A', 'B', 'C', 'D'],
        ],
        [
            'prompt'         => 'Part 6 question 3',
            'difficulty'     => 'medium',
            'correct_choice' => 0,
            'choices'        => ['A', 'B', 'C', 'D'],
        ],
        [
            'prompt'         => 'Part 6 question 4',
            'difficulty'     => 'medium',
            'correct_choice' => 0,
            'choices'        => ['A', 'B', 'C', 'D'],
        ],
    ];

    $check = ToeicQuestionValidator::checkPassageGroup([
        'part_number'  => 6,
        'passage_type' => 'single',
    ], $passages, $questionsWithAudio);

    expect($check['is_valid'])->toBeFalse();
    expect($check['errors'])->toHaveKey('question_1_audio');
});

test('15. Existing Part 1–5 regression', function () {
    // Part 1 with image and audio
    $p1 = ToeicQuestionValidator::check([
        'part_number'    => 1,
        'image_url'      => 'https://example.com/photo.jpg',
        'audio_url'      => 'https://example.com/audio.mp3',
        'prompt'         => 'Look at the picture.',
        'difficulty'     => 'easy',
        'correct_choice' => 0,
        'choices'        => ['A', 'B', 'C', 'D'],
    ]);
    expect($p1['is_valid'])->toBeTrue();

    // Part 5 with prompt and 4 choices
    $p5 = ToeicQuestionValidator::check([
        'part_number'    => 5,
        'prompt'         => 'The financial report will be submitted _____ Friday.',
        'difficulty'     => 'medium',
        'correct_choice' => 2,
        'choices'        => ['at', 'in', 'on', 'by'],
    ]);
    expect($p5['is_valid'])->toBeTrue();
});

test('16. Non-TOEIC regression', function () {
    $nonToeicBank = QuestionBank::create([
        'title'       => 'TOEFL Academic Bank',
        'slug'        => 'toefl-academic-bank-' . uniqid(),
        'test_type'   => TestType::General,
        'category'    => 'TOEFL',
        'status'      => 'draft',
        'created_by'  => $this->teacher->id,
    ]);

    expect(ToeicQuestionValidator::isToeic($nonToeicBank))->toBeFalse();
});
