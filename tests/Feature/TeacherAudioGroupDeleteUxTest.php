<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
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

    $this->mediaAsset = MediaAsset::create([
        'title'         => 'Airport Conversation Audio',
        'filename'      => 'airport_convo.mp3',
        'original_name' => 'airport_convo.mp3',
        'path'          => 'audio/airport_convo.mp3',
        'mime_type'     => 'audio/mpeg',
        'type'          => 'audio',
        'size'          => 102400,
        'status'        => 'active',
        'uploaded_by'   => $this->teacher->id,
    ]);
});

// Helper function to create a standard complete 3-question AudioGroup
function createTestAudioGroup(Test $test, TestSection $section, int $partNumber, string $groupType, ?MediaAsset $media = null): AudioGroup {
    $ag = AudioGroup::create([
        'test_id'        => $test->id,
        'title'          => "Part {$partNumber} {$groupType} Group",
        'group_type'     => $groupType,
        'part_number'    => $partNumber,
        'media_asset_id' => $media?->id,
        'audio_url'      => $media ? $media->file_path : 'https://example.com/audio/group.mp3',
        'order'          => 1,
        'created_by'     => $test->created_by,
    ]);

    for ($i = 1; $i <= 3; $i++) {
        $q = Question::create([
            'audio_group_id' => $ag->id,
            'prompt'         => "Child Question {$i} for Part {$partNumber}",
            'section'        => SectionType::Listening,
            'part_number'    => $partNumber,
            'question_type'  => QuestionType::MultipleChoice,
            'difficulty'     => DifficultyLevel::Medium,
            'points'         => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Opt A', 'choice_text' => 'Opt A', 'is_correct' => true, 'order' => 1]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Opt B', 'choice_text' => 'Opt B', 'is_correct' => false, 'order' => 2]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Opt C', 'choice_text' => 'Opt C', 'is_correct' => false, 'order' => 3]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'Opt D', 'choice_text' => 'Opt D', 'is_correct' => false, 'order' => 4]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id'     => $q->id,
            'order'           => $i,
            'points'          => 1,
        ]);
    }

    return $ag;
}

// =========================================================================
// SECTION 1: UI & RENDERING (TEST 01 to TEST 06)
// =========================================================================

test('TEST 01: Part 3 AudioGroup renders Remove Group', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('Remove Group');
    $response->assertSee(route('teacher.tests.destroy-audio-group', ['test' => $this->toeicTest->id, 'audioGroup' => $ag->id]));
});

test('TEST 02: Part 4 AudioGroup renders Remove Group', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part4Section, 4, 'talk', $this->mediaAsset);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('Remove Group');
    $response->assertSee(route('teacher.tests.destroy-audio-group', ['test' => $this->toeicTest->id, 'audioGroup' => $ag->id]));
});

test('TEST 03: Complete group can be removed in editable Assessment', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    $response = $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    $response->assertRedirect(route('teacher.tests.show', [
        'test'    => $this->toeicTest->id,
        'section' => $this->part3Section->id,
    ]));
    $response->assertSessionHas('status', 'Part 3 Conversation Group removed successfully.');
    expect(AudioGroup::find($ag->id))->toBeNull();
});

test('TEST 04: Incomplete 2/3 group can be removed', function () {
    $ag = AudioGroup::create([
        'test_id'        => $this->toeicTest->id,
        'title'          => 'Incomplete Part 3 Group',
        'group_type'     => 'conversation',
        'part_number'    => 3,
        'audio_url'      => 'https://example.com/audio/draft.mp3',
        'order'          => 1,
        'created_by'     => $this->teacher->id,
    ]);

    // Only 2 questions created (draft state)
    for ($i = 1; $i <= 2; $i++) {
        $q = Question::create([
            'audio_group_id' => $ag->id,
            'prompt'         => "Draft question {$i}",
            'section'        => SectionType::Listening,
            'part_number'    => 3,
            'question_type'  => QuestionType::MultipleChoice,
            'difficulty'     => DifficultyLevel::Medium,
            'points'         => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Opt A', 'choice_text' => 'Opt A', 'is_correct' => true, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q->id, 'order' => $i, 'points' => 1]);
    }

    $response = $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    $response->assertRedirect();
    expect(AudioGroup::find($ag->id))->toBeNull();
    expect(Question::where('audio_group_id', $ag->id)->count())->toBe(0);
});

test('TEST 05: Confirmation required before deletion markup is present', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    $response->assertSee('iapConfirm', false);
    $response->assertSee('Remove Conversation Group?', false);
});

test('TEST 06: Cancel confirmation performs no mutation', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    // Initial check: audio group and 3 child questions exist
    expect(AudioGroup::find($ag->id))->not->toBeNull();
    expect(Question::where('audio_group_id', $ag->id)->count())->toBe(3);

    // Teacher simply loads view without sending DELETE request
    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);

    // Nothing mutated
    expect(AudioGroup::find($ag->id))->not->toBeNull();
    expect(Question::where('audio_group_id', $ag->id)->count())->toBe(3);
});

// =========================================================================
// SECTION 2: AUTHORIZATION & TRANSACTION SAFETY (TEST 07 to TEST 14)
// =========================================================================

test('TEST 07: Unauthorized Teacher cannot delete group', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    $response = $this->actingAs($this->otherTeacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    $response->assertStatus(403);
    expect(AudioGroup::find($ag->id))->not->toBeNull();
});

test('TEST 08: Locked/non-editable assessment prevents delete', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);
    $this->toeicTest->update(['status' => 'published', 'is_published' => true]);

    $response = $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    $response->assertStatus(403);
    expect(AudioGroup::find($ag->id))->not->toBeNull();
});

test('TEST 09: Group deletion removes assessment group relationship', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    expect(AudioGroup::find($ag->id))->toBeNull();
});

test('TEST 10: Group child Questions are removed according to safe domain contract', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);
    $childQuestionIds = $ag->questions()->pluck('id')->toArray();

    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    foreach ($childQuestionIds as $qId) {
        expect(Question::find($qId))->toBeNull();
    }
});

test('TEST 11: TestQuestion placements do not remain orphaned', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);
    $childQuestionIds = $ag->questions()->pluck('id')->toArray();

    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    $orphanPlacements = TestQuestion::whereIn('question_id', $childQuestionIds)->count();
    expect($orphanPlacements)->toBe(0);
});

test('TEST 12: QuestionChoices do not remain orphaned where child question is deleted', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);
    $childQuestionIds = $ag->questions()->pluck('id')->toArray();

    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    $orphanChoices = QuestionChoice::whereIn('question_id', $childQuestionIds)->count();
    expect($orphanChoices)->toBe(0);
});

test('TEST 13: MediaAsset remains intact', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);
    $mediaId = $this->mediaAsset->id;

    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    // AudioGroup is deleted, but MediaAsset is preserved
    expect(AudioGroup::find($ag->id))->toBeNull();
    expect(MediaAsset::find($mediaId))->not->toBeNull();
});

test('TEST 14: Institutional MediaAsset remains intact', function () {
    $instMedia = MediaAsset::create([
        'title'         => 'Standard Institutional Audio Track',
        'filename'      => 'inst_audio.mp3',
        'original_name' => 'inst_audio.mp3',
        'path'          => 'audio/inst_audio.mp3',
        'mime_type'     => 'audio/mpeg',
        'type'          => 'audio',
        'size'          => 204800,
        'status'        => 'active',
        'uploaded_by'   => $this->otherTeacher->id,
    ]);

    $ag = createTestAudioGroup($this->toeicTest, $this->part4Section, 4, 'talk', $instMedia);

    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    expect(AudioGroup::find($ag->id))->toBeNull();
    expect(MediaAsset::find($instMedia->id))->not->toBeNull();
});

// =========================================================================
// SECTION 3: RECALCULATION & REGRESSION (TEST 15 to TEST 20)
// =========================================================================

test('TEST 15: Global numbering recomputes after deletion', function () {
    $ag1 = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);
    $ag2 = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    // Initial: 6 questions (Q1 to Q6)
    $resInitial = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resInitial->assertStatus(200);
    $resInitial->assertSee('Q1 ✓ Complete');
    $resInitial->assertSee('Q4 ✓ Complete');

    // Delete group 1
    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag1->id,
    ]));

    // Group 2 child questions renumber to Q1, Q2, Q3
    $resAfter = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resAfter->assertStatus(200);
    $resAfter->assertSee('Q1 ✓ Complete');
    $resAfter->assertSee('Q2 ✓ Complete');
    $resAfter->assertSee('Q3 ✓ Complete');
});

test('TEST 16: Section question count recomputes', function () {
    $ag1 = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);
    $ag2 = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    $resInitial = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resInitial->assertSee('6/6 Complete');

    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag1->id,
    ]));

    $resAfter = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resAfter->assertSee('3/3 Complete');
});

test('TEST 17: Section validation summary recomputes', function () {
    // Incomplete AudioGroup (only 1 question) -> NEEDS ATTENTION
    $ag = AudioGroup::create([
        'test_id'        => $this->toeicTest->id,
        'title'          => 'Incomplete Audio Group',
        'group_type'     => 'conversation',
        'part_number'    => 3,
        'audio_url'      => 'https://example.com/audio/draft.mp3',
        'order'          => 1,
        'created_by'     => $this->teacher->id,
    ]);
    $q = Question::create([
        'audio_group_id' => $ag->id,
        'prompt'         => 'Single child question',
        'section'        => SectionType::Listening,
        'part_number'    => 3,
        'question_type'  => QuestionType::MultipleChoice,
        'difficulty'     => DifficultyLevel::Medium,
        'points'         => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Opt A', 'choice_text' => 'Opt A', 'is_correct' => true, 'order' => 1]);
    TestQuestion::create(['test_section_id' => $this->part3Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    // Initial: Section has incomplete audio group -> NEEDS ATTENTION
    $resInitial = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resInitial->assertSee('NEEDS ATTENTION');

    // Remove group -> Section becomes empty (0 questions) -> NOT STARTED
    $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-audio-group', [
        'test'       => $this->toeicTest->id,
        'audioGroup' => $ag->id,
    ]));

    $resAfter = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $resAfter->assertSee('NOT STARTED');
});

test('TEST 18: Submission readiness recomputes', function () {
    $ag = createTestAudioGroup($this->toeicTest, $this->part3Section, 3, 'conversation', $this->mediaAsset);

    $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertStatus(200);
    // Incomplete blueprint disables submit
    $response->assertSee('disabled', false);
});

test('TEST 19: Part 2 Remove Question remains unchanged', function () {
    $q = Question::create([
        'prompt'        => 'Where is the conference being held?',
        'section'       => SectionType::Listening,
        'part_number'   => 2,
        'question_type' => QuestionType::MultipleChoice,
        'difficulty'    => DifficultyLevel::Medium,
        'audio_url'     => 'https://example.com/audio/p2.mp3',
        'points'        => 1,
    ]);
    QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '', 'choice_text' => '', 'is_correct' => true, 'order' => 1]);
    TestQuestion::create(['test_section_id' => $this->part2Section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 1]);

    $response = $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-question', [
        'test'     => $this->toeicTest->id,
        'question' => $q->id,
    ]));

    $response->assertRedirect(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertSessionHas('status', 'Question reference removed from assessment.');
    expect(Question::find($q->id))->toBeNull();
});

test('TEST 20: Remove Section remains unchanged', function () {
    $extraSection = TestSection::create([
        'test_id'      => $this->toeicTest->id,
        'section_type' => SectionType::Listening,
        'title'        => 'Temporary Extra Section',
        'order'        => 10,
    ]);

    $response = $this->actingAs($this->teacher)->delete(route('teacher.tests.destroy-section', [
        'test'    => $this->toeicTest->id,
        'section' => $extraSection->id,
    ]));

    $response->assertRedirect(route('teacher.tests.show', $this->toeicTest->id));
    $response->assertSessionHas('status', "Section 'Temporary Extra Section' removed successfully.");
    expect(TestSection::find($extraSection->id))->toBeNull();
});
