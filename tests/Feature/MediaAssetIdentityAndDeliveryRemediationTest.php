<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAssetIdentityAndDeliveryRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $candidate;
    protected Test $test;
    protected TestSection $section1;
    protected TestSection $section2;
    protected TestSection $section3;
    protected MediaAsset $imageAsset;
    protected MediaAsset $audioAsset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['name' => 'Teacher', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->candidate = User::factory()->create(['name' => 'Candidate', 'status' => 'active']);
        $this->candidate->assignRole('student');

        Storage::fake('public');

        // Create physical fake files
        Storage::disk('public')->put('question-media/test_photo.jpg', 'fake-image-bytes');
        Storage::disk('public')->put('question-media/test_audio.mp3', 'fake-audio-bytes');

        $this->imageAsset = MediaAsset::create([
            'filename'      => 'test_photo.jpg',
            'original_name' => 'test_photo.jpg',
            'mime_type'     => 'image/jpeg',
            'type'          => 'image',
            'path'          => 'question-media/test_photo.jpg',
            'size'          => 1024,
            'status'        => 'active',
            'approval_status' => 'draft',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->audioAsset = MediaAsset::create([
            'filename'      => 'test_audio.mp3',
            'original_name' => 'test_audio.mp3',
            'mime_type'     => 'audio/mpeg',
            'type'          => 'audio',
            'path'          => 'question-media/test_audio.mp3',
            'size'          => 2048,
            'status'        => 'active',
            'approval_status' => 'draft',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->test = Test::create([
            'title'            => 'TOEIC Remediation Validation Test',
            'slug'             => 'toeic-remediation-test',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'created_by'       => $this->teacher->id,
            'status'           => 'published',
            'is_published'     => true,
        ]);

        $this->section1 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $this->section2 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 2: Question-Response',
            'section_type' => 'listening',
            'order'        => 2,
        ]);

        $this->section3 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 3: Conversations',
            'section_type' => 'listening',
            'order'        => 3,
        ]);
    }

    public function test_01_create_question_with_image_sets_image_media_asset_id(): void
    {
        $q = Question::create([
            'prompt'               => 'Look at the photograph.',
            'section'              => 'listening',
            'part_number'          => 1,
            'question_type'        => 'multiple_choice',
            'difficulty'           => 'easy',
            'image_media_asset_id' => $this->imageAsset->id,
        ]);

        $this->assertEquals($this->imageAsset->id, $q->image_media_asset_id);
        $this->assertEquals($this->imageAsset->id, $q->imageMediaAsset->id);
        $this->assertNull($q->audio_media_asset_id);
    }

    public function test_02_attach_audio_to_same_question_sets_audio_media_asset_id_without_overwriting_image(): void
    {
        $q = Question::create([
            'prompt'               => 'Look at the photograph.',
            'section'              => 'listening',
            'part_number'          => 1,
            'question_type'        => 'multiple_choice',
            'difficulty'           => 'easy',
            'image_media_asset_id' => $this->imageAsset->id,
        ]);

        $q->update([
            'audio_media_asset_id' => $this->audioAsset->id,
        ]);

        $q->refresh();
        $this->assertEquals($this->imageAsset->id, $q->image_media_asset_id);
        $this->assertEquals($this->audioAsset->id, $q->audio_media_asset_id);
    }

    public function test_03_change_image_preserves_audio_fk(): void
    {
        $newPhoto = MediaAsset::create([
            'filename'      => 'photo2.jpg',
            'original_name' => 'photo2.jpg',
            'mime_type'     => 'image/jpeg',
            'type'          => 'image',
            'path'          => 'question-media/test_photo.jpg',
            'size'          => 1000,
            'status'        => 'active',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $q = Question::create([
            'prompt'               => 'Look at the photograph.',
            'section'              => 'listening',
            'part_number'          => 1,
            'question_type'        => 'multiple_choice',
            'difficulty'           => 'easy',
            'image_media_asset_id' => $this->imageAsset->id,
            'audio_media_asset_id' => $this->audioAsset->id,
        ]);

        $q->update(['image_media_asset_id' => $newPhoto->id]);
        $q->refresh();

        $this->assertEquals($newPhoto->id, $q->image_media_asset_id);
        $this->assertEquals($this->audioAsset->id, $q->audio_media_asset_id);
    }

    public function test_04_remove_image_preserves_audio_and_physical_asset(): void
    {
        $q = Question::create([
            'prompt'               => 'Look at the photograph.',
            'section'              => 'listening',
            'part_number'          => 1,
            'question_type'        => 'multiple_choice',
            'difficulty'           => 'easy',
            'image_media_asset_id' => $this->imageAsset->id,
            'audio_media_asset_id' => $this->audioAsset->id,
        ]);

        $q->update(['image_media_asset_id' => null]);
        $q->refresh();

        $this->assertNull($q->image_media_asset_id);
        $this->assertEquals($this->audioAsset->id, $q->audio_media_asset_id);
        $this->assertNotNull(MediaAsset::find($this->imageAsset->id));
    }

    public function test_05_mime_safety_resolvers_filter_incompatible_media(): void
    {
        // Question where image FK accidentally pointed to audio asset
        $q = new Question([
            'prompt'               => 'Stem',
            'image_media_asset_id' => $this->audioAsset->id, // Incompatible!
            'audio_media_asset_id' => $this->imageAsset->id, // Incompatible!
        ]);

        $this->assertNull($q->getEffectiveImageMedia());
        $this->assertNull($q->getEffectiveAudioMedia());
    }

    public function test_06_candidate_audio_stream_serves_valid_audio(): void
    {
        $q = Question::create([
            'prompt'               => 'Part 1 Question',
            'section'              => 'listening',
            'part_number'          => 1,
            'question_type'        => 'multiple_choice',
            'difficulty'           => 'easy',
            'image_media_asset_id' => $this->imageAsset->id,
            'audio_media_asset_id' => $this->audioAsset->id,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section1->id,
            'question_id'     => $q->id,
            'order'           => 1,
        ]);

        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->candidate->id,
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.exam.audio-stream', [$attempt, $q]));

        $response->assertStatus(200);
        $this->assertEquals('audio/mpeg', $response->headers->get('Content-Type'));
    }

    public function test_07_audio_group_delivers_shared_audio_stream(): void
    {
        $audioGroup = AudioGroup::create([
            'test_id'        => $this->test->id,
            'title'          => 'Conversation 1',
            'group_type'     => 'conversation',
            'part_number'    => 3,
            'media_asset_id' => $this->audioAsset->id,
            'order'          => 1,
            'created_by'     => $this->teacher->id,
        ]);

        $q = Question::create([
            'prompt'         => 'Where does the conversation take place?',
            'section'        => 'listening',
            'part_number'    => 3,
            'question_type'  => 'multiple_choice',
            'difficulty'     => 'medium',
            'audio_group_id' => $audioGroup->id,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section3->id,
            'question_id'     => $q->id,
            'order'           => 1,
        ]);

        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->candidate->id,
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        $this->assertEquals($this->audioAsset->id, $q->getEffectiveAudioMedia()->id);

        $response = $this->actingAs($this->candidate)
            ->get(route('candidate.exam.audio-stream', [$attempt, $q]));

        $response->assertStatus(200);
        $this->assertEquals('audio/mpeg', $response->headers->get('Content-Type'));
    }

    public function test_08_media_reference_counts_include_new_fks(): void
    {
        $q = Question::create([
            'prompt'               => 'Test question',
            'section'              => 'listening',
            'part_number'          => 1,
            'question_type'        => 'multiple_choice',
            'difficulty'           => 'easy',
            'image_media_asset_id' => $this->imageAsset->id,
            'audio_media_asset_id' => $this->audioAsset->id,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get(route('admin.media.usage', $this->imageAsset->id));

        $response->assertStatus(200);
        $this->assertTrue($response->json('is_used'));
        $this->assertGreaterThan(0, $response->json('question_count'));
    }

    public function test_09_media_preview_route_serves_authenticated_users(): void
    {
        $respTeacher = $this->actingAs($this->teacher)
            ->get(route('media.preview', $this->imageAsset->id));
        $respTeacher->assertStatus(200);
        $this->assertEquals('image/jpeg', $respTeacher->headers->get('Content-Type'));

        $respCandidate = $this->actingAs($this->candidate)
            ->get(route('media.preview', $this->imageAsset->id));
        $respCandidate->assertStatus(200);

        // Guest is blocked
        $this->app['auth']->guard()->logout();
        $respGuest = $this->get(route('media.preview', $this->imageAsset->id));
        $respGuest->assertStatus(403);
    }
}
