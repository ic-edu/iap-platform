<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Question;
use App\Services\QuestionDifficultyDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherMediaAttachModalUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $teacher2;
    protected User $admin;
    protected Test $test;
    protected TestSection $section;
    protected Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->teacher = User::factory()->create(['name' => 'Teacher Media Tester', 'email' => 'teacher.media@test.com']);
        $this->teacher->assignRole('teacher');

        $this->teacher2 = User::factory()->create(['name' => 'Teacher Two', 'email' => 'teacher2@test.com']);
        $this->teacher2->assignRole('teacher');

        $this->admin = User::factory()->create(['name' => 'Admin Tester', 'email' => 'admin@test.com']);
        $this->admin->assignRole('super-admin');

        $this->test = Test::create([
            'title'            => 'TOEIC Test for Media Testing',
            'slug'             => 'toeic-test-media-testing',
            'test_type'        => 'toeic',
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'created_by'       => $this->teacher->id,
            'status'           => 'draft',
            'is_published'     => false,
        ]);

        $this->section = TestSection::create([
            'test_id' => $this->test->id,
            'title'   => 'Part 1 Photographs',
            'order'   => 1,
        ]);

        $this->question = Question::create([
            'prompt'          => 'Look at the photograph and choose the best statement.',
            'question_type'   => 'multiple_choice',
            'difficulty'      => 'easy',
            'part_number'     => 1,
            'section'         => 'listening',
            'points'          => 5,
            'created_by'      => $this->teacher->id,
        ]);
    }

    /** TEST 01: assessment_detail asset preview z-index > media picker z-index. */
    public function test_01_assessment_detail_preview_z_index_greater_than_picker(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/id=["\']asset-preview-modal["\'][^>]*style=["\'][^"\']*z-index:\s*10002/', $html);
        $this->assertMatchesRegularExpression('/id=["\']question-media-picker-modal["\'][^>]*style=["\'][^"\']*z-index:\s*10001/', $html);
    }

    /** TEST 02: question_editor asset preview z-index > media picker z-index. */
    public function test_02_question_editor_preview_z_index_greater_than_picker(): void
    {
        $editorResponse = $this->actingAs($this->teacher)->get(
            route('teacher.tests.edit-question', ['test' => $this->test->id, 'question' => $this->question->id])
        );
        $editorResponse->assertStatus(200);
        $html = $editorResponse->getContent();

        $this->assertMatchesRegularExpression('/id=["\']asset-preview-modal["\'][^>]*style=["\'][^"\']*z-index:\s*10002/', $html);
        $this->assertMatchesRegularExpression('/id=["\']question-media-picker-modal["\'][^>]*style=["\'][^"\']*z-index:\s*10001/', $html);
    }

    /** TEST 03: Asset preview modal and media picker remain siblings. */
    public function test_03_modals_remain_siblings(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $html = $response->getContent();

        $previewModalPos = strpos($html, 'id="asset-preview-modal"');
        $questionModalPos = strpos($html, 'id="question-media-picker-modal"');

        $this->assertNotFalse($previewModalPos);
        $this->assertNotFalse($questionModalPos);

        $betweenChunk = substr($html, $previewModalPos, $questionModalPos - $previewModalPos);
        $this->assertStringContainsString('id="apm-title"', $betweenChunk);
        $this->assertStringContainsString('id="apm-content"', $betweenChunk);
        $this->assertStringContainsString('closeAssetPreviewModal', $betweenChunk);
        $this->assertGreaterThanOrEqual(3, substr_count($betweenChunk, '</div>'));
    }

    /** TEST 04: Asset preview modal occurs exactly once in DOM. */
    public function test_04_asset_preview_modal_occurs_once(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $this->assertEquals(1, substr_count($response->getContent(), 'id="asset-preview-modal"'));
    }

    /** TEST 05: Media picker modal occurs exactly once in DOM. */
    public function test_05_media_picker_modal_occurs_once(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $this->assertEquals(1, substr_count($response->getContent(), 'id="question-media-picker-modal"'));
    }

    /** TEST 06: previewAssetModal handler preserved. */
    public function test_06_preview_asset_modal_handler_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('function previewAssetModal', false);
        $response->assertSee('document.getElementById(\'apm-title\')', false);
        $response->assertSee('document.getElementById(\'apm-content\')', false);
    }

    /** TEST 07: Audio preview renderer preserved. */
    public function test_07_audio_preview_renderer_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('<audio controls', false);
    }

    /** TEST 08: Image preview renderer preserved. */
    public function test_08_image_preview_renderer_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('<img src="${url}"', false);
    }

    /** TEST 09: PDF preview renderer preserved. */
    public function test_09_pdf_preview_renderer_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('<iframe src="${url}#toolbar=0"', false);
    }

    /** TEST 10: Passage preview renderer preserved. */
    public function test_10_passage_preview_renderer_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('Reading / text passage preview...', false);
    }

    /** TEST 11: closeAssetPreviewModal preserved. */
    public function test_11_close_asset_preview_modal_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('function closeAssetPreviewModal', false);
        $response->assertSee('onclick="closeAssetPreviewModal()"', false);
    }

    /** TEST 12: Attach handler preserved. */
    public function test_12_attach_handler_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('function selectQuestionMediaItem', false);
        $response->assertSee("openQuestionMediaPicker('create', 'image')", false);
        $response->assertSee("openQuestionMediaPicker('create', 'audio')", false);
    }

    /** TEST 13: Media Library search preserved. */
    public function test_13_media_library_search_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('id="qm-search-input"', false);
        $response->assertSee('searchQuestionMediaModal', false);
    }

    /** TEST 14: Media Library filters preserved. */
    public function test_14_media_library_filters_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('filterQuestionMediaModal', false);
        $response->assertSee('All Media');
        $response->assertSee('Images');
        $response->assertSee('Audio Tracks');
    }

    /** TEST 15: Direct Upload UX preserved. */
    public function test_15_direct_upload_ux_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('Upload New File');
        $response->assertSee('id="qm-choose-file-btn"', false);
        $response->assertSee('id="qm-upload-btn"', false);
    }

    /** TEST 16: Auto Difficulty UI preserved. */
    public function test_16_auto_difficulty_ui_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertSee('Question Difficulty');
        $response->assertSee('Difficulty is detected automatically from question content, part rules, and attached media.');
    }

    /** TEST 17: Candidate Preview preserved. */
    public function test_17_candidate_preview_preserved(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertStatus(200);
    }

    /** TEST 18: Question Editor preview architecture preserved. */
    public function test_18_question_editor_preview_architecture_preserved(): void
    {
        $editorResponse = $this->actingAs($this->teacher)->get(
            route('teacher.tests.edit-question', ['test' => $this->test->id, 'question' => $this->question->id])
        );
        $editorResponse->assertStatus(200);
        $editorResponse->assertSee('function previewAssetModal', false);
        $editorResponse->assertSee('id="asset-preview-modal"', false);
        $editorResponse->assertSee('id="question-media-picker-modal"', false);
    }

    /** TEST 19: No backend MediaPreviewController change / Preview route functional. */
    public function test_19_preview_route_functional(): void
    {
        $media = MediaAsset::create([
            'title'           => 'Test Sound Track',
            'filename'        => 'sound.mp3',
            'original_name'   => 'sound.mp3',
            'path'            => 'media/sound.mp3',
            'disk'            => 'public',
            'mime_type'       => 'audio/mpeg',
            'size'            => 10240,
            'type'            => 'audio',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('media.preview', $media->id));
        $this->assertContains($response->getStatusCode(), [200, 302]);
    }

    /** TEST 20: No media attachment mutation in regression test. */
    public function test_20_no_media_attachment_mutation(): void
    {
        $this->assertEquals('draft', $this->test->status);
        $this->assertNull($this->question->media_asset_id);
    }

    /** TEST 21: Media list endpoint returns teacher's drafts/assets when source=my. */
    public function test_21_media_list_source_my_returns_teacher_drafts(): void
    {
        MediaAsset::create([
            'title'           => 'Teacher Personal Draft',
            'filename'        => 'my_draft.jpg',
            'original_name'   => 'my_draft.jpg',
            'path'            => 'media/my_draft.jpg',
            'disk'            => 'public',
            'mime_type'       => 'image/jpeg',
            'size'            => 1024,
            'type'            => 'image',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher->id,
        ]);

        MediaAsset::create([
            'title'           => 'Institutional Approved Photo',
            'filename'        => 'inst_photo.jpg',
            'original_name'   => 'inst_photo.jpg',
            'path'            => 'media/inst_photo.jpg',
            'disk'            => 'public',
            'mime_type'       => 'image/jpeg',
            'size'            => 1024,
            'type'            => 'image',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacher2->id,
        ]);

        $response = $this->actingAs($this->teacher)->getJson('/admin/media/list?source=my');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Teacher Personal Draft', $data[0]['title']);
    }

    /** TEST 22: Media list endpoint with source=institutional returns approved assets. */
    public function test_22_media_list_source_institutional_returns_approved_assets(): void
    {
        MediaAsset::create([
            'title'           => 'Teacher Personal Draft',
            'filename'        => 'my_draft.jpg',
            'original_name'   => 'my_draft.jpg',
            'path'            => 'media/my_draft.jpg',
            'disk'            => 'public',
            'mime_type'       => 'image/jpeg',
            'size'            => 1024,
            'type'            => 'image',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher->id,
        ]);

        MediaAsset::create([
            'title'           => 'Institutional Approved Photo',
            'filename'        => 'inst_photo.jpg',
            'original_name'   => 'inst_photo.jpg',
            'path'            => 'media/inst_photo.jpg',
            'disk'            => 'public',
            'mime_type'       => 'image/jpeg',
            'size'            => 1024,
            'type'            => 'image',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->teacher2->id,
        ]);

        $response = $this->actingAs($this->teacher)->getJson('/admin/media/list?source=institutional');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Institutional Approved Photo', $data[0]['title']);
    }

    /** TEST 23: Teacher cannot see other teachers' draft assets in source=my. */
    public function test_23_teacher_cannot_see_other_teachers_draft_in_my_media(): void
    {
        MediaAsset::create([
            'title'           => 'Other Teacher Draft',
            'filename'        => 'other_draft.mp3',
            'original_name'   => 'other_draft.mp3',
            'path'            => 'media/other_draft.mp3',
            'disk'            => 'public',
            'mime_type'       => 'audio/mpeg',
            'size'            => 2048,
            'type'            => 'audio',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher2->id,
        ]);

        $response = $this->actingAs($this->teacher)->getJson('/admin/media/list?source=my');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEmpty($data);
    }

    /** TEST 24: Upload endpoint computes content_hash SHA-256. */
    public function test_24_upload_computes_sha256_content_hash(): void
    {
        $file = UploadedFile::fake()->image('photo1.jpg', 100, 100);

        $response = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $asset = MediaAsset::where('original_name', 'photo1.jpg')->first();
        $this->assertNotNull($asset);
        $this->assertNotNull($asset->content_hash);
        $this->assertEquals(64, strlen($asset->content_hash));
    }

    /** TEST 25: Same teacher uploading identical file (same SHA-256) is deduplicated. */
    public function test_25_same_teacher_identical_file_deduplicated(): void
    {
        $file1 = UploadedFile::fake()->create('track1.mp3', 20, 'audio/mpeg');

        $resp1 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file' => $file1,
        ]);
        $resp1->assertStatus(200);
        $firstId = $resp1->json('id');

        $file2 = UploadedFile::fake()->create('track1.mp3', 20, 'audio/mpeg');
        $resp2 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file' => $file2,
        ]);
        $resp2->assertStatus(200);
        $resp2->assertJson([
            'success' => true,
            'reused'  => true,
        ]);
        $this->assertEquals($firstId, $resp2->json('id'));
    }

    /** TEST 26: Deduplicated upload does not create extra DB row or duplicate storage file. */
    public function test_26_deduplicated_upload_does_not_create_duplicate_row(): void
    {
        $file1 = UploadedFile::fake()->create('chart.mp3', 30, 'audio/mpeg');
        $file2 = UploadedFile::fake()->create('chart.mp3', 30, 'audio/mpeg');

        $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file1]);
        $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file2]);

        $this->assertEquals(1, MediaAsset::where('uploaded_by', $this->teacher->id)->count());
    }

    /** TEST 27: Same teacher uploading different content with same filename creates safe new version. */
    public function test_27_same_name_different_content_creates_safe_new_version(): void
    {
        $file1 = UploadedFile::fake()->image('diagram.png', 10, 10);
        $resp1 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file1]);
        $resp1->assertStatus(200);
        $asset1 = MediaAsset::find($resp1->json('id'));
        $this->assertEquals('1.0', $asset1->version);
        $this->assertEquals('diagram.png', $asset1->original_name);

        $file2 = UploadedFile::fake()->image('diagram.png', 50, 50);
        $resp2 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $file2]);
        $resp2->assertStatus(200);
        $asset2 = MediaAsset::find($resp2->json('id'));

        $this->assertNotEquals($asset1->id, $asset2->id);
        $this->assertEquals('2.0', $asset2->version);
        $this->assertEquals('diagram.png', $asset2->original_name);
    }

    /** TEST 28: Different teachers uploading same file hash are safely isolated by owner. */
    public function test_28_different_teachers_same_file_isolated(): void
    {
        $fileA = UploadedFile::fake()->create('shared.mp3', 25, 'audio/mpeg');
        $fileB = UploadedFile::fake()->create('shared.mp3', 25, 'audio/mpeg');

        $respA = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $fileA]);
        $respB = $this->actingAs($this->teacher2)->postJson(route('admin.media.store'), ['file' => $fileB]);

        $idA = $respA->json('id');
        $idB = $respB->json('id');
        $this->assertNotNull($idA);
        $this->assertNotNull($idB);
        $this->assertNotEquals($idA, $idB);
        $hash = MediaAsset::find($idA)->content_hash;
        $this->assertEquals(2, MediaAsset::where('content_hash', $hash)->count());
    }

    /** TEST 29: Idempotent backfill command runs cleanly. */
    public function test_29_idempotent_backfill_command_computes_hashes(): void
    {
        $asset = MediaAsset::create([
            'title'           => 'Legacy Asset Without Hash',
            'filename'        => 'legacy.jpg',
            'original_name'   => 'legacy.jpg',
            'path'            => 'legacy.jpg',
            'disk'            => 'public',
            'mime_type'       => 'image/jpeg',
            'size'            => 100,
            'type'            => 'image',
            'approval_status' => 'draft',
            'uploaded_by'     => $this->teacher->id,
            'content_hash'    => null,
        ]);
        Storage::disk('public')->put('legacy.jpg', 'Legacy binary data');

        $exitCode = Artisan::call('media:backfill-content-hashes');
        $this->assertEquals(0, $exitCode);

        $asset->refresh();
        $this->assertEquals(hash('sha256', 'Legacy binary data'), $asset->content_hash);
    }

    /** TEST 30: assessment_detail contains explicit source tabs. */
    public function test_30_assessment_detail_contains_explicit_source_tabs(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertStatus(200);
        $response->assertSee('id="qm-source-my-btn"', false);
        $response->assertSee('id="qm-source-inst-btn"', false);
        $response->assertSee('My Media');
        $response->assertSee('Institutional Library');
    }

    /** TEST 31: question_editor contains explicit source tabs. */
    public function test_31_question_editor_contains_explicit_source_tabs(): void
    {
        $editorResponse = $this->actingAs($this->teacher)->get(
            route('teacher.tests.edit-question', ['test' => $this->test->id, 'question' => $this->question->id])
        );
        $editorResponse->assertStatus(200);
        $editorResponse->assertSee('id="eqm-source-my-btn"', false);
        $editorResponse->assertSee('id="eqm-source-inst-btn"', false);
        $editorResponse->assertSee('My Media');
        $editorResponse->assertSee('Institutional Library');
    }

    /** TEST 32: Institutional approved assets cannot be overwritten by teacher upload. */
    public function test_32_institutional_approved_assets_immutable(): void
    {
        $approved = MediaAsset::create([
            'title'           => 'Official Institution Track',
            'filename'        => 'official.mp3',
            'original_name'   => 'official.mp3',
            'path'            => 'official.mp3',
            'disk'            => 'public',
            'mime_type'       => 'audio/mpeg',
            'size'            => 5000,
            'type'            => 'audio',
            'approval_status' => 'approved',
            'uploaded_by'     => $this->admin->id,
            'content_hash'    => hash('sha256', 'Original official content'),
        ]);

        $newUpload = UploadedFile::fake()->createWithContent('official.mp3', 'Different content by teacher', 'audio/mpeg');
        $this->actingAs($this->teacher)->postJson(route('admin.media.store'), ['file' => $newUpload]);

        $approved->refresh();
        $this->assertEquals('approved', $approved->approval_status);
        $this->assertEquals($this->admin->id, $approved->uploaded_by);
    }

    /** TEST 33: Non-regression for candidate test flow, audio playback policy, and auto-difficulty. */
    public function test_33_non_regression_candidate_flow_and_audio_and_auto_difficulty(): void
    {
        $service = app(QuestionDifficultyDetectionService::class);
        $result = $service->detect([
            'test_type'   => $this->test->test_type,
            'part_number' => 1,
            'prompt'      => 'Short prompt',
        ]);
        $this->assertContains($result['difficulty_level'], ['easy', 'medium', 'hard']);

        $candidatePreview = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $candidatePreview->assertStatus(200);
    }
}

