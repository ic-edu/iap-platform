<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
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

class TeacherProgressiveAudioGroupAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected Test $test;
    protected TestSection $part3Section;
    protected TestSection $part4Section;
    protected MediaAsset $audioAsset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create([
            'email' => 'teacher.ag2@test.com',
            'status' => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        $this->test = Test::create([
            'title' => 'TOEIC Listening Assessment AG2',
            'slug' => 'toeic-listening-assessment-ag2-' . \Illuminate\Support\Str::random(6),
            'test_type' => 'toeic',
            'assessment_mode' => 'simulator',
            'duration_minutes' => 45,
            'pass_percentage' => 75,
            'status' => 'draft',
            'created_by' => $this->teacher->id,
            'assigned_to' => $this->teacher->id,
        ]);

        $this->part3Section = TestSection::create([
            'test_id' => $this->test->id,
            'title' => 'Part 3: Conversations',
            'section_type' => 'listening',
            'order' => 3,
            'instructions' => 'Listen to conversations.',
        ]);

        $this->part4Section = TestSection::create([
            'test_id' => $this->test->id,
            'title' => 'Part 4: Talks',
            'section_type' => 'listening',
            'order' => 4,
            'instructions' => 'Listen to short talks.',
        ]);

        $this->audioAsset = MediaAsset::create([
            'uploaded_by' => $this->teacher->id,
            'type' => 'audio',
            'filename' => 'conversation_sample.mp3',
            'original_name' => 'conversation_sample.mp3',
            'path' => 'audio/conversation_sample.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024000,
            'is_institutional' => false,
            'title' => 'Office Project Conversation',
        ]);
    }

    /**
     * Helper to create a valid question payload for a single slot.
     */
    protected function validSlotPayload(string $prompt = 'What are the speakers discussing?', int $correctChoice = 0): array
    {
        return [
            'prompt' => $prompt,
            'explanation' => 'Clear rationale for the correct answer.',
            'correct_choice' => $correctChoice,
            'choices' => [
                'A project deadline',
                'A new office policy',
                'A client presentation',
                'A vacation schedule',
            ],
        ];
    }

    /**
     * Helper to create an incomplete question payload.
     */
    protected function incompleteSlotPayload(): array
    {
        return [
            'prompt' => '',
            'explanation' => '',
            'correct_choice' => 0,
            'choices' => ['', '', '', ''],
        ];
    }

    // ==========================================
    // TESTS 01 - 04: MODEL COMPLETENESS LOGIC
    // ==========================================

    public function test_01_question_is_complete_child_returns_true_when_prompt_choices_and_correct_answer_exist(): void
    {
        $q = Question::create([
            'type' => 'multiple_choice',
            'prompt' => 'Where will the event take place?',
            'part_number' => 3,
            'section' => 'listening',
            'difficulty' => 'medium',
        ]);

        $labels = ['A', 'B', 'C', 'D'];
        foreach (['Main Hall', 'Conference Room', 'Hotel Ballroom', 'Cafeteria'] as $idx => $text) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label' => $labels[$idx],
                'content' => $text,
                'is_correct' => $idx === 1,
            ]);
        }

        $this->assertTrue($q->isCompleteChild());
    }

    public function test_02_question_is_complete_child_returns_false_when_prompt_is_empty_or_choices_less_than_four(): void
    {
        $qEmpty = Question::create([
            'type' => 'multiple_choice',
            'prompt' => '',
            'part_number' => 3,
            'section' => 'listening',
        ]);

        $this->assertFalse($qEmpty->isCompleteChild());

        $q3Choices = Question::create([
            'type' => 'multiple_choice',
            'prompt' => 'Valid prompt here?',
            'part_number' => 3,
            'section' => 'listening',
        ]);
        QuestionChoice::create(['question_id' => $q3Choices->id, 'label' => 'A', 'content' => 'Opt 1', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q3Choices->id, 'label' => 'B', 'content' => 'Opt 2', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $q3Choices->id, 'label' => 'C', 'content' => 'Opt 3', 'is_correct' => false]);

        $this->assertFalse($q3Choices->isCompleteChild());
    }

    public function test_03_audio_group_is_complete_returns_true_when_audio_exists_and_three_questions_complete(): void
    {
        $ag = AudioGroup::create([
            'test_id' => $this->test->id,
            'media_asset_id' => $this->audioAsset->id,
            'group_type' => 'conversation',
            'part_number' => 3,
            'title' => 'Complete Conversation',
        ]);

        $labels = ['A', 'B', 'C', 'D'];
        for ($i = 0; $i < 3; $i++) {
            $q = Question::create([
                'audio_group_id' => $ag->id,
                'type' => 'multiple_choice',
                'prompt' => "Child Question {$i}",
                'part_number' => 3,
                'section' => 'listening',
            ]);
            for ($c = 0; $c < 4; $c++) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => $labels[$c],
                    'content' => "Choice {$c}",
                    'is_correct' => $c === 0,
                ]);
            }
        }

        $ag->refresh();
        $this->assertEquals(3, $ag->complete_questions_count);
        $this->assertTrue($ag->isComplete());
        $this->assertEquals('complete', $ag->authoring_status);
        $this->assertTrue($ag->isValidGroup());
    }

    public function test_04_audio_group_is_complete_returns_false_when_complete_questions_count_less_than_three(): void
    {
        $ag = AudioGroup::create([
            'test_id' => $this->test->id,
            'media_asset_id' => $this->audioAsset->id,
            'group_type' => 'talk',
            'part_number' => 4,
            'title' => 'Draft Talk Group',
        ]);

        $labels = ['A', 'B', 'C', 'D'];
        $q = Question::create([
            'audio_group_id' => $ag->id,
            'type' => 'multiple_choice',
            'prompt' => "Only Question 1",
            'part_number' => 4,
            'section' => 'listening',
        ]);
        for ($c = 0; $c < 4; $c++) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label' => $labels[$c],
                'content' => "Choice {$c}",
                'is_correct' => $c === 0,
            ]);
        }

        $ag->refresh();
        $this->assertEquals(1, $ag->complete_questions_count);
        $this->assertFalse($ag->isComplete());
        $this->assertEquals('draft', $ag->authoring_status);
        $this->assertFalse($ag->isValidGroup());
    }

    // ==========================================
    // TESTS 05 - 07: VIEW & MODAL RENDERING
    // ==========================================

    public function test_05_modal_renders_exactly_three_fixed_question_slots(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Question 1 of 3');
        $response->assertSee('Question 2 of 3');
        $response->assertSee('Question 3 of 3');
        $response->assertDontSee('Question 4 of 3');
    }

    public function test_06_modal_does_not_have_dynamic_add_or_remove_slot_controls(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertDontSee('+ Add Question Slot');
        $response->assertDontSee('Remove Slot');
    }

    public function test_07_modal_header_displays_shared_stimulus_and_progressive_save_notice(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('TOEIC Part 3/4 uses exactly 3 questions per audio group.');
        $response->assertSee('Progress: 0 / 3 Complete');
    }

    // ==========================================
    // TESTS 08 - 14: DRAFT SAVE WORKFLOWS
    // ==========================================

    public function test_08_creating_audio_group_with_0_of_3_questions_saves_draft_when_audio_attached(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Empty Draft Group',
            'questions' => [
                0 => $this->incompleteSlotPayload(),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('audio_groups', [
            'test_id' => $this->test->id,
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Empty Draft Group',
        ]);

        $ag = AudioGroup::where('title', 'Empty Draft Group')->first();
        $this->assertNotNull($ag);
        $this->assertEquals(0, $ag->complete_questions_count);
        $this->assertFalse($ag->isComplete());
    }

    public function test_09_creating_audio_group_with_1_of_3_questions_persists_one_question_and_draft_status(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => '1 of 3 Completed Group',
            'questions' => [
                0 => $this->validSlotPayload('Where does this conversation take place?'),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $ag = AudioGroup::where('title', '1 of 3 Completed Group')->first();
        $this->assertNotNull($ag);
        $this->assertEquals(1, $ag->complete_questions_count);
        $this->assertFalse($ag->isComplete());
        $this->assertCount(1, $ag->questions);
        $this->assertDatabaseHas('test_questions', [
            'test_section_id' => $this->part3Section->id,
            'question_id' => $ag->questions->first()->id,
        ]);
    }

    public function test_10_creating_audio_group_with_2_of_3_questions_persists_two_questions_and_draft_status(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => '2 of 3 Completed Group',
            'questions' => [
                0 => $this->validSlotPayload('Question 1 stem'),
                1 => $this->validSlotPayload('Question 2 stem'),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $ag = AudioGroup::where('title', '2 of 3 Completed Group')->first();
        $this->assertNotNull($ag);
        $this->assertEquals(2, $ag->complete_questions_count);
        $this->assertFalse($ag->isComplete());
        $this->assertCount(2, $ag->questions);
    }

    public function test_11_creating_audio_group_with_3_of_3_questions_marks_group_complete(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Full 3 of 3 Group',
            'questions' => [
                0 => $this->validSlotPayload('Question 1 prompt'),
                1 => $this->validSlotPayload('Question 2 prompt'),
                2 => $this->validSlotPayload('Question 3 prompt'),
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $ag = AudioGroup::where('title', 'Full 3 of 3 Group')->first();
        $this->assertNotNull($ag);
        $this->assertEquals(3, $ag->complete_questions_count);
        $this->assertTrue($ag->isComplete());
        $this->assertEquals('complete', $ag->authoring_status);
        $this->assertCount(3, $ag->questions);
    }

    public function test_12_saving_audio_group_without_shared_audio_fails_validation(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => null,
            'audio_url' => null,
            'title' => 'No Audio Group',
            'questions' => [
                0 => $this->validSlotPayload(),
                1 => $this->validSlotPayload(),
                2 => $this->validSlotPayload(),
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertSessionHasErrors(['media_asset_id']);
        $this->assertDatabaseMissing('audio_groups', ['title' => 'No Audio Group']);
    }

    public function test_13_saving_draft_group_displays_draft_progress_in_session_status(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Draft Message Test',
            'questions' => [
                0 => $this->validSlotPayload('Q1 stem'),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('status', function ($val) {
            return str_contains(strtolower($val), 'draft') && str_contains($val, '1/3 Complete');
        });
    }

    public function test_14_saving_complete_group_displays_complete_in_session_status(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Complete Message Test',
            'questions' => [
                0 => $this->validSlotPayload('Q1 stem'),
                1 => $this->validSlotPayload('Q2 stem'),
                2 => $this->validSlotPayload('Q3 stem'),
            ],
        ];

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('status', function ($val) {
            return str_contains($val, '3/3 Complete');
        });
    }

    // ==========================================
    // TESTS 15 - 16: EDITING & ID PRESERVATION
    // ==========================================

    public function test_15_editing_draft_group_preserves_existing_question_ids(): void
    {
        // 1. Create draft with Q1
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Original Draft Group',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Original Q1'),
            ],
        ]);

        $originalQ1 = $ag->questions->first();
        $this->assertNotNull($originalQ1);
        $originalQ1Id = $originalQ1->id;

        // 2. Edit group via PUT route: update Q1, add Q2 and Q3
        $updatePayload = [
            'title' => 'Updated Complete Group',
            'media_asset_id' => $this->audioAsset->id,
            'questions' => [
                0 => array_merge($this->validSlotPayload('Updated Q1 Stem'), ['id' => $originalQ1Id]),
                1 => $this->validSlotPayload('New Q2 Stem'),
                2 => $this->validSlotPayload('New Q3 Stem'),
            ],
        ];

        $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), $updatePayload);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));

        $ag->refresh();
        $this->assertEquals(3, $ag->complete_questions_count);
        $this->assertTrue($ag->isComplete());

        // Verify original Q1 ID is preserved and prompt was updated
        $updatedQ1 = Question::find($originalQ1Id);
        $this->assertNotNull($updatedQ1);
        $this->assertEquals('Updated Q1 Stem', $updatedQ1->prompt);
        $this->assertEquals($ag->id, $updatedQ1->audio_group_id);
    }

    public function test_16_editing_draft_group_preserves_question_order_and_test_question_linkages(): void
    {
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Draft with 2 Qs',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Q1'),
                1 => $this->validSlotPayload('Q2'),
            ],
        ]);

        $qIds = $ag->questions->pluck('id')->toArray();
        $this->assertCount(2, $qIds);

        // Add 3rd question
        $updatePayload = [
            'title' => 'Now Complete Group',
            'media_asset_id' => $this->audioAsset->id,
            'questions' => [
                0 => array_merge($this->validSlotPayload('Q1 Updated'), ['id' => $qIds[0]]),
                1 => array_merge($this->validSlotPayload('Q2 Updated'), ['id' => $qIds[1]]),
                2 => $this->validSlotPayload('Q3 Added'),
            ],
        ];

        $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), $updatePayload);

        $ag->refresh();
        $this->assertEquals(3, $ag->complete_questions_count);
        $testQuestions = TestQuestion::where('test_section_id', $this->part3Section->id)->orderBy('order')->get();
        $this->assertCount(3, $testQuestions);
        $this->assertEquals($qIds[0], $testQuestions[0]->question_id);
        $this->assertEquals($qIds[1], $testQuestions[1]->question_id);
    }

    // ==========================================
    // TESTS 17 - 22: STRICT VALIDATION ASSISTANT
    // ==========================================

    public function test_17_validation_assistant_fails_when_audio_group_has_0_complete_questions(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Empty Group',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [],
        ]);

        $res = $service->validateAssessment($this->test);
        $this->assertFalse($res['is_valid']);
        $this->assertNotEmpty($res['errors']);
        $this->assertTrue(collect($res['errors'])->contains(fn($e) => str_contains($e, '0/3 complete')));
    }

    public function test_18_validation_assistant_fails_when_audio_group_has_1_complete_question(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => '1-Question Group',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Q1'),
            ],
        ]);

        $res = $service->validateAssessment($this->test);
        $this->assertFalse($res['is_valid']);
        $this->assertTrue(collect($res['errors'])->contains(fn($e) => str_contains($e, '1/3 complete')));
    }

    public function test_19_validation_assistant_fails_when_audio_group_has_2_complete_questions(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => '2-Question Group',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Q1'),
                1 => $this->validSlotPayload('Q2'),
            ],
        ]);

        $res = $service->validateAssessment($this->test);
        $this->assertFalse($res['is_valid']);
        $this->assertTrue(collect($res['errors'])->contains(fn($e) => str_contains($e, '2/3 complete')));
    }

    public function test_20_validation_assistant_passes_audio_group_check_when_group_is_3_of_3(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Complete Group',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Q1'),
                1 => $this->validSlotPayload('Q2'),
                2 => $this->validSlotPayload('Q3'),
            ],
        ]);

        $res = $service->validateAssessment($this->test);
        $hasAgError = collect($res['errors'])->contains(fn($e) => str_contains($e, 'Audio Group'));
        $this->assertFalse($hasAgError);
    }

    public function test_21_submit_for_review_is_blocked_when_audio_group_is_incomplete(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Incomplete for Submission',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Q1'),
            ],
        ]);

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.resubmit', $this->test->id));
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('error');

        $this->test->refresh();
        $this->assertEquals('draft', $this->test->status);
    }

    public function test_22_submit_for_review_succeeds_when_all_assessment_rules_including_audio_groups_pass(): void
    {
        $this->test->update(['test_type' => 'general']);
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Valid Group for Submission',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Q1'),
                1 => $this->validSlotPayload('Q2'),
                2 => $this->validSlotPayload('Q3'),
            ],
        ]);

        // Remove empty part 4 section to avoid empty section error
        $this->part4Section->delete();

        $response = $this->actingAs($this->teacher)->post(route('teacher.tests.resubmit', $this->test->id));
        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $response->assertSessionHas('status');

        $this->test->refresh();
        $this->assertEquals('pending_approval', $this->test->status);
    }

    // ==========================================
    // TESTS 23 - 30: SPECIFIC CAPABILITIES
    // ==========================================

    public function test_23_part_3_creates_conversation_group_type(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Part 3 Test',
            'questions' => [
                0 => $this->validSlotPayload(),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $this->assertDatabaseHas('audio_groups', [
            'part_number' => 3,
            'group_type' => 'conversation',
        ]);
    }

    public function test_24_part_4_creates_talk_group_type(): void
    {
        $payload = [
            'test_section_id' => $this->part4Section->id,
            'part_number' => 4,
            'group_type' => 'talk',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Part 4 Test',
            'questions' => [
                0 => $this->validSlotPayload(),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $this->assertDatabaseHas('audio_groups', [
            'part_number' => 4,
            'group_type' => 'talk',
        ]);
    }

    public function test_25_auto_difficulty_is_computed_for_child_questions_in_audio_group(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Auto Difficulty Test',
            'questions' => [
                0 => $this->validSlotPayload('A very long and sophisticated stem prompt asking for a detailed inference.'),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $q = Question::where('prompt', 'like', '%sophisticated stem%')->first();
        $this->assertNotNull($q);
        $this->assertNotNull($q->difficulty);
    }

    public function test_26_update_audio_group_route_handles_put_requests(): void
    {
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Initial Title',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [],
        ]);

        $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), [
            'title' => 'Updated Title',
            'media_asset_id' => $this->audioAsset->id,
            'questions' => [],
        ]);

        $response->assertRedirect(route('teacher.tests.show', $this->test->id));
        $this->assertDatabaseHas('audio_groups', [
            'id' => $ag->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_27_unauthorized_teacher_cannot_update_audio_group_of_another_teacher(): void
    {
        $otherTeacher = User::factory()->create(['status' => 'active']);
        $otherTeacher->assignRole('teacher');

        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Protected Group',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [],
        ]);

        $response = $this->actingAs($otherTeacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), [
            'title' => 'Malicious Update',
            'media_asset_id' => $this->audioAsset->id,
            'questions' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_28_updating_audio_group_title_and_script_persists(): void
    {
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Initial',
            'audio_script' => 'Initial script',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [],
        ]);

        $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), [
            'title' => 'Revised Title',
            'audio_script' => 'Revised conversation transcript',
            'media_asset_id' => $this->audioAsset->id,
            'questions' => [],
        ]);

        $this->assertDatabaseHas('audio_groups', [
            'id' => $ag->id,
            'title' => 'Revised Title',
            'audio_script' => 'Revised conversation transcript',
        ]);
    }

    public function test_29_updating_audio_group_media_asset_persists(): void
    {
        $newAsset = MediaAsset::create([
            'uploaded_by' => $this->teacher->id,
            'type' => 'audio',
            'filename' => 'new_talk.mp3',
            'original_name' => 'new_talk.mp3',
            'path' => 'audio/new_talk.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 2048000,
            'title' => 'New Talk Audio',
        ]);

        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part4Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Talk Group',
            'group_type' => 'talk',
            'part_number' => 4,
            'questions' => [],
        ]);

        $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), [
            'media_asset_id' => $newAsset->id,
            'title' => 'Talk Group with New Audio',
            'questions' => [],
        ]);

        $this->assertDatabaseHas('audio_groups', [
            'id' => $ag->id,
            'media_asset_id' => $newAsset->id,
        ]);
    }

    public function test_30_updating_child_question_prompt_and_choices_updates_existing_choices(): void
    {
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Choice Update Test',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Old Prompt', 0),
            ],
        ]);

        $q = $ag->questions->first();
        $qId = $q->id;

        $newChoices = ['Choice Alpha', 'Choice Beta', 'Choice Gamma', 'Choice Delta'];
        $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), [
            'media_asset_id' => $this->audioAsset->id,
            'questions' => [
                0 => [
                    'id' => $qId,
                    'prompt' => 'New Prompt',
                    'explanation' => 'New Explanation',
                    'correct_choice' => 2,
                    'choices' => $newChoices,
                ],
            ],
        ]);

        $q->refresh();
        $this->assertEquals('New Prompt', $q->prompt);
        $this->assertDatabaseHas('question_choices', [
            'question_id' => $qId,
            'content' => 'Choice Gamma',
            'is_correct' => true,
        ]);
    }

    // ==========================================
    // TESTS 31 - 38: RENDERING, LEAK PREVENTION & LIFECYCLE
    // ==========================================

    public function test_31_audio_group_card_renders_progress_and_incomplete_badge_when_draft(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Draft Incomplete Unit',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Prompt 1'),
            ],
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Progress: 1 / 3 Complete');
        $response->assertSee('INCOMPLETE');
    }

    public function test_32_audio_group_card_renders_valid_badge_when_complete(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Complete Unit',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Prompt 1'),
                1 => $this->validSlotPayload('Prompt 2'),
                2 => $this->validSlotPayload('Prompt 3'),
            ],
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Progress: 3 / 3 Complete');
        $response->assertSee('VALID');
    }

    public function test_33_preview_audio_button_is_accessible_on_draft_audio_group(): void
    {
        $service = app(TestBuilderService::class);
        $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Draft Preview Unit',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [],
        ]);

        $response = $this->actingAs($this->teacher)->get(route('teacher.tests.show', $this->test->id));
        $response->assertOk();
        $response->assertSee('Preview Audio');
    }

    public function test_34_incomplete_slots_do_not_create_orphaned_questions(): void
    {
        $payload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'No Orphaned Qs Test',
            'questions' => [
                0 => $this->validSlotPayload('Valid Q1'),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $payload);
        $ag = AudioGroup::where('title', 'No Orphaned Qs Test')->first();

        $this->assertEquals(1, Question::where('audio_group_id', $ag->id)->count());
    }

    public function test_35_group_level_audio_resolution_remains_available(): void
    {
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Audio Delivery Test',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Delivery Q1'),
            ],
        ]);

        $q = $ag->questions->first();
        $this->assertEquals($ag->id, $q->audio_group_id);
        $this->assertNotNull($q->audioGroup);
        $this->assertEquals($this->audioAsset->id, $q->audioGroup->media_asset_id);
    }

    public function test_36_candidate_preview_shared_audio_link_for_valid_child_questions(): void
    {
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Preview Audio Link Test',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Preview Q1'),
            ],
        ]);

        $q = $ag->questions->first();
        $this->assertNotNull($q->audioGroup->getEffectiveAudioUrl());
    }

    public function test_37_database_transaction_rolls_back_if_error_occurs_during_update(): void
    {
        $service = app(TestBuilderService::class);
        $ag = $service->createAudioGroup($this->part3Section, [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Rollback Test Initial',
            'group_type' => 'conversation',
            'part_number' => 3,
            'questions' => [
                0 => $this->validSlotPayload('Initial Q1'),
            ],
        ]);

        $response = $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), [
            'media_asset_id' => '01nonexistentmediaassetid',
            'title' => 'Invalid Update Title',
            'questions' => [],
        ]);

        $response->assertSessionHasErrors(['media_asset_id']);
        $ag->refresh();
        $this->assertEquals('Rollback Test Initial', $ag->title);
    }

    public function test_38_full_lifecycle_progressive_draft_to_complete_and_submission(): void
    {
        // Step 1: Create Draft (1/3 Complete)
        $createPayload = [
            'test_section_id' => $this->part3Section->id,
            'part_number' => 3,
            'group_type' => 'conversation',
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Progressive Lifecycle Group',
            'questions' => [
                0 => $this->validSlotPayload('Lifecycle Q1'),
                1 => $this->incompleteSlotPayload(),
                2 => $this->incompleteSlotPayload(),
            ],
        ];

        $this->actingAs($this->teacher)->post(route('teacher.tests.create-audio-group', $this->test->id), $createPayload);
        $ag = AudioGroup::where('title', 'Progressive Lifecycle Group')->first();
        $this->assertNotNull($ag);
        $this->assertEquals(1, $ag->complete_questions_count);
        $this->assertFalse($ag->isComplete());

        $q1Id = $ag->questions->first()->id;

        // Step 2: Edit to (2/3 Complete)
        $update2Payload = [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Progressive Lifecycle Group (2 of 3)',
            'questions' => [
                0 => array_merge($this->validSlotPayload('Lifecycle Q1 Updated'), ['id' => $q1Id]),
                1 => $this->validSlotPayload('Lifecycle Q2 Added'),
                2 => $this->incompleteSlotPayload(),
            ],
        ];
        $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), $update2Payload);

        $ag->refresh();
        $this->assertEquals(2, $ag->complete_questions_count);
        $this->assertFalse($ag->isComplete());
        $q2Id = $ag->questions->sortBy('created_at')->values()->get(1)->id;

        // Step 3: Edit to (3/3 Complete)
        $update3Payload = [
            'media_asset_id' => $this->audioAsset->id,
            'title' => 'Progressive Lifecycle Group (3 of 3 Complete)',
            'questions' => [
                0 => array_merge($this->validSlotPayload('Lifecycle Q1 Final'), ['id' => $q1Id]),
                1 => array_merge($this->validSlotPayload('Lifecycle Q2 Final'), ['id' => $q2Id]),
                2 => $this->validSlotPayload('Lifecycle Q3 Added Final'),
            ],
        ];
        $this->actingAs($this->teacher)->put(route('teacher.tests.update-audio-group', [
            'test' => $this->test->id,
            'audioGroup' => $ag->id,
        ]), $update3Payload);

        $ag->refresh();
        $this->assertEquals(3, $ag->complete_questions_count);
        $this->assertTrue($ag->isComplete());

        // Step 4: Submission succeeds
        $this->test->update(['test_type' => 'general']);
        $this->part4Section->delete();
        $submitRes = $this->actingAs($this->teacher)->post(route('teacher.tests.resubmit', $this->test->id));
        $submitRes->assertRedirect(route('teacher.tests.show', $this->test->id));
        $this->test->refresh();
        $this->assertEquals('pending_approval', $this->test->status);
    }
}
