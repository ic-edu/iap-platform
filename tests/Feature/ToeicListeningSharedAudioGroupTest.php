<?php

use App\Models\MediaAsset;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\ToeicQuestionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');
    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');
    $this->student = User::factory()->create(['status' => 'active']);
    $this->student->assignRole('student');
});

test('1. Create Part 3 conversation audio group with one audio asset and exactly 3 questions', function () {
    $bank = QuestionBank::create([
        'title'      => 'TOEIC Listening Bank',
        'slug'       => 'toeic-listening-bank-' . uniqid(),
        'test_type'  => TestType::Toeic,
        'status'     => 'draft',
        'created_by' => $this->teacher->id,
    ]);

    $media = MediaAsset::create([
        'title'           => 'Conversation 1 Audio',
        'original_name'   => 'conversation1.mp3',
        'filename'        => 'conversation1.mp3',
        'path'            => 'media/conversation1.mp3',
        'type'            => 'audio',
        'mime_type'       => 'audio/mpeg',
        'size_bytes'      => 1024000,
        'exam_type'       => 'toeic',
        'approval_status' => 'approved',
        'uploaded_by'     => $this->teacher->id,
    ]);

    $payload = [
        'title'          => 'Office Schedule Conversation',
        'group_type'     => 'conversation',
        'part_number'    => 3,
        'media_asset_id' => $media->id,
        'audio_url'      => null,
        'questions'      => [
            [
                'prompt'         => 'What are the speakers mainly discussing?',
                'difficulty'     => 'medium',
                'choices'        => ['Meeting schedule', 'Budget report', 'Office supplies', 'Travel plans'],
                'correct_choice' => 0,
            ],
            [
                'prompt'         => 'When will the conference take place?',
                'difficulty'     => 'easy',
                'choices'        => ['On Monday', 'On Wednesday', 'On Friday', 'Next month'],
                'correct_choice' => 1,
            ],
            [
                'prompt'         => 'What does the man suggest doing?',
                'difficulty'     => 'hard',
                'choices'        => ['Call the client', 'Send an email', 'Book a room', 'Print handouts'],
                'correct_choice' => 2,
            ],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('admin.question-banks.store-audio-group', $bank->id), $payload);

    $response->assertRedirect(route('admin.question-banks.show', $bank->id));

    // Assert AudioGroup was created
    $audioGroup = AudioGroup::where('question_bank_id', $bank->id)->first();
    expect($audioGroup)->not->toBeNull()
        ->and($audioGroup->group_type)->toBe('conversation')
        ->and($audioGroup->part_number)->toBe(3)
        ->and($audioGroup->media_asset_id)->toBe($media->id);

    // Assert exactly 3 questions reference this audio group
    $questions = Question::where('audio_group_id', $audioGroup->id)->get();
    expect($questions->count())->toBe(3);

    foreach ($questions as $q) {
        expect($q->section->value ?? $q->section)->toBe('listening')
            ->and($q->part_number)->toBe(3)
            ->and($q->choices->count())->toBe(4);
    }
});

test('2. ToeicQuestionValidator validates complete Part 3 and Part 4 audio groups', function () {
    $validGroupData = [
        'group_type'  => 'conversation',
        'part_number' => 3,
        'audio_url'   => 'https://example.com/conv.mp3',
    ];

    $validQuestions = [
        ['prompt' => 'Q1?', 'difficulty' => 'easy', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'Q2?', 'difficulty' => 'medium', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ['prompt' => 'Q3?', 'difficulty' => 'hard', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
    ];

    $check = ToeicQuestionValidator::checkAudioGroup($validGroupData, $validQuestions);
    expect($check['is_valid'])->toBeTrue();
});

test('3. Audio group fails validation with 1 question, 2 questions, or 4 questions', function () {
    $baseGroup = [
        'group_type'  => 'conversation',
        'part_number' => 3,
        'audio_url'   => 'https://example.com/conv.mp3',
    ];

    $qTemplate = ['prompt' => 'Q?', 'difficulty' => 'medium', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0];

    // 1 question -> Fail
    $oneQ = ToeicQuestionValidator::checkAudioGroup($baseGroup, [$qTemplate]);
    expect($oneQ['is_valid'])->toBeFalse()
        ->and($oneQ['errors'])->toHaveKey('question_count');

    // 2 questions -> Fail
    $twoQ = ToeicQuestionValidator::checkAudioGroup($baseGroup, [$qTemplate, $qTemplate]);
    expect($twoQ['is_valid'])->toBeFalse()
        ->and($twoQ['errors'])->toHaveKey('question_count');

    // 4 questions -> Fail
    $fourQ = ToeicQuestionValidator::checkAudioGroup($baseGroup, [$qTemplate, $qTemplate, $qTemplate, $qTemplate]);
    expect($fourQ['is_valid'])->toBeFalse()
        ->and($fourQ['errors'])->toHaveKey('question_count');
});

test('4. Part 4 talk group behaves identically with group_type=talk and part_number=4', function () {
    $talkGroupData = [
        'group_type'  => 'talk',
        'part_number' => 4,
        'audio_url'   => 'https://example.com/talk.mp3',
    ];

    $talkQuestions = [
        ['prompt' => 'Who is the speaker?', 'difficulty' => 'easy', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
        ['prompt' => 'What is announced?', 'difficulty' => 'medium', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
        ['prompt' => 'What should listeners do?', 'difficulty' => 'hard', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
    ];

    $check = ToeicQuestionValidator::checkAudioGroup($talkGroupData, $talkQuestions);
    expect($check['is_valid'])->toBeTrue()
        ->and($check['part_number'])->toBe(4)
        ->and($check['group_type'])->toBe('talk');

    // Missing audio in group -> Fail
    $noAudioGroup = $talkGroupData;
    unset($noAudioGroup['audio_url']);
    expect(ToeicQuestionValidator::checkAudioGroup($noAudioGroup, $talkQuestions)['is_valid'])->toBeFalse();
});

test('5. Assessment Builder createAudioGroup creates group and attaches 3 questions to section', function () {
    $service = app(TestBuilderService::class);

    $test = Test::create([
        'title'            => 'TOEIC Mock Test',
        'slug'             => 'toeic-mock-test-' . uniqid(),
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
        'status'           => 'draft',
    ]);

    $section = TestSection::create([
        'test_id'          => $test->id,
        'title'            => 'Listening Section',
        'section_type'     => 'listening',
        'order'            => 1,
        'duration_minutes' => 45,
    ]);

    $groupPayload = [
        'test_section_id' => $section->id,
        'title'           => 'Airport Announcement',
        'group_type'      => 'talk',
        'part_number'     => 4,
        'audio_url'       => 'https://example.com/airport.mp3',
        'questions'       => [
            ['prompt' => 'Where is the announcement made?', 'difficulty' => 'easy', 'choices' => ['At an airport', 'At a station', 'In a hotel', 'In a store'], 'correct_choice' => 0],
            ['prompt' => 'Why is the flight delayed?', 'difficulty' => 'medium', 'choices' => ['Bad weather', 'Mechanical problem', 'Crew shortage', 'Traffic'], 'correct_choice' => 0],
            ['prompt' => 'What are passengers asked to do?', 'difficulty' => 'hard', 'choices' => ['Go to Gate 12', 'Collect luggage', 'Wait in lounge', 'Call airline'], 'correct_choice' => 0],
        ],
    ];

    $response = $this->actingAs($this->teacher)
        ->post(route('teacher.tests.create-audio-group', $test->id), $groupPayload);

    $response->assertRedirect(route('teacher.tests.show', $test->id));

    $audioGroup = AudioGroup::where('test_id', $test->id)->first();
    expect($audioGroup)->not->toBeNull()
        ->and($audioGroup->part_number)->toBe(4);

    $tqs = $section->testQuestions()->with('question')->get();
    expect($tqs->count())->toBe(3);

    foreach ($tqs as $tq) {
        expect($tq->question->audio_group_id)->toBe($audioGroup->id)
            ->and($tq->question->part_number)->toBe(4)
            ->and($tq->question->section->value ?? $tq->question->section)->toBe('listening');
    }
});

test('6. Editing one question preserves sibling group membership', function () {
    $bank = QuestionBank::create([
        'title'      => 'TOEIC Bank',
        'slug'       => 'toeic-bank-' . uniqid(),
        'test_type'  => TestType::Toeic,
        'status'     => 'draft',
        'created_by' => $this->teacher->id,
    ]);

    $audioGroup = AudioGroup::create([
        'question_bank_id' => $bank->id,
        'title'            => 'Conversation Group',
        'group_type'       => 'conversation',
        'part_number'      => 3,
        'audio_url'        => 'https://example.com/conv.mp3',
        'created_by'       => $this->teacher->id,
    ]);

    $q1 = Question::create([
        'question_bank_id' => $bank->id,
        'audio_group_id'   => $audioGroup->id,
        'prompt'           => 'Initial Q1 Prompt',
        'section'          => SectionType::Listening,
        'part_number'      => 3,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
        'created_by'       => $this->teacher->id,
    ]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    $q2 = Question::create([
        'question_bank_id' => $bank->id,
        'audio_group_id'   => $audioGroup->id,
        'prompt'           => 'Initial Q2 Prompt',
        'section'          => SectionType::Listening,
        'part_number'      => 3,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
        'created_by'       => $this->teacher->id,
    ]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    $q3 = Question::create([
        'question_bank_id' => $bank->id,
        'audio_group_id'   => $audioGroup->id,
        'prompt'           => 'Initial Q3 Prompt',
        'section'          => SectionType::Listening,
        'part_number'      => 3,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
        'created_by'       => $this->teacher->id,
    ]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    // Update Q1 prompt and choices
    $this->actingAs($this->teacher)->put(route('admin.question-banks.update-question', $q1->id), [
        'prompt'         => 'Updated Q1 Prompt',
        'part_number'    => 3,
        'difficulty'     => 'hard',
        'question_type'  => 'multiple_choice',
        'choices'        => [
            ['content' => 'Updated A', 'is_correct' => 1],
            ['content' => 'Updated B', 'is_correct' => 0],
            ['content' => 'Updated C', 'is_correct' => 0],
            ['content' => 'Updated D', 'is_correct' => 0],
        ],
    ]);

    $q1->refresh();
    $q2->refresh();
    $q3->refresh();

    expect($q1->prompt)->toBe('Updated Q1 Prompt')
        ->and($q1->audio_group_id)->toBe($audioGroup->id)
        ->and($q2->audio_group_id)->toBe($audioGroup->id)
        ->and($q3->audio_group_id)->toBe($audioGroup->id)
        ->and($audioGroup->questions()->count())->toBe(3);
});

test('7. Candidate Mock Test audio playback enforces 1 play per shared audio group across Q1, Q2, Q3', function () {
    $test = Test::create([
        'title'            => 'Real TOEIC Exam',
        'slug'             => 'real-toeic-exam-' . uniqid(),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'real_test',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
        'is_published'     => true,
        'status'           => 'published',
    ]);

    $section = TestSection::create([
        'test_id'          => $test->id,
        'title'            => 'Listening Section',
        'section_type'     => 'listening',
        'order'            => 1,
        'duration_minutes' => 45,
    ]);

    $audioGroup = AudioGroup::create([
        'test_id'     => $test->id,
        'title'       => 'Shared Talk Group',
        'group_type'  => 'talk',
        'part_number' => 4,
        'audio_url'   => 'https://example.com/shared_talk.mp3',
        'created_by'  => $this->teacher->id,
    ]);

    $q1 = Question::create([
        'audio_group_id' => $audioGroup->id,
        'prompt'         => 'Group Q1',
        'section'        => SectionType::Listening,
        'part_number'    => 4,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
        'created_by'     => $this->teacher->id,
    ]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    $q2 = Question::create([
        'audio_group_id' => $audioGroup->id,
        'prompt'         => 'Group Q2',
        'section'        => SectionType::Listening,
        'part_number'    => 4,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
        'created_by'     => $this->teacher->id,
    ]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    $q3 = Question::create([
        'audio_group_id' => $audioGroup->id,
        'prompt'         => 'Group Q3',
        'section'        => SectionType::Listening,
        'part_number'    => 4,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
        'created_by'     => $this->teacher->id,
    ]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    $section->testQuestions()->create(['question_id' => $q1->id, 'order' => 1]);
    $section->testQuestions()->create(['question_id' => $q2->id, 'order' => 2]);
    $section->testQuestions()->create(['question_id' => $q3->id, 'order' => 3]);

    $attempt = Attempt::create([
        'test_id'    => $test->id,
        'user_id'    => $this->student->id,
        'status'     => 'in_progress',
        'started_at' => now(),
    ]);

    // 1. Play audio for Q1 in Real Test mode -> Success (redirects to audio URL or streams)
    $resp1 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
    expect($resp1->status())->toBeIn([200, 302]);

    // 2. Play record exists for all questions in the group
    expect(AttemptAudioPlay::where('attempt_id', $attempt->id)->where('question_id', $q1->id)->count())->toBe(1)
        ->and(AttemptAudioPlay::where('attempt_id', $attempt->id)->where('question_id', $q2->id)->count())->toBe(1)
        ->and(AttemptAudioPlay::where('attempt_id', $attempt->id)->where('question_id', $q3->id)->count())->toBe(1);

    // 3. Attempting to play audio again on Q2 or Q3 -> Blocked (403)
    $resp2 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q2]));
    $resp2->assertStatus(403);

    $resp3 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q3]));
    $resp3->assertStatus(403);
});

test('8. TOEIC scoring counts each grouped question independently (3 questions = 3 points)', function () {
    $test = Test::create([
        'title'            => 'TOEIC Scoring Exam',
        'slug'             => 'toeic-scoring-exam-' . uniqid(),
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
    ]);

    $section = TestSection::create([
        'test_id'          => $test->id,
        'title'            => 'Listening Section',
        'section_type'     => 'listening',
        'order'            => 1,
        'duration_minutes' => 45,
    ]);

    $audioGroup = AudioGroup::create([
        'test_id'     => $test->id,
        'title'       => 'Scoring Group',
        'group_type'  => 'conversation',
        'part_number' => 3,
        'audio_url'   => 'https://example.com/conv.mp3',
    ]);

    $q1 = Question::create([
        'audio_group_id' => $audioGroup->id,
        'prompt'         => 'Q1',
        'section'        => SectionType::Listening,
        'part_number'    => 3,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
        'points'         => 1,
    ]);
    $c1Correct = QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    $c1Wrong   = QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);

    $q2 = Question::create([
        'audio_group_id' => $audioGroup->id,
        'prompt'         => 'Q2',
        'section'        => SectionType::Listening,
        'part_number'    => 3,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
        'points'         => 1,
    ]);
    $c2Correct = QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    $c2Wrong   = QuestionChoice::create(['question_id' => $q2->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);

    $q3 = Question::create([
        'audio_group_id' => $audioGroup->id,
        'prompt'         => 'Q3',
        'section'        => SectionType::Listening,
        'part_number'    => 3,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
        'points'         => 1,
    ]);
    $c3Correct = QuestionChoice::create(['question_id' => $q3->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    $c3Wrong   = QuestionChoice::create(['question_id' => $q3->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);

    $section->testQuestions()->create(['question_id' => $q1->id, 'order' => 1]);
    $section->testQuestions()->create(['question_id' => $q2->id, 'order' => 2]);
    $section->testQuestions()->create(['question_id' => $q3->id, 'order' => 3]);

    $attempt = Attempt::create([
        'test_id'    => $test->id,
        'user_id'    => $this->student->id,
        'status'     => 'in_progress',
        'started_at' => now(),
    ]);

    // Student answers Q1 (Correct), Q2 (Correct), Q3 (Wrong)
    $attempt->answers()->create(['question_id' => $q1->id, 'selected_choice_id' => $c1Correct->id]);
    $attempt->answers()->create(['question_id' => $q2->id, 'selected_choice_id' => $c2Correct->id]);
    $attempt->answers()->create(['question_id' => $q3->id, 'selected_choice_id' => $c3Wrong->id]);

    app(AttemptEngine::class)->submitAttempt($attempt);
    $attempt->refresh();

    // 2 correct answers out of 3 -> raw score = 2.0
    expect((float) $attempt->total_score)->toBe(2.0);
});

test('9. Repository revision preserves audio_group_id upon question edit', function () {
    $bank = QuestionBank::create([
        'title'      => 'Revision Bank',
        'slug'       => 'revision-bank-' . uniqid(),
        'test_type'  => TestType::Toeic,
        'status'     => 'needs_revision',
        'created_by' => $this->teacher->id,
    ]);

    $audioGroup = AudioGroup::create([
        'question_bank_id' => $bank->id,
        'title'            => 'Revision Audio Group',
        'group_type'       => 'conversation',
        'part_number'      => 3,
        'audio_url'        => 'https://example.com/rev.mp3',
    ]);

    $q = Question::create([
        'question_bank_id' => $bank->id,
        'audio_group_id'   => $audioGroup->id,
        'prompt'           => 'Original Prompt',
        'section'          => SectionType::Listening,
        'part_number'      => 3,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'B', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'C', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'D', 'is_correct' => false]);

    $repoManager = User::factory()->create(['status' => 'active']);
    $repoManager->assignRole('repository-manager');

    $revRequest = RepositoryRevisionRequest::create([
        'question_bank_id' => $bank->id,
        'teacher_id'       => $this->teacher->id,
        'requested_by_id'  => $repoManager->id,
        'status'           => 'OPEN',
    ]);

    $revItem = RepositoryRevisionItem::create([
        'repository_revision_request_id' => $revRequest->id,
        'question_bank_id'               => $bank->id,
        'question_id'                    => $q->id,
        'finding_type'                   => 'prompt',
        'feedback'                       => 'Clarify prompt',
        'status'                         => 'OPEN',
    ]);

    $response = $this->actingAs($this->teacher)->post(route('teacher.repository-revisions.update-question', [$revRequest->id, $revItem->id]), [
        'question_id'    => $q->id,
        'prompt'         => 'Clarified Prompt for Part 3',
        'part_number'    => 3,
        'difficulty'     => 'medium',
        'audio_group_id' => $audioGroup->id,
        'choices'        => [
            ['content' => 'A', 'is_correct' => 1],
            ['content' => 'B', 'is_correct' => 0],
            ['content' => 'C', 'is_correct' => 0],
            ['content' => 'D', 'is_correct' => 0],
        ],
    ]);

    $q->refresh();
    expect($q->prompt)->toBe('Clarified Prompt for Part 3')
        ->and($q->audio_group_id)->toBe($audioGroup->id);
});

test('10. Candidate exam view renders shared audio group details and audio stream trigger', function () {
    $test = Test::create([
        'title'            => 'Exam with Audio Group',
        'slug'             => 'exam-audio-group-' . uniqid(),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 60,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
        'is_published'     => true,
        'status'           => 'published',
    ]);

    $section = TestSection::create([
        'test_id'          => $test->id,
        'title'            => 'Listening Section',
        'section_type'     => 'listening',
        'order'            => 1,
        'duration_minutes' => 30,
    ]);

    $audioGroup = AudioGroup::create([
        'test_id'     => $test->id,
        'title'       => 'Business Meeting Discussion',
        'group_type'  => 'conversation',
        'part_number' => 3,
        'audio_url'   => 'https://example.com/discussion.mp3',
    ]);

    $q1 = Question::create([
        'audio_group_id' => $audioGroup->id,
        'prompt'         => 'What is the topic of the discussion?',
        'section'        => SectionType::Listening,
        'part_number'    => 3,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
    ]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Quarterly budget', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'Staff hiring', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'C', 'content' => 'Office relocation', 'is_correct' => false]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'D', 'content' => 'New client pitch', 'is_correct' => false]);

    $section->testQuestions()->create(['question_id' => $q1->id, 'order' => 1]);

    $attempt = Attempt::create([
        'test_id'    => $test->id,
        'user_id'    => $this->student->id,
        'status'     => 'in_progress',
        'started_at' => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
    $response->assertStatus(200);
    $response->assertSee('Shared Conversation Audio (Part 3)');
    $response->assertSee('Business Meeting Discussion');
});

test('11. Non-TOEIC tests and questions remain unaffected by audio group rules', function () {
    $toeflTest = Test::create([
        'title'            => 'TOEFL Practice Test',
        'slug'             => 'toefl-practice-' . uniqid(),
        'test_type'        => 'toefl',
        'duration_minutes' => 60,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
        'status'           => 'draft',
    ]);

    $toeflQuestion = [
        'prompt'        => 'Select the best answer.',
        'question_type' => 'multiple_choice',
        'difficulty'    => 'medium',
        'choices'       => ['A', 'B', 'C', 'D'],
        'correct_choice'=> 0,
    ];

    expect(ToeicQuestionValidator::isToeic($toeflTest))->toBeFalse();
    // Non-TOEIC question without part_number is not toeic
    expect(ToeicQuestionValidator::isToeic($toeflQuestion))->toBeFalse();
});

test('12. Teacher assessment view renders Part 3 audio group authoring modal with high contrast theme tokens (P3-UI-01..05)', function () {
    $test = Test::create([
        'title'            => 'TOEIC Mock Test Group UAT',
        'slug'             => 'toeic-mock-test-group-uat-' . uniqid(),
        'test_type'        => 'toeic',
        'assessment_mode'  => 'simulator',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'created_by'       => $this->teacher->id,
        'status'           => 'draft',
    ]);

    $section = TestSection::create([
        'test_id'          => $test->id,
        'title'            => 'Part 3: Conversations',
        'section_type'     => 'listening',
        'order'            => 3,
        'duration_minutes' => 30,
    ]);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $test->id));
    $response->assertStatus(200);

    // P3-UI-01: Modal header and helper text
    $response->assertSee('Create Audio Question Group');
    $response->assertSee('TOEIC Part 3/4 uses exactly 3 questions per audio group.');
    $response->assertSee('text-slate-600 dark:text-slate-300', false);

    // P3-UI-02: Shared audio stimulus helper and empty state
    $response->assertSee('This audio will be shared by all 3 questions in this group.');
    $response->assertSee('No shared audio attached yet');
    $response->assertSee('text-slate-900 dark:text-slate-100', false);

    // P3-UI-03: Labels and placeholders
    $response->assertSee('Group Title (Optional)');
    $response->assertSee('Conversation Script / Transcript (Optional)');
    $response->assertSee('placeholder:text-slate-500 dark:placeholder:text-slate-400', false);

    // P3-UI-04: Child question stems and choice helpers
    $response->assertSee('Question 1 Prompt / Stem');
    $response->assertSee('Answer Choices (A–D) &amp; Correct Answer', false);
    $response->assertSee('Select radio for correct answer');

    // P3-UI-05: Badges and progress
    $response->assertSee('Progress: 0 / 3 Complete');
    $response->assertSee('LISTENING • PART 3');
});
