<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\CandidateTestAnswer;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teacher = User::factory()->create(['status' => 'active']);
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create(['status' => 'active']);
    $this->student->assignRole('student');

    $this->toeicTest = Test::create([
        'title'            => 'TOEIC Full Official CBT Exam',
        'slug'             => 'toeic-full-official-cbt-exam-' . uniqid(),
        'type'             => 'simulator',
        'assessment_mode'  => 'simulator',
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'status'           => 'published',
        'is_published'     => true,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
    ]);

    $this->listeningSection = TestSection::create([
        'test_id'          => $this->toeicTest->id,
        'section_type'     => SectionType::Listening,
        'title'            => 'Listening Section',
        'order'            => 1,
    ]);

    $this->readingSection = TestSection::create([
        'test_id'          => $this->toeicTest->id,
        'section_type'     => SectionType::Reading,
        'title'            => 'Reading Section',
        'order'            => 2,
    ]);
});

test('1. Part 1 Photographs renders image + audio + letters only (A, B, C, D)', function () {
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

    $q = Question::create([
        'prompt'        => 'Look at the photograph and choose the statement that best describes what you see.',
        'section'       => SectionType::Listening,
        'part_number'   => 1,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'image_url'     => $photoAsset->path,
        'audio_url'     => 'https://example.com/audio/part1-01.mp3',
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'She is typing on the laptop.', 'choice_text' => 'She is typing on the laptop.', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'He is drinking coffee.', 'choice_text' => 'He is drinking coffee.', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'They are shaking hands.', 'choice_text' => 'They are shaking hands.', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'The documents are on the table.', 'choice_text' => 'The documents are on the table.', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p1-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('https://example.com/media/office-photo.jpg');
    $response->assertSee(route('candidate.exam.audio-stream', [$attempt, $q]));
    $response->assertSee('(A)');
    $response->assertSee('(B)');
    $response->assertSee('(C)');
    $response->assertSee('(D)');
});

test('2. Part 1 hides option text in candidate view', function () {
    $secretOption1 = 'SECRET_WORDING_SHE_IS_TYPING_ON_THE_LAPTOP';
    $secretOption2 = 'SECRET_WORDING_HE_IS_DRINKING_COFFEE';

    $q = Question::create([
        'prompt'        => 'Look at the photograph and choose the statement.',
        'section'       => SectionType::Listening,
        'part_number'   => 1,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'audio_url'     => 'https://example.com/audio/part1-02.mp3',
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => $secretOption1, 'choice_text' => $secretOption1, 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => $secretOption2, 'choice_text' => $secretOption2, 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Option C', 'choice_text' => 'Option C', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'Option D', 'choice_text' => 'Option D', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p1-hide-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertDontSee($secretOption1);
    $response->assertDontSee($secretOption2);
});

test('3. Part 2 Question-Response renders audio + A/B/C only', function () {
    $q = Question::create([
        'prompt'        => 'Listen to the question and responses.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'audio_url'     => 'https://example.com/audio/part2-01.mp3',
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'At two oclock', 'choice_text' => 'At two oclock', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Yes I did', 'choice_text' => 'Yes I did', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'In the room', 'choice_text' => 'In the room', 'is_correct' => false, 'order' => 3]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q->id, 'order' => 2, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p2-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee(route('candidate.exam.audio-stream', [$attempt, $q]));
    $response->assertSee('(A)');
    $response->assertSee('(B)');
    $response->assertSee('(C)');
});

test('4. Part 2 hides option text and choice D', function () {
    $secretPart2A = 'SECRET_PART2_CHOICE_A';
    $secretPart2B = 'SECRET_PART2_CHOICE_B';
    $secretPart2C = 'SECRET_PART2_CHOICE_C';
    $secretPart2D = 'SECRET_PART2_CHOICE_D';

    $q = Question::create([
        'prompt'        => 'Listen to the question and responses.',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'audio_url'     => 'https://example.com/audio/part2-02.mp3',
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => $secretPart2A, 'choice_text' => $secretPart2A, 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => $secretPart2B, 'choice_text' => $secretPart2B, 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => $secretPart2C, 'choice_text' => $secretPart2C, 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => $secretPart2D, 'choice_text' => $secretPart2D, 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q->id, 'order' => 2, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p2-hide-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertDontSee('(D)');
    $response->assertDontSee($secretPart2A);
    $response->assertDontSee($secretPart2B);
    $response->assertDontSee($secretPart2C);
    $response->assertDontSee($secretPart2D);
});

test('5. Part 3 Conversations uses one shared audio player and visible choice text', function () {
    $audioGroup = AudioGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Airport Conversation',
        'group_type'   => 'conversation',
        'part_number'  => 3,
        'audio_url'    => 'https://example.com/audio/p3-shared.mp3',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    $q1 = Question::create(['audio_group_id' => $audioGroup->id, 'prompt' => 'Where does this take place?', 'section' => SectionType::Listening, 'part_number' => 3, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'At terminal 2', 'choice_text' => 'At terminal 2', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'At a hotel', 'choice_text' => 'At a hotel', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'C', 'content' => 'In a restaurant', 'choice_text' => 'In a restaurant', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'D', 'content' => 'On a bus', 'choice_text' => 'On a bus', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q1->id, 'order' => 3, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p3-cbt-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('Shared Conversation Audio (Part 3)');
    $response->assertSee('Airport Conversation');
    $response->assertSee('At terminal 2');
});

test('6. Part 4 Talks uses one shared audio player and visible choice text', function () {
    $audioGroup = AudioGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Radio Announcement',
        'group_type'   => 'talk',
        'part_number'  => 4,
        'audio_url'    => 'https://example.com/audio/p4-shared.mp3',
        'order'        => 2,
        'created_by'   => $this->teacher->id,
    ]);

    $q1 = Question::create(['audio_group_id' => $audioGroup->id, 'prompt' => 'What is being broadcast?', 'section' => SectionType::Listening, 'part_number' => 4, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Traffic update', 'choice_text' => 'Traffic update', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'Weather forecast', 'choice_text' => 'Weather forecast', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'C', 'content' => 'Financial report', 'choice_text' => 'Financial report', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'D', 'content' => 'Sports scores', 'choice_text' => 'Sports scores', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q1->id, 'order' => 4, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p4-cbt-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('Shared Talk Audio (Part 4)');
    $response->assertSee('Radio Announcement');
    $response->assertSee('Traffic update');
});

test('7. Mock Test blocks group replay across Part 3/4 audio group', function () {
    $mockTest = Test::create([
        'title'            => 'TOEIC Official Mock Test',
        'slug'             => 'toeic-official-mock-test-' . uniqid(),
        'type'             => 'real_test',
        'assessment_mode'  => 'real_test',
        'test_type'        => 'toeic',
        'duration_minutes' => 120,
        'pass_score'       => 500,
        'status'           => 'published',
        'is_published'     => true,
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
    ]);

    $sec = TestSection::create(['test_id' => $mockTest->id, 'section_type' => SectionType::Listening, 'title' => 'Listening', 'order' => 1]);

    $group = AudioGroup::create([
        'test_id'      => $mockTest->id,
        'title'        => 'Mock Test Conversation Group',
        'group_type'   => 'conversation',
        'part_number'  => 3,
        'audio_url'    => 'https://example.com/audio/mock-p3.mp3',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    $q1 = Question::create(['audio_group_id' => $group->id, 'prompt' => 'Q1', 'section' => SectionType::Listening, 'part_number' => 3, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);
    $q2 = Question::create(['audio_group_id' => $group->id, 'prompt' => 'Q2', 'section' => SectionType::Listening, 'part_number' => 3, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);
    $q3 = Question::create(['audio_group_id' => $group->id, 'prompt' => 'Q3', 'section' => SectionType::Listening, 'part_number' => 3, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);

    QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
    QuestionChoice::create(['question_id' => $q3->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);

    $sec->testQuestions()->create(['question_id' => $q1->id, 'order' => 1]);
    $sec->testQuestions()->create(['question_id' => $q2->id, 'order' => 2]);
    $sec->testQuestions()->create(['question_id' => $q3->id, 'order' => 3]);

    $attempt = Attempt::create([
        'test_id'    => $mockTest->id,
        'user_id'    => $this->student->id,
        'status'     => 'in_progress',
        'started_at' => now(),
    ]);

    // First playback on Q1 succeeds
    $resp1 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q1]));
    expect($resp1->status())->toBeIn([200, 302]);

    // Sibling Q2 and Q3 replay attempts are blocked with 403
    $resp2 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q2]));
    $resp2->assertStatus(403);

    $resp3 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q3]));
    $resp3->assertStatus(403);
});

test('8. Simulator preserves replay behavior on audio groups', function () {
    $audioGroup = AudioGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Simulator Talk',
        'group_type'   => 'talk',
        'part_number'  => 4,
        'audio_url'    => 'https://example.com/audio/sim-p4.mp3',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    $q = Question::create(['audio_group_id' => $audioGroup->id, 'prompt' => 'Sim Q', 'section' => SectionType::Listening, 'part_number' => 4, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);
    TestQuestion::create(['test_section_id' => $this->listeningSection->id, 'question_id' => $q->id, 'order' => 1]);

    $attempt = Attempt::create([
        'test_id'    => $this->toeicTest->id,
        'user_id'    => $this->student->id,
        'status'     => 'in_progress',
        'started_at' => now(),
    ]);

    // In simulator mode, multiple streams are allowed
    $resp1 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q]));
    expect($resp1->status())->toBeIn([200, 302]);

    $resp2 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $q]));
    expect($resp2->status())->toBeIn([200, 302]);
});

test('9. Part 5 Incomplete Sentences renders normal Reading layout without passage or audio', function () {
    $q = Question::create([
        'prompt'        => 'Employees are reminded to submit their timecards by 5:00 PM.',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'promptly', 'choice_text' => 'promptly', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'prompt', 'choice_text' => 'prompt', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'prompting', 'choice_text' => 'prompting', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'prompts', 'choice_text' => 'prompts', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p5-layout-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('Employees are reminded to submit their timecards by 5:00 PM.');
    $response->assertSee('promptly');
    $response->assertDontSee('passage-pane-' . $q->id);
    $response->assertDontSee('audio-container-' . $q->id);
});

test('10. Part 6 Text Completion split-screen renders passage and questions with option text', function () {
    $group = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Text Completion Memo',
        'part_number'  => 6,
        'passage_type' => 'single',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    Passage::create([
        'passage_group_id' => $group->id,
        'test_id'          => $this->toeicTest->id,
        'order_in_group'   => 1,
        'document_type'    => 'memo',
        'title'            => 'Annual Review Memo',
        'content'          => 'Please review the schedule [1] for next week.',
    ]);

    $q = Question::create([
        'passage_group_id' => $group->id,
        'prompt'           => 'Blank [1] choice',
        'section'          => SectionType::Reading,
        'part_number'      => 6,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Medium,
        'points'           => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'carefully', 'choice_text' => 'carefully', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'careful', 'choice_text' => 'careful', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'caring', 'choice_text' => 'caring', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'care', 'choice_text' => 'care', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p6-split-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('Part 6 Single Passage');
    $response->assertSee('Annual Review Memo');
    $response->assertSee('Please review the schedule [1] for next week.');
    $response->assertSee('carefully');
});

test('11. Part 7 Single split-screen renders single passage and question choices', function () {
    $group = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Single Passage Notice',
        'part_number'  => 7,
        'passage_type' => 'single',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    Passage::create([
        'passage_group_id' => $group->id,
        'test_id'          => $this->toeicTest->id,
        'order_in_group'   => 1,
        'document_type'    => 'notice',
        'title'            => 'Facility Maintenance',
        'content'          => 'Water will be turned off between 1:00 PM and 3:00 PM.',
    ]);

    $q = Question::create([
        'passage_group_id' => $group->id,
        'prompt'           => 'What time will water service resume?',
        'section'          => SectionType::Reading,
        'part_number'      => 7,
        'question_type'    => QuestionType::MultipleChoice,
        'difficulty'       => DifficultyLevel::Easy,
        'points'           => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'At 3:00 PM', 'choice_text' => 'At 3:00 PM', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'At 1:00 PM', 'choice_text' => 'At 1:00 PM', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Tomorrow', 'choice_text' => 'Tomorrow', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'Never', 'choice_text' => 'Never', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p7-single-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('Part 7 Single Passage');
    $response->assertSee('Facility Maintenance');
    $response->assertSee('Water will be turned off between 1:00 PM and 3:00 PM.');
    $response->assertSee('At 3:00 PM');
});

test('12. Part 7 Double tabs render in split-screen', function () {
    $group = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Supplier Agreement',
        'part_number'  => 7,
        'passage_type' => 'double',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->toeicTest->id, 'order_in_group' => 1, 'document_type' => 'email', 'title' => 'Initial Proposal', 'content' => 'Contract details.']);
    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->toeicTest->id, 'order_in_group' => 2, 'document_type' => 'letter', 'title' => 'Signed Confirmation', 'content' => 'Agreement signed.']);

    $q = Question::create(['passage_group_id' => $group->id, 'prompt' => 'What was agreed upon?', 'section' => SectionType::Reading, 'part_number' => 7, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Delivery on Monday', 'choice_text' => 'Delivery on Monday', 'is_correct' => true, 'order' => 1]);
    TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p7-double-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('passage-doc-tab');
    $response->assertSee('Initial Proposal');
    $response->assertSee('Signed Confirmation');
});

test('13. Part 7 Triple tabs render in split-screen', function () {
    $group = PassageGroup::create([
        'test_id'      => $this->toeicTest->id,
        'title'        => 'Conference Package',
        'part_number'  => 7,
        'passage_type' => 'triple',
        'order'        => 1,
        'created_by'   => $this->teacher->id,
    ]);

    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->toeicTest->id, 'order_in_group' => 1, 'document_type' => 'schedule', 'title' => 'Session Timetable', 'content' => 'Timetable info']);
    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->toeicTest->id, 'order_in_group' => 2, 'document_type' => 'email', 'title' => 'Keynote Invite', 'content' => 'Keynote info']);
    Passage::create(['passage_group_id' => $group->id, 'test_id' => $this->toeicTest->id, 'order_in_group' => 3, 'document_type' => 'notice', 'title' => 'Registration Note', 'content' => 'Registration info']);

    $q = Question::create(['passage_group_id' => $group->id, 'prompt' => 'Who will speak?', 'section' => SectionType::Reading, 'part_number' => 7, 'question_type' => QuestionType::MultipleChoice, 'difficulty' => DifficultyLevel::Medium, 'points' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Dr. Miller', 'choice_text' => 'Dr. Miller', 'is_correct' => true, 'order' => 1]);
    TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'p7-triple-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('passage-doc-tab');
    $response->assertSee('Session Timetable');
    $response->assertSee('Keynote Invite');
    $response->assertSee('Registration Note');
});

test('14. Autosave persists answer selection in candidate CBT session', function () {
    $q = Question::create([
        'prompt'        => 'Autosave test prompt',
        'section'       => SectionType::Reading,
        'part_number'   => 5,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'points'        => 1,
    ]);

    $choiceA = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Option A', 'choice_text' => 'Option A', 'is_correct' => true, 'order' => 1]);
    $choiceB = QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Option B', 'choice_text' => 'Option B', 'is_correct' => false, 'order' => 2]);

    TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $this->toeicTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'autosave-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)
        ->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id'     => $q->id,
            'selected_choice' => $choiceA->id,
        ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('answers', [
        'attempt_id'         => $attempt->id,
        'question_id'        => $q->id,
        'selected_choice_id' => $choiceA->id,
    ]);
});

test('15. Non-TOEIC test renders full option wording for all question parts', function () {
    $generalTest = Test::create([
        'title'            => 'IELTS General Examination',
        'slug'             => 'ielts-general-exam-' . uniqid(),
        'type'             => 'simulator',
        'test_type'        => TestType::General,
        'duration_minutes' => 60,
        'status'           => 'published',
        'is_active'        => true,
        'created_by'       => $this->teacher->id,
    ]);

    $sec = TestSection::create([
        'test_id'      => $generalTest->id,
        'section_type' => SectionType::Reading,
        'title'        => 'General Section',
        'order'        => 1,
    ]);

    $q = Question::create([
        'prompt'        => 'Select the most appropriate antonym for "expand".',
        'section'       => SectionType::Reading,
        'part_number'   => null,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Easy,
        'points'        => 1,
    ]);

    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Contract', 'choice_text' => 'Contract', 'is_correct' => true, 'order' => 1]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Grow', 'choice_text' => 'Grow', 'is_correct' => false, 'order' => 2]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Develop', 'choice_text' => 'Develop', 'is_correct' => false, 'order' => 3]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'Broaden', 'choice_text' => 'Broaden', 'is_correct' => false, 'order' => 4]);

    TestQuestion::create(['test_section_id' => $sec->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $attempt = Attempt::create([
        'test_id'       => $generalTest->id,
        'user_id'       => $this->student->id,
        'attempt_token' => 'ielts-token-' . uniqid(),
        'status'        => 'in_progress',
        'started_at'    => now(),
    ]);

    $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

    $response->assertStatus(200);
    $response->assertSee('Contract');
    $response->assertSee('Grow');
    $response->assertSee('Develop');
    $response->assertSee('Broaden');
});
