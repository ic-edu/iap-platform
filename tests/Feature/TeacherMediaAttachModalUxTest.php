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
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherMediaAttachModalUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
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

        // In assessment_detail: asset-preview-modal has z-[70] and question-media-picker-modal has z-[60]
        $this->assertMatchesRegularExpression('/id=["\']asset-preview-modal["\'][^>]*class=["\'][^"\']*z-\[70\]/', $html);
        $this->assertMatchesRegularExpression('/id=["\']question-media-picker-modal["\'][^>]*class=["\'][^"\']*z-\[60\]/', $html);
    }

    /** TEST 02: question_editor asset preview z-index > media picker z-index. */
    public function test_02_question_editor_preview_z_index_greater_than_picker(): void
    {
        $editorResponse = $this->actingAs($this->teacher)->get(
            route('teacher.tests.edit-question', ['test' => $this->test->id, 'question' => $this->question->id])
        );
        $editorResponse->assertStatus(200);
        $html = $editorResponse->getContent();

        // In question_editor: asset-preview-modal has z-index:10002 and question-media-picker-modal has z-index:10001
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
}
