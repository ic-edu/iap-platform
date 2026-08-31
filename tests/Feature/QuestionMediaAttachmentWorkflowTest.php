<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Engines\AssessmentEngine;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuestionMediaAttachmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $otherTeacher;
    protected User $candidate;
    protected Test $test;
    protected TestSection $section;
    protected MediaAsset $photoAsset;
    protected MediaAsset $audioAsset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'TOEIC Instructor',
            'email'  => 'toeic_inst@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->otherTeacher = User::factory()->create([
            'name'   => 'Other Teacher',
            'email'  => 'other_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->otherTeacher->assignRole('teacher');

        $this->candidate = User::factory()->create([
            'name'   => 'TOEIC Candidate',
            'email'  => 'candidate_toeic@icedu.org',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->photoAsset = MediaAsset::create([
            'filename'      => 'part1_photo1.jpg',
            'original_name' => 'Part 1 Photograph 1.jpg',
            'mime_type'     => 'image/jpeg',
            'type'          => 'image',
            'path'          => 'question-media/part1_photo1.jpg',
            'size'          => 350000,
            'status'        => 'active',
            'title'         => 'Part 1 Front Desk Photograph',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->audioAsset = MediaAsset::create([
            'filename'      => 'part1_q1_audio.mp3',
            'original_name' => 'Part 1 Question 1 Audio.mp3',
            'mime_type'     => 'audio/mpeg',
            'type'          => 'audio',
            'path'          => 'question-media/part1_q1_audio.mp3',
            'size'          => 180000,
            'status'        => 'active',
            'title'         => 'Part 1 Question 1 Audio Prompt',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->test = Test::create([
            'title'            => 'TOEIC Listening & Reading for SMK Perhotelan',
            'slug'             => 'toeic-listening-reading-smk-perhotelan-qm',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $this->section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'instructions' => 'Listen to the audio and select the statement that best describes the picture.',
            'order'        => 1,
        ]);
    }

    /**
     * Requirement 1: Flat media upload response returns success, id, url, filename, type.
     */
    public function test_01_flat_media_upload_response_contract()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('hotel_lobby.jpg', 800, 600);

        $response = $this->actingAs($this->teacher)
            ->postJson(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Hotel Lobby Photo',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'id',
            'url',
            'filename',
            'title',
            'type',
            'size',
        ]);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('id'));
        $this->assertEquals('image', $response->json('type'));
    }

    /**
     * Requirement 2 & 9: Create Question persists image_url.
     */
    public function test_02_create_question_persists_image_url()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.create-question', $this->test->id), [
                'test_section_id' => $this->section->id,
                'prompt'          => 'Look at the photograph and choose the best description.',
                'question_type'   => 'multiple_choice',
                'points'          => 1,
                'image_url'       => $this->photoAsset->publicUrl(),
                'media_asset_id'  => $this->photoAsset->id,
                'choices'         => ['Option A', 'Option B', 'Option C', 'Option D'],
                'correct_choice'  => 0,
            ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $this->assertDatabaseHas('questions', [
            'prompt'         => 'Look at the photograph and choose the best description.',
            'image_url'      => $this->photoAsset->publicUrl(),
            'media_asset_id' => $this->photoAsset->id,
        ]);
    }

    /**
     * Requirement 3 & 10: Create Question persists audio_url.
     */
    public function test_03_create_question_persists_audio_url()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.create-question', $this->test->id), [
                'test_section_id' => $this->section->id,
                'prompt'          => 'Listen to the audio statements.',
                'question_type'   => 'multiple_choice',
                'points'          => 1,
                'audio_url'       => $this->audioAsset->publicUrl(),
                'media_asset_id'  => $this->audioAsset->id,
                'choices'         => ['Option A', 'Option B', 'Option C', 'Option D'],
                'correct_choice'  => 1,
            ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $this->assertDatabaseHas('questions', [
            'prompt'    => 'Listen to the audio statements.',
            'audio_url' => $this->audioAsset->publicUrl(),
        ]);
    }

    /**
     * Requirement 4: One Question can retain image + audio simultaneously.
     */
    public function test_04_one_question_retains_image_and_audio_simultaneously()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.create-question', $this->test->id), [
                'test_section_id' => $this->section->id,
                'prompt'          => 'Listen to the audio and select the statement that best describes the picture.',
                'question_type'   => 'multiple_choice',
                'points'          => 1,
                'image_url'       => $this->photoAsset->publicUrl(),
                'audio_url'       => $this->audioAsset->publicUrl(),
                'media_asset_id'  => $this->photoAsset->id,
                'choices'         => ['Statement A', 'Statement B', 'Statement C', 'Statement D'],
                'correct_choice'  => 2,
            ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $question = Question::where('prompt', 'Listen to the audio and select the statement that best describes the picture.')->first();
        $this->assertNotNull($question);
        $this->assertEquals($this->photoAsset->publicUrl(), $question->image_url);
        $this->assertEquals($this->audioAsset->publicUrl(), $question->audio_url);
    }

    /**
     * Requirement 5 & 11: Image change does not remove Audio when updating question.
     */
    public function test_05_image_change_does_not_remove_audio()
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Initial Question with Image and Audio',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => 'https://example.com/initial_image.jpg',
            'audio_url'        => $this->audioAsset->publicUrl(),
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $newImageUrl = $this->photoAsset->publicUrl();

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $question->id]), [
                'prompt'    => 'Initial Question with Image and Audio (Updated Image)',
                'image_url' => $newImageUrl,
                'audio_url' => $this->audioAsset->publicUrl(),
            ]);

        $response->assertRedirect(route('teacher.tests.show', ['test' => $this->test->id, 'section' => $this->section->id, 'focus' => "question-card-{$question->id}"]));

        $question->refresh();
        $this->assertEquals($newImageUrl, $question->image_url);
        $this->assertEquals($this->audioAsset->publicUrl(), $question->audio_url);
    }

    /**
     * Requirement 6 & 11: Audio change does not remove Image when updating question.
     */
    public function test_06_audio_change_does_not_remove_image()
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Question to change audio',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
            'audio_url'        => 'https://example.com/initial_audio.mp3',
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $newAudioUrl = $this->audioAsset->publicUrl();

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $question->id]), [
                'prompt'    => 'Question to change audio (Updated)',
                'image_url' => $this->photoAsset->publicUrl(),
                'audio_url' => $newAudioUrl,
            ]);

        $response->assertRedirect(route('teacher.tests.show', ['test' => $this->test->id, 'section' => $this->section->id, 'focus' => "question-card-{$question->id}"]));

        $question->refresh();
        $this->assertEquals($this->photoAsset->publicUrl(), $question->image_url);
        $this->assertEquals($newAudioUrl, $question->audio_url);
    }

    /**
     * Requirement 7: Image removal works without removing Audio.
     */
    public function test_07_image_removal_works()
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Question with both media',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
            'audio_url'        => $this->audioAsset->publicUrl(),
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $question->id]), [
                'prompt'    => 'Question with both media',
                'image_url' => null,
                'audio_url' => $this->audioAsset->publicUrl(),
            ]);

        $response->assertRedirect(route('teacher.tests.show', ['test' => $this->test->id, 'section' => $this->section->id, 'focus' => "question-card-{$question->id}"]));

        $question->refresh();
        $this->assertNull($question->image_url);
        $this->assertEquals($this->audioAsset->publicUrl(), $question->audio_url);
    }

    /**
     * Requirement 8: Audio removal works without removing Image.
     */
    public function test_08_audio_removal_works()
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Question to remove audio from',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
            'audio_url'        => $this->audioAsset->publicUrl(),
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $question->id]), [
                'prompt'    => 'Question to remove audio from',
                'image_url' => $this->photoAsset->publicUrl(),
                'audio_url' => null,
            ]);

        $response->assertRedirect(route('teacher.tests.show', ['test' => $this->test->id, 'section' => $this->section->id, 'focus' => "question-card-{$question->id}"]));

        $question->refresh();
        $this->assertEquals($this->photoAsset->publicUrl(), $question->image_url);
        $this->assertNull($question->audio_url);
    }

    /**
     * Requirement 12 & 13: Candidate exam renders image and audio in correct sequence.
     */
    public function test_12_and_13_candidate_exam_renders_image_and_audio()
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Look at the picture and choose the best statement.',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
            'audio_url'        => $this->audioAsset->publicUrl(),
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'A',
            'content'     => 'She is checking into the hotel.',
            'is_correct'  => true,
        ]);
        QuestionChoice::create([
            'question_id' => $question->id,
            'label'       => 'B',
            'content'     => 'She is preparing food in the kitchen.',
            'is_correct'  => false,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        $attempt = Attempt::create([
            'test_id'       => $this->test->id,
            'user_id'       => $this->candidate->id,
            'attempt_token' => 'qm-token-' . uniqid(),
            'status'        => 'in_progress',
            'started_at'    => now(),
        ]);

        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.exam', $attempt));

        $response->assertStatus(200);
        $response->assertSee($this->photoAsset->publicUrl(), false);
        $response->assertSee($this->audioAsset->publicUrl(), false);
        $response->assertSee('Question Audio Prompt');
        $response->assertSee('Look at the picture and choose the best statement.');
        $response->assertSee('She is checking into the hotel.');
    }

    /**
     * Requirement 14: Locked Assessment prevents media modification.
     */
    public function test_14_locked_assessment_prevents_media_modification()
    {
        $question = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Locked Question Prompt',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $this->test->update([
            'status'       => 'published',
            'is_published' => true,
        ]);

        // Attempting to edit question on published/locked test is forbidden
        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $question->id]), [
                'prompt'    => 'Attempted modification on locked assessment',
                'image_url' => 'https://example.com/hacked.jpg',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Requirement 15: Master Question remains immutable from Test Builder.
     */
    public function test_15_master_question_remains_immutable_from_test_builder()
    {
        $bank = QuestionBank::create([
            'title'       => 'SMK Master Bank',
            'slug'        => 'smk-master-bank',
            'category'    => 'English',
            'status'      => 'published',
            'created_by'  => $this->teacher->id,
        ]);

        $masterQuestion = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'Master Bank Question Prompt',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
            'audio_url'        => $this->audioAsset->publicUrl(),
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $masterQuestion->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->put(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $masterQuestion->id]), [
                'prompt'    => 'Tampered Master Question',
                'image_url' => 'https://example.com/tampered.jpg',
            ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('error');

        $jsonResponse = $this->actingAs($this->teacher)
            ->putJson(route('teacher.tests.update-question', ['test' => $this->test->id, 'question' => $masterQuestion->id]), [
                'prompt'    => 'Tampered Master Question JSON',
                'image_url' => 'https://example.com/tampered_json.jpg',
            ]);

        $jsonResponse->assertStatus(403);

        $masterQuestion->refresh();
        $this->assertEquals('Master Bank Question Prompt', $masterQuestion->prompt);
        $this->assertEquals($this->photoAsset->publicUrl(), $masterQuestion->image_url);
    }

    /**
     * Requirement: Question Image & Audio Preview buttons render in Assessment Detail view.
     */
    public function test_16_question_image_and_audio_preview_buttons_render_in_detail_view(): void
    {
        $q1 = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Question 1 with Image and Audio',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
            'audio_url'        => $this->audioAsset->publicUrl(),
            'media_asset_id'   => $this->photoAsset->id,
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $q1->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.tests.show', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('🖼 Image ✓', false);
        $response->assertSee('🎧 Audio ✓', false);
        // Image preview button
        $response->assertSee("previewAssetModal('', '" . addslashes(basename($q1->image_url)) . "', 'image', '" . $q1->image_url . "')", false);
        // Audio preview button
        $response->assertSee("previewAssetModal('', '" . addslashes(basename($q1->audio_url)) . "', 'audio', '" . $q1->audio_url . "')", false);
        $response->assertSee('asset-preview-modal', false);
    }

    /**
     * Requirement: Question Editor renders Preview buttons for attached Image & Audio.
     */
    public function test_17_question_editor_renders_preview_buttons(): void
    {
        $q1 = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Question Editor Test',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => $this->photoAsset->publicUrl(),
            'audio_url'        => $this->audioAsset->publicUrl(),
            'media_asset_id'   => $this->photoAsset->id,
        ]);
        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $q1->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.tests.edit-question', ['test' => $this->test->id, 'question' => $q1->id]));

        $response->assertStatus(200);
        $response->assertSee("previewQuestionModalMedia('image')", false);
        $response->assertSee("previewQuestionModalMedia('audio')", false);
        $response->assertSee('asset-preview-modal', false);
    }

    /**
     * Requirement: Multi-question media integrity — Q1, Q2, Q3 each keep their distinct media URLs.
     */
    public function test_18_multi_question_media_integrity_remains_distinct(): void
    {
        $q1 = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Q1 Prompt',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => 'http://localhost:8000/media/asset_img_1/preview',
            'audio_url'        => 'http://localhost:8000/media/asset_aud_1/preview',
        ]);
        $q2 = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Q2 Prompt',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => 'http://localhost:8000/media/asset_img_2/preview',
            'audio_url'        => 'http://localhost:8000/media/asset_aud_2/preview',
        ]);
        $q3 = Question::create([
            'question_bank_id' => null,
            'prompt'           => 'Q3 Prompt',
            'question_type'    => 'multiple_choice',
            'points'           => 1,
            'image_url'        => 'http://localhost:8000/media/asset_img_3/preview',
            'audio_url'        => 'http://localhost:8000/media/asset_aud_3/preview',
        ]);

        TestQuestion::create(['test_section_id' => $this->section->id, 'question_id' => $q1->id, 'order' => 1, 'points' => 1]);
        TestQuestion::create(['test_section_id' => $this->section->id, 'question_id' => $q2->id, 'order' => 2, 'points' => 1]);
        TestQuestion::create(['test_section_id' => $this->section->id, 'question_id' => $q3->id, 'order' => 3, 'points' => 1]);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.tests.show', $this->test->id));

        $response->assertStatus(200);

        // Verify Q1 preview triggers
        $response->assertSee("previewAssetModal('', 'preview', 'image', 'http://localhost:8000/media/asset_img_1/preview')", false);
        $response->assertSee("previewAssetModal('', 'preview', 'audio', 'http://localhost:8000/media/asset_aud_1/preview')", false);

        // Verify Q2 preview triggers
        $response->assertSee("previewAssetModal('', 'preview', 'image', 'http://localhost:8000/media/asset_img_2/preview')", false);
        $response->assertSee("previewAssetModal('', 'preview', 'audio', 'http://localhost:8000/media/asset_aud_2/preview')", false);

        // Verify Q3 preview triggers
        $response->assertSee("previewAssetModal('', 'preview', 'image', 'http://localhost:8000/media/asset_img_3/preview')", false);
        $response->assertSee("previewAssetModal('', 'preview', 'audio', 'http://localhost:8000/media/asset_aud_3/preview')", false);
    }
}
