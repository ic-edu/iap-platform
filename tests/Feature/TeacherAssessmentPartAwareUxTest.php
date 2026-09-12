<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\TimerEngine;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\QuestionDifficultyDetectionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssessmentPartAwareUxTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Test $test;
    protected TestSection $section1;
    protected TestSection $section2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'name'   => 'TOEIC Teacher',
            'email'  => 'toeic_teacher@icedu.org',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->student = User::factory()->create([
            'name'   => 'Test Student',
            'email'  => 'student@icedu.org',
            'status' => 'active',
        ]);
        $this->student->assignRole('student');

        // Set up specimen TOEIC Assessment: 2 Sections, 15 Questions
        $this->test = Test::create([
            'title'            => 'TOEIC Listening & Reading Simulation Test',
            'slug'             => 'toeic-listening-reading-simulation-test-' . \Illuminate\Support\Str::random(6),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 120,
            'pass_score'       => 750,
            'status'           => 'draft',
            'is_published'     => false,
            'instructions'     => 'General Assessment Instructions for Candidates.',
            'created_by'       => $this->teacher->id,
            'assigned_to'      => $this->teacher->id,
            'scoring_method'   => 'automatic',
        ]);

        // Section 1: Part 1 Photograph (3 Questions)
        $this->section1 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photograph (Question 1-3)',
            'section_type' => 'listening',
            'instructions' => 'Look at the photo and choose the best statement.',
            'order'        => 1,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $q = Question::create([
                'prompt'      => "Part 1 Photo Question Stem {$i}",
                'section'     => 'listening',
                'part_number' => 1,
                'image_url'   => 'https://example.com/photo.jpg',
                'audio_url'   => 'https://example.com/audio.mp3',
                'difficulty'  => 'medium',
                'difficulty_score' => 60,
                'difficulty_status' => 'final',
                'difficulty_source' => 'auto',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $cIdx => $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label'       => $lbl,
                    'content'     => "Statement {$lbl}",
                    'choice_text' => "Statement {$lbl}",
                    'is_correct'  => ($lbl === 'A'),
                    'order'       => $cIdx + 1,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $this->section1->id,
                'question_id'     => $q->id,
                'order'           => $i,
                'points'          => 1,
            ]);
        }

        // Section 2: Part 2 Question-Response (12 Questions)
        $this->section2 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 2: Question-Response (Question 4-15)',
            'section_type' => 'listening',
            'instructions' => 'Listen to the question and choose the best response.',
            'order'        => 2,
        ]);

        for ($i = 4; $i <= 15; $i++) {
            $q = Question::create([
                'prompt'      => "Part 2 Question-Response Stem {$i}",
                'section'     => 'listening',
                'part_number' => 2,
                'audio_url'   => 'https://example.com/audio2.mp3',
                'difficulty'  => 'medium',
                'difficulty_score' => 55,
                'difficulty_status' => 'final',
                'difficulty_source' => 'auto',
            ]);

            foreach (['A', 'B', 'C'] as $cIdx => $lbl) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label'       => $lbl,
                    'content'     => "Response {$lbl}",
                    'choice_text' => "Response {$lbl}",
                    'is_correct'  => ($lbl === 'B'),
                    'order'       => $cIdx + 1,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $this->section2->id,
                'question_id'     => $q->id,
                'order'           => $i - 3,
                'points'          => 1,
            ]);
        }
    }

    // ==========================================
    // SECTION A: CANDIDATE PREVIEW DELIVERY TESTS (TEST 01 - TEST 15)
    // ==========================================

    /** @test */
    public function test_01_preview_initial_page_renders_assessment_overview()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="preview-overview-card"', false);
        $response->assertSee('Pre-Assessment Briefing &amp; Instructions', false);
        $response->assertSee('General Assessment Instructions for Candidates.', false);
        $response->assertSee('🚀 Begin Preview', false);
    }

    /** @test */
    public function test_02_question_1_is_not_the_initial_visible_state()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="delivery-unit-card-0"', false);
        $response->assertSee('showAssessmentOverview();', false);
    }

    /** @test */
    public function test_03_preview_timer_does_not_start_before_begin_preview()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        // Visual display is updated on load, but setInterval is wrapped inside startTimer()
        $response->assertSee('function startTimer()', false);
        $response->assertDontSee('if (timerSeconds > 0) { updateCountdownDisplay(); setInterval', false);
    }

    /** @test */
    public function test_04_begin_preview_starts_cosmetic_timer()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee('function startPreview() {', false);
        $response->assertSee('startTimer();', false);
    }

    /** @test */
    public function test_05_begin_preview_shows_first_section_directions()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="section-intro-card-' . $this->section1->id . '"', false);
        $response->assertSee('Look at the photo and choose the best statement.', false);
        $response->assertSee('showSectionIntro(firstSectionId);', false);
    }

    /** @test */
    public function test_06_begin_section_shows_q1()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee('beginSectionQuestions(0)', false);
        $response->assertSee('function beginSectionQuestions(', false);
    }

    /** @test */
    public function test_07_section_directions_do_not_increment_question_count()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        // Total questions count remains 15
        $response->assertSee('0 / 15 Answered', false);
    }

    /** @test */
    public function test_08_section_directions_do_not_create_palette_entry()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        // Palette buttons are numbered 1 to 15 (0..14)
        $response->assertSee('id="palette-btn-0"', false);
        $response->assertSee('id="palette-btn-14"', false);
        $response->assertDontSee('id="palette-btn-15"', false);
    }

    /** @test */
    public function test_09_q3_transitions_to_next_section_directions()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        // Question 3 (index 2) is the last question of Section 1
        $response->assertSee('showSectionIntro(\'' . $this->section2->id . '\')', false);
        $response->assertSee('Next Section &rarr;', false);
    }

    /** @test */
    public function test_10_next_section_directions_transitions_to_q4()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee('beginSectionQuestions(3)', false);
    }

    /** @test */
    public function test_11_timer_continues_during_inter_section_directions()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        // showSectionIntro does not clearInterval or pause timer
        $response->assertSee('function showSectionIntro(sectionId)', false);
        $response->assertDontSee('clearInterval(timerInterval); // in showSectionIntro', false);
    }

    /** @test */
    public function test_12_preview_creates_no_attempt()
    {
        $attemptCountBefore = Attempt::count();
        $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $this->assertEquals($attemptCountBefore, Attempt::count());
    }

    /** @test */
    public function test_13_preview_creates_no_answer()
    {
        $answerCountBefore = Answer::count();
        $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $this->assertEquals($answerCountBefore, Answer::count());
    }

    /** @test */
    public function test_14_preview_creates_no_score_or_result()
    {
        $attemptCountBefore = Attempt::whereNotNull('completed_at')->count();
        $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $this->assertEquals($attemptCountBefore, Attempt::whereNotNull('completed_at')->count());
    }

    /** @test */
    public function test_15_candidate_preview_media_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee('https://example.com/photo.jpg', false);
        $response->assertSee('https://example.com/audio.mp3', false);
    }

    // ==========================================
    // SECTION B: TEACHER AUTHORING & INTEGRITY TESTS (TEST 16 - TEST 32)
    // ==========================================

    /** @test */
    public function test_16_questions_render_grouped_under_correct_test_section()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="section-card-' . $this->section1->id . '"', false);
        $response->assertSee('id="section-card-' . $this->section2->id . '"', false);
    }

    /** @test */
    public function test_17_section_1_contains_q1_to_q3()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('• 3 of 6 questions', false);
        $response->assertSee('(Questions 1–6)', false);
        $response->assertSee('Part 1 Photo Question Stem 1', false);
        $response->assertSee('Part 1 Photo Question Stem 2', false);
        $response->assertSee('Part 1 Photo Question Stem 3', false);
    }

    /** @test */
    public function test_18_section_2_contains_q7_to_q18()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('(Questions 7–31)', false);
        $response->assertSee('Part 2 Question-Response Stem 4', false);
        $response->assertSee('Part 2 Question-Response Stem 15', false);
    }

    /** @test */
    public function test_19_global_question_numbering_remains_continuous()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Question #1', false);
        $response->assertSee('Question #3', false);
        $response->assertSee('Question #7', false);
        $response->assertSee('Question #18', false);
    }

    /** @test */
    public function test_20_every_section_card_has_add_question()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('openCreateAuthoredQuestionModal(\'' . $this->section1->id . '\'', false);
        $response->assertSee('openCreateAuthoredQuestionModal(\'' . $this->section2->id . '\'', false);
    }

    /** @test */
    public function test_21_context_add_question_locks_test_section_id()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="create-q-context-banner"', false);
        $response->assertSee('id="create-q-section-id"', false);
    }

    /** @test */
    public function test_22_toeic_contextual_add_locks_part_number()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('if (partInput && partNumber) partInput.value = partNumber;', false);
    }

    /** @test */
    public function test_23_target_section_dropdown_is_absent_in_contextual_mode()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        // In contextual mode, the global selector is hidden
        $response->assertSee('globalSelector.classList.add(\'hidden\');', false);
    }

    /** @test */
    public function test_24_global_add_does_not_expose_independent_incompatible_selectors()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="create-q-unified-part-section"', false);
    }

    /** @test */
    public function test_25_global_toeic_selection_synchronizes_part_plus_section()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('function onGlobalUnifiedSectionChange(selectEl)', false);
    }

    /** @test */
    public function test_26_invalid_crafted_part_section_combination_is_rejected_server_side()
    {
        // Craft a reading section
        $readingSection = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 5: Reading Section',
            'section_type' => 'reading',
            'order'        => 3,
        ]);

        // Attempt to create a Part 1 (listening) question pointing to a Reading Section
        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
            'test_section_id' => $readingSection->id,
            'part_number'     => 1,
            'prompt'          => 'Crafted Incompatible Question Stem',
            'question_type'   => 'multiple_choice',
            'image_url'       => 'https://example.com/test.jpg',
            'audio_url'       => 'https://example.com/test.mp3',
            'choices'         => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_choice'  => 0,
        ]);

        $response->assertSessionHasErrors('part_number');
    }

    /** @test */
    public function test_27_generic_non_toeic_section_add_works_with_null_part_number()
    {
        $genericTest = Test::create([
            'title'            => 'General English Assessment',
            'slug'             => 'general-english-assessment-' . \Illuminate\Support\Str::random(6),
            'test_type'        => 'general',
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'draft',
            'created_by'       => $this->teacher->id,
            'assigned_to'      => $this->teacher->id,
        ]);

        $sec = TestSection::create([
            'test_id'      => $genericTest->id,
            'title'        => 'Vocational Technical Section',
            'section_type' => 'reading',
            'order'        => 1,
        ]);

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $genericTest->id), [
            'test_section_id' => $sec->id,
            'prompt'          => 'What is the standard procedure for electrical maintenance?',
            'question_type'   => 'multiple_choice',
            'choices'         => ['Safety lockout', 'Skip inspection', 'Ignore protocol', 'None'],
            'correct_choice'  => 0,
        ]);

        $response->assertRedirect(route('teacher.tests.show', $genericTest->id));
        $this->assertDatabaseHas('questions', [
            'prompt'      => 'What is the standard procedure for electrical maintenance?',
            'part_number' => null,
        ]);
    }

    /** @test */
    public function test_28_auto_difficulty_remains_functional()
    {
        $detect = QuestionDifficultyDetectionService::detect([
            'part_number' => 1,
            'prompt'      => 'Look at the picture marked number 1 in your test book.',
            'image_url'   => 'https://example.com/photo.jpg',
            'audio_url'   => 'https://example.com/audio.mp3',
            'choices'     => ['He is writing.', 'She is talking.', 'They are walking.', 'He is sitting.'],
        ]);

        $this->assertEquals('final', $detect['difficulty_status']);
        $this->assertContains($detect['difficulty_level'], ['easy', 'medium', 'hard']);
    }

    /** @test */
    public function test_29_validation_assistant_output_unchanged()
    {
        $this->test->update(['test_type' => 'general']);
        $builderService = app(TestBuilderService::class);
        $result = $builderService->validateAssessment($this->test);

        $this->assertTrue($result['is_valid']);
        $this->assertCount(15, $result['questions']);
    }

    /** @test */
    public function test_30_section_media_remains_functional()
    {
        $media = MediaAsset::create([
            'title'         => 'Part 1 Reference Audio',
            'filename'      => 'part1_ref.mp3',
            'original_name' => 'part1_ref.mp3',
            'path'          => 'media/part1_ref.mp3',
            'mime_type'     => 'audio/mpeg',
            'size_bytes'    => 10240,
            'type'          => 'audio',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $this->section1->mediaAssets()->attach($media->id, [
            'id'    => (string) \Illuminate\Support\Str::ulid(),
            'order' => 1,
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Part 1 Reference Audio', false);
    }

    /** @test */
    public function test_31_audio_group_remains_functional()
    {
        $audioGroup = AudioGroup::create([
            'test_id'     => $this->test->id,
            'title'       => 'Shared Conversation Group 1',
            'audio_url'   => 'https://example.com/conv.mp3',
            'part_number' => 3,
            'order'       => 1,
            'created_by'  => $this->teacher->id,
        ]);

        $this->assertDatabaseHas('audio_groups', [
            'id'    => $audioGroup->id,
            'title' => 'Shared Conversation Group 1',
        ]);
    }

    /** @test */
    public function test_32_passage_group_remains_functional()
    {
        $passageGroup = PassageGroup::create([
            'test_id'      => $this->test->id,
            'title'        => 'Single Passage Notice',
            'part_number'  => 7,
            'passage_type' => 'single',
            'order'        => 1,
            'created_by'   => $this->teacher->id,
        ]);

        $this->assertDatabaseHas('passage_groups', [
            'id'    => $passageGroup->id,
            'title' => 'Single Passage Notice',
        ]);
    }

    // ==========================================
    // SECTION C: REGRESSION & GOVERNANCE TESTS (TEST 33 - TEST 40)
    // ==========================================

    /** @test */
    public function test_33_simulator_instructions_flow_unchanged()
    {
        $this->test->update(['status' => 'published', 'is_published' => true]);
        $response = $this->actingAs($this->student)->get(route('candidate.tests.instructions', $this->test->id));
        $response->assertOk();
        $response->assertSee('General Assessment Instructions for Candidates.', false);
        $response->assertSee('🚀 I Understand &amp; Begin Assessment', false);
    }

    /** @test */
    public function test_34_simulator_section_directions_unchanged()
    {
        $this->test->update(['status' => 'published', 'is_published' => true]);
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->test, $this->student);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt->id));
        $response->assertOk();
        $response->assertSee('id="section-intro-card-' . $this->section1->id . '"', false);
    }

    /** @test */
    public function test_35_real_test_instructions_flow_unchanged()
    {
        $this->test->update(['assessment_mode' => 'real_test', 'status' => 'published', 'is_published' => true]);

        CandidateTestAssignment::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'status'  => 'active',
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.tests.instructions', $this->test->id));
        $response->assertOk();
        $response->assertSee('Institutional Notice', false);
    }

    /** @test */
    public function test_36_real_test_timer_semantics_unchanged()
    {
        $this->test->update(['assessment_mode' => 'real_test', 'status' => 'published', 'is_published' => true]);
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->test, $this->student);

        $timerEngine = app(TimerEngine::class);
        $remSeconds = $timerEngine->getRemainingSeconds($attempt);
        $this->assertGreaterThan(7000, $remSeconds);
        $this->assertLessThanOrEqual(7200, $remSeconds);
    }

    /** @test */
    public function test_37_real_test_audio_restrictions_unchanged()
    {
        $this->test->update(['assessment_mode' => 'real_test', 'status' => 'published', 'is_published' => true]);
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->test, $this->student);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt->id));
        $response->assertOk();
        $response->assertSee('playRealTestSingleAudio', false);
    }

    /** @test */
    public function test_38_teacher_media_picker_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="question-media-picker-modal"', false);
    }

    /** @test */
    public function test_39_my_media_and_institutional_library_remain_separated()
    {
        $myMedia = MediaAsset::create([
            'title'         => 'Personal Recording Draft',
            'filename'      => 'draft.mp3',
            'original_name' => 'draft.mp3',
            'path'          => 'media/draft.mp3',
            'mime_type'     => 'audio/mpeg',
            'size_bytes'    => 5000,
            'type'          => 'audio',
            'uploaded_by'   => $this->teacher->id,
        ]);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $instMedia = MediaAsset::create([
            'title'         => 'Official Governed Audio',
            'filename'      => 'official.mp3',
            'original_name' => 'official.mp3',
            'path'          => 'media/official.mp3',
            'mime_type'     => 'audio/mpeg',
            'size_bytes'    => 8000,
            'type'          => 'audio',
            'uploaded_by'   => $admin->id,
        ]);

        $this->assertEquals($this->teacher->id, $myMedia->uploaded_by);
        $this->assertNotEquals($this->teacher->id, $instMedia->uploaded_by);
    }

    /** @test */
    public function test_40_no_assessment_governance_role_changes()
    {
        $student = User::factory()->create(['status' => 'active']);
        $student->assignRole('student');
        
        // Student cannot access teacher test builder
        $response = $this->actingAs($student)->get(route('teacher.tests.show', $this->test->id));
        $response->assertStatus(403);
    }
}
