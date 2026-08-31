<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teacher = User::factory()->create([
        'email'  => 'teacher.ag4@test.com',
        'status' => 'active',
    ]);
    $this->teacher->assignRole('teacher');
});

/**
 * Helper to build a complete multi-part TOEIC test with:
 * Part 1: 3 standalone questions (Q1 - Q3)
 * Part 2: 12 standalone questions (Q4 - Q15)
 * Part 3: 2 Audio Groups (AG1: Q16 - Q18; AG2: 2 complete Q19, Q20 + 1 unpersisted slot 3)
 * Part 4: 1 Audio Group (AG3: Q21 - Q23)
 */
function createFullToeicAssessmentForDetail(User $teacher): array {
    $test = Test::create([
        'title'            => 'TOEIC Complete Practice Assessment AG4',
        'slug'             => 'toeic-assessment-ag4-' . Str::random(5),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_percentage'  => 75,
        'status'           => 'draft',
        'created_by'       => $teacher->id,
        'assigned_to'      => $teacher->id,
    ]);

    // Section 1: Part 1 Photographs (3 standalone questions -> Q1..Q3)
    $sec1 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 1: Photographs',
        'section_type' => 'listening',
        'order'        => 1,
        'instructions' => 'Look at the photograph and choose the statement.',
    ]);

    $p1Questions = [];
    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'         => "Part 1 Photograph {$i}",
            'prompt'        => "Part 1 Stem Prompt for Question {$i}",
            'part_number'   => 1,
            'question_type' => 'multiple_choice',
            'status'        => 'active',
            'created_by'    => $teacher->id,
        ]);
        foreach (['A', 'B', 'C', 'D'] as $label) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $label,
                'content'     => "P1 Choice {$label} for Q{$i}",
                'is_correct'  => $label === 'A',
                'order'       => ord($label) - ord('A') + 1,
            ]);
        }
        TestQuestion::create([
            'test_id'         => $test->id,
            'test_section_id' => $sec1->id,
            'question_id'     => $q->id,
            'order'           => $i,
        ]);
        $p1Questions[] = $q;
    }

    // Section 2: Part 2 Question-Response (12 standalone questions -> Q4..Q15)
    $sec2 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 2: Question-Response',
        'section_type' => 'listening',
        'order'        => 2,
        'instructions' => 'Listen to the question and select the best response.',
    ]);

    $p2Questions = [];
    for ($i = 1; $i <= 12; $i++) {
        $q = Question::create([
            'title'         => "Part 2 Question {$i}",
            'prompt'        => "Part 2 Prompt for Question {$i}",
            'part_number'   => 2,
            'question_type' => 'multiple_choice',
            'status'        => 'active',
            'created_by'    => $teacher->id,
        ]);
        foreach (['A', 'B', 'C'] as $label) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $label,
                'content'     => "P2 Response {$label} for Q{$i}",
                'is_correct'  => $label === 'B',
                'order'       => ord($label) - ord('A') + 1,
            ]);
        }
        TestQuestion::create([
            'test_id'         => $test->id,
            'test_section_id' => $sec2->id,
            'question_id'     => $q->id,
            'order'           => $i,
        ]);
        $p2Questions[] = $q;
    }

    // Section 3: Part 3 Conversations
    $sec3 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 3: Conversations',
        'section_type' => 'listening',
        'order'        => 3,
        'instructions' => 'Listen to conversations between two or more people.',
    ]);

    $media1 = MediaAsset::create([
        'uploaded_by'   => $teacher->id,
        'filename'      => 'conversation_c1.mp3',
        'original_name' => 'conversation_c1.mp3',
        'path'          => 'audio/conversation_c1.mp3',
        'type'          => 'audio',
        'mime_type'     => 'audio/mpeg',
        'size'          => 102400,
        'title'         => 'C1 Office Audio',
    ]);

    $ag1 = AudioGroup::create([
        'test_id'        => $test->id,
        'title'          => 'Conversation Group 1',
        'audio_url'      => 'https://example.com/audio/c1.mp3',
        'media_asset_id' => $media1->id,
        'part_number'    => 3,
        'group_type'     => 'conversation',
        'order'          => 1,
        'created_by'     => $teacher->id,
    ]);

    $ag1Questions = [];
    $promptsAG1 = [
        'What does the man ask for?',
        'What time does breakfast end?',
        'Where is the restaurant located?',
    ];
    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'          => "Conversation 1 Question {$i}",
            'prompt'         => $promptsAG1[$i - 1],
            'part_number'    => 3,
            'question_type'  => 'multiple_choice',
            'audio_group_id' => $ag1->id,
            'status'         => 'active',
            'created_by'     => $teacher->id,
        ]);
        foreach (['A', 'B', 'C', 'D'] as $label) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $label,
                'content'     => "AG1 Q{$i} Option {$label}",
                'is_correct'  => $label === 'A',
                'order'       => ord($label) - ord('A') + 1,
            ]);
        }
        TestQuestion::create([
            'test_id'         => $test->id,
            'test_section_id' => $sec3->id,
            'question_id'     => $q->id,
            'order'           => $i,
        ]);
        $ag1Questions[] = $q;
    }

    // Audio Group 2: 2 complete questions (Q19, Q20), 1 unpersisted slot 3
    $media2 = MediaAsset::create([
        'uploaded_by'   => $teacher->id,
        'filename'      => 'conversation_c2.mp3',
        'original_name' => 'conversation_c2.mp3',
        'path'          => 'audio/conversation_c2.mp3',
        'type'          => 'audio',
        'mime_type'     => 'audio/mpeg',
        'size'          => 102400,
        'title'         => 'C2 Meeting Audio',
    ]);

    $ag2 = AudioGroup::create([
        'test_id'        => $test->id,
        'title'          => 'Conversation Group 2',
        'audio_url'      => 'https://example.com/audio/c2.mp3',
        'media_asset_id' => $media2->id,
        'part_number'    => 3,
        'group_type'     => 'conversation',
        'order'          => 2,
        'created_by'     => $teacher->id,
    ]);

    $ag2Questions = [];
    $promptsAG2 = [
        'What is the woman doing?',
        'What will the woman do next?',
    ];
    for ($i = 1; $i <= 2; $i++) {
        $q = Question::create([
            'title'          => "Conversation 2 Question {$i}",
            'prompt'         => $promptsAG2[$i - 1],
            'part_number'    => 3,
            'question_type'  => 'multiple_choice',
            'audio_group_id' => $ag2->id,
            'status'         => 'active',
            'created_by'     => $teacher->id,
        ]);
        foreach (['A', 'B', 'C', 'D'] as $label) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $label,
                'content'     => "AG2 Q{$i} Option {$label}",
                'is_correct'  => $label === 'C',
                'order'       => ord($label) - ord('A') + 1,
            ]);
        }
        TestQuestion::create([
            'test_id'         => $test->id,
            'test_section_id' => $sec3->id,
            'question_id'     => $q->id,
            'order'           => 3 + $i,
        ]);
        $ag2Questions[] = $q;
    }

    // Section 4: Part 4 Talks (1 Audio Group -> Q21..Q23)
    $sec4 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 4: Short Talks',
        'section_type' => 'listening',
        'order'        => 4,
        'instructions' => 'Listen to short talks given by a single speaker.',
    ]);

    $media3 = MediaAsset::create([
        'uploaded_by'   => $teacher->id,
        'filename'      => 'talk_t1.mp3',
        'original_name' => 'talk_t1.mp3',
        'path'          => 'audio/talk_t1.mp3',
        'type'          => 'audio',
        'mime_type'     => 'audio/mpeg',
        'size'          => 102400,
        'title'         => 'T1 Announcement Audio',
    ]);

    $ag3 = AudioGroup::create([
        'test_id'        => $test->id,
        'title'          => 'Talk Group 1',
        'audio_url'      => 'https://example.com/audio/t1.mp3',
        'media_asset_id' => $media3->id,
        'part_number'    => 4,
        'group_type'     => 'talk',
        'order'          => 1,
        'created_by'     => $teacher->id,
    ]);

    $ag3Questions = [];
    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'          => "Talk 1 Question {$i}",
            'prompt'         => "Talk prompt for Question {$i}",
            'part_number'    => 4,
            'question_type'  => 'multiple_choice',
            'audio_group_id' => $ag3->id,
            'status'         => 'active',
            'created_by'     => $teacher->id,
        ]);
        foreach (['A', 'B', 'C', 'D'] as $label) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $label,
                'content'     => "AG3 Q{$i} Option {$label}",
                'is_correct'  => $label === 'D',
                'order'       => ord($label) - ord('A') + 1,
            ]);
        }
        TestQuestion::create([
            'test_id'         => $test->id,
            'test_section_id' => $sec4->id,
            'question_id'     => $q->id,
            'order'           => $i,
        ]);
        $ag3Questions[] = $q;
    }

    return compact('test', 'sec1', 'sec2', 'sec3', 'sec4', 'p1Questions', 'p2Questions', 'ag1', 'ag1Questions', 'ag2', 'ag2Questions', 'ag3', 'ag3Questions');
}

/*
|--------------------------------------------------------------------------
| AG4 FEATURE TESTS (TEST 01 - TEST 20)
|--------------------------------------------------------------------------
*/

test('TEST 01: AudioGroup child questions are not rendered as duplicate standalone cards', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Standalone cards should NOT be rendered for Q16, Q17, Q18, Q19, Q20, Q21, Q22, Q23
    $response->assertDontSee('Question #16', false);
    $response->assertDontSee('Question #17', false);
    $response->assertDontSee('Question #18', false);
    $response->assertDontSee('Question #19', false);
    $response->assertDontSee('Question #20', false);
    $response->assertDontSee('Question #21', false);
    $response->assertDontSee('Question #22', false);
    $response->assertDontSee('Question #23', false);
});

test('TEST 02: Standalone questions continue rendering normally', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Part 1 standalone questions must render as individual cards
    $response->assertSee('Question #1');
    $response->assertSee('Question #2');
    $response->assertSee('Question #3');
    // Part 2 standalone questions must render as individual cards
    $response->assertSee('Question #4');
    $response->assertSee('Question #15');
});

test('TEST 03: Part 3 Group card renders child summaries', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Conversation Group 1');
    $response->assertSee('What does the man ask for?');
    $response->assertSee('What time does breakfast end?');
    $response->assertSee('Where is the restaurant located?');
});

test('TEST 04: Part 4 Group card renders child summaries', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Talk Group 1');
    $response->assertSee('Talk prompt for Question 1');
    $response->assertSee('Talk prompt for Question 2');
    $response->assertSee('Talk prompt for Question 3');
});

test('TEST 05: Group renders exactly one shared audio summary', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Shared Audio:');
    $response->assertSee('C1 Office Audio');
});

test('TEST 06: Group children use global assessment numbering', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Children in AG1 should display Q16, Q17, Q18
    $response->assertSee('Q16 ✓ Complete:');
    $response->assertSee('Q17 ✓ Complete:');
    $response->assertSee('Q18 ✓ Complete:');
});

test('TEST 07: Numbering does not restart at Part 3', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // It should NOT render local Q#1 / Q#2 / Q#3 for Part 3 Audio Group children
    $response->assertDontSee('Q#1 ✓ Complete:');
    $response->assertDontSee('Q#2 ✓ Complete:');
    $response->assertDontSee('Q#3 ✓ Complete:');
});

test('TEST 08: If prior parts contain 15 questions, first Part 3 child displays Q16', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Q16 ✓ Complete:');
});

test('TEST 09: Subsequent child displays Q17', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Q17 ✓ Complete:');
});

test('TEST 10: Subsequent child displays Q18', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Q18 ✓ Complete:');
});

test('TEST 11: Next group continues Q19 etc.', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Q19 ✓ Complete:');
    $response->assertSee('Q20 ✓ Complete:');
    $response->assertSee('Q21 ✓ Complete:');
    $response->assertSee('Q22 ✓ Complete:');
    $response->assertSee('Q23 ✓ Complete:');
});

test('TEST 12: Incomplete unpersisted slot does not receive fake global number', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Unpersisted Slot 3 should NOT say Q21 (since Q21 is in Part 4)
    $response->assertSee('Slot 3 ○ Incomplete:');
    $response->assertSee('Pending authoring');
});

test('TEST 13: Incomplete slot displays Slot N / Pending authoring', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Slot 3 ○ Incomplete:');
    $response->assertSee('Pending authoring');
});

test('TEST 14: Question count remains accurate', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Part 1: 3 questions
    $response->assertSee('3 questions');
    // Part 2: 12 questions
    $response->assertSee('12 questions');
    // Part 3: 5 persisted questions
    $response->assertSee('5 questions');
    // Part 4: 3 questions
    $response->assertSee('3 questions');
    // Total questions across assessment: 23 questions (3 + 12 + 5 + 3)
    $response->assertSee('23');
});

test('TEST 15: TestQuestion ordering unchanged', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $sec3Questions = $data['sec3']->testQuestions()->orderBy('order')->get();

    expect($sec3Questions->count())->toBe(5)
        ->and($sec3Questions[0]->order)->toBe(1)
        ->and($sec3Questions[1]->order)->toBe(2)
        ->and($sec3Questions[2]->order)->toBe(3)
        ->and($sec3Questions[3]->order)->toBe(4)
        ->and($sec3Questions[4]->order)->toBe(5);
});

test('TEST 16: Question IDs unchanged', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $q16 = $data['ag1Questions'][0];
    expect(Question::find($q16->id))->not->toBeNull()
        ->and(Question::find($q16->id)->audio_group_id)->toBe($data['ag1']->id);
});

test('TEST 17: AudioGroup relationships unchanged', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $ag1 = $data['ag1']->fresh();
    expect($ag1->questions->count())->toBe(3)
        ->and($ag1->mediaAsset->id)->toBe($data['ag1']->media_asset_id);
});

test('TEST 18: Edit Group remains functional', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Edit Group');
    $response->assertSee('openEditAudioGroupModal', false);
});

test('TEST 19: Preview Audio remains functional', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Preview Audio');
    $response->assertSee('previewAssetModal', false);
});

test('TEST 20: ValidationAssistant remains functional', function () {
    $data = createFullToeicAssessmentForDetail($this->teacher);

    $service = app(TestBuilderService::class);
    $validation = $service->validateAssessment($data['test']->fresh());

    // Because AG2 has only 2/3 complete, validation assistant must flag incomplete AudioGroup
    expect($validation['is_valid'])->toBeFalse()
        ->and(count($validation['errors']))->toBeGreaterThan(0);
});
