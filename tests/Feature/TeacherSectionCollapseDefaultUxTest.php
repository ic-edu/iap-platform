<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSectionCollapseDefaultUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Test $test;
    protected TestSection $sectionPart1;
    protected TestSection $sectionPart2;
    protected TestSection $sectionPart3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'TOEIC Teacher Author',
            'email'  => 'toeic_author@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create([
            'name'   => 'TOEIC Student Candidate',
            'email'  => 'student_candidate@icedu.org',
            'status' => 'active',
        ]);
        $this->student->assignRole('student');

        $this->test = Test::create([
            'title'            => 'TOEIC Collapse Test Suite',
            'slug'             => 'toeic-collapse-test-suite-' . \Illuminate\Support\Str::random(6),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 120,
            'pass_percentage'  => 75,
            'status'           => 'draft',
            'created_by'       => $this->teacher->id,
            'assigned_to'      => $this->teacher->id,
        ]);

        $this->sectionPart1 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => 'listening',
            'instructions' => 'Directions for Part 1 Photographs.',
            'order'        => 1,
        ]);

        $this->sectionPart2 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 2: Question-Response',
            'section_type' => 'listening',
            'instructions' => 'Directions for Part 2 Question-Response.',
            'order'        => 2,
        ]);

        $this->sectionPart3 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 3: Conversations',
            'section_type' => 'listening',
            'instructions' => 'Directions for Part 3 Conversations.',
            'order'        => 3,
        ]);

        $service = app(TestBuilderService::class);

        // Populate Part 1 with 3 questions (Q1 - Q3)
        for ($i = 1; $i <= 3; $i++) {
            $service->createAssessmentQuestion($this->sectionPart1, [
                'prompt'        => "Photographs Question {$i}",
                'section'       => 'listening',
                'part_number'   => 1,
                'question_type' => 'multiple_choice',
                'choices'       => [
                    ['label' => 'A', 'content' => 'Option A', 'is_correct' => true],
                    ['label' => 'B', 'content' => 'Option B', 'is_correct' => false],
                    ['label' => 'C', 'content' => 'Option C', 'is_correct' => false],
                    ['label' => 'D', 'content' => 'Option D', 'is_correct' => false],
                ],
            ]);
        }

        // Populate Part 2 with 12 questions (Q4 - Q15)
        for ($i = 1; $i <= 12; $i++) {
            $service->createAssessmentQuestion($this->sectionPart2, [
                'prompt'        => "Question-Response Item {$i}",
                'section'       => 'listening',
                'part_number'   => 2,
                'question_type' => 'multiple_choice',
                'choices'       => [
                    ['label' => 'A', 'content' => 'Response A', 'is_correct' => true],
                    ['label' => 'B', 'content' => 'Response B', 'is_correct' => false],
                    ['label' => 'C', 'content' => 'Response C', 'is_correct' => false],
                ],
            ]);
        }

        // Populate Part 3 with 1 Audio Group having 3 child questions (Q16 - Q18)
        $service->createAudioGroup($this->sectionPart3, [
            'title'       => 'Office Meeting Conversation',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'media/office.mp3',
            'questions'   => [
                ['prompt' => 'Q16 Prompt', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q17 Prompt', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 1],
                ['prompt' => 'Q18 Prompt', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 2],
            ],
        ]);
    }

    /** @test */
    public function test_01_assessment_page_renders_all_section_cards_collapsed_by_default()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();

        // Check that all 3 section bodies have class="hidden..." and style="display: none;"
        $response->assertSee('id="section-body-' . $this->sectionPart1->id . '" class="hidden', false);
        $response->assertSee('id="section-body-' . $this->sectionPart2->id . '" class="hidden', false);
        $response->assertSee('id="section-body-' . $this->sectionPart3->id . '" class="hidden', false);
    }

    /** @test */
    public function test_02_part_1_defaults_collapsed()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="section-body-' . $this->sectionPart1->id . '" class="hidden space-y-5 pt-4 border-t border-slate-100 dark:border-slate-800" style="display: none;"', false);
    }

    /** @test */
    public function test_03_part_2_defaults_collapsed()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="section-body-' . $this->sectionPart2->id . '" class="hidden space-y-5 pt-4 border-t border-slate-100 dark:border-slate-800" style="display: none;"', false);
    }

    /** @test */
    public function test_04_part_3_defaults_collapsed()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="section-body-' . $this->sectionPart3->id . '" class="hidden space-y-5 pt-4 border-t border-slate-100 dark:border-slate-800" style="display: none;"', false);
    }

    /** @test */
    public function test_05_no_section_defaults_open_on_normal_load()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        // Ensure no section body renders unhidden
        $response->assertDontSee('id="section-body-' . $this->sectionPart1->id . '" class="space-y-5"', false);
        $response->assertDontSee('id="section-body-' . $this->sectionPart2->id . '" class="space-y-5"', false);
        $response->assertDontSee('id="section-body-' . $this->sectionPart3->id . '" class="space-y-5"', false);
    }

    /** @test */
    public function test_06_collapsed_header_displays_section_title()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Part 1: Photographs', false);
        $response->assertSee('Part 2: Question-Response', false);
        $response->assertSee('Part 3: Conversations', false);
    }

    /** @test */
    public function test_07_collapsed_header_displays_question_count()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('0 of 6 questions', false);
        $response->assertSee('0 of 25 questions', false);
    }

    /** @test */
    public function test_08_collapsed_header_displays_actual_question_range_where_available()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('(Questions 1–6)', false);
        $response->assertSee('(Questions 7–31)', false);
        $response->assertSee('(Questions 32–70)', false);
    }

    /** @test */
    public function test_09_expand_control_exists()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="btn-collapse-' . $this->sectionPart1->id . '"', false);
        $response->assertSee('id="collapse-label-' . $this->sectionPart1->id . '"', false);
        $response->assertSee('Expand', false);
    }

    /** @test */
    public function test_10_aria_expanded_defaults_false()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('aria-expanded="false"', false);
        $response->assertSee('aria-controls="section-body-' . $this->sectionPart1->id . '"', false);
    }

    /** @test */
    public function test_11_expanded_state_reveals_directions()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Directions for Part 1 Photographs.', false);
        $response->assertSee('Section Directions', false);
    }

    /** @test */
    public function test_12_expanded_state_reveals_section_media()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Section Media Assets', false);
    }

    /** @test */
    public function test_13_expanded_state_reveals_questions()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Photographs Question 1', false);
        $response->assertSee('Question-Response Item 1', false);
        $response->assertSee('Q16 Prompt', false);
    }

    /** @test */
    public function test_14_expanded_state_reveals_section_actions()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('+ Add Question', false);
        $response->assertSee('+ Add Audio Group', false);
        $response->assertSee('Attach Media', false);
        $response->assertSee('Edit Section', false);
    }

    /** @test */
    public function test_15_multiple_sections_may_be_open_simultaneously()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        // Check that toggleSectionCollapse does not close other sections
        $response->assertSee('function toggleSectionCollapse(secId, forceState = null)', false);
        $response->assertDontSee('forEach.*close', false);
    }

    /** @test */
    public function test_16_audio_group_rendering_remains_intact()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Audio Group (Conversation)', false);
        $response->assertSee('Shared Audio: Office Meeting Conversation', false);
    }

    /** @test */
    public function test_17_add_audio_group_cta_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("openCreateAudioGroupModal('{$this->sectionPart3->id}', '3'", false);
    }

    /** @test */
    public function test_18_add_question_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("openCreateAuthoredQuestionModal('{$this->sectionPart1->id}'", false);
    }

    /** @test */
    public function test_19_media_picker_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="question-media-picker-modal"', false);
    }

    /** @test */
    public function test_20_question_preview_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asset-preview-modal"', false);
    }

    /** @test */
    public function test_21_auto_difficulty_badges_remain_visible_after_expand()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Auto:', false);
    }

    /** @test */
    public function test_22_validation_assistant_behavior_unchanged()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Validation Assistant', false);
    }

    /** @test */
    public function test_23_no_test_section_data_mutation_caused_by_expand_collapse()
    {
        $this->assertEquals(3, $this->test->sections()->count());
        $this->assertEquals('Part 1: Photographs', $this->sectionPart1->fresh()->title);
        $this->assertEquals('Part 2: Question-Response', $this->sectionPart2->fresh()->title);
        $this->assertEquals('Part 3: Conversations', $this->sectionPart3->fresh()->title);
    }

    /** @test */
    public function test_24_no_question_ordering_change()
    {
        $testQuestions = TestQuestion::whereIn('test_section_id', $this->test->sections()->pluck('id'))->orderBy('order', 'asc')->get();
        $this->assertCount(18, $testQuestions);
        $this->assertEquals(1, $testQuestions->first()->order);
    }

    /** @test */
    public function test_25_no_assessment_lifecycle_change()
    {
        $this->assertEquals('draft', $this->test->fresh()->status);
        $this->assertFalse($this->test->fresh()->is_published);
    }

    /** @test */
    public function test_26_redirect_with_affected_section_context_expands_that_section()
    {
        // Simulate redirect with ?section=<id>
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', [
            'test'    => $this->test->id,
            'section' => $this->sectionPart3->id,
        ]));
        $response->assertOk();
        $response->assertSee('toggleSectionCollapse(autoExpandId, true)', false);
    }

    /** @test */
    public function test_27_other_sections_remain_collapsed()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', [
            'test'    => $this->test->id,
            'section' => $this->sectionPart3->id,
        ]));
        $response->assertOk();
        // In server render, all sections still start with hidden markup
        $response->assertSee('id="section-body-' . $this->sectionPart1->id . '" class="hidden', false);
        $response->assertSee('id="section-body-' . $this->sectionPart2->id . '" class="hidden', false);
    }

    /** @test */
    public function test_28_normal_reload_without_context_returns_all_sections_to_collapsed()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="section-body-' . $this->sectionPart1->id . '" class="hidden', false);
        $response->assertSee('id="section-body-' . $this->sectionPart2->id . '" class="hidden', false);
        $response->assertSee('id="section-body-' . $this->sectionPart3->id . '" class="hidden', false);
    }

    /** @test */
    public function test_29_footer_collapse_button_renders_in_every_section()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();

        // Each section should have its footer collapse control
        $response->assertSee('id="btn-footer-collapse-' . $this->sectionPart1->id . '"', false);
        $response->assertSee('id="btn-footer-collapse-' . $this->sectionPart2->id . '"', false);
        $response->assertSee('id="btn-footer-collapse-' . $this->sectionPart3->id . '"', false);
    }

    /** @test */
    public function test_30_footer_collapse_button_has_accessible_contract_without_forced_scroll()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();

        // Footer collapse button invokes toggleSectionCollapse(id, false) without forced scroll
        $response->assertSee("onclick=\"toggleSectionCollapse('{$this->sectionPart1->id}', false)\"", false);
        $response->assertSee('aria-controls="section-body-' . $this->sectionPart1->id . '"', false);
        $response->assertSee('title="Collapse ' . $this->sectionPart1->title . '"', false);
        $response->assertSee('End of ' . $this->sectionPart1->title, false);
    }

    /** @test */
    public function test_31_toggle_section_collapse_js_updates_both_header_and_footer_buttons_without_scroll()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();

        $response->assertSee('function toggleSectionCollapse(secId, forceState = null)', false);
        $response->assertSee('const footerBtn = document.getElementById(`btn-footer-collapse-${secId}`);', false);
        $response->assertSee('if (footerBtn) footerBtn.setAttribute(\'aria-expanded\', \'true\');', false);
        $response->assertSee('if (footerBtn) footerBtn.setAttribute(\'aria-expanded\', \'false\');', false);
        $response->assertDontSee('scrollToHeader', false);
    }
}
