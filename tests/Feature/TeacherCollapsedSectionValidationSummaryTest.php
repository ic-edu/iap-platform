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
        'email'  => 'teacher.summary@test.com',
        'status' => 'active',
    ]);
    $this->teacher->assignRole('teacher');
});

/**
 * Helper to build a multi-part TOEIC test with:
 * Part 1: 6 complete standalone questions (Q1 - Q6) with image & audio -> READY
 * Part 2: 25 complete standalone questions (Q7 - Q31) with audio & 3 choices -> READY
 * Part 3: 13 AudioGroups (AG1..12: 3/3 complete; AG13: 2/3 complete) -> NEEDS ATTENTION (1 issue)
 * Part 4: 0 questions -> NOT STARTED (1 issue)
 */
function createToeicTestForSummary(User $teacher): array {
    $test = Test::create([
        'title'            => 'TOEIC Collapsed Summary Test',
        'slug'             => 'toeic-summary-' . Str::random(5),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_percentage'  => 75,
        'status'           => 'draft',
        'created_by'       => $teacher->id,
        'assigned_to'      => $teacher->id,
    ]);

    // Section 1: Part 1 Photographs (6 questions -> Q1..Q6)
    $sec1 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 1: Photographs',
        'section_type' => 'listening',
        'order'        => 1,
        'instructions' => 'Directions for Part 1.',
    ]);

    $p1Questions = [];
    for ($i = 1; $i <= 6; $i++) {
        $q = Question::create([
            'title'         => "Part 1 Photograph {$i}",
            'prompt'        => "Part 1 Stem Prompt for Question {$i}",
            'image_url'     => "https://example.com/p1_img_{$i}.jpg",
            'audio_url'     => "https://example.com/p1_audio_{$i}.mp3",
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

    // Section 2: Part 2 Question-Response (25 questions -> Q7..Q31)
    $sec2 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 2: Question-Response',
        'section_type' => 'listening',
        'order'        => 2,
        'instructions' => 'Directions for Part 2.',
    ]);

    $p2Questions = [];
    for ($i = 1; $i <= 25; $i++) {
        $q = Question::create([
            'title'         => "Part 2 Question {$i}",
            'prompt'        => "Part 2 Prompt for Question {$i}",
            'audio_url'     => "https://example.com/p2_audio_{$i}.mp3",
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

    // Section 3: Part 3 Conversations (13 AudioGroups: AG1..12 are 3/3 complete, AG13 is 2/3 complete -> 38/39 questions)
    $sec3 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 3: Conversations',
        'section_type' => 'listening',
        'order'        => 3,
        'instructions' => 'Directions for Part 3.',
    ]);

    $agGroups = [];
    $allAgQuestions = [];
    $agOrder = 1;
    for ($g = 1; $g <= 13; $g++) {
        $media = MediaAsset::create([
            'uploaded_by'   => $teacher->id,
            'filename'      => "c{$g}.mp3",
            'original_name' => "c{$g}.mp3",
            'path'          => "audio/c{$g}.mp3",
            'type'          => 'audio',
            'mime_type'     => 'audio/mpeg',
            'size'          => 102400,
            'title'         => "C{$g} Shared Audio",
        ]);

        $ag = AudioGroup::create([
            'test_id'        => $test->id,
            'title'          => "Conversation Group {$g}",
            'audio_url'      => "https://example.com/audio/c{$g}.mp3",
            'media_asset_id' => $media->id,
            'part_number'    => 3,
            'group_type'     => 'conversation',
            'order'          => $g,
            'created_by'     => $teacher->id,
        ]);
        $agGroups[$g] = $ag;

        $targetQInGroup = ($g === 13) ? 2 : 3;
        for ($i = 1; $i <= $targetQInGroup; $i++) {
            $q = Question::create([
                'title'          => "AG{$g} Question {$i}",
                'prompt'         => "Prompt for AG{$g} Q{$i}",
                'part_number'    => 3,
                'question_type'  => 'multiple_choice',
                'audio_group_id' => $ag->id,
                'status'         => 'active',
                'created_by'     => $teacher->id,
            ]);
            foreach (['A', 'B', 'C', 'D'] as $label) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label'       => $label,
                    'content'     => "AG{$g} Choice {$label} for Q{$i}",
                    'is_correct'  => $label === 'A',
                    'order'       => ord($label) - ord('A') + 1,
                ]);
            }
            TestQuestion::create([
                'test_id'         => $test->id,
                'test_section_id' => $sec3->id,
                'question_id'     => $q->id,
                'order'           => $agOrder++,
            ]);
            $allAgQuestions[] = $q;
        }
    }

    $ag1 = $agGroups[1];
    $ag2 = $agGroups[13]; // Last group is the incomplete one

    // Section 4: Part 4 Talks (Empty -> 0 questions)
    $sec4 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 4: Short Talks',
        'section_type' => 'listening',
        'order'        => 4,
        'instructions' => 'Directions for Part 4.',
    ]);

    return compact('test', 'sec1', 'sec2', 'sec3', 'sec4', 'p1Questions', 'p2Questions', 'ag1', 'ag2', 'agGroups', 'allAgQuestions');
}

/*
|--------------------------------------------------------------------------
| AG5 FEATURE TESTS (TEST 01 - TEST 20)
|--------------------------------------------------------------------------
*/

test('TEST 01: Valid Section renders READY', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Part 1 and Part 2 are valid, must render READY
    $response->assertSee('READY');
    $response->assertSee('✓');
});

test('TEST 02: Section with blocking finding renders NEEDS ATTENTION', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Part 3 has incomplete AudioGroup, must render NEEDS ATTENTION
    $response->assertSee('NEEDS ATTENTION');
    $response->assertSee('⚠');
});

test('TEST 03: Empty Section renders appropriate NOT STARTED state', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Part 4 has 0 questions, must render NOT STARTED
    $response->assertSee('NOT STARTED');
    $response->assertSee('○');
});

test('TEST 04: READY Section has zero blocking findings', function () {
    $data = createToeicTestForSummary($this->teacher);

    $service = app(TestBuilderService::class);
    $validation = $service->validateAssessment($data['test']->fresh());

    // Filter questions in Section 1 (Part 1)
    $sec1Questions = collect($validation['questions'])->filter(fn($item) => (string)$item['section']->id === (string)$data['sec1']->id);
    expect($sec1Questions->count())->toBe(6);
    foreach ($sec1Questions as $qItem) {
        expect($qItem['warnings'])->toBeEmpty();
    }
});

test('TEST 05: Submission-blocking Section cannot render READY', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Check that section card 3 has data-status="needs_attention" and NOT data-status="ready"
    $response->assertSee('id="section-card-' . $data['sec3']->id . '" class="section-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4" data-section-id="' . $data['sec3']->id . '" data-status="needs_attention"', false);
});

test('TEST 06: Question count remains accurate', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('6 of 6 questions');
    $response->assertSee('25 of 25 questions');
    $response->assertSee('38 of 39 questions');
    $response->assertSee('0 of 30 questions');
});

test('TEST 07: Actual question range remains accurate', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('(Questions 1–6)');
    $response->assertSee('(Questions 7–31)');
    $response->assertSee('(Questions 32–70)');
});

test('TEST 08: Incomplete AudioGroup rolls up to Section status', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Part 3 has incomplete group -> rolled up to NEEDS ATTENTION
    $response->assertSee('data-section-id="' . $data['sec3']->id . '" data-status="needs_attention"', false);
    $response->assertSee('1 question missing');
});

test('TEST 09: Complete AudioGroups produce no group blocking finding', function () {
    $teacher = $this->teacher;
    $test = Test::create([
        'title'            => 'TOEIC Fully Complete AG Test',
        'slug'             => 'toeic-complete-ag-' . Str::random(5),
        'test_type'        => 'general',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_percentage'  => 75,
        'status'           => 'draft',
        'created_by'       => $teacher->id,
        'assigned_to'      => $teacher->id,
    ]);

    $sec = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 3: Conversations',
        'section_type' => 'listening',
        'order'        => 1,
    ]);

    $media = MediaAsset::create([
        'uploaded_by'   => $teacher->id,
        'filename'      => 'c.mp3',
        'original_name' => 'c.mp3',
        'path'          => 'audio/c.mp3',
        'type'          => 'audio',
        'mime_type'     => 'audio/mpeg',
        'size'          => 102400,
        'title'         => 'C Audio',
    ]);

    $ag = AudioGroup::create([
        'test_id'        => $test->id,
        'title'          => 'Conversation Group Complete',
        'audio_url'      => 'https://example.com/audio/c.mp3',
        'media_asset_id' => $media->id,
        'part_number'    => 3,
        'group_type'     => 'conversation',
        'order'          => 1,
        'created_by'     => $teacher->id,
    ]);

    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'          => "Complete Q{$i}",
            'prompt'         => "Prompt for complete Q{$i}",
            'part_number'    => 3,
            'question_type'  => 'multiple_choice',
            'audio_group_id' => $ag->id,
            'status'         => 'active',
            'created_by'     => $teacher->id,
        ]);
        foreach (['A', 'B', 'C', 'D'] as $label) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => $label,
                'content'     => "Option {$label}",
                'is_correct'  => $label === 'A',
                'order'       => ord($label) - ord('A') + 1,
            ]);
        }
        TestQuestion::create([
            'test_id'         => $test->id,
            'test_section_id' => $sec->id,
            'question_id'     => $q->id,
            'order'           => $i,
        ]);
    }

    $response = $this->actingAs($teacher)->get(route('teacher.tests.show', $test->id));
    $response->assertOk();
    $response->assertSee('data-section-id="' . $sec->id . '" data-status="ready"', false);
});

test('TEST 10: Issue count uses authoritative validation findings', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Part 3 has 1 question missing according to TOEIC blueprint
    $response->assertSee('1 question missing');
});

test('TEST 11: No accidental duplicate group/child issue count where semantically equivalent', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Section 3 has 1 incomplete Audio Group + 1 blueprint count issue, status is needs_attention
    $response->assertSee('data-section-id="' . $data['sec3']->id . '" data-status="needs_attention"', false);
    $response->assertSee('1 question missing');
});

test('TEST 12: Status visible while Section collapsed', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Status badges are in the section header (outside the collapsed body)
    $response->assertSee('READY');
    $response->assertSee('NEEDS ATTENTION');
    $response->assertSee('NOT STARTED');
    // Bodies are hidden
    $response->assertSee('id="section-body-' . $data['sec1']->id . '" class="hidden', false);
    $response->assertSee('id="section-body-' . $data['sec2']->id . '" class="hidden', false);
    $response->assertSee('id="section-body-' . $data['sec3']->id . '" class="hidden', false);
    $response->assertSee('id="section-body-' . $data['sec4']->id . '" class="hidden', false);
});

test('TEST 13: Status remains visible while expanded', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Section Card Header contains both the badge and the toggle button
    $response->assertSee('btn-collapse-' . $data['sec1']->id);
    $response->assertSee('READY');
});

test('TEST 14: Click NEEDS ATTENTION expands Section if implemented', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('expandAndFocusFirstIssue', false);
});

test('TEST 15: First issue focus works if implemented', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('data-first-issue-id="audio-group-card-' . $data['ag2']->id . '"', false);
    $response->assertSee('id="audio-group-card-' . $data['ag2']->id . '"', false);
});

test('TEST 16: Fixing final issue changes status to READY', function () {
    $data = createToeicTestForSummary($this->teacher);

    // Add 3rd question to AG2 to complete it
    $q3 = Question::create([
        'title'          => "AG2 Question 3",
        'prompt'         => "Prompt for AG2 Q3",
        'part_number'    => 3,
        'question_type'  => 'multiple_choice',
        'audio_group_id' => $data['ag2']->id,
        'status'         => 'active',
        'created_by'     => $this->teacher->id,
    ]);
    foreach (['A', 'B', 'C', 'D'] as $label) {
        QuestionChoice::create([
            'question_id' => $q3->id,
            'label'       => $label,
            'content'     => "AG2 Choice {$label} for Q3",
            'is_correct'  => $label === 'A',
            'order'       => ord($label) - ord('A') + 1,
        ]);
    }
    TestQuestion::create([
        'test_id'         => $data['test']->id,
        'test_section_id' => $data['sec3']->id,
        'question_id'     => $q3->id,
        'order'           => 39,
    ]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));
    $response->assertOk();

    // Section 3 must now render READY
    $response->assertSee('data-section-id="' . $data['sec3']->id . '" data-status="ready"', false);
});

test('TEST 17: Collapse default behavior unchanged', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('id="section-body-' . $data['sec1']->id . '" class="hidden', false);
    $response->assertSee('id="section-body-' . $data['sec2']->id . '" class="hidden', false);
    $response->assertSee('id="section-body-' . $data['sec3']->id . '" class="hidden', false);
});

test('TEST 18: AudioGroup compact rendering unchanged', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // Standalone cards are not duplicated
    $response->assertDontSee('Question #32', false);
    $response->assertDontSee('Question #33', false);
    $response->assertDontSee('Question #34', false);
    // Tree overview is rendered
    $response->assertSee('Q32 ✓ Complete:');
    $response->assertSee('Q33 ✓ Complete:');
    $response->assertSee('Q34 ✓ Complete:');
});

test('TEST 19: Global numbering unchanged', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Q32 ✓ Complete:');
    $response->assertSee('Q33 ✓ Complete:');
    $response->assertSee('Q34 ✓ Complete:');
    $response->assertSee('Q35 ✓ Complete:');
    $response->assertSee('Q36 ✓ Complete:');
});

test('TEST 20: Submission gate unchanged', function () {
    $data = createToeicTestForSummary($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $data['test']->id));

    $response->assertOk();
    // With Part 3 incomplete and Part 4 empty, submission is disabled with diagnostic message
    $response->assertSee('Submission Disabled');
    $response->assertSee('Need Attention');
});
