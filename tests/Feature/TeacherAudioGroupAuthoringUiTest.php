<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\QuestionDifficultyDetectionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherAudioGroupAuthoringUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Test $test;
    protected TestSection $sectionPart1;
    protected TestSection $sectionPart2;
    protected TestSection $sectionPart3;
    protected TestSection $sectionPart4;
    protected TestSection $sectionPart5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');

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
            'title'            => 'TOEIC Audio Group Master Test',
            'slug'             => 'toeic-audio-group-master-test-' . \Illuminate\Support\Str::random(6),
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
            'instructions' => 'Directions for Photographs',
            'order'        => 1,
        ]);

        $this->sectionPart2 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 2: Question-Response',
            'section_type' => 'listening',
            'instructions' => 'Directions for Question-Response',
            'order'        => 2,
        ]);

        $this->sectionPart3 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 3: Conversations',
            'section_type' => 'listening',
            'instructions' => 'Directions for Conversations',
            'order'        => 3,
        ]);

        $this->sectionPart4 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 4: Talks',
            'section_type' => 'listening',
            'instructions' => 'Directions for Talks',
            'order'        => 4,
        ]);

        $this->sectionPart5 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 5: Incomplete Sentences',
            'section_type' => 'reading',
            'instructions' => 'Directions for Incomplete Sentences',
            'order'        => 5,
        ]);
    }

    // =========================================================================
    // SECTION 44: FOCUSED TESTS — UI (TEST 01 - TEST 18)
    // =========================================================================

    /** @test */
    public function test_01_part_3_renders_add_audio_group_cta()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("+ Add Audio Group", false);
        $response->assertSee("openCreateAudioGroupModal('{$this->sectionPart3->id}', '3'", false);
    }

    /** @test */
    public function test_02_part_4_renders_add_audio_group_cta()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("openCreateAudioGroupModal('{$this->sectionPart4->id}', '4'", false);
    }

    /** @test */
    public function test_03_part_1_does_not_render_audio_group_cta()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertDontSee("openCreateAudioGroupModal('{$this->sectionPart1->id}'", false);
    }

    /** @test */
    public function test_04_part_2_does_not_render_audio_group_cta()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertDontSee("openCreateAudioGroupModal('{$this->sectionPart2->id}'", false);
    }

    /** @test */
    public function test_05_part_5_does_not_render_audio_group_cta()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertDontSee("openCreateAudioGroupModal('{$this->sectionPart5->id}'", false);
    }

    /** @test */
    public function test_06_open_create_audio_group_modal_js_function_exists()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('function openCreateAudioGroupModal(', false);
        $response->assertSee('function closeCreateAudioGroupModal(', false);
    }

    /** @test */
    public function test_07_create_audio_group_modal_container_exists_in_dom()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="create-audio-group-modal"', false);
        $response->assertSee('id="create-audio-group-form"', false);
    }

    /** @test */
    public function test_08_part_3_context_locks_section_id_in_modal()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="ag-section-id"', false);
        $response->assertSee('id="ag-target-section-title"', false);
    }

    /** @test */
    public function test_09_part_3_context_locks_part_number_in_modal()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="ag-part-number"', false);
        $response->assertSee('id="ag-part-badge"', false);
    }

    /** @test */
    public function test_10_part_3_group_type_locked_to_conversation()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="ag-group-type"', false);
    }

    /** @test */
    public function test_11_part_4_part_number_locked_to_4_via_js()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("isTalk ? 'talk' : 'conversation'", false);
    }

    /** @test */
    public function test_12_part_4_group_type_locked_to_talk_via_js()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("isTalk ? 'Create Talk Group' : 'Create Conversation Group'", false);
    }

    /** @test */
    public function test_13_modal_renders_exactly_three_child_question_panels()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Question 1 of 3', false);
        $response->assertSee('Question 2 of 3', false);
        $response->assertSee('Question 3 of 3', false);
        $response->assertDontSee('Question 4 of 3', false);
    }

    /** @test */
    public function test_14_each_child_renders_exactly_four_answer_choices()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('name="questions[0][choices][]"', false);
        $response->assertSee('name="questions[1][choices][]"', false);
        $response->assertSee('name="questions[2][choices][]"', false);
    }

    /** @test */
    public function test_15_each_child_exposes_correct_answer_selection()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('name="questions[0][correct_choice]"', false);
        $response->assertSee('name="questions[1][correct_choice]"', false);
        $response->assertSee('name="questions[2][correct_choice]"', false);
    }

    /** @test */
    public function test_16_shared_audio_field_exists_once_per_group()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="ag-media-asset-id"', false);
        $response->assertSee('id="ag-audio-url"', false);
        $response->assertSee('Shared Audio Stimulus', false);
    }

    /** @test */
    public function test_17_audio_script_field_is_optional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('name="audio_script"', false);
        $response->assertSee('id="ag-audio-script"', false);
    }

    /** @test */
    public function test_18_save_audio_group_action_posts_existing_route()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee(route('teacher.tests.create-audio-group', $this->test->id), false);
        $response->assertSee('Save Audio Group', false);
    }

    // =========================================================================
    // SECTION 45: FOCUSED TESTS — MEDIA (TEST 19 - TEST 23)
    // =========================================================================

    /** @test */
    public function test_19_my_media_works_in_audio_group_modal()
    {
        $media = MediaAsset::create([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Teacher Office Audio',
            'filename'        => 'office.mp3',
            'original_name'   => 'office.mp3',
            'type'            => 'audio',
            'mime_type'       => 'audio/mpeg',
            'path'            => 'media/office.mp3',
            'size'            => 50000,
            'approval_status' => 'draft',
            'content_hash'    => hash('sha256', 'office_audio_data'),
        ]);

        $response = $this->actingAs($this->teacher)->get('/admin/media/list?source=my');
        $response->assertOk();
        $response->assertJsonFragment([
            'id'    => $media->id,
            'title' => 'Teacher Office Audio',
        ]);
    }

    /** @test */
    public function test_20_institutional_library_works()
    {
        $media = MediaAsset::create([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Institutional TOEIC Track 03',
            'filename'        => 'track03.mp3',
            'original_name'   => 'track03.mp3',
            'type'            => 'audio',
            'mime_type'     => 'audio/mpeg',
            'path'          => 'media/track03.mp3',
            'size'          => 80000,
            'approval_status' => 'approved',
            'content_hash'    => hash('sha256', 'track03_audio_data'),
        ]);

        $response = $this->actingAs($this->teacher)->get('/admin/media/list?source=institutional');
        $response->assertOk();
        $response->assertJsonFragment([
            'id'    => $media->id,
            'title' => 'Institutional TOEIC Track 03',
        ]);
    }

    /** @test */
    public function test_21_audio_preview_works_above_modal()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="asset-preview-modal"', false);
        $response->assertSee('z-[10002]', false);
    }

    /** @test */
    public function test_22_sha256_dedupe_remains_active()
    {
        $file = UploadedFile::fake()->create('conv1.mp3', 100, 'audio/mpeg');

        $res1 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file' => $file,
        ]);
        $res1->assertOk();
        $assetId1 = $res1->json('asset.id');

        // Re-upload same file by same teacher -> deduplicated
        $res2 = $this->actingAs($this->teacher)->postJson(route('admin.media.store'), [
            'file' => $file,
        ]);
        $res2->assertOk();
        $assetId2 = $res2->json('asset.id');

        $this->assertEquals($assetId1, $assetId2);
    }

    /** @test */
    public function test_23_institutional_media_cannot_be_overwritten()
    {
        $media = MediaAsset::create([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Locked Institutional Master Audio',
            'filename'        => 'inst_locked.mp3',
            'original_name'   => 'inst_locked.mp3',
            'type'            => 'audio',
            'mime_type'       => 'audio/mpeg',
            'path'            => 'media/inst_locked.mp3',
            'size'            => 70000,
            'approval_status' => 'approved',
            'content_hash'    => hash('sha256', 'locked_inst_data'),
        ]);

        $this->assertEquals('approved', $media->approval_status);
    }

    // =========================================================================
    // SECTION 46: FOCUSED TESTS — BACKEND CONTRACT (TEST 24 - TEST 35)
    // =========================================================================

    /** @test */
    public function test_24_part_3_valid_group_creates_audio_group()
    {
        $media = MediaAsset::create([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Conversation 1 Audio',
            'filename'        => 'conv1.mp3',
            'original_name'   => 'conv1.mp3',
            'type'            => 'audio',
            'mime_type'       => 'audio/mpeg',
            'path'            => 'media/conv1.mp3',
            'size'            => 40000,
            'approval_status' => 'draft',
            'content_hash'    => hash('sha256', 'conv1_data'),
        ]);

        $payload = [
            'test_section_id' => $this->sectionPart3->id,
            'title'           => 'Conversation Set 1',
            'group_type'      => 'conversation',
            'part_number'     => 3,
            'media_asset_id'  => $media->id,
            'audio_url'       => $media->path,
            'audio_script'    => 'M: Did you see the memo? W: Yes, I did.',
            'questions'       => [
                [
                    'prompt'         => 'Where is the conversation most likely taking place?',
                    'difficulty'     => 'easy',
                    'explanation'    => 'Context indicates an office setting.',
                    'choices'        => ['In an office', 'At an airport', 'In a restaurant', 'At a bank'],
                    'correct_choice' => 0,
                ],
                [
                    'prompt'         => 'What does the man ask about?',
                    'difficulty'     => 'medium',
                    'explanation'    => 'He asks about the memo.',
                    'choices'        => ['A flight ticket', 'A recent memo', 'A client meeting', 'A lunch order'],
                    'correct_choice' => 1,
                ],
                [
                    'prompt'         => 'What will the woman probably do next?',
                    'difficulty'     => 'hard',
                    'explanation'    => 'She confirms she read it.',
                    'choices'        => ['Call the manager', 'Leave the building', 'Review the proposal', 'Attend a conference'],
                    'correct_choice' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('audio_groups', [
            'test_id'        => $this->test->id,
            'title'          => 'Conversation Set 1',
            'group_type'     => 'conversation',
            'part_number'    => 3,
            'media_asset_id' => $media->id,
        ]);
    }

    /** @test */
    public function test_25_part_3_valid_group_creates_exactly_3_questions()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $group = AudioGroup::where('test_id', $this->test->id)->first();

        $this->assertNotNull($group);
        $this->assertCount(3, $group->questions);
    }

    /** @test */
    public function test_26_all_child_questions_share_same_audio_group_id()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $group = AudioGroup::where('test_id', $this->test->id)->first();

        $questions = $group->questions;
        foreach ($questions as $q) {
            $this->assertEquals($group->id, $q->audio_group_id);
            $this->assertEquals(3, $q->part_number);
        }
    }

    /** @test */
    public function test_27_four_choices_created_per_question()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $group = AudioGroup::where('test_id', $this->test->id)->first();

        foreach ($group->questions as $q) {
            $this->assertCount(4, $q->choices);
        }
    }

    /** @test */
    public function test_28_correct_answer_saved_independently_per_question()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $group = AudioGroup::where('test_id', $this->test->id)->first();

        $questions = $group->questions()->orderBy('id', 'asc')->get();
        $this->assertEquals('A', $questions[0]->choices->where('is_correct', true)->first()?->label);
        $this->assertEquals('B', $questions[1]->choices->where('is_correct', true)->first()?->label);
        $this->assertEquals('C', $questions[2]->choices->where('is_correct', true)->first()?->label);
    }

    /** @test */
    public function test_29_auto_difficulty_saved_independently_per_question()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $group = AudioGroup::where('test_id', $this->test->id)->first();

        foreach ($group->questions as $q) {
            $this->assertNotNull($q->difficulty);
            $this->assertNotNull($q->difficulty_score);
            $this->assertNotNull($q->difficulty_status);
        }
    }

    /** @test */
    public function test_30_test_question_global_ordering_remains_sequential()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $testQuestions = TestQuestion::where('test_section_id', $this->sectionPart3->id)->orderBy('order', 'asc')->get();

        $this->assertCount(3, $testQuestions);
        $this->assertEquals(1, $testQuestions[0]->order);
        $this->assertEquals(2, $testQuestions[1]->order);
        $this->assertEquals(3, $testQuestions[2]->order);
    }

    /** @test */
    public function test_31_missing_audio_rejected()
    {
        $payload = [
            'test_section_id' => $this->sectionPart3->id,
            'title'           => 'No Audio Set',
            'group_type'      => 'conversation',
            'part_number'     => 3,
            'media_asset_id'  => null,
            'audio_url'       => null,
            'questions'       => [
                ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function test_32_2_questions_rejected()
    {
        $payload = [
            'test_section_id' => $this->sectionPart3->id,
            'title'           => 'Two Questions Group',
            'group_type'      => 'conversation',
            'part_number'     => 3,
            'audio_url'       => 'https://example.com/audio.mp3',
            'questions'       => [
                ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertSessionHasErrors('questions');
    }

    /** @test */
    public function test_33_4_questions_rejected()
    {
        $payload = [
            'test_section_id' => $this->sectionPart3->id,
            'title'           => 'Four Questions Group',
            'group_type'      => 'conversation',
            'part_number'     => 3,
            'audio_url'       => 'https://example.com/audio.mp3',
            'questions'       => [
                ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q4', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertSessionHasErrors('questions');
    }

    /** @test */
    public function test_34_part_3_with_group_type_talk_rejected()
    {
        $payload = [
            'test_section_id' => $this->sectionPart3->id,
            'title'           => 'Mismatched Part 3',
            'group_type'      => 'talk',
            'part_number'     => 3,
            'audio_url'       => 'https://example.com/audio.mp3',
            'questions'       => [
                ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function test_35_part_4_with_group_type_conversation_rejected()
    {
        $payload = [
            'test_section_id' => $this->sectionPart4->id,
            'title'           => 'Mismatched Part 4',
            'group_type'      => 'conversation',
            'part_number'     => 4,
            'audio_url'       => 'https://example.com/audio.mp3',
            'questions'       => [
                ['prompt' => 'Q1', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q2', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
                ['prompt' => 'Q3', 'choices' => ['A', 'B', 'C', 'D'], 'correct_choice' => 0],
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertSessionHasErrors();
    }

    // =========================================================================
    // SECTION 47: REGRESSION TESTS (TEST 36 - TEST 42)
    // =========================================================================

    /** @test */
    public function test_36_candidate_preview_shared_audio_still_resolves()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $group = AudioGroup::where('test_id', $this->test->id)->first();
        $question = $group->questions->first();

        $this->assertEquals(route('media.preview', $group->media_asset_id), $question->getEffectiveAudioUrl());

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.preview', $this->test->id));
        $response->assertOk();
        $response->assertSee(route('media.preview', $group->media_asset_id), false);
    }

    /** @test */
    public function test_37_simulator_shared_audio_still_works()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->test, $this->student);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt->id));
        $response->assertOk();
        $response->assertSee('Shared Conversation Audio', false);
        $response->assertSee('Shared Group', false);
    }

    /** @test */
    public function test_38_real_test_group_single_play_remains_enforced()
    {
        $this->test_24_part_3_valid_group_creates_audio_group();
        $realTest = Test::create([
            'title'            => 'TOEIC Real Exam Single Play',
            'slug'             => 'toeic-real-exam-sp-' . \Illuminate\Support\Str::random(6),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_percentage'  => 75,
            'status'           => 'published',
            'created_by'       => $this->teacher->id,
        ]);

        $sec = TestSection::create([
            'test_id'      => $realTest->id,
            'title'        => 'Part 3: Real Test Conversations',
            'section_type' => 'listening',
            'order'        => 1,
        ]);

        $service = app(TestBuilderService::class);
        $audioGroup = $service->createAudioGroup($sec, [
            'title'       => 'Real Test Conv 1',
            'group_type'  => 'conversation',
            'part_number' => 3,
            'audio_url'   => 'media/conv1.mp3',
            'questions'   => [
                ['prompt' => 'Q1 Prompt', 'choices' => ['A1', 'B1', 'C1', 'D1'], 'correct_choice' => 0],
                ['prompt' => 'Q2 Prompt', 'choices' => ['A2', 'B2', 'C2', 'D2'], 'correct_choice' => 1],
                ['prompt' => 'Q3 Prompt', 'choices' => ['A3', 'B3', 'C3', 'D3'], 'correct_choice' => 2],
            ],
        ]);

        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($realTest, $this->student);

        $qList = $audioGroup->questions()->get();

        // Create sample audio file in storage so stream endpoint can find it
        $path = storage_path('app/public/media/conv1.mp3');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'dummy_mp3_content');

        // Stream audio for question 1 -> records play for all 3 questions in group
        $streamRes1 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt->id, $qList[0]->id]));
        $streamRes1->assertOk();

        $this->assertDatabaseHas('attempt_audio_plays', [
            'attempt_id'  => $attempt->id,
            'question_id' => $qList[0]->id,
            'play_count'  => 1,
        ]);
        $this->assertDatabaseHas('attempt_audio_plays', [
            'attempt_id'  => $attempt->id,
            'question_id' => $qList[1]->id,
            'play_count'  => 1,
        ]);
        $this->assertDatabaseHas('attempt_audio_plays', [
            'attempt_id'  => $attempt->id,
            'question_id' => $qList[2]->id,
            'play_count'  => 1,
        ]);

        // Attempting to stream audio again for question 2 -> 403 Forbidden
        $streamRes2 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt->id, $qList[1]->id]));
        $streamRes2->assertForbidden();

        @unlink($path);
    }

    /** @test */
    public function test_39_section_media_remains_independent()
    {
        $secMedia = MediaAsset::create([
            'uploaded_by'     => $this->teacher->id,
            'title'           => 'Part 3 Section Directions Media',
            'filename'        => 'p3_directions.mp3',
            'original_name'   => 'p3_directions.mp3',
            'type'            => 'audio',
            'mime_type'       => 'audio/mpeg',
            'path'            => 'media/p3_directions.mp3',
            'size'            => 20000,
            'approval_status' => 'draft',
            'content_hash'    => hash('sha256', 'p3_directions_data'),
        ]);

        $this->sectionPart3->mediaAssets()->attach($secMedia->id, ['id' => (string) \Illuminate\Support\Str::ulid(), 'order' => 1]);

        $this->assertDatabaseHas('test_section_media', [
            'test_section_id' => $this->sectionPart3->id,
            'media_asset_id'  => $secMedia->id,
        ]);
    }

    /** @test */
    public function test_40_single_question_authoring_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-question', $this->test->id), [
            'test_section_id' => $this->sectionPart1->id,
            'prompt'          => 'A man is painting a wall.',
            'section'         => 'listening',
            'part_number'     => 1,
            'question_type'   => 'multiple_choice',
            'choices'         => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_choice'  => 0,
            'image_url'       => 'media/photo1.jpg',
            'audio_url'       => 'media/photo1.mp3',
        ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $this->assertDatabaseHas('questions', [
            'prompt'      => 'A man is painting a wall.',
            'part_number' => 1,
        ]);
    }

    /** @test */
    public function test_41_auto_difficulty_single_question_flow_remains_functional()
    {
        $this->test_40_single_question_authoring_remains_functional();
        $q = Question::where('part_number', 1)->first();

        $this->assertNotNull($q->difficulty);
        $this->assertNotNull($q->difficulty_score);
    }

    /** @test */
    public function test_42_media_architecture_remains_functional()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="question-media-picker-modal"', false);
        $response->assertSee('id="attach-section-media-modal"', false);
    }

    /** @test */
    public function test_43_audio_group_modal_renders_close_and_cancel_with_attempt_close_guard()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('onclick="attemptCloseAudioGroupModal()"', false);
        $response->assertSee('id="create-audio-group-modal"', false);
    }

    /** @test */
    public function test_44_audio_group_modal_renders_draft_restored_banner_and_discard_button()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('id="ag-draft-restored-banner"', false);
        $response->assertSee('onclick="discardAudioGroupDraft(true)"', false);
        $response->assertSee('Discard Draft', false);
    }

    /** @test */
    public function test_45_audio_group_modal_includes_session_storage_draft_key_and_restore_logic()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('getAudioGroupDraftStorageKey', false);
        $response->assertSee('triggerAudioGroupDraftSave', false);
        $response->assertSee('sessionStorage.getItem', false);
    }

    /** @test */
    public function test_46_audio_group_modal_includes_dirty_check_and_confirmation_guard()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('isAudioGroupFormDirty', false);
        $response->assertSee('attemptCloseAudioGroupModal', false);
        $response->assertSee('forceCloseAudioGroupModal', false);
        $response->assertSee('iapConfirm', false);
    }

    /** @test */
    public function test_47_audio_group_modal_includes_beforeunload_guard()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('beforeunload', false);
        $response->assertSee('isAudioGroupFormDirty()', false);
    }

    /** @test */
    public function test_48_escape_key_listener_targets_audio_group_guard_when_modal_is_open()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("event.key === 'Escape'", false);
        $response->assertSee('attemptCloseAudioGroupModal();', false);
    }

    /** @test */
    public function test_49_successful_submission_clears_transient_draft()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('sessionStorage.removeItem(getAudioGroupDraftStorageKey(secId))', false);
    }

    /** @test */
    public function test_50_part_4_shares_identical_unsaved_work_protection_architecture()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee("openCreateAudioGroupModal('{$this->sectionPart4->id}', '4'", false);
        $response->assertSee('attemptCloseAudioGroupModal', false);
    }

    /** @test */
    public function test_51_user_scoped_draft_storage_key_includes_authenticated_user_id()
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $expectedKeyPrefix = "iap:audio_group_draft_{$this->teacher->id}_{$this->test->id}_";
        $response->assertSee($expectedKeyPrefix, false);
    }

    /** @test */
    public function test_52_cross_user_isolation_teacher_a_draft_cannot_restore_for_teacher_b()
    {
        $teacherB = User::factory()->create([
            'name'   => 'TOEIC Teacher Secondary Author',
            'email'  => 'toeic_author_b@icedu.org',
            'status' => 'active',
        ]);
        $teacherB->assignRole('teacher');

        $responseA = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $responseA->assertOk();
        $expectedKeyA = "iap:audio_group_draft_{$this->teacher->id}_{$this->test->id}_";
        $responseA->assertSee($expectedKeyA, false);

        // Assign teacher B so they can view/author the same test
        $this->test->update(['assigned_to' => $teacherB->id]);

        $responseB = $this->actingAs($teacherB)->get(route('teacher.tests.show', $this->test->id));
        $responseB->assertOk();
        $expectedKeyB = "iap:audio_group_draft_{$teacherB->id}_{$this->test->id}_";
        $responseB->assertSee($expectedKeyB, false);
        $responseB->assertDontSee($expectedKeyA, false);

        $this->assertNotEquals($expectedKeyA, $expectedKeyB);
    }

    /** @test */
    public function test_53_same_user_different_test_and_section_isolation()
    {
        $test2 = Test::create([
            'title'            => 'TOEIC Audio Group Master Test 2',
            'slug'             => 'toeic-audio-group-master-test-2-' . \Illuminate\Support\Str::random(6),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 120,
            'pass_percentage'  => 75,
            'status'           => 'draft',
            'created_by'       => $this->teacher->id,
            'assigned_to'      => $this->teacher->id,
        ]);

        $responseTest1 = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $responseTest1->assertOk();
        $responseTest1->assertSee("iap:audio_group_draft_{$this->teacher->id}_{$this->test->id}_", false);

        $responseTest2 = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $test2->id));
        $responseTest2->assertOk();
        $responseTest2->assertSee("iap:audio_group_draft_{$this->teacher->id}_{$test2->id}_", false);
        $responseTest2->assertDontSee("iap:audio_group_draft_{$this->teacher->id}_{$this->test->id}_", false);
    }
}
