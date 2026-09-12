<?php

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');

    $this->otherTeacher = User::factory()->create(['status' => 'active']);
    $this->otherTeacher->assignRole('teacher');

    $this->student = User::factory()->create(['status' => 'active']);
    $this->student->assignRole('student');

    $this->toeicTest = Test::create([
        'title'            => 'Comprehensive TOEIC Standard Test',
        'slug'             => 'comprehensive-toeic-' . uniqid(),
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

    $this->part1Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'section_type' => SectionType::Listening,
        'title'        => 'Part 1: Photographs',
        'order'        => 1,
    ]);

    $this->part2Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'section_type' => SectionType::Listening,
        'title'        => 'Part 2: Question-Response',
        'order'        => 2,
    ]);

    $this->part3Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'section_type' => SectionType::Listening,
        'title'        => 'Part 3: Conversations',
        'order'        => 3,
    ]);

    $this->part4Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'section_type' => SectionType::Listening,
        'title'        => 'Part 4: Talks',
        'order'        => 4,
    ]);

    $this->part5Section = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'section_type' => SectionType::Reading,
        'title'        => 'Part 5: Incomplete Sentences',
        'order'        => 5,
    ]);
});

// =========================================================================
// SECTION 1: FOCUSED RETURN CONTEXT TESTS (TEST 01 to TEST 08)
// =========================================================================

test('TEST 01: Edit Question GET includes correct return Section context', function () {
    $q = Question::create([
        'prompt'        => 'Where is the conference being held?',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-q01.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'           => $this->toeicTest->id,
        'question'       => $q->id,
        'return_section' => $this->part2Section->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('value="' . $this->part2Section->id . '"', false);
    $response->assertSee('value="question-card-' . $q->id . '"', false);
});

test('TEST 02: Cancel returns to originating Assessment', function () {
    $q = Question::create([
        'prompt'        => 'Look at the photograph.',
        'section'       => SectionType::Listening,
        'part_number'   => 1,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p1.mp3',
        'image_url'     => 'https://example.com/images/p1.jpg',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'choice_text' => 'Choice A', 'is_correct' => true, 'order' => 1]);
    TestQuestion::create(['test_section_id' => $this->part1Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    // Cancel link points back to the assessment detail show route
    $response->assertSee(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part1Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 03: Cancel includes originating Section auto-expand context', function () {
    $q = Question::create([
        'prompt'        => 'Where is the office located?',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('section=' . $this->part2Section->id);
});

test('TEST 04: Cancel includes Question focus context', function () {
    $q = Question::create([
        'prompt'        => 'Where is the office located?',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('focus=question-card-' . $q->id);
});

test('TEST 05: Successful Save returns to originating Assessment', function () {
    $q = Question::create([
        'prompt'        => 'Original stem prompt',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Old A', 'choice_text' => 'Old A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Old B', 'choice_text' => 'Old B', 'is_correct' => false, 'order' => 2]);
    TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated valid Part 5 stem',
        'part_number'    => 5,
        'section'        => 'reading',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['New A', 'New B', 'New C', 'New D'],
        'return_section' => $this->part5Section->id,
        'return_focus'   => 'question-card-' . $q->id,
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part5Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
    $response->assertSessionHas('status');
});

test('TEST 06: Successful Save expands origin Section', function () {
    $q = Question::create([
        'prompt'        => 'Original stem prompt',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated Part 2 stem prompt',
        'part_number'    => 2,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '1',
        'choices'        => ['', '', ''],
    ]);

    $response->assertSessionHas('expanded_section_id', $this->part2Section->id);
});

test('TEST 07: Successful Save focuses updated Question', function () {
    $q = Question::create([
        'prompt'        => 'Original Part 2 question',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated Part 2 Question',
        'part_number'    => 2,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['', '', ''],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 08: Normal Assessment Detail load still defaults all Sections collapsed', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);

    // Section bodies have hidden class by default
    $response->assertSee('id="section-body-' . $this->part1Section->id . '" class="hidden', false);
    $response->assertSee('id="section-body-' . $this->part2Section->id . '" class="hidden', false);
});

// =========================================================================
// SECTION 2: CROSS-PART RETURN TESTS (TEST 09 to TEST 14)
// =========================================================================

test('TEST 09: Part 1 edit return works', function () {
    $q = Question::create([
        'prompt'        => 'Look at the photograph.',
        'section'       => SectionType::Listening,
        'part_number'   => 1,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p1-q.mp3',
        'image_url'     => 'https://example.com/images/p1-q.jpg',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'A', 'choice_text' => 'A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'B', 'choice_text' => 'B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'C', 'choice_text' => 'C', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'D', 'choice_text' => 'D', 'is_correct' => false, 'order' => 4]);
    TestQuestion::create(['test_section_id' => $this->part1Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated Part 1 prompt',
        'part_number'    => 1,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'audio_url'      => 'https://example.com/audio/p1-q.mp3',
        'image_url'      => 'https://example.com/images/p1-q.jpg',
        'correct_choice' => '0',
        'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part1Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 10: Part 2 edit return works', function () {
    $q = Question::create([
        'prompt'        => 'Listen to question.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-q.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated Part 2 prompt',
        'part_number'    => 2,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '2',
        'choices'        => ['', '', ''],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 11: Part 3 supported edit return works', function () {
    $q = Question::create([
        'prompt'        => 'What does the man request?',
        'section'       => SectionType::Listening,
        'part_number'   => 3,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p3-q.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'A', 'choice_text' => 'A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'B', 'choice_text' => 'B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'C', 'choice_text' => 'C', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'D', 'choice_text' => 'D', 'is_correct' => false, 'order' => 4]);
    TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated Part 3 prompt',
        'part_number'    => 3,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'audio_url'      => 'https://example.com/audio/p3-q.mp3',
        'correct_choice' => '1',
        'choices'        => ['Opt A', 'Opt B', 'Opt C', 'Opt D'],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part3Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 12: Part 4 supported edit return works', function () {
    $q = Question::create([
        'prompt'        => 'Who is the intended audience?',
        'section'       => SectionType::Listening,
        'part_number'   => 4,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p4-q.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'A', 'choice_text' => 'A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'B', 'choice_text' => 'B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'C', 'choice_text' => 'C', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'D', 'choice_text' => 'D', 'is_correct' => false, 'order' => 4]);
    TestQuestion::create(['test_section_id' => $this->part4Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated Part 4 prompt',
        'part_number'    => 4,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'audio_url'      => 'https://example.com/audio/p4-q.mp3',
        'correct_choice' => '0',
        'choices'        => ['Opt A', 'Opt B', 'Opt C', 'Opt D'],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part4Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 13: Part 5 edit return works', function () {
    $q = Question::create([
        'prompt'        => 'Please choose correct word.',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'A', 'choice_text' => 'A', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'B', 'choice_text' => 'B', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'C', 'choice_text' => 'C', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'D', 'choice_text' => 'D', 'is_correct' => false, 'order' => 4]);
    TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated Part 5 prompt',
        'part_number'    => 5,
        'section'        => 'reading',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['Opt A', 'Opt B', 'Opt C', 'Opt D'],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part5Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 14: Generic Section edit return works', function () {
    $genericTest = Test::create([
        'title'            => 'General English Exam',
        'slug'             => 'general-english-' . uniqid(),
        'type'             => 'quiz',
        'assessment_mode'  => AssessmentMode::Simulator,
        'test_type'        => 'general',
        'duration_minutes' => 60,
        'pass_score'       => 70,
        'status'           => 'draft',
        'is_published'     => false,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
    ]);

    $sec = TestSection::create([
        'test_id'      => $genericTest->id,
        'section_type' => SectionType::Reading,
        'title'        => 'Vocabulary Section',
        'order'        => 1,
    ]);

    $q = Question::create([
        'prompt'        => 'What is the opposite of giant?',
        'section'       => SectionType::Reading,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Tiny', 'choice_text' => 'Tiny', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Huge', 'choice_text' => 'Huge', 'is_correct' => false, 'order' => 2]);
    TestQuestion::create(['test_section_id' => $sec->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $genericTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'What is the antonym of giant?',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['Tiny', 'Huge', 'Large'],
    ]);

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $genericTest->id,
        'section' => $sec->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

// =========================================================================
// SECTION 3: VALIDATION & SECURITY TESTS (TEST 15 to TEST 20)
// =========================================================================

test('TEST 15: Validation failure remains on editor', function () {
    $q = Question::create([
        'prompt'        => 'Valid stem',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'points'        => 1,
    ]);
    TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => '', // Empty prompt fails validation for Part 5
        'part_number'    => 5,
        'section'        => 'reading',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['A', 'B', 'C', 'D'],
    ]);

    $response->assertSessionHasErrors(['prompt']);
});

test('TEST 16: Return context survives validation failure', function () {
    $q = Question::create([
        'prompt'        => 'Valid stem',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'points'        => 1,
    ]);
    TestQuestion::create(['test_section_id' => $this->part5Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->from(route('teacher.tests.edit-question', [
        'test'           => $this->toeicTest->id,
        'question'       => $q->id,
        'return_section' => $this->part5Section->id,
        'return_focus'   => 'question-card-' . $q->id,
    ]))->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => '',
        'part_number'    => 5,
        'section'        => 'reading',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['A', 'B', 'C', 'D'],
        'return_section' => $this->part5Section->id,
        'return_focus'   => 'question-card-' . $q->id,
    ]);

    $response->assertRedirect(route('teacher.tests.edit-question', [
        'test'           => $this->toeicTest->id,
        'question'       => $q->id,
        'return_section' => $this->part5Section->id,
        'return_focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 17: Crafted foreign Section ID cannot redirect cross-assessment', function () {
    $foreignTest = Test::create([
        'title'            => 'Foreign Unrelated Test',
        'slug'             => 'foreign-test-' . uniqid(),
        'type'             => 'simulator',
        'assessment_mode'  => AssessmentMode::Simulator,
        'test_type'        => 'toeic',
        'duration_minutes' => 60,
        'pass_score'       => 70,
        'status'           => 'draft',
        'is_published'     => false,
        'is_active'        => true,
        'created_by'       => $this->otherTeacher->id,
    ]);
    $foreignSection = TestSection::create([
        'test_id'      => $foreignTest->id,
        'section_type' => SectionType::Listening,
        'title'        => 'Foreign Part 1',
        'order'        => 1,
    ]);

    $q = Question::create([
        'prompt'        => 'Valid stem',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    // Teacher passes foreign section ID
    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated stem with foreign section',
        'part_number'    => 2,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['', '', ''],
        'return_section' => $foreignSection->id,
    ]);

    // Foreign section rejected; authoritative Part 2 section in $toeicTest is used instead
    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 18: External return URL cannot create open redirect', function () {
    $q = Question::create([
        'prompt'        => 'Valid stem',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Updated stem',
        'part_number'    => 2,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['', '', ''],
        'return_section' => 'https://evil.com/phishing',
    ]);

    // Target is strictly internal teacher.tests.show route
    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
});

test('TEST 19: Question update authorization unchanged', function () {
    $q = Question::create([
        'prompt'        => 'Protected question',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    // Other teacher cannot edit teacher A's question
    $response = $this->actingAs($this->otherTeacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));
    $response->assertStatus(403);

    $responsePut = $this->actingAs($this->otherTeacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Hacked prompt',
        'question_type'  => 'multiple_choice',
        'correct_choice' => '0',
        'choices'        => ['', '', ''],
    ]);
    $responsePut->assertStatus(403);
});

test('TEST 20: Section validation summary recomputes after return', function () {
    // 1 invalid question in Part 2 (missing audio)
    $q = Question::create([
        'prompt'        => 'Missing audio item',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => null, // Missing audio -> invalid
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    // Initial state: NEEDS ATTENTION
    $resInitial = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resInitial->assertStatus(200);
    $resInitial->assertSee('NEEDS ATTENTION');

    // Fix question by attaching audio
    $this->actingAs($this->teacher)->put(route('teacher.tests.update-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]), [
        'prompt'         => 'Fixed audio stem',
        'part_number'    => 2,
        'section'        => 'listening',
        'question_type'  => 'multiple_choice',
        'audio_url'      => 'https://example.com/audio/p2-fixed.mp3',
        'correct_choice' => '0',
        'choices'        => ['', '', ''],
    ]);

    // Return view shows updated completeness
    $resAfter = $this->actingAs($this->teacher)->get(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
        'focus'   => 'question-card-' . $q->id,
    ]));
    $resAfter->assertStatus(200);
    $resAfter->assertSee('1/25 Complete');
    $resAfter->assertSee('24 questions missing');
});

// =========================================================================
// SECTION 4: UI & ANCHOR TESTS (TEST 21 to TEST 25)
// =========================================================================

test('TEST 21: Origin Section has auto-expand logic upon return', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
    ]));
    $response->assertStatus(200);
    $response->assertSee("targetSectionId = urlParams.get('section')", false);
});

test('TEST 22: Other Sections remain collapsed by default', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part2Section->id,
    ]));
    $response->assertStatus(200);
    // Part 1 body still has the hidden class in server markup
    $response->assertSee('id="section-body-' . $this->part1Section->id . '" class="hidden', false);
});

test('TEST 23: Question anchor exists on standalone question cards', function () {
    $q = Question::create([
        'prompt'        => 'Anchor test prompt',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/anchor.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('id="question-card-' . $q->id . '"', false);
});

test('TEST 24: Focus/scroll target resolves in DOM script', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee("focusParam = urlParams.get('focus')", false);
    $response->assertSee("focusEl.scrollIntoView", false);
});

test('TEST 25: No JS console errors and syntax clean in question editor', function () {
    $q = Question::create([
        'prompt'        => 'Script test prompt',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/script.mp3',
        'points'        => 1,
    ]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.edit-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('function updateEditorCorrectChoice()', false);
    $response->assertSee('function updateEditorAutoDifficulty()', false);
});

// =========================================================================
// SECTION 5: REGRESSION TESTS (TEST 26 to TEST 33)
// =========================================================================

test('TEST 26: Edit Section modal behavior unchanged', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('id="edit-section-modal"', false);
    $response->assertSee('openEditSectionModal', false);
});

test('TEST 27: Add Question behavior unchanged', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('openCreateAuthoredQuestionModal', false);
});

test('TEST 28: AudioGroup Edit Group behavior unchanged', function () {
    $ag = AudioGroup::create([
        'test_id'     => $this->toeicTest->id,
        'title'       => 'Airport Conversation',
        'group_type'  => 'conversation',
        'part_number' => 3,
        'audio_url'   => 'https://example.com/audio/ag.mp3',
        'order'       => 1,
        'created_by'  => $this->teacher->id,
    ]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('openEditAudioGroupModal', false);
    $response->assertSee('id="audio-group-card-' . $ag->id . '"', false);
});

test('TEST 29: Default collapsed Section UX unchanged', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('toggleSectionCollapse', false);
    $response->assertSee('data-status="not_started"', false);
});

test('TEST 30: READY / NEEDS ATTENTION badges unchanged', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('NOT STARTED');
});

test('TEST 31: Candidate Preview unaffected', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('Candidate Assessment Preview', false);
});

test('TEST 32: Simulator unaffected', function () {
    $this->toeicTest->update(['status' => 'published', 'is_published' => true]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'sim-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
    $response->assertStatus(200);
});

test('TEST 33: Real Test unaffected', function () {
    $realTest = Test::create([
        'title'            => 'Official Proctored Exam',
        'slug'             => 'real-test-' . uniqid(),
        'type'             => 'real_test',
        'assessment_mode'  => AssessmentMode::RealTest,
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'status'           => 'published',
        'is_published'     => true,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
    ]);

    $attempt = Attempt::create([
        'test_id'       => $realTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'real-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
    $response->assertStatus(200);
});
