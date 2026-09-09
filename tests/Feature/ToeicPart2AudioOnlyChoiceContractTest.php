<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\RepositoryQualityService;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create(['status' => 'active']);
    $this->student->assignRole('student');

    $this->test = Test::create([
        'title'            => 'TOEIC Mock Test Group UAT Contract',
        'slug'             => 'toeic-mock-contract-' . uniqid(),
        'type'             => 'simulator',
        'assessment_mode'  => 'simulator',
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 700,
        'status'           => 'draft',
        'is_published'     => false,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
        'assigned_to'      => $this->teacher->id,
    ]);

    $this->listeningSection = TestSection::create([
        'test_id'          => $this->test->id,
        'section_type'     => SectionType::Listening,
        'title'            => 'Listening Section',
        'order'            => 1,
    ]);

    $this->readingSection = TestSection::create([
        'test_id'          => $this->test->id,
        'section_type'     => SectionType::Reading,
        'title'            => 'Reading Section',
        'order'            => 2,
    ]);

    $this->audioAsset = MediaAsset::create([
        'title'         => 'Part 2 Combined Audio Clip',
        'filename'      => 'part2_sample.mp3',
        'original_name' => 'part2_sample.mp3',
        'mime_type'     => 'audio/mpeg',
        'size_bytes'    => 2048,
        'path'          => 'https://example.com/audio/part2_sample.mp3',
        'type'          => 'audio',
        'uploaded_by'   => $this->teacher->id,
    ]);
});

// TOEIC-P2-01: Part 2 blank text save success via TestBuilderController createAssessmentQuestion
test('TOEIC-P2-01: Part 2 saves successfully with 3 choices, blank choice text, and valid audio', function () {
    $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
        'test_section_id' => $this->listeningSection->id,
        'part_number'     => 2,
        'prompt'          => '',
        'question_type'   => 'multiple_choice',
        'audio_url'       => $this->audioAsset->path,
        'correct_choice'  => 1, // B
        'choices'         => ['', '', ''],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('questions', [
        'part_number' => 2,
        'section'     => 'listening',
    ]);

    $createdQ = Question::where('part_number', 2)->first();
    expect($createdQ)->not->toBeNull();
    expect($createdQ->choices)->toHaveCount(3);
    expect($createdQ->choices->pluck('label')->toArray())->toEqual(['A', 'B', 'C']);
    expect($createdQ->choices->where('is_correct', true)->first()->label)->toEqual('B');
});

// TOEIC-P2-02: Part 2 requires a correct answer (validation fails if missing)
test('TOEIC-P2-02: Part 2 with A/B/C but no correct answer fails validation', function () {
    $this->expectException(ValidationException::class);

    ToeicQuestionValidator::validate([
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio/p2.mp3',
        'correct_choice' => null,
        'choices'        => ['', '', ''],
    ]);
});

// TOEIC-P2-03: Part 2 enforces exactly three choices (A, B, C; D rejected, <3 rejected)
test('TOEIC-P2-03: Part 2 rejects 4 choices (choice D forbidden) and rejects fewer than 3 choices', function () {
    // 4 choices should fail
    $check4 = ToeicQuestionValidator::check([
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio/p2.mp3',
        'correct_choice' => 0,
        'choices'        => ['Option A', 'Option B', 'Option C', 'Option D'],
    ]);
    expect($check4['is_valid'])->toBeFalse();
    expect($check4['errors'])->toHaveKey('choices');

    // 2 choices should fail
    $check2 = ToeicQuestionValidator::check([
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio/p2.mp3',
        'correct_choice' => 0,
        'choices'        => ['', ''],
    ]);
    expect($check2['is_valid'])->toBeFalse();
    expect($check2['errors'])->toHaveKey('choices');

    // Exactly 3 choices with 1 correct passes
    $check3 = ToeicQuestionValidator::check([
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio/p2.mp3',
        'correct_choice' => 0,
        'choices'        => ['', '', ''],
    ]);
    expect($check3['is_valid'])->toBeTrue();
});

// TOEIC-P2-04: Part 2 with optional transcripts still saves correctly
test('TOEIC-P2-04: Part 2 with optional transcripts persists correctly for authoring reference', function () {
    $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
        'test_section_id' => $this->listeningSection->id,
        'part_number'     => 2,
        'prompt'          => 'Where is the nearest post office?',
        'question_type'   => 'multiple_choice',
        'audio_url'       => $this->audioAsset->path,
        'correct_choice'  => 0, // A
        'choices'         => [
            'Just around the corner on Elm Street.',
            'Yes, I sent the letter yesterday.',
            'No, at three o clock.',
        ],
    ]);

    $response->assertRedirect();
    $createdQ = Question::where('part_number', 2)->latest('id')->first();
    expect($createdQ->choices)->toHaveCount(3);
    expect($createdQ->choices->first()->content)->toEqual('Just around the corner on Elm Street.');
});

// TOEIC-P2-05: Candidate delivery displays selectable A, B, C choices
test('TOEIC-P2-05: Candidate CBT delivery renders selectable A, B, C choices without requiring choice text', function () {
    $q = Question::create([
        'prompt'        => '',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-cbt.mp3',
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'choice_text' => '', 'is_correct' => false, 'order' => 3]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->test->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p2-cbt-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
    $response->assertStatus(200);
    $response->assertSee('(A)');
    $response->assertSee('(B)');
    $response->assertSee('(C)');
    $response->assertDontSee('(D)');
});

// TOEIC-P2-06: Candidate delivery does not expose optional transcript
test('TOEIC-P2-06: Candidate CBT delivery does not expose optional response transcript text', function () {
    $secretTranscriptA = 'SECRET_INTERNAL_TRANSCRIPT_A_CORNER';
    $secretTranscriptB = 'SECRET_INTERNAL_TRANSCRIPT_B_YESTERDAY';
    $secretTranscriptC = 'SECRET_INTERNAL_TRANSCRIPT_C_THREE_OCLOCK';

    $q = Question::create([
        'prompt'        => 'Spoken statement in audio',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2-transcript.mp3',
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => $secretTranscriptA, 'choice_text' => $secretTranscriptA, 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => $secretTranscriptB, 'choice_text' => $secretTranscriptB, 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => $secretTranscriptC, 'choice_text' => $secretTranscriptC, 'is_correct' => false, 'order' => 3]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->test->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p2-trans-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
    $response->assertStatus(200);
    $response->assertDontSee($secretTranscriptA);
    $response->assertDontSee($secretTranscriptB);
    $response->assertDontSee($secretTranscriptC);
});

// TOEIC-P2-07: Part 1 audio-only choice behavior remains valid (A, B, C, D)
test('TOEIC-P2-07: Part 1 audio-only choices remain exactly 4 choices (A, B, C, D) with no regression', function () {
    $photoAsset = MediaAsset::create([
        'title'         => 'Part 1 Office Photo',
        'filename'      => 'photo.jpg',
        'original_name' => 'photo.jpg',
        'mime_type'     => 'image/jpeg',
        'size_bytes'    => 1024,
        'path'          => 'https://example.com/media/office-photo.jpg',
        'type'          => 'image',
        'uploaded_by'   => $this->teacher->id,
    ]);

    $checkP1 = ToeicQuestionValidator::check([
        'part_number'    => 1,
        'image_url'      => $photoAsset->path,
        'audio_url'      => 'https://example.com/audio/p1.mp3',
        'correct_choice' => 0,
        'choices'        => ['', '', '', ''],
    ]);

    expect($checkP1['is_valid'])->toBeTrue();
    expect(ToeicQuestionValidator::isAudioOnlyChoicePart(1))->toBeTrue();
    expect(ToeicQuestionValidator::isAudioOnlyChoicePart(2))->toBeTrue();
});

// TOEIC-P2-08: Text-based MCQ (Part 5) blank answer text remains INVALID
test('TOEIC-P2-08: TOEIC Part 5 text-based MCQ strictly rejects blank choices (no global weakening)', function () {
    $checkP5 = ToeicQuestionValidator::check([
        'part_number'    => 5,
        'prompt'         => 'The meeting will begin _____ 9:00 AM.',
        'correct_choice' => 0,
        'choices'        => ['', '', '', ''], // Blank choices for Part 5 must fail
    ]);

    expect($checkP5['is_valid'])->toBeFalse();
    expect($checkP5['errors'])->toHaveKey('choices');
    expect(ToeicQuestionValidator::requiresChoiceText(null, 5))->toBeTrue();
    expect(ToeicQuestionValidator::requiresChoiceText(null, 6))->toBeTrue();
    expect(ToeicQuestionValidator::requiresChoiceText(null, 7))->toBeTrue();
});

// TOEIC-P2-09: RM review accepts valid audio-only Part 2 without flagging it incomplete
test('TOEIC-P2-09: RepositoryQualityService does not flag valid Part 2 item as incomplete when choice text is blank', function () {
    $bank = QuestionBank::create([
        'title'       => 'TOEIC Part 2 Audio Bank',
        'slug'        => 'toeic-part-2-audio-bank',
        'test_type'   => 'toeic',
        'status'      => 'draft',
        'created_by'  => $this->teacher->id,
    ]);

    $q = Question::create([
        'question_bank_id' => $bank->id,
        'prompt'           => '',
        'section'          => 'listening',
        'part_number'      => 2,
        'question_type'    => 'multiple_choice',
        'difficulty'       => 'medium',
        'audio_url'        => $this->audioAsset->path,
        'points'           => 1,
        'explanation'      => 'Response B correctly answers the where question.',
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'is_correct' => false, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => '', 'is_correct' => true, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => '', 'is_correct' => false, 'order' => 3]);

    $qualityService = app(RepositoryQualityService::class);
    $report = $qualityService->validateRepository($bank);

    expect($report['scores']['questions'])->toEqual(100);
    $warnings = collect($report['warnings']);
    expect($warnings->filter(fn($w) => str_contains($w, 'missing prompt') || str_contains($w, 'incomplete') || str_contains($w, 'answer choices'))->isEmpty())->toBeTrue();
});

// P2-LIVE-01: Actual browser-shaped trailing blank D payload => normalized => SAVE PASS
test('P2-LIVE-01: Actual browser-shaped trailing blank D payload is normalized to 3 choices and saves successfully', function () {
    $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
        'test_section_id' => $this->listeningSection->id,
        'part_number'     => 2,
        'prompt'          => '',
        'question_type'   => 'multiple_choice',
        'audio_url'       => $this->audioAsset->path,
        'correct_choice'  => 1, // B
        'choices'         => ['', '', '', ''], // 4th trailing blank choice from browser form
    ]);

    $response->assertRedirect();
    $savedQ = Question::where('part_number', 2)->latest('id')->first();
    expect($savedQ)->not->toBeNull();
    expect($savedQ->choices)->toHaveCount(3);
    expect($savedQ->choices->pluck('label')->toArray())->toEqual(['A', 'B', 'C']);
    expect($savedQ->choices->where('is_correct', true)->first()->label)->toEqual('B');
});

// P2-LIVE-02: Direct A/B/C-only payload => PASS
test('P2-LIVE-02: Direct 3-choice A/B/C payload saves cleanly', function () {
    $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
        'test_section_id' => $this->listeningSection->id,
        'part_number'     => 2,
        'prompt'          => '',
        'question_type'   => 'multiple_choice',
        'audio_url'       => $this->audioAsset->path,
        'correct_choice'  => 2, // C
        'choices'         => ['', '', ''],
    ]);

    $response->assertRedirect();
    $savedQ = Question::where('part_number', 2)->latest('id')->first();
    expect($savedQ->choices)->toHaveCount(3);
    expect($savedQ->choices->where('is_correct', true)->first()->label)->toEqual('C');
});

// P2-LIVE-03: Non-empty D => FAIL
test('P2-LIVE-03: Non-empty fourth choice D strictly fails Part 2 validation', function () {
    $check = ToeicQuestionValidator::check([
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio/p2.mp3',
        'correct_choice' => 0,
        'choices'        => ['', '', '', 'Fourth spoken transcript'],
    ]);

    expect($check['is_valid'])->toBeFalse();
    expect($check['errors'])->toHaveKey('choices');
});

// P2-LIVE-04: D selected correct => FAIL
test('P2-LIVE-04: Choice D selected as correct answer strictly fails Part 2 validation', function () {
    $check = ToeicQuestionValidator::check([
        'part_number'    => 2,
        'audio_url'      => 'https://example.com/audio/p2.mp3',
        'correct_choice' => 3, // D is forbidden
        'choices'        => ['', '', '', ''],
    ]);

    expect($check['is_valid'])->toBeFalse();
});

// P2-LIVE-05: Correct B remains B after normalization => PASS
test('P2-LIVE-05: Correct choice B mapping remains intact after phantom D normalization', function () {
    $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
        'test_section_id' => $this->listeningSection->id,
        'part_number'     => 2,
        'prompt'          => '',
        'question_type'   => 'multiple_choice',
        'audio_url'       => $this->audioAsset->path,
        'correct_choice'  => '1', // B
        'choices'         => ['', '', '', ''],
    ]);

    $response->assertRedirect();
    $q = Question::where('part_number', 2)->latest('id')->first();
    expect($q->choices)->toHaveCount(3);
    $correct = $q->choices->where('is_correct', true)->first();
    expect($correct->label)->toEqual('B');
});

// P2-LIVE-06: Part 2 without audio => FAIL
test('P2-LIVE-06: Part 2 without audio attachment fails validation', function () {
    $check = ToeicQuestionValidator::check([
        'part_number'    => 2,
        'audio_url'      => null,
        'correct_choice' => 0,
        'choices'        => ['', '', ''],
    ]);

    expect($check['is_valid'])->toBeFalse();
    expect($check['errors'])->toHaveKey('audio_url');
});

// P2-LIVE-07 & P2-LIVE-08: Authoring UI contracts
test('P2-LIVE-07 & 08: Authoring UI modal markup contains Part 2 specific media controls and audio required hints', function () {
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
    $response->assertStatus(200);
    $response->assertSee('id="create-q-media-section"', false);
    $response->assertSee('id="q-image-controls-wrapper"', false);
    $response->assertSee('id="q-audio-controls-wrapper"', false);
    $response->assertSee('Required for Part 2');
});

// P2-LIVE-15: Part switch scenario and stale image sanitization
test('P2-LIVE-15: Part 2 creation sanitizes image_url to null even if stale image was submitted in payload', function () {
    $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
        'test_section_id' => $this->listeningSection->id,
        'part_number'     => 2,
        'prompt'          => '',
        'question_type'   => 'multiple_choice',
        'audio_url'       => $this->audioAsset->path,
        'image_url'       => 'https://example.com/stale-image-from-part1.jpg',
        'correct_choice'  => 0,
        'choices'         => ['', '', '', ''],
    ]);

    $response->assertRedirect();
    $savedQ = Question::where('part_number', 2)->latest('id')->first();
    expect($savedQ->image_url)->toBeNull();
    expect($savedQ->image_media_asset_id)->toBeNull();
    expect($savedQ->audio_url)->toEqual($this->audioAsset->path);
});
