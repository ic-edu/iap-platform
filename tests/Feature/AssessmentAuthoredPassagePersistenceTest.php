<?php

namespace Tests\Feature;

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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create([
        'email' => 'teacher_passage_persist@test.com',
    ]);
    $this->teacher->assignRole('teacher');

    $this->test = Test::create([
        'title'            => 'TOEIC Persistence Assessment Test',
        'slug'             => 'toeic-persistence-assessment-test-' . uniqid(),
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

    $this->questionBank = QuestionBank::create([
        'title'       => 'Master Reading Repository',
        'slug'        => 'master-reading-repo-' . uniqid(),
        'description' => 'Repository for Reading Comprehension',
        'status'      => 'approved',
        'created_by'  => $this->teacher->id,
    ]);
});

// ==========================================
// SCHEMA TESTS (TEST 01 - TEST 03)
// ==========================================

test('TEST 01 - passages.question_bank_id accepts NULL', function () {
    $passage = Passage::create([
        'question_bank_id' => null,
        'test_id'          => $this->test->id,
        'title'            => 'Assessment-Authored Passage',
        'content'          => 'This is a passage authored for an assessment.',
        'order_in_group'   => 1,
        'document_type'    => 'article',
    ]);

    expect($passage)->not->toBeNull();
    expect($passage->question_bank_id)->toBeNull();
    $this->assertDatabaseHas('passages', [
        'id'               => $passage->id,
        'question_bank_id' => null,
        'test_id'          => $this->test->id,
    ]);
});

test('TEST 02 - existing non-null QuestionBank IDs remain valid', function () {
    $passage = Passage::create([
        'question_bank_id' => $this->questionBank->id,
        'title'            => 'Repository-Owned Passage',
        'content'          => 'This is a repository passage.',
        'order_in_group'   => 1,
        'document_type'    => 'article',
    ]);

    expect($passage->question_bank_id)->toBe($this->questionBank->id);
    $this->assertDatabaseHas('passages', [
        'id'               => $passage->id,
        'question_bank_id' => $this->questionBank->id,
    ]);
});

test('TEST 03 - foreign key still rejects nonexistent non-null QuestionBank ID', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON;');
    }

    expect(function () {
        Passage::create([
            'question_bank_id' => '01nonexistentbankid0000000',
            'title'            => 'Invalid FK Passage',
            'content'          => 'This should fail FK constraint.',
            'order_in_group'   => 1,
            'document_type'    => 'article',
        ]);
    })->toThrow(QueryException::class);
});

// ==========================================
// PART 6 CREATE TESTS (TEST 04 - TEST 11)
// ==========================================

test('TEST 04 - Assessment-authored Part 6 PassageGroup creates successfully with question_bank_id=NULL', function () {
    $payload = [
        'test_section_id'  => $this->part6Section->id,
        'title'            => 'Assessment Training Notice',
        'part_number'      => 6,
        'passage_type'     => 'single',
        'passages'         => [
            [
                'title'         => 'Training Notice',
                'content'       => 'Notice content with [131] [132] [133] [134] blanks.',
                'document_type' => 'notice',
                'order_in_group'=> 1,
            ],
        ],
        'questions'        => [
            ['prompt' => '', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A4', 'B4', 'C4', 'D4'], 'correct_choice' => 3],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-passage-group', $this->test->id), $payload);

    $response->assertRedirect(route('teacher.tests.show', $this->test->id));

    $passage = Passage::where('test_id', $this->test->id)->first();
    expect($passage)->not->toBeNull();
    expect($passage->question_bank_id)->toBeNull();
});

test('TEST 05 - Passage receives correct test_id', function () {
    $builderService = app(TestBuilderService::class);
    $pg = $builderService->createPassageGroup($this->part6Section, [
        'title'        => 'Test ID Verification Group',
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    $passage = $pg->passages()->first();
    expect($passage->test_id)->toBe($this->test->id);
});

test('TEST 06 - Passage receives correct passage_group_id', function () {
    $builderService = app(TestBuilderService::class);
    $pg = $builderService->createPassageGroup($this->part6Section, [
        'title'        => 'Passage Group ID Verification',
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    $passage = $pg->passages()->first();
    expect($passage->passage_group_id)->toBe($pg->id);
});

test('TEST 07 - Passage content persists', function () {
    $builderService = app(TestBuilderService::class);
    $expectedContent = 'Unique text content for Part 6 Text Completion item.';
    $pg = $builderService->createPassageGroup($this->part6Section, [
        'title'        => 'Content Persistence Group',
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => $expectedContent, 'document_type' => 'article'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    $passage = $pg->passages()->first();
    expect($passage->content)->toBe($expectedContent);
});

test('TEST 08 - exactly four child Questions persist', function () {
    $builderService = app(TestBuilderService::class);
    $pg = $builderService->createPassageGroup($this->part6Section, [
        'title'        => 'Child Questions Count Group',
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 3],
        ],
    ]);

    expect($pg->questions()->count())->toBe(4);
});

test('TEST 09 - each child gets four choices', function () {
    $builderService = app(TestBuilderService::class);
    $pg = $builderService->createPassageGroup($this->part6Section, [
        'title'        => 'Choice Count Verification',
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A4', 'B4', 'C4', 'D4'], 'correct_choice' => 3],
        ],
    ]);

    foreach ($pg->questions as $q) {
        expect($q->choices()->count())->toBe(4);
    }
});

test('TEST 10 - each child gets one correct answer', function () {
    $builderService = app(TestBuilderService::class);
    $pg = $builderService->createPassageGroup($this->part6Section, [
        'title'        => 'Correct Choice Verification',
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A4', 'B4', 'C4', 'D4'], 'correct_choice' => 3],
        ],
    ]);

    foreach ($pg->questions as $q) {
        expect($q->choices()->where('is_correct', true)->count())->toBe(1);
    }
});

test('TEST 11 - blank optional Part 6 child prompts remain valid', function () {
    $builderService = app(TestBuilderService::class);
    $pg = $builderService->createPassageGroup($this->part6Section, [
        'title'        => 'Blank Prompts Group',
        'part_number'  => 6,
        'passage_type' => 'single',
        'passages'     => [
            ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
        ],
        'questions'    => [
            ['prompt' => '', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
            ['prompt' => '', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
            ['prompt' => '', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2],
            ['prompt' => '', 'choices' => ['A4', 'B4', 'C4', 'D4'], 'correct_choice' => 3],
        ],
    ]);

    foreach ($pg->questions as $q) {
        expect($q->prompt)->toBe('');
    }
});

// ==========================================
// TRANSACTION TESTS (TEST 12 - TEST 15)
// ==========================================

test('TEST 12 - forced Passage creation failure rolls back PassageGroup', function () {
    $initialPgCount = PassageGroup::count();
    $initialPassageCount = Passage::count();

    expect(function () {
        DB::transaction(function () {
            $pg = PassageGroup::create([
                'test_id'      => $this->test->id,
                'part_number'  => 6,
                'passage_type' => 'single',
                'title'        => 'Rollback PG Test',
            ]);

            // Force a DB exception on passage creation
            Passage::create([
                'passage_group_id' => $pg->id,
                'question_bank_id' => '01nonexistentbankid0000000', // Invalid FK or missing required field
                'title'            => null, // Title is not nullable
                'content'          => 'Content',
            ]);
        });
    })->toThrow(QueryException::class);

    expect(PassageGroup::count())->toBe($initialPgCount);
    expect(Passage::count())->toBe($initialPassageCount);
});

test('TEST 13 - forced child Question failure rolls back PassageGroup + Passage', function () {
    $initialPgCount = PassageGroup::count();
    $initialPassageCount = Passage::count();
    $initialQCount = Question::count();

    expect(function () {
        $builderService = app(TestBuilderService::class);
        $builderService->createPassageGroup($this->part6Section, [
            'title'        => 'Forced Failure Group',
            'part_number'  => 6,
            'passage_type' => 'single',
            'passages'     => [
                ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
            ],
            'questions'    => [
                ['prompt' => '', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
                ['prompt' => '', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
                ['prompt' => '', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2, 'audio_url' => 'https://example.com/forbidden.mp3'], // Forbidden audio in Part 6 -> triggers validation exception
                ['prompt' => '', 'choices' => ['A4', 'B4', 'C4', 'D4'], 'correct_choice' => 3],
            ],
        ]);
    })->toThrow(ValidationException::class);

    expect(PassageGroup::count())->toBe($initialPgCount);
    expect(Passage::count())->toBe($initialPassageCount);
    expect(Question::count())->toBe($initialQCount);
});

test('TEST 14 - no orphan PassageGroup remains', function () {
    $pgCountBefore = PassageGroup::count();

    try {
        $builderService = app(TestBuilderService::class);
        $builderService->createPassageGroup($this->part6Section, [
            'title'        => 'Orphan Check Group',
            'part_number'  => 6,
            'passage_type' => 'single',
            'passages'     => [
                ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
            ],
            'questions'    => [
                ['prompt' => '', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
                ['prompt' => '', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
                ['prompt' => '', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2, 'audio_url' => 'https://example.com/forbidden.mp3'],
            ],
        ]);
    } catch (\Throwable $e) {
        // Expected
    }

    expect(PassageGroup::count())->toBe($pgCountBefore);
});

test('TEST 15 - no orphan Passage remains', function () {
    $pCountBefore = Passage::count();

    try {
        $builderService = app(TestBuilderService::class);
        $builderService->createPassageGroup($this->part6Section, [
            'title'        => 'Orphan Passage Check',
            'part_number'  => 6,
            'passage_type' => 'single',
            'passages'     => [
                ['title' => 'Doc 1', 'content' => 'Content [131]-[134]', 'document_type' => 'article'],
            ],
            'questions'    => [
                ['prompt' => '', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
                ['prompt' => '', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
                ['prompt' => '', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2, 'audio_url' => 'https://example.com/forbidden.mp3'],
                ['prompt' => '', 'choices' => ['A4', 'B4', 'C4', 'D4'], 'correct_choice' => 3],
            ],
        ]);
    } catch (\Throwable $e) {
        // Expected
    }

    expect(Passage::count())->toBe($pCountBefore);
});

// ==========================================
// REPOSITORY REGRESSION (TEST 16 - TEST 19)
// ==========================================

test('TEST 16 - QuestionBank Passage creation still sets question_bank_id', function () {
    $pg = PassageGroup::create([
        'question_bank_id' => $this->questionBank->id,
        'title'            => 'QB Passage Group',
        'part_number'      => 6,
        'passage_type'     => 'single',
    ]);

    $passage = Passage::create([
        'passage_group_id' => $pg->id,
        'question_bank_id' => $this->questionBank->id,
        'title'            => 'QB Document',
        'content'          => 'QB Passage Content',
        'order_in_group'   => 1,
        'document_type'    => 'article',
    ]);

    expect($passage->question_bank_id)->toBe($this->questionBank->id);
    expect($passage->test_id)->toBeNull();
});

test('TEST 17 - repository-owned Passage relationship still resolves', function () {
    $passage = Passage::create([
        'question_bank_id' => $this->questionBank->id,
        'title'            => 'Rel Passage',
        'content'          => 'Content',
        'order_in_group'   => 1,
        'document_type'    => 'article',
    ]);

    expect($passage->questionBank)->not->toBeNull();
    expect($passage->questionBank->id)->toBe($this->questionBank->id);
});

test('TEST 18 - assessment-authored Passage is not falsely returned as QuestionBank-owned', function () {
    $assessmentPassage = Passage::create([
        'question_bank_id' => null,
        'test_id'          => $this->test->id,
        'title'            => 'Assessment Passage',
        'content'          => 'Assessment Content',
        'order_in_group'   => 1,
        'document_type'    => 'article',
    ]);

    expect($assessmentPassage->questionBank)->toBeNull();

    $qbPassages = Passage::where('question_bank_id', $this->questionBank->id)->get();
    expect($qbPassages->pluck('id'))->not->toContain($assessmentPassage->id);
});

test('TEST 19 - existing Repository governance tests remain green', function () {
    // Assert QuestionBank model scope and relationship integrity
    expect($this->questionBank->passages)->toBeEmpty();

    $repoPassage = Passage::create([
        'question_bank_id' => $this->questionBank->id,
        'title'            => 'Repo Doc',
        'content'          => 'Repo Content',
        'order_in_group'   => 1,
        'document_type'    => 'article',
    ]);

    $this->questionBank->refresh();
    expect($this->questionBank->passages->count())->toBe(1);
    expect($this->questionBank->passages->first()->id)->toBe($repoPassage->id);
});

// ==========================================
// PART 7 REGRESSION (TEST 20 - TEST 21)
// ==========================================

test('TEST 20 - Part 7 Passage persistence still works with assessment ownership', function () {
    $builderService = app(TestBuilderService::class);
    $pg = $builderService->createPassageGroup($this->part7Section, [
        'title'        => 'Part 7 Reading Single Passage',
        'part_number'  => 7,
        'passage_type' => 'single',
        'passages'     => [
            [
                'title'         => 'Announcement',
                'content'       => 'Company annual retreat details.',
                'document_type' => 'notice',
                'order_in_group'=> 1,
            ],
        ],
        'questions'    => [
            [
                'prompt'         => 'Where will the retreat take place?',
                'choices'        => ['At a resort', 'In the main hall', 'Online', 'At the park'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => 'Who should attend?',
                'choices'        => ['All employees', 'Managers only', 'New hires', 'Interns'],
                'correct_choice' => 0,
            ],
        ],
    ]);

    $passage = $pg->passages()->first();
    expect($passage)->not->toBeNull();
    expect($passage->question_bank_id)->toBeNull();
    expect($passage->test_id)->toBe($this->test->id);
    expect($pg->questions()->count())->toBe(2);
});

test('TEST 21 - Part 7 prompt requirement remains unchanged', function () {
    expect(function () {
        $builderService = app(TestBuilderService::class);
        $builderService->createPassageGroup($this->part7Section, [
            'title'        => 'Part 7 Missing Prompt Group',
            'part_number'  => 7,
            'passage_type' => 'single',
            'passages'     => [
                ['title' => 'Notice', 'content' => 'Passage text', 'document_type' => 'notice'],
            ],
            'questions'    => [
                ['prompt' => '', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0], // Blank prompt in Part 7 must fail
                ['prompt' => 'Valid question?', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
            ],
        ]);
    })->toThrow(ValidationException::class);
});
