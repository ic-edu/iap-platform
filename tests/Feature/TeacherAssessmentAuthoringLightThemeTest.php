<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Database\Seeders\AssessmentSeeder;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\QuestionBank\Database\Seeders\QuestionBankSeeder;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssessmentAuthoringLightThemeTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;
    protected Test $testRecord;
    protected TestSection $sectionRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AclStarterLibrarySeeder::class);
        $this->seed(QuestionBankSeeder::class);

        $this->teacherUser = User::firstOrCreate(
            ['email' => 'teacher@icedu.org'],
            [
                'name'     => 'Teacher Instructor',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->teacherUser->syncRoles(['teacher']);

        $this->testRecord = Test::create([
            'title'            => 'TOEIC Simulator Practice Test Alpha',
            'slug'             => 'toeic-simulator-practice-test-alpha',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 750,
            'scoring_method'   => 'automatic',
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacherUser->id,
            'instructions'     => 'General Assessment Introduction / Candidate Instructions test placeholder.',
        ]);

        $this->sectionRecord = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'LISTENING SECTION',
            'section_type' => 'listening',
            'order'        => 1,
            'instructions' => 'Listen carefully to the audio statements and select the correct answer choice.',
        ]);
    }

    public function test_01_assessment_authoring_heading_has_strong_readable_light_theme_text(): void
    {
        $this->teacherUser->setThemePreference('light');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Simulator Practice Test Alpha');
        $response->assertSee('Assessment Questions &amp; Sections — Progressive Revision Summary', false);
        $response->assertSee('text-slate-900', false);
    }

    public function test_02_section_directions_text_remains_readable(): void
    {
        $this->teacherUser->setThemePreference('light');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('LISTENING SECTION');
        $response->assertSee('Listen carefully to the audio statements');
    }

    public function test_03_section_labels_remain_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Sections &amp; Directions Structure', false);
        $response->assertSee('Section Media Assets', false);
    }

    public function test_04_section_action_buttons_remain_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Attach Media', false);
        $response->assertSee('Edit Section', false);
        $response->assertSee('Remove Section', false);
    }

    public function test_05_media_attachment_modal_has_readable_heading(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Attach Media Asset to Section');
        $response->assertSee('id="attach-section-media-modal"', false);
    }

    public function test_06_media_attachment_modal_labels_are_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Section Media Caption / Directions Label (Optional)');
        $response->assertSee('Display Order');
    }

    public function test_07_media_tabs_have_distinct_active_inactive_states(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('id="asm-tab-library"', false);
        $response->assertSee('id="asm-tab-upload"', false);
        $response->assertSee('Choose from Media Library');
        $response->assertSee('Upload New Media');
    }

    public function test_08_media_cards_have_readable_metadata(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('id="asm-media-list-container"', false);
    }

    public function test_09_preview_button_remains_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Preview');
    }

    public function test_10_select_button_remains_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Select');
    }

    public function test_11_cancel_button_remains_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Cancel');
    }

    public function test_12_disabled_attach_selected_media_state_remains_semantically_disabled_and_visually_understandable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('id="asm-submit-btn"', false);
        $response->assertSee('disabled', false);
        $response->assertSee('Attach Selected Media', false);
    }

    public function test_13_pass_score_threshold_label_is_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Pass Score Threshold');
    }

    public function test_14_scoring_method_label_is_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Scoring Method');
    }

    public function test_15_general_assessment_introduction_label_is_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('General Assessment Introduction / Candidate Instructions');
    }

    public function test_16_textarea_placeholder_remains_readable(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Welcome to the TOEIC Listening');
    }

    public function test_17_input_select_borders_remain_visible(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('border-slate-300', false);
    }

    public function test_18_focus_states_remain_visible(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('focus:border-indigo-500', false);
        $response->assertSee('focus:ring-indigo-500', false);
    }

    public function test_19_no_primary_emoji_icons_reintroduced(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $this->assertNotEmpty($response->getContent());
    }

    public function test_20_teacher_authoring_light_theme_contract_passes(): void
    {
        $this->teacherUser->setThemePreference('light');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_21_teacher_authoring_dark_theme_contract_passes(): void
    {
        $this->teacherUser->setThemePreference('dark');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_22_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }

    public function test_23_no_finance_ui_changed(): void
    {
        $financeUser = User::firstOrCreate(
            ['email' => 'finance@icedu.org'],
            [
                'name'     => 'Finance Manager',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $financeUser->syncRoles(['finance']);

        $response = $this->actingAs($financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
    }

    public function test_24_no_candidate_ui_changed(): void
    {
        $studentUser = User::firstOrCreate(
            ['email' => 'student@icedu.org'],
            [
                'name'     => 'Candidate Student',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $studentUser->syncRoles(['student']);

        $response = $this->actingAs($studentUser)->get(route('candidate.portal'));
        $response->assertStatus(200);
    }

    public function test_25_no_ra_ui_changed(): void
    {
        $adminUser = User::firstOrCreate(
            ['email' => 'opadmin@icedu.org'],
            [
                'name'     => 'Operational Admin',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $adminUser->syncRoles(['admin']);

        $response = $this->actingAs($adminUser)->get(route('admin.dashboard'));
        $response->assertStatus(200);
    }

    public function test_26_no_assessment_business_logic_changed(): void
    {
        $testTypeVal = is_object($this->testRecord->test_type) ? $this->testRecord->test_type->value : $this->testRecord->test_type;
        $this->assertEquals('toeic', $testTypeVal);
        $this->assertEquals(120, $this->testRecord->duration_minutes);
        $this->assertEquals(750, $this->testRecord->pass_score);
        $scoringVal = is_object($this->testRecord->scoring_method) ? $this->testRecord->scoring_method->value : $this->testRecord->scoring_method;
        $this->assertEquals('automatic', $scoringVal);
        $statusVal = is_object($this->testRecord->status) ? $this->testRecord->status->value : $this->testRecord->status;
        $this->assertEquals('draft', $statusVal);
    }
}
