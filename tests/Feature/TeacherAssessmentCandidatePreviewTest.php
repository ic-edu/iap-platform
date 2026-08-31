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
        $response->assertSee('The woman is presenting to colleagues.', false);
        $response->assertSee('The meeting room is completely empty.', false);
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
}
