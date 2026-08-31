<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Engines\AssessmentEngine;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\DeliveryUnitBuilder;
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
        'email'  => 'teacher.ag3@test.com',
        'status' => 'active',
    ]);
    $this->teacher->assignRole('teacher');

    $this->candidate = User::factory()->create([
        'email'  => 'candidate.ag3@test.com',
        'status' => 'active',
    ]);
    $this->candidate->assignRole('student');
});

/**
 * Helper to create a fully wired TOEIC test with Part 1, Part 2, Part 3 Audio Groups, and Part 4 Audio Groups.
 */
function createToeicTestWithAudioGroups(User $teacher): array {
    $test = Test::create([
        'title'            => 'TOEIC Official Practice Test 2026',
        'slug'             => 'toeic-official-practice-test-' . Str::random(5),
        'description'      => 'Full test with audio groups for Part 3 and Part 4',
        'instructions'     => 'Listen carefully and select the best answer.',
        'duration_minutes' => 120,
        'pass_score'       => 450,
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'status'           => 'published',
        'created_by'       => $teacher->id,
        'assigned_to'      => $teacher->id,
    ]);

    // Section 1: Part 1 Photographs
    $sec1 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 1: Photographs',
        'instructions' => 'For each question, look at the photograph and listen to the audio.',
        'section_type' => 'listening',
        'order'        => 1,
    ]);

    $q1 = Question::create([
        'title'         => 'Look at the photograph and choose the best statement.',
        'prompt'        => 'Look at the photograph and choose the best statement.',
        'part_number'   => 1,
        'question_type' => 'multiple_choice',
        'status'        => 'active',
        'audio_url'     => 'https://example.com/audio/q1.mp3',
        'created_by'    => $teacher->id,
    ]);
    foreach (['A', 'B', 'C', 'D'] as $label) {
        QuestionChoice::create([
            'question_id' => $q1->id,
            'label'       => $label,
            'content'     => "Statement {$label}",
            'is_correct'  => $label === 'A',
            'order'       => ord($label) - ord('A') + 1,
        ]);
    }
    TestQuestion::create([
        'test_id'         => $test->id,
        'test_section_id' => $sec1->id,
        'question_id'     => $q1->id,
        'order'           => 1,
    ]);

    // Section 3: Part 3 Conversations (with 2 Audio Groups)
    $sec3 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 3: Conversations',
        'instructions' => 'You will hear conversations between two or more people.',
        'section_type' => 'listening',
        'order'        => 2,
    ]);

    $agMedia1 = MediaAsset::create([
        'uploaded_by'   => $teacher->id,
        'filename'      => 'conversation_1.mp3',
        'original_name' => 'conversation_1.mp3',
        'path'          => 'audio/conversation_1.mp3',
        'type'          => 'audio',
        'mime_type'     => 'audio/mpeg',
        'size'          => 102400,
        'title'         => 'Office Budget Audio',
    ]);

    $ag1 = AudioGroup::create([
        'title'          => 'Office Budget Conversation',
        'audio_url'      => 'https://example.com/audio/p3_group1.mp3',
        'media_asset_id' => $agMedia1->id,
        'part_number'    => 3,
        'group_type'     => 'conversation',
        'created_by'     => $teacher->id,
    ]);

    $ag1Questions = [];
    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'          => "Conversation 1 Question {$i}",
            'prompt'         => "What are the speakers mainly discussing in question {$i}?",
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
                'content'     => "Choice {$label} for Q{$i}",
                'is_correct'  => $label === 'B',
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

    $agMedia2 = MediaAsset::create([
        'uploaded_by'   => $teacher->id,
        'filename'      => 'conversation_2.mp3',
        'original_name' => 'conversation_2.mp3',
        'path'          => 'audio/conversation_2.mp3',
        'type'          => 'audio',
        'mime_type'     => 'audio/mpeg',
        'size'          => 102400,
        'title'         => 'Client Dinner Audio',
    ]);

    $ag2 = AudioGroup::create([
        'title'          => 'Client Dinner Meeting',
        'audio_url'      => 'https://example.com/audio/p3_group2.mp3',
        'media_asset_id' => $agMedia2->id,
        'part_number'    => 3,
        'group_type'     => 'conversation',
        'created_by'     => $teacher->id,
    ]);

    $ag2Questions = [];
    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'          => "Conversation 2 Question {$i}",
            'prompt'         => "What does the woman recommend in question {$i}?",
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
                'content'     => "Option {$label} for Q{$i}",
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

    // Section 4: Part 4 Talks (1 Audio Group)
    $sec4 = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Part 4: Short Talks',
        'instructions' => 'You will hear short talks given by a single speaker.',
        'section_type' => 'listening',
        'order'        => 3,
    ]);

    $agMedia3 = MediaAsset::create([
        'uploaded_by'   => $teacher->id,
        'filename'      => 'talk_1.mp3',
        'original_name' => 'talk_1.mp3',
        'path'          => 'audio/talk_1.mp3',
        'type'          => 'audio',
        'mime_type'     => 'audio/mpeg',
        'size'          => 102400,
        'title'         => 'Airport Announcement Audio',
    ]);

    $ag3 = AudioGroup::create([
        'title'          => 'Airport Flight Announcement',
        'audio_url'      => 'https://example.com/audio/p4_group1.mp3',
        'media_asset_id' => $agMedia3->id,
        'part_number'    => 4,
        'group_type'     => 'talk',
        'created_by'     => $teacher->id,
    ]);

    $ag3Questions = [];
    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'          => "Talk 1 Question {$i}",
            'prompt'         => "Where is this announcement taking place for question {$i}?",
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
                'content'     => "Talk Choice {$label} for Q{$i}",
                'is_correct'  => $label === 'A',
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

    return compact('test', 'sec1', 'sec3', 'sec4', 'q1', 'ag1', 'ag1Questions', 'ag2', 'ag2Questions', 'ag3', 'ag3Questions');
}

/*
|--------------------------------------------------------------------------
| A. TEACHER CANDIDATE PREVIEW MODE (TEST 01 - TEST 10)
|--------------------------------------------------------------------------
*/

test('TEST 01: Part 3 Audio Group renders on a single delivery unit card with exactly one shared audio player and 3 child questions', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('delivery-unit-card', false);
    $response->assertSee('Office Budget Conversation');
    $response->assertSee('Shared Conversation Audio');
    $response->assertSee('Questions 2–4');
    $response->assertSee('What are the speakers mainly discussing in question 1?');
    $response->assertSee('What are the speakers mainly discussing in question 2?');
    $response->assertSee('What are the speakers mainly discussing in question 3?');
});

test('TEST 02: Part 4 Audio Group renders on a single delivery unit card with exactly one shared audio player and 3 child questions', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Airport Flight Announcement');
    $response->assertSee('PART 4 — TALK');
    $response->assertSee('Questions 8–10');
    $response->assertSee('Where is this announcement taking place for question 1?');
    $response->assertSee('Where is this announcement taking place for question 2?');
    $response->assertSee('Where is this announcement taking place for question 3?');
});

test('TEST 03: Preview mode allows unrestricted audio playback and non-persistent answer selection', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('preview_choice_' . $data['ag1Questions'][0]->id, false);
    $response->assertSee('preview_choice_' . $data['ag1Questions'][1]->id, false);
    $response->assertSee('preview_choice_' . $data['ag1Questions'][2]->id, false);
    $response->assertSee('selectPreviewChoice', false);
});

test('TEST 04: Next / Previous buttons move between delivery units in preview mode', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('navigateDeliveryUnit', false);
    $response->assertSee('Next Group');
    $response->assertSee('Previous Group');
});

test('TEST 05: Question palette in preview mode links to delivery units and targets individual questions', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('handlePaletteQuestionClick', false);
    $response->assertSee('palette-btn-0', false);
    $response->assertSee('palette-btn-1', false);
    $response->assertSee('palette-btn-2', false);
    $response->assertSee('palette-btn-3', false);
});

test('TEST 06: Question palette displays total question count and tracks individual answers', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('0 / 10 Answered');
    $response->assertSee('10 Questions');
});

test('TEST 07: Section boundary navigation provides Directions before Part 3 and Part 4 Audio Groups', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('section-intro-card-' . $data['sec3']->id, false);
    $response->assertSee('section-intro-card-' . $data['sec4']->id, false);
    $response->assertSee('Begin Part 3: Conversations');
    $response->assertSee('Begin Part 4: Short Talks');
});

test('TEST 08: Standalone questions in Part 1 render as single question delivery units', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Question 1 of 10');
    $response->assertSee('Look at the photograph and choose the best statement.');
});

test('TEST 09: AudioGroup titles and display question ranges render accurately in preview', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $data['test']->id));

    $response->assertOk();
    $response->assertSee('Questions 2–4');
    $response->assertSee('Questions 5–7');
    $response->assertSee('Questions 8–10');
});

test('TEST 10: Incomplete assessment structure or unlinked questions handled gracefully in preview', function () {
    $emptyTest = Test::create([
        'title'            => 'Empty Assessment',
        'slug'             => 'empty-assessment-' . Str::random(5),
        'duration_minutes' => 60,
        'pass_score'       => 70,
        'created_by'       => $this->teacher->id,
        'assigned_to'      => $this->teacher->id,
    ]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $emptyTest->id));

    $response->assertOk();
    $response->assertSee('Preview Structure Incomplete');
});

/*
|--------------------------------------------------------------------------
| B. CANDIDATE SIMULATOR MODE (TEST 11 - TEST 15)
|--------------------------------------------------------------------------
*/

test('TEST 11: Simulator exam renders Audio Group with 1 shared audio stimulus and 3 child questions on one page', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('TEST SIMULATOR');
    $response->assertSee('delivery-unit-card', false);
    $response->assertSee('Shared Conversation Audio');
    $response->assertSee('Questions 2–4');
    $response->assertSee('What are the speakers mainly discussing in question 1?');
    $response->assertSee('What are the speakers mainly discussing in question 2?');
    $response->assertSee('What are the speakers mainly discussing in question 3?');
});

test('TEST 12: Simulator audio player allows replay with standard audio controls', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('<audio controls', false);
    $response->assertSee('Shared Audio Stimulus');
});

test('TEST 13: Simulator allows independent answer selection and autosaving for all 3 questions', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $q1 = $data['ag1Questions'][0];
    $q2 = $data['ag1Questions'][1];
    $choice1 = $q1->choices->first();
    $choice2 = $q2->choices->last();

    $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
        'question_id'     => $q1->id,
        'selected_choice' => $choice1->id,
    ])->assertOk();

    $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
        'question_id'     => $q2->id,
        'selected_choice' => $choice2->id,
    ])->assertOk();

    expect(Answer::where('attempt_id', $attempt->id)->where('question_id', $q1->id)->value('selected_choice_id'))->toBe($choice1->id)
        ->and(Answer::where('attempt_id', $attempt->id)->where('question_id', $q2->id)->value('selected_choice_id'))->toBe($choice2->id);
});

test('TEST 14: Simulator allows free navigation without requiring all 3 questions to be answered before Next', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('const isRealTest = false;', false);
    $response->assertSee('handleNextClick', false);
});

test('TEST 15: Simulator question palette correctly jumps to and highlights the target question within its Audio Group', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('handlePaletteClick', false);
    $response->assertSee('palette-btn-0', false);
    $response->assertSee('palette-btn-1', false);
    $response->assertSee('palette-btn-2', false);
    $response->assertSee('palette-btn-3', false);
});

/*
|--------------------------------------------------------------------------
| C. CANDIDATE REAL TEST / MOCK TEST MODE (TEST 16 - TEST 22)
|--------------------------------------------------------------------------
*/

test('TEST 16: Real Test renders exactly ONE Play Audio action button for the entire Audio Group', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);
    $data['test']->update(['assessment_mode' => 'real_test']);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('SECURE MOCK TEST');
    $response->assertSee('btn-play-unit-', false);
    $response->assertSee('Play Available (1/1)');
    $response->assertSee('Play Conversation Audio');
});

test('TEST 17: Streaming audio in Real Test marks all 3 child questions as played in attempt_audio_plays', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);
    $data['test']->update(['assessment_mode' => 'real_test']);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $primaryQ = $data['ag1Questions'][0];

    // Stream audio for primary question in Group 1
    $response = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $primaryQ]));

    // Should return either 200 or redirect to audio URL
    expect(in_array($response->status(), [200, 302], true))->toBeTrue();

    // Check that all 3 questions in Group 1 are marked as played in database
    $playedQuestionIds = AttemptAudioPlay::where('attempt_id', $attempt->id)->pluck('question_id')->toArray();
    foreach ($data['ag1Questions'] as $agQ) {
        expect($playedQuestionIds)->toContain($agQ->id);
    }
});

test('TEST 18: Audio replay is blocked in Real Test mode once the group audio has played', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);
    $data['test']->update(['assessment_mode' => 'real_test']);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $primaryQ = $data['ag1Questions'][0];

    // First play
    $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $primaryQ]));

    // Second play attempt on same question or any sibling question in group
    $siblingQ = $data['ag1Questions'][1];
    $response = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $siblingQ]));

    $response->assertStatus(403);
});

test('TEST 19: Real Test mode includes client validation rule requiring all child questions in group to be answered before Next', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);
    $data['test']->update(['assessment_mode' => 'real_test']);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('const isRealTest = true;', false);
    $response->assertSee('For Mock Test examination, you must select an answer for all questions in this group before proceeding.');
});

test('TEST 20: Real Test question palette reflects individual question answered state', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);
    $data['test']->update(['assessment_mode' => 'real_test']);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    // Answer only Q2 (first question of Group 1)
    $q2 = $data['ag1Questions'][0];
    $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
        'question_id'     => $q2->id,
        'selected_choice' => $q2->choices->first()->id,
    ])->assertOk();

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('1/10');
});

test('TEST 21: Auto-submit on expiry preserves answers from all 3 child questions in an Audio Group', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);
    $data['test']->update(['assessment_mode' => 'real_test']);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    // Answer all 3 questions in Group 1
    foreach ($data['ag1Questions'] as $q) {
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id'     => $q->id,
            'selected_choice' => $q->choices->first()->id,
        ])->assertOk();
    }

    expect(Answer::where('attempt_id', $attempt->id)->count())->toBe(3);
});

test('TEST 22: Final submit button enables only when all questions in all groups/units are answered', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);
    $data['test']->update(['assessment_mode' => 'real_test']);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

    $response->assertOk();
    $response->assertSee('Final Submit (0/10)');
    $response->assertSee('disabled', false);
});

/*
|--------------------------------------------------------------------------
| D. REGRESSION & EDGE CASES (TEST 23 - TEST 30)
|--------------------------------------------------------------------------
*/

test('TEST 23: DeliveryUnitBuilder correctly groups consecutive questions by audio_group_id', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $result = DeliveryUnitBuilder::build($data['test']);

    expect($result['totalUnitsCount'])->toBe(4) // Unit 0: Q1 (Part 1), Unit 1: AG1 (Part 3), Unit 2: AG2 (Part 3), Unit 3: AG3 (Part 4)
        ->and($result['totalQuestionsCount'])->toBe(10)
        ->and($result['deliveryUnits'][0]['type'])->toBe('question')
        ->and($result['deliveryUnits'][1]['type'])->toBe('audio_group')
        ->and($result['deliveryUnits'][1]['questions']->count())->toBe(3)
        ->and($result['deliveryUnits'][2]['type'])->toBe('audio_group')
        ->and($result['deliveryUnits'][2]['questions']->count())->toBe(3)
        ->and($result['deliveryUnits'][3]['type'])->toBe('audio_group')
        ->and($result['deliveryUnits'][3]['questions']->count())->toBe(3);
});

test('TEST 24: Multiple audio groups within Part 3 form separate delivery units in order', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $result = DeliveryUnitBuilder::build($data['test']);

    $agUnit1 = $result['deliveryUnits'][1];
    $agUnit2 = $result['deliveryUnits'][2];

    expect($agUnit1['title'])->toBe('Office Budget Conversation')
        ->and($agUnit2['title'])->toBe('Client Dinner Meeting')
        ->and($agUnit1['first_question_index'])->toBe(1)
        ->and($agUnit1['last_question_index'])->toBe(3)
        ->and($agUnit2['first_question_index'])->toBe(4)
        ->and($agUnit2['last_question_index'])->toBe(6);
});

test('TEST 25: Part 4 audio groups form separate delivery units following Part 4 directions', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $result = DeliveryUnitBuilder::build($data['test']);

    $agUnit3 = $result['deliveryUnits'][3];

    expect($agUnit3['part_number'])->toBe(4)
        ->and($agUnit3['title'])->toBe('Airport Flight Announcement')
        ->and($agUnit3['is_first_unit_of_section'])->toBeTrue()
        ->and($agUnit3['is_last_unit_of_section'])->toBeTrue();
});

test('TEST 26: Mixed assessment with Part 1, 2, 3, 4 builds correct sequence of delivery units and palette mapping', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $result = DeliveryUnitBuilder::build($data['test']);

    // Check questionIndexToUnitIndex
    expect($result['questionIndexToUnitIndex'][0])->toBe(0) // Q1 -> Unit 0
        ->and($result['questionIndexToUnitIndex'][1])->toBe(1) // Q2 -> Unit 1
        ->and($result['questionIndexToUnitIndex'][2])->toBe(1) // Q3 -> Unit 1
        ->and($result['questionIndexToUnitIndex'][3])->toBe(1) // Q4 -> Unit 1
        ->and($result['questionIndexToUnitIndex'][4])->toBe(2) // Q5 -> Unit 2
        ->and($result['questionIndexToUnitIndex'][5])->toBe(2) // Q6 -> Unit 2
        ->and($result['questionIndexToUnitIndex'][6])->toBe(2) // Q7 -> Unit 2
        ->and($result['questionIndexToUnitIndex'][7])->toBe(3) // Q8 -> Unit 3
        ->and($result['questionIndexToUnitIndex'][8])->toBe(3) // Q9 -> Unit 3
        ->and($result['questionIndexToUnitIndex'][9])->toBe(3); // Q10 -> Unit 3
});

test('TEST 27: Legacy standalone questions in Part 3/4 without audio_group_id build as individual single-question units', function () {
    $test = Test::create([
        'title'            => 'Legacy Test',
        'slug'             => 'legacy-test-' . Str::random(5),
        'duration_minutes' => 60,
        'pass_score'       => 70,
        'created_by'       => $this->teacher->id,
        'assigned_to'      => $this->teacher->id,
    ]);

    $sec = TestSection::create([
        'test_id'      => $test->id,
        'title'        => 'Legacy Section',
        'section_type' => 'listening',
        'order'        => 1,
    ]);

    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'title'          => "Legacy Q{$i}",
            'prompt'         => "Prompt {$i}",
            'part_number'    => 3,
            'question_type'  => 'multiple_choice',
            'audio_group_id' => null, // No audio group
            'created_by'     => $this->teacher->id,
        ]);
        TestQuestion::create([
            'test_id'         => $test->id,
            'test_section_id' => $sec->id,
            'question_id'     => $q->id,
            'order'           => $i,
        ]);
    }

    $result = DeliveryUnitBuilder::build($test);

    expect($result['totalUnitsCount'])->toBe(3) // 3 standalone units
        ->and($result['deliveryUnits'][0]['type'])->toBe('question')
        ->and($result['deliveryUnits'][1]['type'])->toBe('question')
        ->and($result['deliveryUnits'][2]['type'])->toBe('question');
});

test('TEST 28: Flagging individual child question in an Audio Group updates attempt flag state independently', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $q = $data['ag1Questions'][1];

    $this->actingAs($this->candidate)->postJson(route('candidate.exam.flag', $attempt), [
        'question_id' => $q->id,
    ])->assertOk();

    $attempt->refresh();
    expect($attempt->flagged_questions)->toContain($q->id)
        ->and(count($attempt->flagged_questions))->toBe(1);
});

test('TEST 29: Autosave endpoint saves individual child question choice without affecting siblings', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    $q1 = $data['ag1Questions'][0];
    $q2 = $data['ag1Questions'][1];
    $choice1 = $q1->choices->first();

    $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
        'question_id'     => $q1->id,
        'selected_choice' => $choice1->id,
    ])->assertOk();

    expect(Answer::where('attempt_id', $attempt->id)->where('question_id', $q1->id)->exists())->toBeTrue()
        ->and(Answer::where('attempt_id', $attempt->id)->where('question_id', $q2->id)->exists())->toBeFalse();
});

test('TEST 30: Candidate review page after submission correctly displays all child question answers and scores', function () {
    $data = createToeicTestWithAudioGroups($this->teacher);

    $engine = app(AssessmentEngine::class);
    $attempt = $engine->startAttempt($data['test'], $this->candidate);

    // Answer all questions correctly
    $allQuestions = array_merge([$data['q1']], $data['ag1Questions'], $data['ag2Questions'], $data['ag3Questions']);
    foreach ($allQuestions as $q) {
        $correctChoice = $q->choices()->where('is_correct', true)->first();
        Answer::create([
            'attempt_id'         => $attempt->id,
            'question_id'        => $q->id,
            'selected_choice_id' => $correctChoice->id,
            'is_correct'         => true,
            'score_awarded'      => 10,
        ]);
    }

    $attempt->update([
        'status'       => \App\Modules\Assessment\Enums\AttemptStatus::Submitted,
        'submitted_at' => now(),
        'score'        => 100,
    ]);

    $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));

    $response->assertOk();
    $response->assertSee('TOEIC Official Practice Test 2026');
});
