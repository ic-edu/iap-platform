<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\MediaInstitutionalRepositorySeeder;
use Database\Seeders\AclStarterLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherMediaWordingAndReadabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'email' => 'teacher_readability@icedu.org',
            'name'  => 'Teacher Readability',
        ]);
        $this->teacher->assignRole('teacher');

        $this->admin = User::factory()->create([
            'email' => 'admin_readability@icedu.org',
            'name'  => 'Admin Readability',
        ]);
        $this->admin->assignRole('super-admin');
    }

    protected function createMediaAsset(array $overrides = []): MediaAsset
    {
        $hashName = 'sample_' . Str::random(12) . '.mp3';
        return MediaAsset::create(array_merge([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Sample Educational Asset',
            'filename'        => $hashName,
            'original_name'   => 'sample_asset.mp3',
            'path'            => 'media/' . $hashName,
            'disk'            => 'public',
            'mime_type'       => 'audio/mpeg',
            'file_size'       => 1024 * 350,
            'type'            => 'audio',
            'exam_type'       => 'toeic',
            'category'        => 'Listening Practice',
            'approval_status' => 'approved',
            'version'         => '1.0',
        ], $overrides));
    }

    /**
     * TEST 1: Teacher My Media renders with institutional title and subtitle
     */
    public function test_01_teacher_my_media_renders_with_institutional_title_and_subtitle(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('My Media &amp; Working Library', false);
        $response->assertSee('Teacher Workspace');
        $response->assertSee('Manage personal working media');
    }

    /**
     * TEST 2: Teacher My Media KPI labels are readable in Light Theme
     */
    public function test_02_teacher_my_media_kpi_labels_are_readable(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Total My Media');
        $response->assertSee('Working / Draft');
        $response->assertSee('Used in Assessments');
        $response->assertSee('Pending RM Review');
        $response->assertSee('Institutional Approved');
        $response->assertSee('Needs Revision');
    }

    /**
     * TEST 3: Teacher My Media status pills are readable in Light Theme
     */
    public function test_03_teacher_my_media_status_pills_are_readable(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('tm-pill-btn', false);
        $response->assertSee('In Assessments');
        $response->assertSee('Pending Review');
        $response->assertSee('Approved');
        $response->assertSee('Needs Revision');
        $response->assertSee('Rejected');
    }

    /**
     * TEST 4: Teacher My Media type pills are readable in Light Theme
     */
    public function test_04_teacher_my_media_type_pills_are_readable(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('All Types');
        $response->assertSee('Images');
        $response->assertSee('Audio');
        $response->assertSee('PDFs');
        $response->assertSee('Passages');
    }

    /**
     * TEST 5: Teacher My Media card title is styled with strong contrast
     */
    public function test_05_teacher_my_media_card_title_has_strong_contrast(): void
    {
        $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'TOEIC Listening Practice Audio 01',
            'type'            => 'audio',
            'exam_type'       => 'toeic',
            'approval_status' => 'working',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Listening Practice Audio 01');
        $response->assertSee('text-slate-900 dark:text-white', false);
    }

    /**
     * TEST 6: Teacher My Media card filename and size are styled with strong contrast
     */
    public function test_06_teacher_my_media_card_filename_and_size_have_strong_contrast(): void
    {
        $asset = $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Sample Audio Clip',
            'original_name'   => 'test_sample_audio.mp3',
            'type'            => 'audio',
            'file_size'       => 1024 * 500,
            'approval_status' => 'working',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('test_sample_audio.mp3');
        $response->assertSee($asset->humanSize());
    }

    /**
     * TEST 7: Teacher My Media approved badge is high contrast
     */
    public function test_07_teacher_my_media_approved_badge_is_high_contrast(): void
    {
        $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Approved Asset Item',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Institutional Asset');
        $response->assertSee('bg-emerald-100 text-emerald-800', false);
    }

    /**
     * TEST 8: Teacher My Media pending review badge is high contrast
     */
    public function test_08_teacher_my_media_pending_review_badge_is_high_contrast(): void
    {
        $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Pending Asset Item',
            'approval_status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Pending RM Review');
        $response->assertSee('bg-amber-100 text-amber-900', false);
    }

    /**
     * TEST 9: Teacher My Media revision requested badge is high contrast
     */
    public function test_09_teacher_my_media_revision_requested_badge_is_high_contrast(): void
    {
        $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Revision Asset Item',
            'approval_status' => 'revision_requested',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Revision Requested');
        $response->assertSee('bg-orange-100 text-orange-900', false);
    }

    /**
     * TEST 10: Teacher My Media rejected badge is high contrast
     */
    public function test_10_teacher_my_media_rejected_badge_is_high_contrast(): void
    {
        $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Rejected Asset Item',
            'approval_status' => 'rejected',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Rejected');
        $response->assertSee('bg-rose-100 text-rose-900', false);
    }

    /**
     * TEST 11: Teacher My Media working media badge is high contrast
     */
    public function test_11_teacher_my_media_working_media_badge_is_high_contrast(): void
    {
        $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Working Asset Item',
            'approval_status' => 'working',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Working Media');
        $response->assertSee('bg-slate-100 text-slate-800', false);
    }

    /**
     * TEST 12: Teacher My Media action buttons (Usage, Edit, Submit) render with SVG icons without emoji regression
     */
    public function test_12_teacher_my_media_action_buttons_render_with_svg_icons(): void
    {
        $this->createMediaAsset([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Interactive SVG Asset',
            'approval_status' => 'working',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Usage');
        $response->assertSee('Edit');
        $response->assertSee('Submit');
        $response->assertSee('<svg class="w-3.5 h-3.5', false);
    }

    /**
     * TEST 13: Institutional Media index hero renders institutional subtitle
     */
    public function test_13_institutional_media_index_hero_renders_institutional_subtitle(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Institutional single source of truth for Question Media Library');
    }

    /**
     * TEST 14: Institutional Media index cards render with strong contrast
     */
    public function test_14_institutional_media_index_cards_render_with_strong_contrast(): void
    {
        $this->createMediaAsset([
            'title'           => 'Institutional Listening Dialogue Track',
            'exam_type'       => 'toeic',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Institutional Listening Dialogue Track');
        $response->assertSee('imr-card__title', false);
        $response->assertSee('imr-card__sub', false);
    }

    /**
     * TEST 15: Institutional Media index exam filter options use institutional wording
     */
    public function test_15_institutional_media_index_exam_filter_uses_institutional_wording(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.media.index'));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Institutional');
        $response->assertDontSee('TOEIC Official');
    }

    /**
     * TEST 16: Institutional Media index action buttons render with SVG icons
     */
    public function test_16_institutional_media_index_action_buttons_render_with_svg_icons(): void
    {
        $this->createMediaAsset([
            'title' => 'Sample SVG Index Asset',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.index'));

        $response->assertStatus(200);
        $response->assertSee('Preview');
        $response->assertSee('Edit');
        $response->assertSee('Version History');
        $response->assertSee('Tracker');
        $response->assertSee('<svg class="w-3.5 h-3.5', false);
    }

    /**
     * TEST 17: Media Detail (show) overview default description uses institutional wording
     */
    public function test_17_media_detail_overview_uses_institutional_wording(): void
    {
        $asset = $this->createMediaAsset([
            'title'       => 'Generic Institutional Asset',
            'description' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.show', $asset->id));

        $response->assertStatus(200);
        $response->assertSee('Institutional asset configured for academic evaluation');
        $response->assertDontSee('Official institutional asset');
    }

    /**
     * TEST 18: Media Detail (show) metadata labels have strong contrast
     */
    public function test_18_media_detail_metadata_labels_have_strong_contrast(): void
    {
        $asset = $this->createMediaAsset([
            'title'     => 'Metadata Contrast Asset',
            'exam_type' => 'toefl',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.show', $asset->id));

        $response->assertStatus(200);
        $response->assertSee('Asset Metadata');
        $response->assertSee('Media Type');
        $response->assertSee('Exam Repository');
        $response->assertSee('Folder Category');
    }

    /**
     * TEST 19: Media Detail (show) action buttons render with SVG icons
     */
    public function test_19_media_detail_action_buttons_render_with_svg_icons(): void
    {
        $asset = $this->createMediaAsset([
            'title' => 'Show View SVG Asset',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.show', $asset->id));

        $response->assertStatus(200);
        $response->assertSee('Edit Asset');
        $response->assertSee('Copy URL');
        $response->assertSee('<svg class="w-3.5 h-3.5', false);
    }

    /**
     * TEST 20: Media Edit exam type select uses institutional wording
     */
    public function test_20_media_edit_exam_type_select_uses_institutional_wording(): void
    {
        $asset = $this->createMediaAsset([
            'title'     => 'Editable Institutional Asset',
            'exam_type' => 'toeic',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.edit', $asset->id));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Institutional');
        $response->assertDontSee('TOEIC Official');
    }

    /**
     * TEST 21: Media Edit form labels and headers have strong contrast
     */
    public function test_21_media_edit_form_labels_have_strong_contrast(): void
    {
        $asset = $this->createMediaAsset([
            'title' => 'Form Label Contrast Asset',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.edit', $asset->id));

        $response->assertStatus(200);
        $response->assertSee('General Information');
        $response->assertSee('Asset Title *');
        $response->assertSee('Description');
        $response->assertSee('Folder / Category');
        $response->assertSee('Version Control');
    }

    /**
     * TEST 22: Media Version History table headers and badges have strong contrast
     */
    public function test_22_media_version_history_table_headers_have_strong_contrast(): void
    {
        $asset = $this->createMediaAsset([
            'title' => 'Version History Asset',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.media.versions', $asset->id));

        $response->assertStatus(200);
        $response->assertSee('Version History Releases');
        $response->assertSee('Version Number');
        $response->assertSee('Title Snapshot');
        $response->assertSee('Created By');
        $response->assertSee('Change Reason');
        $response->assertSee('Append-Only Immutable Audit Trail');
        $response->assertSee('READ-ONLY &amp; UNALTERABLE', false);
    }

    /**
     * TEST 23: Seeded media assets and QuestionBanks contain zero misleading "Official" wording in descriptions or titles
     */
    public function test_23_seeded_media_assets_and_question_banks_contain_zero_misleading_official_wording(): void
    {
        $this->seed(MediaInstitutionalRepositorySeeder::class);
        $this->seed(AclStarterLibrarySeeder::class);

        $officialMedia = MediaAsset::query()
            ->where('title', 'like', '%Official%')
            ->orWhere('description', 'like', '%Official%')
            ->get();

        $this->assertCount(0, $officialMedia, 'Found MediaAssets with misleading Official wording in title/description');

        $officialQuestionBanks = QuestionBank::query()
            ->where('title', 'like', '%Official%')
            ->orWhere('description', 'like', '%Official%')
            ->get();

        $this->assertCount(0, $officialQuestionBanks, 'Found QuestionBanks with misleading Official wording in title/description');
    }
}
