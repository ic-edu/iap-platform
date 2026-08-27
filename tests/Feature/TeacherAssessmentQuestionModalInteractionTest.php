<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssessmentQuestionModalInteractionTest extends TestCase
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
            'instructions'     => 'General Assessment Introduction / Candidate Instructions.',
        ]);

        $this->sectionRecord = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'LISTENING SECTION',
            'section_type' => 'listening',
            'order'        => 1,
            'instructions' => 'Listen carefully to the audio statements.',
        ]);
    }

    public function test_01_create_assessment_authored_question_modal_exists(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('id="create-authored-question-modal"', false);
        $response->assertSee('Create Assessment-Authored Question', false);
    }

    public function test_02_radio_correct_choice_controls_exist(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('name="correct_choice"', false);
        $response->assertSee('id="create-correct-0"', false);
        $response->assertSee('id="create-correct-1"', false);
        $response->assertSee('id="create-correct-2"', false);
        $response->assertSee('id="create-correct-3"', false);
    }

    public function test_03_radio_selection_handler_exists(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('onchange="updateCreateModalCorrectChoice()"', false);
        $response->assertSee('function updateCreateModalCorrectChoice()', false);
    }

    public function test_04_close_create_authored_question_modal_supports_direct_invocation(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('function closeCreateAuthoredQuestionModal(e)', $content);
        $this->assertStringContainsString('if (!e || e.target === modal)', $content);
    }

    public function test_05_x_button_calls_close_create_authored_question_modal(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('onclick="closeCreateAuthoredQuestionModal()"', false);
    }

    public function test_06_cancel_button_calls_close_create_authored_question_modal(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<button\s+type="button"\s+onclick="closeCreateAuthoredQuestionModal\(\)"[^>]*>\s*Cancel\s*<\/button>/i',
            $content
        );
    }

    public function test_07_backdrop_uses_explicit_event_handling(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('id="create-authored-question-modal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeCreateAuthoredQuestionModal(event)"', false);
    }

    public function test_08_inner_card_stops_backdrop_propagation(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('onclick="event.stopPropagation()"', false);
    }

    public function test_09_modal_close_handler_does_not_depend_on_window_event(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('function closeCreateAuthoredQuestionModal(e)', $content);
        $this->assertStringNotContainsString('window.event', $content);
    }

    public function test_10_media_filter_function_receives_explicit_event_parameter_if_event_is_needed(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee("filterQuestionMediaModal('all', event)", false);
        $response->assertSee("filterQuestionMediaModal('image', event)", false);
        $response->assertSee('function filterQuestionMediaModal(type, e = null)', false);
    }

    public function test_11_selecting_correct_answer_does_not_submit_the_form(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Ensure radio buttons only call updateCreateModalCorrectChoice and do not submit form
        $this->assertStringContainsString('name="correct_choice" value="0" id="create-correct-0" onchange="updateCreateModalCorrectChoice()"', $content);
        $this->assertStringNotContainsString('onchange="this.form.submit()"', $content);
    }

    public function test_12_selecting_correct_answer_does_not_create_network_request(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        // updateCreateModalCorrectChoice has no fetch or ajax
        preg_match('/function updateCreateModalCorrectChoice\(\)\s*\{(.*?)\}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $functionBody = $matches[1];

        $this->assertStringNotContainsString('fetch(', $functionBody);
        $this->assertStringNotContainsString('XMLHttpRequest', $functionBody);
        $this->assertStringNotContainsString('$.ajax', $functionBody);
    }

    public function test_13_selecting_correct_answer_does_not_alter_modal_visibility(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        preg_match('/function updateCreateModalCorrectChoice\(\)\s*\{(.*?)\}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $functionBody = $matches[1];

        $this->assertStringNotContainsString('create-authored-question-modal', $functionBody);
        $this->assertStringNotContainsString('modal.classList.add(\'hidden\')', $functionBody);
    }

    public function test_14_selecting_correct_answer_does_not_disable_controls(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        preg_match('/function updateCreateModalCorrectChoice\(\)\s*\{(.*?)\}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $functionBody = $matches[1];

        $this->assertStringNotContainsString('disabled = true', $functionBody);
        $this->assertStringNotContainsString('disabled', $functionBody);
    }

    public function test_15_no_pointer_events_none_introduced(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check that pointer-events-none is not added to modal or choice rows
        preg_match('/function updateCreateModalCorrectChoice\(\)\s*\{(.*?)\}/s', $content, $matches);
        $this->assertNotEmpty($matches);
        $functionBody = $matches[1];

        $this->assertStringNotContainsString('pointer-events-none', $functionBody);
        $this->assertStringNotContainsString('pointerEvents = \'none\'', $functionBody);
    }

    public function test_16_no_overlay_markup_introduced(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Modal should only have the standard backdrop container
        $this->assertEquals(1, substr_count($content, 'id="create-authored-question-modal"'));
    }

    public function test_17_no_global_modal_changes(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('id="iap-global-dialog"', false);
    }

    public function test_18_current_teacher_assessment_authoring_route_remains_accessible(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Simulator Practice Test Alpha');
    }

    public function test_19_light_theme_contract_remains_intact(): void
    {
        $this->teacherUser->setThemePreference('light');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_20_dark_theme_contract_remains_intact(): void
    {
        $this->teacherUser->setThemePreference('dark');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_21_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
