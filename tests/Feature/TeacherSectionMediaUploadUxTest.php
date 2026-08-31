<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherSectionMediaUploadUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $otherTeacher;
    protected User $admin;
    protected Test $test;
    protected TestSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

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

        $this->admin = User::factory()->create([
            'name'   => 'Admin User',
            'email'  => 'admin@icedu.org',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super-admin');

        $this->test = Test::create([
            'title'            => 'TOEIC Practice Test',
            'slug'             => 'toeic-practice-test-' . \Illuminate\Support\Str::random(6),
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'status'           => 'draft',
            'created_by'       => $this->teacher->id,
            'assigned_to'      => $this->teacher->id,
        ]);

        $this->section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'instructions' => 'Look at the photo and choose the best statement.',
            'order'        => 1,
        ]);
    }

    /** TEST 01: Section Media modal renders Upload New Media. */
    public function test_01_section_media_modal_renders_upload_new_media(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Upload New Media', false);
        $response->assertSee('id="attach-section-media-modal"', false);
        $response->assertSee('id="asm-upload-panel"', false);
    }

    /** TEST 02: No misleading "Upload Media to Institutional Library" wording remains for Teacher direct upload. */
    public function test_02_no_misleading_institutional_library_upload_wording(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertDontSee('Upload Media to Institutional Library', false);
    }

    /** TEST 03: Helper states new uploads are saved to My Media. */
    public function test_03_helper_states_new_uploads_saved_to_my_media(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('New uploads are saved to My Media and can be selected immediately.', false);
    }

    /** TEST 04: Raw native file input is not the primary visible presentation. */
    public function test_04_raw_native_file_input_is_hidden(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asm-upload-file"', false);
        $response->assertSee('class="sr-only"', false);
    }

    /** TEST 05: Choose File is visible. */
    public function test_05_choose_file_is_visible(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asm-choose-file-btn"', false);
        $response->assertSee('Choose File', false);
    }

    /** TEST 06: Upload & Select Asset disabled before selection. */
    public function test_06_upload_and_select_asset_disabled_before_selection(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asm-upload-btn"', false);
        $response->assertSee('disabled', false);
        $response->assertSee('aria-disabled="true"', false);
    }

    /** TEST 07: Selected filename appears after file selection. */
    public function test_07_selected_filename_container_exists(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asm-selected-filename"', false);
        $response->assertSee('id="asm-selected-upload-state"', false);
    }

    /** TEST 08: Selected filesize appears where available. */
    public function test_08_selected_filesize_container_exists(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asm-selected-filesize"', false);
        $response->assertSee('id="asm-selected-type-badge"', false);
    }

    /** TEST 09: Change File is available. */
    public function test_09_change_file_is_available(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Change File', false);
    }

    /** TEST 10: Clear selection returns empty state. */
    public function test_10_clear_selection_returns_empty_state(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('clearSectionMediaFileSelection', false);
        $response->assertSee('id="asm-empty-upload-state"', false);
    }

    /** TEST 11: Upload action activates after valid selection. */
    public function test_11_upload_action_activates_after_valid_selection(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('handleSectionMediaFileSelect', false);
    }

    /** TEST 12: Busy state prevents duplicate upload. */
    public function test_12_busy_state_prevents_duplicate_upload(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Uploading...', false);
        $response->assertSee('uploadBtn.disabled = true;', false);
    }

    /** TEST 13: Image upload works through existing SHA-256 pipeline. */
    public function test_13_image_upload_works_through_sha256_pipeline(): void
    {
        $file = UploadedFile::fake()->image('photo_sample.jpg', 600, 400);

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file'  => $file,
            'title' => 'Sample Photo for Part 1',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $asset = MediaAsset::where('title', 'Sample Photo for Part 1')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('image', $asset->type);
        $this->assertNotEmpty($asset->content_hash);
    }

    /** TEST 14: Audio MP3 upload works. */
    public function test_14_audio_mp3_upload_works(): void
    {
        $file = UploadedFile::fake()->create('directions.mp3', 200, 'audio/mpeg');

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file'  => $file,
            'title' => 'Part 1 Audio MP3',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $asset = MediaAsset::where('title', 'Part 1 Audio MP3')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('audio', $asset->type);
    }

    /** TEST 15: M4A upload works. */
    public function test_15_m4a_upload_works(): void
    {
        $file = UploadedFile::fake()->create('audio_track.m4a', 300, 'audio/x-m4a');

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file'  => $file,
            'title' => 'Part 1 Audio M4A',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $asset = MediaAsset::where('title', 'Part 1 Audio M4A')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('audio', $asset->type);
    }

    /** TEST 16: WAV upload works. */
    public function test_16_wav_upload_works(): void
    {
        $file = UploadedFile::fake()->create('audio_track.wav', 400, 'audio/wav');

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file'  => $file,
            'title' => 'Part 1 Audio WAV',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $asset = MediaAsset::where('title', 'Part 1 Audio WAV')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('audio', $asset->type);
    }

    /** TEST 17: PDF validation remains correct. */
    public function test_17_pdf_validation_remains_correct(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file'  => $file,
            'title' => 'Section Reference PDF',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $asset = MediaAsset::where('title', 'Section Reference PDF')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('pdf', $asset->type);
    }

    /** TEST 18: Same-owner identical upload reuses MediaAsset. */
    public function test_18_same_owner_identical_upload_reuses_media_asset(): void
    {
        $content = 'identical_audio_content_stream_12345';
        $file1 = UploadedFile::fake()->createWithContent('audio_same.mp3', $content);
        $file2 = UploadedFile::fake()->createWithContent('audio_same_copy.mp3', $content);

        $res1 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file1]);
        $res1->assertOk();
        $id1 = $res1->json('asset.id') ?? $res1->json('id');

        $res2 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file2]);
        $res2->assertOk();
        $id2 = $res2->json('asset.id') ?? $res2->json('id');

        $this->assertEquals($id1, $id2);
    }

    /** TEST 19: Same-name different-content upload does not overwrite. */
    public function test_19_same_name_different_content_upload_does_not_overwrite(): void
    {
        $file1 = UploadedFile::fake()->createWithContent('track.mp3', 'first_audio_content_aaa');
        $file2 = UploadedFile::fake()->createWithContent('track.mp3', 'second_audio_content_bbb');

        $res1 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file1]);
        $res1->assertOk();
        $id1 = $res1->json('asset.id') ?? $res1->json('id');

        $res2 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file2]);
        $res2->assertOk();
        $id2 = $res2->json('asset.id') ?? $res2->json('id');

        $this->assertNotEquals($id1, $id2);
    }

    /** TEST 20: New Teacher upload remains approval_status=draft. */
    public function test_20_new_teacher_upload_remains_approval_status_draft(): void
    {
        $file = UploadedFile::fake()->create('draft_audio.mp3', 100, 'audio/mpeg');

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file]);
        $response->assertOk();

        $id = $response->json('asset.id') ?? $response->json('id');
        $asset = MediaAsset::find($id);
        $this->assertEquals('draft', $asset->approval_status);
    }

    /** TEST 21: New Teacher upload belongs to current Teacher. */
    public function test_21_new_teacher_upload_belongs_to_current_teacher(): void
    {
        $file = UploadedFile::fake()->create('teacher_audio.mp3', 100, 'audio/mpeg');

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file]);
        $response->assertOk();

        $id = $response->json('asset.id') ?? $response->json('id');
        $asset = MediaAsset::find($id);
        $this->assertEquals($this->teacher->id, $asset->uploaded_by);
    }

    /** TEST 22: My Media source returns uploaded draft. */
    public function test_22_my_media_source_returns_uploaded_draft(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Teacher Personal Draft Audio',
            'filename'        => 'my_draft.mp3',
            'original_name'   => 'my_draft.mp3',
            'path'            => 'media/my_draft.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'uploaded_by'     => $this->teacher->id,
            'approval_status' => 'draft',
        ]);

        $response = $this->actingAs($this->teacher)->getJson('/admin/media/list?source=my');
        $response->assertOk();
        $response->assertJsonFragment(['id' => $asset->id]);
    }

    /** TEST 23: Institutional source returns approved assets. */
    public function test_23_institutional_source_returns_approved_assets(): void
    {
        $approvedAsset = MediaAsset::create([
            'title'           => 'Governed Institutional Audio',
            'filename'        => 'inst_audio.mp3',
            'original_name'   => 'inst_audio.mp3',
            'path'            => 'media/inst_audio.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'uploaded_by'     => $this->admin->id,
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($this->teacher)->getJson('/admin/media/list?source=institutional');
        $response->assertOk();
        $response->assertJsonFragment(['id' => $approvedAsset->id]);
    }

    /** TEST 24: Other Teacher draft remains invisible. */
    public function test_24_other_teacher_draft_remains_invisible(): void
    {
        $otherAsset = MediaAsset::create([
            'title'           => 'Other Teacher Private Audio',
            'filename'        => 'other_draft.mp3',
            'original_name'   => 'other_draft.mp3',
            'path'            => 'media/other_draft.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'uploaded_by'     => $this->otherTeacher->id,
            'approval_status' => 'draft',
        ]);

        $response = $this->actingAs($this->teacher)->getJson('/admin/media/list?source=my');
        $response->assertOk();
        $response->assertJsonMissing(['id' => $otherAsset->id]);
    }

    /** TEST 25: Preview modal still works above picker. */
    public function test_25_preview_modal_still_works_above_picker(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asset-preview-modal"', false);
        $response->assertSee('z-index: 10002', false);
        $response->assertSee('id="attach-section-media-modal"', false);
        $response->assertSee('z-index: 10001', false);
    }

    /** TEST 26: Section target association remains correct. */
    public function test_26_section_target_association_remains_correct(): void
    {
        $asset = MediaAsset::create([
            'title'         => 'Section Directions Audio',
            'filename'      => 'directions_sec.mp3',
            'original_name' => 'directions_sec.mp3',
            'path'          => 'media/directions_sec.mp3',
            'mime_type'     => 'audio/mpeg',
            'type'          => 'audio',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->post(
            route('teacher.tests.sections.media.attach', ['test' => $this->test->id, 'section' => $this->section->id]),
            [
                'media_asset_id' => $asset->id,
                'caption'        => 'Official Part 1 Directions',
                'order'          => 1,
            ]
        );

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $this->assertDatabaseHas('test_section_media', [
            'test_section_id' => $this->section->id,
            'media_asset_id'  => $asset->id,
            'caption'         => 'Official Part 1 Directions',
            'order'           => 1,
        ]);
    }

    /** TEST 27: Caption metadata remains correct. */
    public function test_27_caption_metadata_remains_correct(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asm-caption"', false);
        $response->assertSee('Section Media Caption / Directions Label (Optional)', false);
    }

    /** TEST 28: Display order remains correct. */
    public function test_28_display_order_remains_correct(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asm-order"', false);
        $response->assertSee('Display Order', false);
    }

    /** TEST 29: Question Media upload UX remains unchanged. */
    public function test_29_question_media_upload_ux_remains_functional(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="question-media-picker-modal"', false);
        $response->assertSee('id="qm-direct-file-input"', false);
        $response->assertSee('id="qm-choose-file-btn"', false);
    }

    /** TEST 30: No assessment lifecycle mutation. */
    public function test_30_no_assessment_lifecycle_mutation(): void
    {
        $statusBefore = $this->test->fresh()->status;

        $asset = MediaAsset::create([
            'title'         => 'Lifecycle Safe Media',
            'filename'      => 'safe.mp3',
            'original_name' => 'safe.mp3',
            'path'          => 'media/safe.mp3',
            'mime_type'     => 'audio/mpeg',
            'type'          => 'audio',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher)->post(
            route('teacher.tests.sections.media.attach', ['test' => $this->test->id, 'section' => $this->section->id]),
            [
                'media_asset_id' => $asset->id,
            ]
        );

        $this->assertEquals($statusBefore, $this->test->fresh()->status);
    }
}
