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
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentSectionMediaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $otherTeacher;
    protected User $candidate;
    protected Test $test;
    protected TestSection $section;
    protected MediaAsset $audioMedia;
    protected MediaAsset $imageMedia;
    protected MediaAsset $passageMedia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'TOEIC Teacher',
            'email'  => 'toeic_teacher@icedu.org',
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
            'name'   => 'Hospitality Candidate',
            'email'  => 'candidate@icedu.org',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->audioMedia = MediaAsset::create([
            'filename'      => 'part1_directions.mp3',
            'original_name' => 'Part 1 Directions Audio.mp3',
            'mime_type'     => 'audio/mpeg',
            'type'          => 'audio',
            'path'          => 'question-media/part1_directions.mp3',
            'size'          => 204800,
            'status'        => 'active',
            'title'         => 'Part 1 Directions Audio Track',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->imageMedia = MediaAsset::create([
            'filename'      => 'hotel_front_desk.png',
            'original_name' => 'Hotel Front Desk Photograph.png',
            'mime_type'     => 'image/png',
            'type'          => 'image',
            'path'          => 'question-media/hotel_front_desk.png',
            'size'          => 512000,
            'status'        => 'active',
            'title'         => 'Hotel Front Desk Photograph',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->passageMedia = MediaAsset::create([
            'filename'      => 'memo_schedule.pdf',
            'original_name' => 'Staff Memo & Schedule.pdf',
            'mime_type'     => 'application/pdf',
            'type'          => 'pdf',
            'path'          => 'question-media/memo_schedule.pdf',
            'size'          => 102400,
            'status'        => 'active',
            'title'         => 'Staff Memo & Schedule Document',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->test = Test::create([
            'title'            => 'TOEIC Listening & Reading for SMK Perhotelan',
            'slug'             => 'toeic-listening-reading-smk-perhotelan',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
            'status'           => 'draft',
            'is_published'     => false,
            'instructions'     => 'Welcome to the test.',
        ]);

        $this->section = TestSection::create([
            'test_id'          => $this->test->id,
            'title'            => 'Part 1: Photographs',
            'section_type'     => 'listening',
            'instructions'     => 'For each question, you will hear four statements about a picture.',
            'duration_minutes' => 15,
            'order'            => 1,
        ]);
    }

    /**
     * TEST 1: Teacher can attach MediaAsset to Draft Section.
     */
    public function test_1_teacher_can_attach_media_asset_to_draft_section()
    {
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
                'caption'        => 'Listening Directions Audio',
                'order'          => 1,
            ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $this->assertDatabaseHas('test_section_media', [
            'test_section_id' => $this->section->id,
            'media_asset_id'  => $this->audioMedia->id,
            'caption'         => 'Listening Directions Audio',
            'order'           => 1,
        ]);
    }

    /**
     * TEST 2: Teacher can attach multiple media assets.
     */
    public function test_2_teacher_can_attach_multiple_media_assets()
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
                'caption'        => 'Listening Directions Audio',
                'order'          => 1,
            ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->imageMedia->id,
                'caption'        => 'Reference Photograph A',
                'order'          => 2,
            ]);

        $this->assertEquals(2, $this->section->fresh()->mediaAssets()->count());
    }

    /**
     * TEST 3: Duplicate attachment is prevented.
     */
    public function test_3_duplicate_attachment_is_prevented()
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
            ]);

        // Attempt second attachment of same media
        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
            ]);

        $response->assertSessionHasErrors(['media_asset_id']);
        $this->assertEquals(1, $this->section->fresh()->mediaAssets()->count());
    }

    /**
     * TEST 4: Media ordering persists.
     */
    public function test_4_media_ordering_persists()
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->imageMedia->id,
                'order'          => 1,
            ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
                'order'          => 2,
            ]);

        $orderedAssets = $this->section->fresh()->mediaAssets;
        $this->assertEquals($this->imageMedia->id, $orderedAssets[0]->id);
        $this->assertEquals($this->audioMedia->id, $orderedAssets[1]->id);
    }

    /**
     * TEST 5: Caption persists.
     */
    public function test_5_caption_persists()
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->imageMedia->id,
                'caption'        => 'Hotel Reception Desk Diagram',
            ]);

        $asset = $this->section->fresh()->mediaAssets->first();
        $this->assertEquals('Hotel Reception Desk Diagram', $asset->pivot->caption);
    }

    /**
     * TEST 6: Teacher can detach media.
     */
    public function test_6_teacher_can_detach_media()
    {
        $this->section->mediaAssets()->attach($this->audioMedia->id, [
            'id'    => (string) \Illuminate\Support\Str::ulid(),
            'order' => 1,
        ]);

        $this->assertEquals(1, $this->section->fresh()->mediaAssets()->count());

        $response = $this->actingAs($this->teacher)
            ->delete(route('teacher.tests.sections.media.detach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
                'media'   => $this->audioMedia->id,
            ]));

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $this->assertEquals(0, $this->section->fresh()->mediaAssets()->count());
    }

    /**
     * TEST 7: Unauthorized teacher cannot attach media.
     */
    public function test_7_unauthorized_teacher_cannot_attach_media()
    {
        $response = $this->actingAs($this->otherTeacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * TEST 8: Pending approval blocks media mutation.
     */
    public function test_8_pending_approval_blocks_media_mutation()
    {
        $this->test->update(['status' => 'pending_approval']);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * TEST 9: Approved blocks media mutation.
     */
    public function test_9_approved_blocks_media_mutation()
    {
        $this->test->update(['status' => 'approved']);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * TEST 10: Published blocks media mutation.
     */
    public function test_10_published_blocks_media_mutation()
    {
        $this->test->update(['status' => 'published', 'is_published' => true]);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $this->audioMedia->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * TEST 11: Candidate delivery loads section media.
     */
    public function test_11_candidate_delivery_loads_section_media()
    {
        $this->section->mediaAssets()->attach($this->audioMedia->id, [
            'id'      => (string) \Illuminate\Support\Str::ulid(),
            'caption' => 'Spoken Conversation Audio',
            'order'   => 1,
        ]);

        $question = Question::create([
            'prompt'        => 'What does the picture depict?',
            'question_type' => 'multiple_choice',
            'points'        => 1,
        ]);

        QuestionChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'Front desk receptionist', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $question->id, 'label' => 'B', 'content' => 'Chef in kitchen', 'is_correct' => false]);

        TestQuestion::create([
            'test_section_id' => $this->section->id,
            'question_id'     => $question->id,
            'order'           => 1,
            'points'          => 1,
        ]);

        $this->test->update(['status' => 'published', 'is_published' => true]);

        /** @var AssessmentEngine $engine */
        $engine = app(AssessmentEngine::class);
        $attempt = $engine->startAttempt($this->test, $this->candidate);

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt->id));

        $response->assertStatus(200);
        $response->assertSee('Part 1: Photographs');
        $response->assertSee('Spoken Conversation Audio');
        $response->assertSee(route('media.preview', $this->audioMedia->id));
    }

    /**
     * TEST 12: Existing question-level media remains functional.
     */
    public function test_12_existing_question_level_media_remains_functional()
    {
        $qMedia = MediaAsset::create([
            'filename'      => 'q_specific.png',
            'original_name' => 'Question Specific Image.png',
            'mime_type'     => 'image/png',
            'type'          => 'image',
            'path'          => 'question-media/q_specific.png',
            'size'          => 1024,
            'status'        => 'active',
            'title'         => 'Question Specific Image',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $question = Question::create([
            'prompt'         => 'Look at the question-specific diagram.',
            'media_asset_id' => $qMedia->id,
            'question_type'  => 'multiple_choice',
            'points'         => 1,
        ]);

        $this->assertEquals($qMedia->id, $question->mediaAsset->id);
        $this->assertEquals('Question Specific Image', $question->mediaAsset->title);
    }

    /**
     * TEST 13: Teacher can upload supported image.
     */
    public function test_13_teacher_can_upload_supported_image()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('hotel_lobby.png', 800, 600);

        $response = $this->actingAs($this->teacher)
            ->postJson(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Hotel Lobby Photo',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'title'   => 'Hotel Lobby Photo',
            'type'    => 'image',
        ]);

        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'hotel_lobby.png',
            'title'         => 'Hotel Lobby Photo',
            'type'          => 'image',
            'uploaded_by'   => $this->teacher->id,
            'status'        => 'active',
        ]);
    }

    /**
     * TEST 14: Teacher can upload supported audio.
     */
    public function test_14_teacher_can_upload_supported_audio()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('lecture.mp3', 2048, 'audio/mpeg');

        $response = $this->actingAs($this->teacher)
            ->postJson(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Part 1 Audio Instructions',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'title'   => 'Part 1 Audio Instructions',
            'type'    => 'audio',
        ]);

        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'lecture.mp3',
            'title'         => 'Part 1 Audio Instructions',
            'type'          => 'audio',
            'uploaded_by'   => $this->teacher->id,
        ]);
    }

    /**
     * TEST 15: Teacher can upload supported PDF.
     */
    public function test_15_teacher_can_upload_supported_pdf()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('schedule.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->teacher)
            ->postJson(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Staff Schedule Reference Document',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'title'   => 'Staff Schedule Reference Document',
            'type'    => 'pdf',
        ]);

        $this->assertDatabaseHas('media_assets', [
            'original_name' => 'schedule.pdf',
            'type'          => 'pdf',
            'uploaded_by'   => $this->teacher->id,
        ]);
    }

    /**
     * TEST 16: Oversized file is rejected (> 10MB).
     */
    public function test_16_oversized_file_is_rejected()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        // 11 MB file exceeds 10240 KB limit
        $file = \Illuminate\Http\UploadedFile::fake()->create('huge_audio.mp3', 11500, 'audio/mpeg');

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertArrayHasKey('file', $response->json('errors') ?? []);
    }

    /**
     * TEST 17: Unsupported file type is rejected.
     */
    public function test_17_unsupported_file_type_is_rejected()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('script.exe', 500, 'application/x-msdownload');

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.media.store'), [
                'file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertArrayHasKey('file', $response->json('errors') ?? []);
    }

    /**
     * TEST 18: Uploaded media becomes available in Media Library list.
     */
    public function test_18_uploaded_media_becomes_available_in_media_library()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('restaurant_menu.jpg', 600, 400);

        $uploadResponse = $this->actingAs($this->teacher)
            ->postJson(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Restaurant Menu Visual',
            ]);

        $uploadResponse->assertStatus(200);
        $assetId = $uploadResponse->json('id');

        $listResponse = $this->actingAs($this->teacher)->getJson(route('admin.media.list'));
        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment(['id' => $assetId, 'title' => 'Restaurant Menu Visual']);
    }

    /**
     * TEST 19: Uploaded media can be attached directly to Assessment Section.
     */
    public function test_19_uploaded_media_can_be_attached_to_assessment_section()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('conversation_part1.mp3', 1024, 'audio/mpeg');

        $uploadResponse = $this->actingAs($this->teacher)
            ->postJson(route('admin.media.store'), [
                'file'  => $file,
                'title' => 'Conversation Part 1 Audio Track',
            ]);

        $assetId = $uploadResponse->json('id');

        $attachResponse = $this->actingAs($this->teacher)
            ->post(route('teacher.tests.sections.media.attach', [
                'test'    => $this->test->id,
                'section' => $this->section->id,
            ]), [
                'media_asset_id' => $assetId,
                'caption'        => 'Conversation Audio Directions',
                'order'          => 1,
            ]);

        $attachResponse->assertRedirect(route('teacher.tests.show', $this->test->id));
        $this->assertDatabaseHas('test_section_media', [
            'test_section_id' => $this->section->id,
            'media_asset_id'  => $assetId,
            'caption'         => 'Conversation Audio Directions',
            'order'           => 1,
        ]);
    }

    /**
     * TEST 20: Unauthorized role cannot upload media.
     */
    public function test_20_unauthorized_role_cannot_upload()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('candidate_photo.png');

        // Candidate / student cannot upload
        $responseCandidate = $this->actingAs($this->candidate)
            ->postJson(route('admin.media.store'), [
                'file' => $file,
            ]);

        $responseCandidate->assertStatus(403);
    }
}
