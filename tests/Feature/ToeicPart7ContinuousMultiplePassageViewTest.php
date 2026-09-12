<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToeicPart7ContinuousMultiplePassageViewTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $candidate;
    protected Test $test;
    protected TestSection $p7Section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AclStarterLibrarySeeder::class);

        $this->teacher = User::firstOrCreate(
            ['email' => 'teacher@icedu.org'],
            [
                'name'     => 'Teacher Instructor',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->teacher->syncRoles(['teacher']);

        $this->candidate = User::firstOrCreate(
            ['email' => 'candidate@icedu.org'],
            [
                'name'     => 'Candidate Student',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->candidate->syncRoles(['student']);

        $this->test = Test::create([
            'title'            => 'TOEIC Mock Test - Continuous Stimulus UAT',
            'slug'             => 'toeic-mock-test-continuous-' . uniqid(),
            'test_type'        => 'toeic',
            'duration_minutes' => 75,
            'pass_score'       => 700,
            'scoring_method'   => 'automatic',
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $this->p7Section = TestSection::create([
            'test_id'          => $this->test->id,
            'title'            => 'Part 7: Reading Comprehension',
            'section_type'     => 'reading',
            'order'            => 1,
            'duration_minutes' => 75,
        ]);
    }

    private function createActiveAttempt(string $mode = 'simulator'): Attempt
    {
        CandidateTestAssignment::firstOrCreate(
            [
                'user_id' => $this->candidate->id,
                'test_id' => $this->test->id,
            ],
            [
                'assigned_by' => $this->teacher->id,
                'assigned_at' => now(),
                'status'      => 'assigned',
            ]
        );

        return Attempt::create([
            'test_id'          => $this->test->id,
            'user_id'          => $this->candidate->id,
            'attempt_token'    => 'attempt-' . uniqid(),
            'attempt_number'   => 1,
            'status'           => AttemptStatus::InProgress,
            'mode'             => $mode,
            'started_at'       => now(),
            'section_timer_sec'=> 4500,
        ]);
    }

    /**
     * P7-MULTI-01: Double Passage contains Document A and Document B.
     * Expected Candidate Preview HTML contains BOTH documents simultaneously.
     */
    public function test_p7_multi_01_double_passage_renders_both_documents_simultaneously(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Advertisement and Follow-up Email',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'advertisement',
            'title'            => 'Grand Hotel Job Opening',
            'content'          => 'We are hiring front desk managers with hospitality experience.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'email',
            'title'            => 'Application Inquiry',
            'content'          => 'Dear HR Manager, I would like to submit my resume for the position.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'What position is advertised?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Front desk manager', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('Grand Hotel Job Opening', false);
        $response->assertSee('We are hiring front desk managers with hospitality experience.', false);
        $response->assertSee('Application Inquiry', false);
        $response->assertSee('Dear HR Manager, I would like to submit my resume for the position.', false);
    }

    /**
     * P7-MULTI-02: Double Passage initial render — both documents are visible without clicking tabs.
     * Neither document content container has the "hidden" class.
     */
    public function test_p7_multi_02_double_passage_no_tab_hiding(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Double Set',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'notice',
            'title'            => 'Notice 1',
            'content'          => 'First notice text.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'memo',
            'title'            => 'Memo 2',
            'content'          => 'Second memo text.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'Question 1',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $content = $response->getContent();

        // Check document 0 container exists and does not contain hidden
        $this->assertStringContainsString('id="passage-doc-unit-0-0"', $content);
        // Check document 1 container exists and does not contain hidden
        $this->assertStringContainsString('id="passage-doc-unit-0-1"', $content);
        $this->assertStringNotContainsString('passage-doc-content-unit-0 hidden', $content);
        $this->assertStringNotContainsString('passage-doc-content-unit-0  hidden', $content);
    }

    /**
     * P7-MULTI-03: Quick-jump Document 2 targets Document 2 anchor; Document 1 remains rendered.
     */
    public function test_p7_multi_03_quick_jump_anchor_navigation(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Double Set',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'advertisement',
            'title'            => 'Advertisement',
            'content'          => 'Product specs.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'email',
            'title'            => 'Email',
            'content'          => 'Customer question.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'Question 1',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('switchPassageDocUnit(0, 0)', false);
        $response->assertSee('switchPassageDocUnit(0, 1)', false);
        $response->assertSee('1 — Advertisement', false);
        $response->assertSee('2 — Email', false);
        // Ensure no redundant duplication
        $response->assertDontSee('Advertisement (Advertisement)');
        $response->assertDontSee('Email (Email)');
    }

    /**
     * P7-MULTI-04: Triple Passage: Document 1, Document 2, Document 3 all render simultaneously.
     */
    public function test_p7_multi_04_triple_passage_all_three_render_simultaneously(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Conference Triad',
            'part_number'  => 7,
            'passage_type' => 'triple',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'schedule',
            'title'            => 'Workshop Timetable',
            'content'          => '10:00 AM - Registration and Coffee.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'email',
            'title'            => 'Speaker Confirmation',
            'content'          => 'Dear Dr. Chen, your session is confirmed.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 3,
            'document_type'    => 'feedback_form',
            'title'            => 'Participant Evaluation',
            'content'          => 'Please rate the clarity of the presentation.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'When does registration start?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => '10:00 AM', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('Document 1 of 3', false);
        $response->assertSee('Workshop Timetable', false);
        $response->assertSee('10:00 AM - Registration and Coffee.', false);

        $response->assertSee('Document 2 of 3', false);
        $response->assertSee('Speaker Confirmation', false);
        $response->assertSee('Dear Dr. Chen, your session is confirmed.', false);

        $response->assertSee('Document 3 of 3', false);
        $response->assertSee('Participant Evaluation', false);
        $response->assertSee('Please rate the clarity of the presentation.', false);

        $response->assertSee('1 — Workshop Timetable', false);
        $response->assertSee('2 — Speaker Confirmation', false);
        $response->assertSee('3 — Participant Evaluation', false);
    }

    /**
     * P7-MULTI-05: Mixed Content: Visual document + text document both render correctly.
     */
    public function test_p7_multi_05_mixed_visual_and_text_content_renders(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Mixed Stimulus Set',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'webpage',
            'title'            => 'Product Flyer',
            'image_url'        => 'https://images.unsplash.com/photo-1557804506-669a67965ba0',
            'content'          => 'Online catalog excerpt.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'letter',
            'title'            => 'Customer Support Letter',
            'content'          => 'Thank you for contacting our warranty team regarding your invoice.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'What is discussed in the letter?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Warranty invoice', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        // Visual image rendered
        $response->assertSee('https://images.unsplash.com/photo-1557804506-669a67965ba0', false);
        $response->assertSee('data-stimulus-zoomable="true"', false);
        $response->assertSee('Click image to enlarge', false);
        // Text prose rendered
        $response->assertSee('Thank you for contacting our warranty team regarding your invoice.', false);
    }

    /**
     * P7-MULTI-06: Image zoom regression — Each visual document remains zoomable.
     */
    public function test_p7_multi_06_image_zoom_regression_in_multi_passage(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Dual Visual Set',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'advertisement',
            'title'            => 'Ad Poster',
            'image_url'        => 'https://images.unsplash.com/photo-1586281380349-632531db7ed4',
            'content'          => 'Visual advertisement flyer.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'schedule',
            'title'            => 'Schedule Chart',
            'image_url'        => 'https://images.unsplash.com/photo-1506784983877-45594efa4cbe',
            'content'          => 'Visual timetable schedule.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'Question 1',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('id="stimulus-lightbox-modal"', false);
        $response->assertSee('openStimulusLightbox', false);
        $response->assertSee('closeStimulusLightbox', false);
        $response->assertSee('https://images.unsplash.com/photo-1586281380349-632531db7ed4', false);
        $response->assertSee('https://images.unsplash.com/photo-1506784983877-45594efa4cbe', false);
    }

    /**
     * P7-MULTI-07: Lightbox return state contract — modal markup and Escape/backdrop handlers preserved.
     */
    public function test_p7_multi_07_lightbox_contract_preserves_candidate_state(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Dual Set',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'notice',
            'title'            => 'Doc 1',
            'content'          => 'Text 1',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'Question 1',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('id="stimulus-lightbox-modal"', false);
        $response->assertSee('id="stimulus-lightbox-close-btn"', false);
        $response->assertSee('closeStimulusLightbox', false);
    }

    /**
     * P7-MULTI-08: Question regression — All linked question blocks remain present and answerable.
     */
    public function test_p7_multi_08_all_linked_questions_render_and_are_answerable(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Reading Set with 5 Linked Questions',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'notice',
            'title'            => 'Notice',
            'content'          => 'Office renovation notice.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'email',
            'title'            => 'Email',
            'content'          => 'Relocation details email.',
        ]);

        for ($i = 171; $i <= 175; $i++) {
            $q = Question::create([
                'passage_group_id' => $group->id,
                'prompt'           => "Target Question $i Prompt",
                'section'          => 'reading',
                'part_number'      => 7,
                'question_type'    => 'multiple_choice',
                'difficulty'       => 'medium',
                'points'           => 1,
            ]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => "Choice A for $i", 'is_correct' => true]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => "Choice B for $i", 'is_correct' => false]);
            TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => $i - 170]);
        }

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        for ($i = 171; $i <= 175; $i++) {
            $response->assertSee("Question $i", false);
            $response->assertSee("Target Question $i Prompt", false);
            $response->assertSee("Choice A for $i", false);
            $response->assertSee("Choice B for $i", false);
        }
    }

    /**
     * P7-MULTI-09: Single Passage remains unchanged with no quick-jump buttons.
     */
    public function test_p7_multi_09_single_passage_remains_simple_without_quick_jump(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Single Article',
            'part_number'  => 7,
            'passage_type' => 'single',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'article',
            'title'            => 'Single Newspaper Article',
            'content'          => 'The local bakery celebrated its 50th anniversary.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'What is being celebrated?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Anniversary', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('Single Newspaper Article', false);
        $response->assertSee('The local bakery celebrated its 50th anniversary.', false);
        // Single passage does not render quick-jump tabs
        $response->assertDontSee('id="passage-tab-unit-0-0"', false);
        $response->assertDontSee('Jump to:', false);
    }

    /**
     * P7-MULTI-10: Part 6 regression — Part 6 text completion remains structurally unchanged.
     */
    public function test_p7_multi_10_part_6_text_completion_group_remains_unchanged(): void
    {
        $p6Section = TestSection::create([
            'test_id'          => $this->test->id,
            'title'            => 'Part 6: Text Completion',
            'section_type'     => 'reading',
            'order'            => 0,
            'duration_minutes' => 15,
        ]);

        $p6Group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Company Memo with 4 Blanks',
            'part_number'  => 6,
            'passage_type' => 'single',
            'order'        => 0,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $p6Group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'memo',
            'title'            => 'Annual Health Checkup Notice',
            'content'          => 'All staff must schedule their appointments by [131].',
        ]);

        for ($i = 131; $i <= 134; $i++) {
            $q = Question::create([
                'passage_group_id' => $p6Group->id,
                'prompt'           => "Question $i Blank Prompt",
                'section'          => 'reading',
                'part_number'      => 6,
                'question_type'    => 'multiple_choice',
                'difficulty'       => 'medium',
                'points'           => 1,
            ]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => "Option A for $i", 'is_correct' => true]);
            TestQuestion::create(['test_section_id' => $p6Section->id, 'question_id' => $q->id, 'order' => $i - 130]);
        }

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('PART 6 — COMPANY MEMO WITH 4 BLANKS', false);
        $response->assertSee('Annual Health Checkup Notice', false);
        for ($i = 131; $i <= 134; $i++) {
            $response->assertSee("Question $i", false);
            $response->assertSee("Option A for $i", false);
        }
    }

    /**
     * P7-MULTI-11: Actual Candidate CBT Double/Triple Passage also renders all documents continuously.
     */
    public function test_p7_multi_11_candidate_cbt_renders_documents_continuously(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'CBT Triple Passage Set',
            'part_number'  => 7,
            'passage_type' => 'triple',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'schedule',
            'title'            => 'Bus Schedule',
            'content'          => 'Route 44 departs at 8:00 AM.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'notice',
            'title'            => 'Detour Notice',
            'content'          => 'Roadwork near 5th Avenue causes a 10 min delay.',
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 3,
            'document_type'    => 'email',
            'title'            => 'Commuter Feedback',
            'content'          => 'Thanks for updating the timetable.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'What causes the delay?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Roadwork', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        $attempt = $this->createActiveAttempt('simulator');
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

        $response->assertStatus(200);
        $response->assertSee('Document 1 of 3', false);
        $response->assertSee('Bus Schedule', false);
        $response->assertSee('Route 44 departs at 8:00 AM.', false);

        $response->assertSee('Document 2 of 3', false);
        $response->assertSee('Detour Notice', false);
        $response->assertSee('Roadwork near 5th Avenue causes a 10 min delay.', false);

        $response->assertSee('Document 3 of 3', false);
        $response->assertSee('Commuter Feedback', false);
        $response->assertSee('Thanks for updating the timetable.', false);

        $content = $response->getContent();
        $this->assertStringNotContainsString('passage-doc-content-unit-0 hidden', $content);
        $response->assertSee('passage-doc-tab', false);
    }

    /**
     * P7-MULTI-12: Delivery policies preserved across Preview, Simulator, and Mock/Real CBT.
     */
    public function test_p7_multi_12_delivery_policies_preserved(): void
    {
        $group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Policy Passage Set',
            'part_number'  => 7,
            'passage_type' => 'single',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        Passage::create([
            'passage_group_id' => $group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'notice',
            'title'            => 'Notice',
            'content'          => 'Standard policy content.',
        ]);

        $q = Question::create([
            'passage_group_id' => $group->id,
            'prompt'           => 'Policy question?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->p7Section->id, 'question_id' => $q->id, 'order' => 1]);

        // Preview: unrestricted QA mode
        $prevResponse = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $prevResponse->assertStatus(200);
        $prevResponse->assertSee('Preview only — no attempt, score, or result will be recorded.', false);

        // Simulator: practice mode
        $simAttempt = $this->createActiveAttempt('simulator');
        $simResponse = $this->actingAs($this->candidate)->get(route('candidate.exam', $simAttempt));
        $simResponse->assertStatus(200);

        // Real: governed mode
        $realAttempt = $this->createActiveAttempt('real');
        $realResponse = $this->actingAs($this->candidate)->get(route('candidate.exam', $realAttempt));
        $realResponse->assertStatus(200);
    }
}
