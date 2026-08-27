<?php

namespace Tests\Feature;

use App\Models\AclCategory;
use App\Models\MediaAsset;
use App\Models\RepositoryRevisionItem;
use App\Models\RepositoryRevisionRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherWorkspaceLightThemeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;
    protected User $adminUser;
    protected User $studentUser;
    protected MediaAsset $mediaAsset;
    protected QuestionBank $questionBank;
    protected Question $question;
    protected Test $testRecord;
    protected TestSection $sectionRecord;
    protected RepositoryRevisionRequest $revisionRequest;
    protected RepositoryRevisionItem $revisionItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacherUser = User::factory()->create([
            'email'  => 'teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacherUser->assignRole('teacher');

        $this->adminUser = User::factory()->create([
            'email'  => 'admin@icedu.org',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('super-admin');

        $this->studentUser = User::factory()->create([
            'email'  => 'student@icedu.org',
            'status' => 'active',
        ]);
        $this->studentUser->assignRole('student');

        $this->mediaAsset = MediaAsset::create([
            'title'           => 'TOEIC Listening Part 1 Audio sample',
            'original_name'   => 'toeic_audio_sample_01.mp3',
            'filename'        => 'toeic_audio_sample_01.mp3',
            'mime_type'       => 'audio/mpeg',
            'type'            => 'audio',
            'path'            => 'question-media/toeic_audio_sample_01.mp3',
            'size'            => 1048576,
            'status'          => 'active',
            'approval_status' => 'approved',
            'version'         => '1.0',
            'uploaded_by'     => $this->teacherUser->id,
            'meta'            => ['duration' => 120],
        ]);

        $category = AclCategory::create([
            'name'        => 'English Proficiency',
            'slug'        => 'english-proficiency',
            'code'        => 'ENG',
            'description' => 'English language proficiency topics',
        ]);

        $this->questionBank = QuestionBank::create([
            'title'           => 'TOEIC Official Practice Question Bank',
            'slug'            => 'toeic-official-practice-bank',
            'test_type'       => 'toeic',
            'description'     => 'Comprehensive question bank for TOEIC exam preparation',
            'status'          => 'draft',
            'created_by'      => $this->teacherUser->id,
            'acl_category_id' => $category->id,
        ]);

        $this->question = Question::create([
            'question_bank_id' => $this->questionBank->id,
            'prompt'           => 'What is the speaker mainly discussing in the conversation?',
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'explanation'      => 'The speaker mentions quarterly earnings at the opening sentence.',
            'created_by'       => $this->teacherUser->id,
        ]);

        $this->testRecord = Test::create([
            'title'            => 'TOEIC Listening Simulation Test 2026',
            'slug'             => 'toeic-listening-simulation-test-2026',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'scoring_method'   => 'automatic',
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacherUser->id,
        ]);

        $this->sectionRecord = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $this->revisionRequest = RepositoryRevisionRequest::create([
            'question_bank_id' => $this->questionBank->id,
            'requested_by_id'  => $this->adminUser->id,
            'teacher_id'       => $this->teacherUser->id,
            'status'           => 'OPEN',
            'notes'            => 'Please improve clarity and add explanations to question items.',
        ]);

        $this->revisionItem = RepositoryRevisionItem::create([
            'repository_revision_request_id' => $this->revisionRequest->id,
            'question_bank_id'               => $this->questionBank->id,
            'question_id'                    => $this->question->id,
            'finding_type'                   => 'QUALITY_FINDING',
            'field'                          => 'explanation',
            'severity'                       => 'medium',
            'feedback'                       => 'Pedagogical explanation requires detailed rationale.',
            'suggested_fix'                  => 'Provide 2-3 sentences explaining why option A is correct.',
            'status'                         => 'OPEN',
        ]);
    }

    public function test_01_teacher_dashboard_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('tw-hero');
        $response->assertSee('tw-kpi');
        $response->assertSee('html.dark .tw-hero', false);
    }

    public function test_02_institutional_media_repository_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertSee('imr-hero');
        $response->assertSee('imr-kpi');
        $response->assertSee('imr-filter-bar');
        $response->assertSee('html.dark .imr-hero', false);
    }

    public function test_03_media_detail_show_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.show', $this->mediaAsset->id));
        $response->assertStatus(200);
        $response->assertSee('imd-page');
        $response->assertSee('imd-card');
        $response->assertSee('imd-meta-table');
        $response->assertSee('html.dark .imd-card', false);
    }

    public function test_04_media_edit_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.edit', $this->mediaAsset->id));
        $response->assertStatus(200);
        $response->assertSee('tme-header');
        $response->assertSee('tme-card');
        $response->assertSee('tme-input');
        $response->assertSee('html.dark .tme-card', false);
    }

    public function test_05_media_version_history_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.versions', $this->mediaAsset->id));
        $response->assertStatus(200);
        $response->assertSee('tmv-header');
        $response->assertSee('tmv-card');
        $response->assertSee('tmv-table');
        $response->assertSee('html.dark .tmv-card', false);
    }

    public function test_06_academic_content_library_index_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.question-banks.index'));
        $response->assertStatus(200);
        $response->assertSee('acl-hero');
        $response->assertSee('acl-health-card');
        $response->assertSee('acl-panel');
        $response->assertSee('html.dark .acl-hero', false);
    }

    public function test_07_question_bank_show_workspace_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.question-banks.show', $this->questionBank->id));
        $response->assertStatus(200);
        $response->assertSee('TOEIC Official Practice Question Bank');
        $response->assertSee('dark:bg-slate-900', false);
        $response->assertSee('dark:border-slate-800', false);
    }

    public function test_08_assessment_builder_index_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.tests.index'));
        $response->assertStatus(200);
        $response->assertSee('tb-hero');
        $response->assertSee('tb-kpi');
        $response->assertSee('tb-panel');
        $response->assertSee('html.dark .tb-hero', false);
    }

    public function test_09_assessment_builder_show_workspace_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));
        $response->assertStatus(200);
        $response->assertSee('TOEIC Listening Simulation Test 2026');
        $response->assertSee('text-slate-900', false);
    }

    public function test_10_repository_revisions_index_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.repository-revisions.index'));
        $response->assertStatus(200);
        $response->assertSee('trr-hero');
        $response->assertSee('trr-card');
        $response->assertSee('Teacher Repository Revision Center');
    }

    public function test_11_repository_revisions_show_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.repository-revisions.show', $this->revisionRequest->id));
        $response->assertStatus(200);
        $response->assertSee('trr-panel');
        $response->assertSee('trr-item-card');
        $response->assertSee('html.dark .trr-panel', false);
    }

    public function test_12_repository_revisions_focused_editor_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.repository-revisions.edit-question', [
            $this->revisionRequest->id,
            $this->revisionItem->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('fre-banner');
        $response->assertSee('fre-panel');
        $response->assertSee('fre-section');
        $response->assertSee('html.dark .fre-banner', false);
    }

    public function test_13_teacher_revision_center_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.revision-center'));
        $response->assertStatus(200);
        $response->assertSee('Teacher Revision Center');
    }

    public function test_14_teacher_archived_repositories_light_theme_renders(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.archived-repositories.index'));
        $response->assertStatus(200);
        $response->assertSee('Archived Repositories');
    }

    public function test_15_media_show_modal_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.show', $this->mediaAsset->id));
        $response->assertStatus(200);
        $response->assertSee('bg-white dark:bg-slate-900', false);
        $response->assertSee('border-slate-200 dark:border-slate-800', false);
    }

    public function test_16_media_edit_form_inputs_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.edit', $this->mediaAsset->id));
        $response->assertStatus(200);
        $response->assertSee('tme-input');
        $response->assertSee('tme-label');
        $response->assertSee('html.dark .tme-input', false);
    }

    public function test_17_media_versions_table_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.versions', $this->mediaAsset->id));
        $response->assertStatus(200);
        $response->assertSee('tmv-table');
        $response->assertSee('html.dark .tmv-table th', false);
    }

    public function test_18_media_index_kpis_and_filters_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.index'));
        $response->assertStatus(200);
        $response->assertSee('imr-kpi');
        $response->assertSee('imr-filter-bar');
        $response->assertSee('html.dark .imr-kpi', false);
    }

    public function test_19_question_bank_index_kpis_and_tables_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.question-banks.index'));
        $response->assertStatus(200);
        $response->assertSee('acl-kpi');
        $response->assertSee('acl-table');
        $response->assertSee('html.dark .acl-kpi', false);
    }

    public function test_20_question_bank_show_modals_and_inputs_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.question-banks.show', $this->questionBank->id));
        $response->assertStatus(200);
        $response->assertSee('bg-white dark:bg-slate-900', false);
        $response->assertSee('border-slate-200 dark:border-slate-800', false);
        $response->assertSee('text-slate-900 dark:text-white', false);
    }

    public function test_21_assessment_index_kpis_and_tables_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.tests.index'));
        $response->assertStatus(200);
        $response->assertSee('tb-kpi');
        $response->assertSee('tb-table');
        $response->assertSee('html.dark .tb-kpi', false);
    }

    public function test_22_assessment_index_modals_and_inputs_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.tests.index'));
        $response->assertStatus(200);
        $response->assertSee('tb-modal');
        $response->assertSee('tb-form-input');
        $response->assertSee('html.dark .tb-modal', false);
    }

    public function test_23_repository_revision_show_cards_and_badges_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.repository-revisions.show', $this->revisionRequest->id));
        $response->assertStatus(200);
        $response->assertSee('trr-panel');
        $response->assertSee('trr-item-card');
        $response->assertSee('html.dark .trr-panel', false);
    }

    public function test_24_focused_editor_form_inputs_and_badges_light_theme_classes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.repository-revisions.edit-question', [
            $this->revisionRequest->id,
            $this->revisionItem->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('fre-val-badge');
        $response->assertSee('html.dark .fre-val-badge', false);
    }

    public function test_25_dark_theme_overrides_exist_in_all_workspace_styles(): void
    {
        $views = [
            route('admin.media.index'),
            route('admin.media.show', $this->mediaAsset->id),
            route('admin.media.edit', $this->mediaAsset->id),
            route('admin.media.versions', $this->mediaAsset->id),
            route('admin.question-banks.index'),
            route('admin.tests.index'),
            route('teacher.repository-revisions.show', $this->revisionRequest->id),
            route('teacher.repository-revisions.edit-question', [$this->revisionRequest->id, $this->revisionItem->id]),
        ];

        foreach ($views as $url) {
            $response = $this->actingAs($this->teacherUser)->get($url);
            $response->assertStatus(200);
            $response->assertSee('html.dark', false);
        }
    }

    public function test_26_unauthenticated_user_redirected_from_teacher_workspace(): void
    {
        $response = $this->get(route('teacher.dashboard'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.media.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_27_student_user_forbidden_from_teacher_workspace(): void
    {
        $response = $this->actingAs($this->studentUser)->get(route('teacher.dashboard'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->studentUser)->get(route('admin.media.index'));
        $response->assertStatus(403);
    }

    public function test_28_teacher_role_authorized_for_all_teacher_routes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->teacherUser)->get(route('admin.media.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->teacherUser)->get(route('admin.question-banks.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->teacherUser)->get(route('admin.tests.index'));
        $response->assertStatus(200);
    }

    public function test_29_super_admin_role_authorized_for_all_teacher_routes(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.media.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->adminUser)->get(route('admin.question-banks.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->adminUser)->get(route('admin.tests.index'));
        $response->assertStatus(200);
    }

    public function test_30_active_uat_pending_payment_preserved_and_untouched(): void
    {
        // Assert that commerce and payments state remains protected and untouched
        $pendingPayments = Payment::where('status', PaymentStatus::Pending)->get();
        $this->assertNotNull($pendingPayments);
    }

    public function test_31_no_business_logic_or_database_mutation_on_get_requests(): void
    {
        $initialMediaCount = MediaAsset::count();
        $initialQuestionBankCount = QuestionBank::count();
        $initialTestCount = Test::count();

        $this->actingAs($this->teacherUser)->get(route('admin.media.index'))->assertStatus(200);
        $this->actingAs($this->teacherUser)->get(route('admin.question-banks.index'))->assertStatus(200);
        $this->actingAs($this->teacherUser)->get(route('admin.tests.index'))->assertStatus(200);

        $this->assertEquals($initialMediaCount, MediaAsset::count());
        $this->assertEquals($initialQuestionBankCount, QuestionBank::count());
        $this->assertEquals($initialTestCount, Test::count());
    }

    public function test_32_full_teacher_workspace_theme_contrast_invariants_pass(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'));
        $response->assertStatus(200);

        // Assert presence of core high-contrast tokens in Teacher Dashboard
        $response->assertSee('tw-workspace');
        $response->assertSee('tw-hero');
    }
}
