<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
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

class CandidateStimulusImageLightboxTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $candidate;
    protected Test $test;
    protected TestSection $readingSection;
    protected PassageGroup $part7Group;
    protected Passage $webpagePassage;
    protected Question $q1;
    protected Question $q2;

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
            'title'            => 'TOEIC Reading Simulation with Stimulus Image',
            'slug'             => 'toeic-reading-simulation-' . uniqid(),
            'test_type'        => 'toeic',
            'duration_minutes' => 75,
            'pass_score'       => 700,
            'scoring_method'   => 'automatic',
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        $this->readingSection = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'PART 7: READING COMPREHENSION',
            'section_type' => 'reading',
            'order'        => 1,
            'instructions' => 'Read the selections and answer the questions below.',
        ]);

        $this->part7Group = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Web Page - Grand Hotel Conference Facilities',
            'part_number'  => 7,
            'passage_type' => 'single',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        $this->webpagePassage = Passage::create([
            'passage_group_id' => $this->part7Group->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'webpage',
            'title'            => 'Grand Hotel Conference Webpage',
            'image_url'        => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df',
            'content'          => 'Welcome to the Grand Hotel. Review meeting room capacities and booking rates.',
        ]);

        $this->q1 = Question::create([
            'passage_group_id' => $this->part7Group->id,
            'prompt'           => 'What is the maximum capacity of Ballroom A?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => '250 people', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => '500 people', 'is_correct' => false]);

        $this->q2 = Question::create([
            'passage_group_id' => $this->part7Group->id,
            'prompt'           => 'How much is the hourly rate for the Executive Boardroom?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => '$150', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => '$300', 'is_correct' => false]);

        TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $this->q2->id, 'order' => 2]);
    }

    /**
     * Helper to create active attempt for CBT.
     */
    protected function createActiveAttempt(string $mode = 'simulator'): Attempt
    {
        $assignment = CandidateTestAssignment::create([
            'test_id'        => $this->test->id,
            'user_id'        => $this->candidate->id,
            'status'         => 'in_progress',
            'assigned_by'    => $this->teacher->id,
            'valid_until'    => now()->addDays(7),
        ]);

        return Attempt::create([
            'test_id'       => $this->test->id,
            'user_id'       => $this->candidate->id,
            'assignment_id' => $assignment->id,
            'status'        => AttemptStatus::InProgress,
            'mode'          => $mode,
            'started_at'    => now(),
            'total_score'   => 0,
        ]);
    }

    /**
     * IMAGE-ZOOM-01: Part 7 Candidate Preview renders visual stimulus as zoomable.
     */
    public function test_image_zoom_01_preview_renders_visual_stimulus_as_zoomable(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('data-stimulus-zoomable="true"', false);
        $response->assertSee('stimulus-zoomable', false);
        $response->assertSee('cursor-zoom-in', false);
        $response->assertSee('openStimulusLightbox', false);
        $response->assertSee('Click image to enlarge', false);
    }

    /**
     * IMAGE-ZOOM-02: Stimulus contains lightbox trigger markup and modal structure in preview.
     */
    public function test_image_zoom_02_preview_contains_lightbox_modal_structure(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('id="stimulus-lightbox-modal"', false);
        $response->assertSee('id="stimulus-lightbox-image"', false);
        $response->assertSee('id="stimulus-lightbox-caption"', false);
        $response->assertSee('id="stimulus-lightbox-close-btn"', false);
        $response->assertSee('handleStimulusLightboxBackdropClick', false);
        $response->assertSee('closeStimulusLightbox', false);
    }

    /**
     * IMAGE-ZOOM-03: Zoomed image container preserves aspect ratio (object-contain).
     */
    public function test_image_zoom_03_zoomed_image_preserves_aspect_ratio_and_containment(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('object-contain', false);
        $response->assertSee('max-w-[95vw]', false);
        $response->assertSee('max-h-[82vh]', false);
    }

    /**
     * IMAGE-ZOOM-04 & IMAGE-ZOOM-05: Backdrop click closes lightbox while image click stops propagation.
     */
    public function test_image_zoom_04_05_backdrop_click_and_image_event_propagation_contract(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('handleStimulusLightboxBackdropClick(event)', $content);
        $this->assertStringContainsString('event.stopPropagation()', $content);
        $this->assertStringContainsString('function handleStimulusLightboxBackdropClick(event)', $content);
    }

    /**
     * IMAGE-ZOOM-06: Escape closes lightbox without side effects.
     */
    public function test_image_zoom_06_escape_closes_lightbox_without_triggering_parent_modals(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('if (isStimulusLightboxOpen())', $content);
        $this->assertStringContainsString('closeStimulusLightbox()', $content);
    }

    /**
     * IMAGE-ZOOM-07: Closing returns to same question / delivery unit.
     */
    public function test_image_zoom_07_closing_preserves_delivery_unit_and_question_blocks(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('id="delivery-unit-card-0"', false);
        $response->assertSee('id="unit-question-block-0"', false);
        $response->assertSee('id="unit-question-block-1"', false);
    }

    /**
     * IMAGE-ZOOM-08: Selected answer radio inputs survive zoom open/close without DOM alteration.
     */
    public function test_image_zoom_08_selected_answer_state_intact_in_preview_dom(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('name="preview_choice_' . $this->q1->id . '"', false);
        $response->assertSee('name="preview_choice_' . $this->q2->id . '"', false);
    }

    /**
     * IMAGE-ZOOM-09: Part 7 multi-document tab remains selected and zoomable.
     */
    public function test_image_zoom_09_multi_document_tab_images_each_support_zoom(): void
    {
        $doubleGroup = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Double Passage: Memo and Schedule',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 2,
            'created_by'   => $this->teacher->id,
        ]);

        $doc1 = Passage::create([
            'passage_group_id' => $doubleGroup->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'memo',
            'title'            => 'Department Memo',
            'image_url'        => 'https://images.unsplash.com/photo-1586281380349-632531db7ed4',
            'content'          => 'Staff training memo details.',
        ]);

        $doc2 = Passage::create([
            'passage_group_id' => $doubleGroup->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 2,
            'document_type'    => 'schedule',
            'title'            => 'Training Schedule',
            'image_url'        => 'https://images.unsplash.com/photo-1506784983877-45594efa4cbe',
            'content'          => 'Workshop schedule breakdown.',
        ]);

        $dq = Question::create([
            'passage_group_id' => $doubleGroup->id,
            'prompt'           => 'When is the workshop session?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $dq->id, 'label' => 'A', 'content' => 'Friday afternoon', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $dq->id, 'order' => 3]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('switchPassageDocUnit', false);
        $response->assertSee('Department Memo (Memo)', false);
        $response->assertSee('Training Schedule (Schedule)', false);
        $response->assertSee('https://images.unsplash.com/photo-1586281380349-632531db7ed4', false);
        $response->assertSee('https://images.unsplash.com/photo-1506784983877-45594efa4cbe', false);
    }

    /**
     * IMAGE-ZOOM-10: Actual Candidate CBT also supports authorized image zoom.
     */
    public function test_image_zoom_10_candidate_cbt_supports_stimulus_image_zoom(): void
    {
        $attempt = $this->createActiveAttempt('simulator');

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

        $response->assertStatus(200);
        $response->assertSee('id="stimulus-lightbox-modal"', false);
        $response->assertSee('id="stimulus-lightbox-image"', false);
        $response->assertSee('id="stimulus-lightbox-close-btn"', false);
        $response->assertSee('data-stimulus-zoomable="true"', false);
        $response->assertSee('stimulus-zoomable', false);
        $response->assertSee('cursor-zoom-in', false);
        $response->assertSee('openStimulusLightbox', false);
    }

    /**
     * IMAGE-ZOOM-11: Simulator / Mock-Real navigation policy remains unchanged.
     */
    public function test_image_zoom_11_mock_real_and_simulator_policies_remain_intact(): void
    {
        $mockAttempt = $this->createActiveAttempt('mock');

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $mockAttempt));

        $response->assertStatus(200);
        // Real Test policy invariants are preserved
        $response->assertSee('handleNextClick', false);
        $response->assertSee('isRealTest', false);
    }

    /**
     * IMAGE-ZOOM-12: Ordinary text passage without image is rendered normally without zoom overlay errors.
     */
    public function test_image_zoom_12_text_only_passage_rendered_normally(): void
    {
        $textGroup = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Text Only Article',
            'part_number'  => 7,
            'passage_type' => 'single',
            'order'        => 3,
            'created_by'   => $this->teacher->id,
        ]);

        $textPassage = Passage::create([
            'passage_group_id' => $textGroup->id,
            'test_id'          => $this->test->id,
            'order_in_group'   => 1,
            'document_type'    => 'article',
            'title'            => 'Clean Text Article',
            'content'          => 'This is a pure textual passage with no image attached.',
        ]);

        $tq = Question::create([
            'passage_group_id' => $textGroup->id,
            'prompt'           => 'What is the topic?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'easy',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $tq->id, 'label' => 'A', 'content' => 'Article topic', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $this->readingSection->id, 'question_id' => $tq->id, 'order' => 4]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));

        $response->assertStatus(200);
        $response->assertSee('This is a pure textual passage with no image attached.', false);
    }
}
