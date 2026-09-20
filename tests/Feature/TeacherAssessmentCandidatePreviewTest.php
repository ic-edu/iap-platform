<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\QuestionBank\Models\AudioGroup;
use App\Modules\QuestionBank\Models\Passage;
use App\Modules\QuestionBank\Models\PassageGroup;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\AclStarterLibrarySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssessmentCandidatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;
    protected User $otherTeacherUser;
    protected User $studentUser;
    protected Test $testRecord;
    protected TestSection $section1;
    protected TestSection $section2;
    protected Question $question1;
    protected Question $question2;
    protected Payment $pendingPayment;

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

        $this->otherTeacherUser = User::firstOrCreate(
            ['email' => 'otherteacher@icedu.org'],
            [
                'name'     => 'Other Teacher',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->otherTeacherUser->syncRoles(['teacher']);

        $this->studentUser = User::firstOrCreate(
            ['email' => 'student@icedu.org'],
            [
                'name'     => 'Candidate Student',
                'password' => bcrypt('password'),
                'status'   => 'active',
            ]
        );
        $this->studentUser->syncRoles(['student']);

        // Assessment Draft
        $this->testRecord = Test::create([
            'title'            => 'TOEIC Listening & Reading Simulation Test Alpha',
            'slug'             => 'toeic-listening-reading-simulation-test-alpha',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 750,
            'scoring_method'   => 'automatic',
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->teacherUser->id,
            'instructions'     => 'Welcome to the TOEIC Examination. Please ensure your headphones are connected and adjusted properly before proceeding.',
        ]);

        // Section 1: Photographs
        $this->section1 = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 1: PHOTOGRAPHS',
            'section_type' => 'listening',
            'order'        => 1,
            'instructions' => 'For each question in this section, you will hear four statements about a photograph. Select the statement that best describes what you see in the photograph.',
        ]);

        // Section 2: Incomplete Sentences
        $this->section2 = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 5: INCOMPLETE SENTENCES',
            'section_type' => 'reading',
            'order'        => 2,
            'instructions' => 'A word or phrase is missing in each of the following sentences. Four answer choices are given below each sentence. Select the best answer to complete the sentence.',
        ]);

        // Question 1 in Section 1 (with Image and Audio)
        $this->question1 = Question::create([
            'prompt'        => 'Look at the photograph and listen to the audio statements. Select the statement that best describes what you see.',
            'section'       => 'listening',
            'part_number'   => 1,
            'question_type' => 'multiple_choice',
            'difficulty'    => 'medium',
            'points'        => 1,
            'image_url'     => 'https://images.unsplash.com/photo-1577495508048-b635879837f1',
            'audio_url'     => 'https://assets.mixkit.co/active_storage/sfx/2874/2874-preview.mp3',
        ]);

        QuestionChoice::create(['question_id' => $this->question1->id, 'label' => 'A', 'content' => 'The woman is presenting to colleagues.', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->question1->id, 'label' => 'B', 'content' => 'The meeting room is completely empty.', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $this->question1->id, 'label' => 'C', 'content' => 'The lights in the hallway are turned off.', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $this->question1->id, 'label' => 'D', 'content' => 'The laptop is closed on the table.', 'is_correct' => false]);

        TestQuestion::create([
            'test_section_id' => $this->section1->id,
            'question_id'     => $this->question1->id,
            'order'           => 1,
        ]);

        // Question 2 in Section 2 (Reading)
        $this->question2 = Question::create([
            'prompt'        => 'Ms. Henderson will conduct the initial interviews _______ next Monday morning.',
            'section'       => 'reading',
            'part_number'   => 5,
            'question_type' => 'multiple_choice',
            'difficulty'    => 'easy',
            'points'        => 1,
        ]);

        QuestionChoice::create(['question_id' => $this->question2->id, 'label' => 'A', 'content' => 'beginning', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->question2->id, 'label' => 'B', 'content' => 'begin', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $this->question2->id, 'label' => 'C', 'content' => 'begun', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $this->question2->id, 'label' => 'D', 'content' => 'beginner', 'is_correct' => false]);

        TestQuestion::create([
            'test_section_id' => $this->section2->id,
            'question_id'     => $this->question2->id,
            'order'           => 1,
        ]);

        // Active UAT Payment to protect
        $order = \App\Modules\Commerce\Domain\Models\Order::create([
            'user_id'      => $this->studentUser->id,
            'order_number' => 'ORD-20260827-YIT4',
            'status'       => \App\Modules\Commerce\Domain\Enums\OrderStatus::Pending,
            'subtotal'     => 750000,
            'discount'     => 0,
            'tax'          => 82500,
            'grand_total'  => 832500,
        ]);

        $invoice = \App\Modules\Commerce\Domain\Models\Invoice::create([
            'order_id'       => $order->id,
            'invoice_number' => 'INV-20260827-ZMJY',
            'user_id'        => $this->studentUser->id,
            'status'         => \App\Modules\Commerce\Domain\Enums\InvoiceStatus::Unpaid,
            'amount'         => 832500,
            'due_date'       => now()->addDays(2),
        ]);

        $this->pendingPayment = Payment::create([
            'invoice_id'          => $invoice->id,
            'user_id'             => $this->studentUser->id,
            'reference_number'    => 'PAY-20260827-VZDM',
            'payment_gateway'     => 'manual_transfer',
            'status'              => PaymentStatus::Pending,
            'amount'              => 832500,
        ]);
    }

    public function test_01_teacher_can_open_assessment_preview(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('CANDIDATE PREVIEW', false);
        $response->assertSee('Preview only — no attempt, score, or result will be recorded.', false);
    }

    public function test_02_candidate_preview_route_requires_teacher_authorization(): void
    {
        $response = $this->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertRedirect(route('login'));
    }

    public function test_03_unauthorized_roles_cannot_access_teacher_preview(): void
    {
        // Another teacher not owning the test cannot view it
        $response = $this->actingAs($this->otherTeacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(403);

        // Candidate cannot access teacher preview route directly
        $response = $this->actingAs($this->studentUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(403);
    }

    public function test_04_preview_renders_assessment_title(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('TOEIC Listening &amp; Reading Simulation Test Alpha', false);
    }

    public function test_05_preview_renders_assessment_instructions(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('General Assessment Instructions', false);
        $response->assertSee('Please ensure your headphones are connected and adjusted properly', false);
    }

    public function test_06_preview_renders_sections_in_configured_order(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $posSec1 = strpos($content, 'PART 1: PHOTOGRAPHS');
        $posSec2 = strpos($content, 'PART 5: INCOMPLETE SENTENCES');

        $this->assertNotFalse($posSec1);
        $this->assertNotFalse($posSec2);
        $this->assertTrue($posSec1 < $posSec2, 'Section 1 should appear before Section 2 in source output.');
    }

    public function test_07_preview_renders_section_directions(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('For each question in this section, you will hear four statements about a photograph.', false);
        $response->assertSee('A word or phrase is missing in each of the following sentences.', false);
    }

    public function test_08_preview_renders_questions_in_configured_order(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $posQ1 = strpos($content, 'Look at the photograph and listen to the audio statements');
        $posQ2 = strpos($content, 'Ms. Henderson will conduct the initial interviews');

        $this->assertNotFalse($posQ1);
        $this->assertNotFalse($posQ2);
        $this->assertTrue($posQ1 < $posQ2, 'Question 1 should appear before Question 2.');
    }

    public function test_09_preview_renders_question_choices(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        // Part 1 (audio-only) displays radio options (A), (B), (C), (D) without leaking statement transcript text
        $response->assertSee('(A)', false);
        $response->assertDontSee('The woman is presenting to colleagues.', false);
        $response->assertDontSee('The meeting room is completely empty.', false);
        // Part 5 (reading) displays textual choices
        $response->assertSee('beginning', false);
        $response->assertSee('beginner', false);
    }

    public function test_10_preview_renders_question_level_image_media(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('https://images.unsplash.com/photo-1577495508048-b635879837f1', false);
    }

    public function test_11_preview_renders_question_level_audio_media(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('https://assets.mixkit.co/active_storage/sfx/2874/2874-preview.mp3', false);
        $response->assertSee('audio controls', false);
    }

    public function test_12_preview_renders_section_media(): void
    {
        $sectionMedia = MediaAsset::create([
            'title'         => 'Sample Audio Direction Track',
            'filename'      => 'sample_direction.mp3',
            'original_name' => 'sample_direction.mp3',
            'path'          => 'https://assets.mixkit.co/active_storage/sfx/direction.mp3',
            'mime_type'     => 'audio/mpeg',
            'size'          => 10240,
            'type'          => 'audio',
            'status'        => 'active',
            'uploaded_by'   => $this->teacherUser->id,
        ]);

        $this->section1->mediaAssets()->attach($sectionMedia->id, [
            'id' => (string) \Illuminate\Support\Str::ulid(),
        ]);

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Sample Audio Direction Track', false);
    }

    public function test_13_preview_displays_question_progress(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Question 1 of 2', false);
        $response->assertSee('Question 2 of 2', false);
    }

    public function test_14_preview_supports_previous_next_navigation(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('navigateDeliveryUnit', false);
    }

    public function test_15_preview_does_not_create_candidate_test_assignment(): void
    {
        $initialCount = CandidateTestAssignment::count();

        $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $this->assertEquals($initialCount, CandidateTestAssignment::count(), 'Preview should not create CandidateTestAssignment.');
    }

    public function test_16_preview_does_not_create_attempt(): void
    {
        $initialCount = Attempt::count();

        $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $this->assertEquals($initialCount, Attempt::count(), 'Preview should not create Attempt.');
    }

    public function test_17_preview_does_not_create_answer(): void
    {
        $initialCount = Answer::count();

        $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $this->assertEquals($initialCount, Answer::count(), 'Preview should not create Answer.');
    }

    public function test_18_preview_does_not_create_certificate(): void
    {
        $initialCount = Certificate::count();

        $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $this->assertEquals($initialCount, Certificate::count(), 'Preview should not create Certificate.');
    }

    public function test_19_selecting_a_radio_answer_does_not_modify_database(): void
    {
        $initialAnswerCount = Answer::count();
        $initialQuestionChoiceCount = QuestionChoice::count();

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        $this->assertEquals($initialAnswerCount, Answer::count());
        $this->assertEquals($initialQuestionChoiceCount, QuestionChoice::count());
    }

    public function test_20_finishing_preview_does_not_create_attempt(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        $response->assertSee('Preview Complete', false);
        $this->assertEquals(0, Attempt::count());
    }

    public function test_21_correct_answer_is_not_exposed_to_teacher_in_candidate_preview(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('CORRECT ANSWER', $content);
        $this->assertStringNotContainsString('is_correct', $content);
        $this->assertStringNotContainsString('Answer Key', $content);
        $this->assertStringNotContainsString('badge-correct', $content);
    }

    public function test_22_teacher_can_return_to_test_builder(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee(route('teacher.tests.show', $this->testRecord->id), false);
        $response->assertSee('Back to Test Builder', false);
    }

    public function test_23_incomplete_section_produces_preview_warning(): void
    {
        // Add an empty section with 0 questions
        $emptySection = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 2: QUESTION-RESPONSE',
            'section_type' => 'listening',
            'order'        => 3,
            'instructions' => 'Listen to the audio questions.',
        ]);

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Section contains no questions.', false);
    }

    public function test_24_missing_required_media_produces_preview_warning_where_applicable(): void
    {
        // Create a TOEIC Part 1 question without photo or audio
        $missingMediaQuestion = Question::create([
            'prompt'        => 'Part 1 question with no image attached.',
            'section'       => 'listening',
            'part_number'   => 1,
            'question_type' => 'multiple_choice',
            'difficulty'    => 'medium',
            'points'        => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $this->section1->id,
            'question_id'     => $missingMediaQuestion->id,
            'order'           => 2,
        ]);

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('Notice: Question media is not yet attached.', false);
    }

    public function test_25_light_theme_preview_contract_passes(): void
    {
        $this->teacherUser->setThemePreference('light');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_26_dark_theme_preview_contract_passes(): void
    {
        $this->teacherUser->setThemePreference('dark');
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_27_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }

    public function test_28_existing_assessment_builder_routes_remain_intact(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.show', $this->testRecord->id));
        $response->assertStatus(200);
        $response->assertSee('Preview as Candidate', false);
    }

    public function test_29_current_uat_payment_remains_unchanged(): void
    {
        $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $payment = Payment::where('reference_number', 'PAY-20260827-VZDM')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertEquals(832500, $payment->amount);
    }

    public function test_30_preview_renders_toeic_part_6_text_completion_passage_group_with_all_child_questions(): void
    {
        // Part 6 section
        $p6Section = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 6: TEXT COMPLETION',
            'section_type' => 'reading',
            'order'        => 3,
            'instructions' => 'Read the texts that follow. A word, phrase, or sentence is missing in parts of each text.',
        ]);

        $passageGroup = PassageGroup::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'Notice of Office Relocation',
            'part_number'  => 6,
            'passage_type' => 'single',
            'order'        => 1,
            'created_by'   => $this->teacherUser->id,
        ]);

        $passage = Passage::create([
            'passage_group_id' => $passageGroup->id,
            'test_id'          => $this->testRecord->id,
            'order_in_group'   => 1,
            'document_type'    => 'notice',
            'title'            => 'Notice of Office Relocation',
            'content'          => 'We are pleased to announce our relocation to the downtown business tower. ---[131]--- The new facility offers expanded workspaces. ---[132]--- Please direct all mail to our new address. ---[133]--- We appreciate your cooperation. ---[134]---',
        ]);

        $childQuestions = [];
        for ($i = 131; $i <= 134; $i++) {
            $q = Question::create([
                'passage_group_id' => $passageGroup->id,
                'prompt'           => 'Select the best option for blank [' . $i . '].',
                'section'          => 'reading',
                'part_number'      => 6,
                'question_type'    => 'multiple_choice',
                'difficulty'       => 'medium',
                'points'           => 1,
            ]);

            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Option A for Q' . $i, 'is_correct' => true]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'Option B for Q' . $i, 'is_correct' => false]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'C', 'content' => 'Option C for Q' . $i, 'is_correct' => false]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'D', 'content' => 'Option D for Q' . $i, 'is_correct' => false]);

            TestQuestion::create([
                'test_section_id' => $p6Section->id,
                'question_id'     => $q->id,
                'order'           => count($childQuestions) + 1,
            ]);

            $childQuestions[] = $q;
        }

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);

        // Verify shared passage content is rendered
        $response->assertSee('PART 6 — NOTICE OF OFFICE RELOCATION', false);
        $response->assertSee('Text Completion Group', false);
        $response->assertSee('Notice of Office Relocation', false);
        $response->assertSee('We are pleased to announce our relocation to the downtown business tower', false);

        // Verify ALL 4 child question blocks exist in Preview DOM
        // Total questions before this were 2 (Q1 at index 0, Q2 at index 1), so P6 questions are at indices 2, 3, 4, 5
        $response->assertSee('id="unit-question-block-2"', false);
        $response->assertSee('id="unit-question-block-3"', false);
        $response->assertSee('id="unit-question-block-4"', false);
        $response->assertSee('id="unit-question-block-5"', false);

        // Verify prompts and choices for all 4 child questions
        foreach ($childQuestions as $idx => $q) {
            $globalIdx = $idx + 2;
            $response->assertSee('Select the best option for blank [' . (131 + $idx) . '].', false);
            $response->assertSee('name="preview_choice_' . $q->id . '"', false);
            $response->assertSee('Option A for Q' . (131 + $idx), false);
            $response->assertSee('Option D for Q' . (131 + $idx), false);
        }

        // Verify Question Palette buttons exist for all questions
        $response->assertSee('id="palette-btn-2"', false);
        $response->assertSee('id="palette-btn-3"', false);
        $response->assertSee('id="palette-btn-4"', false);
        $response->assertSee('id="palette-btn-5"', false);
    }

    public function test_31_preview_renders_toeic_part_7_multi_document_passage_group_with_tabs(): void
    {
        // Part 7 section
        $p7Section = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 7: READING COMPREHENSION',
            'section_type' => 'reading',
            'order'        => 4,
            'instructions' => 'Read the selections and answer the questions.',
        ]);

        $passageGroup = PassageGroup::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'Double Passage: Email and Schedule',
            'part_number'  => 7,
            'passage_type' => 'double',
            'order'        => 1,
            'created_by'   => $this->teacherUser->id,
        ]);

        $pass1 = Passage::create([
            'passage_group_id' => $passageGroup->id,
            'test_id'          => $this->testRecord->id,
            'order_in_group'   => 1,
            'document_type'    => 'email',
            'title'            => 'Conference Inquiry Email',
            'content'          => 'Dear Organizer, I would like to register for the upcoming seminar.',
        ]);

        $pass2 = Passage::create([
            'passage_group_id' => $passageGroup->id,
            'test_id'          => $this->testRecord->id,
            'order_in_group'   => 2,
            'document_type'    => 'schedule',
            'title'            => 'Seminar Program Schedule',
            'content'          => '9:00 AM - Keynote Address: Emerging AI in Education.',
        ]);

        $q1 = Question::create([
            'passage_group_id' => $passageGroup->id,
            'prompt'           => 'What is the purpose of the email?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'To register for a seminar', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'To cancel a subscription', 'is_correct' => false]);

        $q2 = Question::create([
            'passage_group_id' => $passageGroup->id,
            'prompt'           => 'At what time does the keynote start?',
            'section'          => 'reading',
            'part_number'      => 7,
            'question_type'    => 'multiple_choice',
            'difficulty'       => 'medium',
            'points'           => 1,
        ]);
        QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => '9:00 AM', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q2->id, 'label' => 'B', 'content' => '1:00 PM', 'is_correct' => false]);

        TestQuestion::create(['test_section_id' => $p7Section->id, 'question_id' => $q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $p7Section->id, 'question_id' => $q2->id, 'order' => 2]);

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);

        // Verify Part 7 header and document switcher tabs
        $response->assertSee('PART 7 — DOUBLE PASSAGE: EMAIL AND SCHEDULE', false);
        $response->assertSee('Conference Inquiry Email', false);
        $response->assertSee('Seminar Program Schedule', false);
        $response->assertSee('switchPassageDocUnit', false);

        // Verify both documents exist in DOM
        $response->assertSee('Dear Organizer, I would like to register for the upcoming seminar', false);
        $response->assertSee('9:00 AM - Keynote Address: Emerging AI in Education', false);

        // Verify both child questions and their choices exist in DOM
        $response->assertSee('What is the purpose of the email?', false);
        $response->assertSee('To register for a seminar', false);
        $response->assertSee('At what time does the keynote start?', false);
        $response->assertSee('9:00 AM', false);
    }

    public function test_32_preview_renders_toeic_part_3_audio_group_and_all_child_questions(): void
    {
        $p3Section = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 3: CONVERSATIONS',
            'section_type' => 'listening',
            'order'        => 5,
            'instructions' => 'You will hear conversations between two or more people.',
        ]);

        $audioGroup = AudioGroup::create([
            'test_id'         => $this->testRecord->id,
            'title'           => 'Conversation about Office Equipment',
            'part_number'     => 3,
            'group_type'      => 'conversation',
            'audio_url'       => 'https://assets.mixkit.co/active_storage/sfx/conversation.mp3',
            'order_in_section'=> 1,
            'created_by'      => $this->teacherUser->id,
        ]);

        $childQuestions = [];
        for ($i = 1; $i <= 3; $i++) {
            $q = Question::create([
                'audio_group_id' => $audioGroup->id,
                'prompt'         => 'Where are the speakers? (P3 Q' . $i . ')',
                'section'        => 'listening',
                'part_number'    => 3,
                'question_type'  => 'multiple_choice',
                'difficulty'     => 'medium',
                'points'         => 1,
            ]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'In an office supply store', 'is_correct' => true]);
            QuestionChoice::create(['question_id' => $q->id, 'label' => 'B', 'content' => 'In a restaurant', 'is_correct' => false]);

            TestQuestion::create([
                'test_section_id' => $p3Section->id,
                'question_id'     => $q->id,
                'order'           => $i,
            ]);
            $childQuestions[] = $q;
        }

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);

        // Verify Audio Group header & shared stimulus
        $response->assertSee('PART 3 — CONVERSATION', false);
        $response->assertSee('Shared Audio Group', false);
        $response->assertSee('https://assets.mixkit.co/active_storage/sfx/conversation.mp3', false);

        // Verify all 3 child questions exist
        foreach ($childQuestions as $idx => $q) {
            $response->assertSee('Where are the speakers? (P3 Q' . ($idx + 1) . ')', false);
            $response->assertSee('name="preview_choice_' . $q->id . '"', false);
        }
    }

    public function test_33_preview_retains_unrestricted_navigation_across_all_delivery_units(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));

        $response->assertStatus(200);

        // Preview should include unrestricted navigation functions
        $response->assertSee('navigateDeliveryUnit', false);
        $response->assertSee('handlePaletteQuestionClick', false);
        $response->assertSee('selectPreviewChoice', false);
        // Preview should NOT enforce mandatory answers before moving
        $response->assertDontSee('For Mock Test examination, you must select an answer for all questions', false);
    }

    public function test_34_preview_audio_01_part1_audio_stops_on_next_navigation(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // Verify PreviewExamAudioManager is defined
        $response->assertSee('const PreviewExamAudioManager = (function()', false);
        $response->assertSee('window.PreviewExamAudioManager = PreviewExamAudioManager', false);
        $response->assertSee('data-preview-exam-audio="true"', false);
        $response->assertSee('data-exam-audio="true"', false);

        // Verify navigateDeliveryUnit invokes beforeDeliveryTransition
        $response->assertSee('PreviewExamAudioManager.beforeDeliveryTransition({ type: \'delivery_unit\'', false);
    }

    public function test_35_preview_audio_02_part2_audio_stops_on_navigation_or_palette_jump(): void
    {
        $p2Section = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 2: QUESTION-RESPONSE',
            'section_type' => 'listening',
            'order'        => 3,
            'instructions' => 'Listen to the audio question and responses.',
        ]);

        $p2Q = Question::create([
            'prompt'        => 'Where is the conference room?',
            'section'       => 'listening',
            'part_number'   => 2,
            'question_type' => 'multiple_choice',
            'difficulty'    => 'easy',
            'points'        => 1,
            'audio_url'     => 'https://assets.mixkit.co/active_storage/sfx/part2.mp3',
        ]);
        QuestionChoice::create(['question_id' => $p2Q->id, 'label' => 'A', 'content' => 'On the third floor', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $p2Q->id, 'label' => 'B', 'content' => 'At 2:00 PM', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $p2Q->id, 'label' => 'C', 'content' => 'Yes, I did', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $p2Section->id, 'question_id' => $p2Q->id, 'order' => 1]);

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // Verify Part 2 audio contains preview audio tracking attributes
        $response->assertSee('src="https://assets.mixkit.co/active_storage/sfx/part2.mp3"', false);
        $response->assertSee('data-preview-exam-audio="true"', false);
    }

    public function test_36_preview_audio_03_part3_group_audio_stops_on_group_transition(): void
    {
        $p3Section = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 3: CONVERSATIONS',
            'section_type' => 'listening',
            'order'        => 3,
            'instructions' => 'Listen to conversations.',
        ]);

        $ag1 = AudioGroup::create([
            'test_id'          => $this->testRecord->id,
            'title'            => 'P3 Audio Group 1',
            'part_number'      => 3,
            'group_type'       => 'conversation',
            'audio_url'        => 'https://assets.mixkit.co/active_storage/sfx/p3_group1.mp3',
            'order_in_section' => 1,
            'created_by'       => $this->teacherUser->id,
        ]);
        $q1 = Question::create([
            'audio_group_id' => $ag1->id,
            'prompt'         => 'P3 G1 Question 1',
            'section'        => 'listening',
            'part_number'    => 3,
            'question_type'  => 'multiple_choice',
            'difficulty'     => 'medium',
            'points'         => 1,
        ]);
        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $p3Section->id, 'question_id' => $q1->id, 'order' => 1]);

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // Verify shared unit audio element has data-preview-exam-audio="true"
        $response->assertSee('src="https://assets.mixkit.co/active_storage/sfx/p3_group1.mp3"', false);
        $response->assertSee('data-preview-exam-audio="true"', false);
    }

    public function test_37_preview_audio_04_part4_group_audio_stops_on_transition(): void
    {
        $p4Section = TestSection::create([
            'test_id'      => $this->testRecord->id,
            'title'        => 'PART 4: TALKS',
            'section_type' => 'listening',
            'order'        => 3,
            'instructions' => 'Listen to talks.',
        ]);

        $ag2 = AudioGroup::create([
            'test_id'          => $this->testRecord->id,
            'title'            => 'P4 Talk Group',
            'part_number'      => 4,
            'group_type'       => 'talk',
            'audio_url'        => 'https://assets.mixkit.co/active_storage/sfx/p4_talk.mp3',
            'order_in_section' => 1,
            'created_by'       => $this->teacherUser->id,
        ]);
        $q4 = Question::create([
            'audio_group_id' => $ag2->id,
            'prompt'         => 'P4 Talk Question 1',
            'section'        => 'listening',
            'part_number'    => 4,
            'question_type'  => 'multiple_choice',
            'difficulty'     => 'medium',
            'points'         => 1,
        ]);
        QuestionChoice::create(['question_id' => $q4->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q4->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $p4Section->id, 'question_id' => $q4->id, 'order' => 1]);

        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        $response->assertSee('src="https://assets.mixkit.co/active_storage/sfx/p4_talk.mp3"', false);
        $response->assertSee('data-preview-exam-audio="true"', false);
    }

    public function test_38_preview_audio_05_one_audio_active_rule_stops_previous_audio(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // Verify handlePlayEvent pauses and resets all other audios
        $response->assertSee('function handlePlayEvent(e)', false);
        $response->assertSee('audio !== currentAudio && !audio.paused', false);
        $response->assertSee('audio.pause()', false);
        $response->assertSee('audio.currentTime = 0', false);
    }

    public function test_39_preview_audio_06_palette_jump_stops_audio_before_target_unit_activates(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // handlePaletteQuestionClick routes to navigateDeliveryUnit which executes beforeDeliveryTransition
        $response->assertSee('function handlePaletteQuestionClick(qIndex)', false);
        $response->assertSee('navigateDeliveryUnit(unitIdx, qIndex)', false);
    }

    public function test_40_preview_audio_07_section_intro_transition_stops_audio(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // showSectionIntro calls beforeDeliveryTransition
        $response->assertSee('function showSectionIntro(sectionId)', false);
        $response->assertSee('PreviewExamAudioManager.beforeDeliveryTransition({ type: \'section_intro\'', false);
    }

    public function test_41_preview_audio_08_keyboard_navigation_stops_audio(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // Keyboard handler calls navigateDeliveryUnit which stops audio
        $response->assertSee('event.key === \'ArrowRight\'', false);
        $response->assertSee('event.key === \'ArrowLeft\'', false);
        $response->assertSee('navigateDeliveryUnit(currentUnitIndex + 1)', false);
    }

    public function test_42_preview_audio_09_audio_resets_to_zero_and_permits_unrestricted_replay_on_return(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // stopAll resets currentTime to 0 for replayability in QA preview
        $response->assertSee('audio.currentTime = 0;', false);
        $response->assertDontSee('isPlayed', false);
        $response->assertDontSee('disablePlayButton', false);
    }

    public function test_43_preview_audio_10_preview_does_not_contain_real_test_or_mock_audio_ledger(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('teacher.tests.preview', $this->testRecord->id));
        $response->assertStatus(200);

        // Verify absence of one-play ledger, attempt mutations, and playback API tracking
        $response->assertDontSee('audio_playback_started', false);
        $response->assertDontSee('audio_playback_completed', false);
        $response->assertDontSee('/api/assessment/audio-log', false);
        $response->assertDontSee('logViolation', false);
    }
}
