<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateCbtSessionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;
    protected Test $test;
    protected QuestionBank $bank;
    protected Question $q1;
    protected Question $q2;
    protected Question $q3;
    protected QuestionChoice $c1;
    protected QuestionChoice $c2;
    protected QuestionChoice $c3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->student = User::factory()->create(['name' => 'Candidate A', 'status' => 'active']);
        $this->student->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Teacher B', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->test = Test::create([
            'title'            => 'TOEIC Listening & Reading Hardening',
            'slug'             => 'toeic-listening-reading-hardening',
            'test_type'        => TestType::Toeic,
            'duration_minutes' => 60,
            'pass_score'       => 500,
            'is_published'     => true,
            'status'           => 'approved',
            'created_by'       => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 1: Photographs',
            'section_type' => SectionType::Listening,
            'instructions' => 'Look at the photograph and select the statement that best describes what you see.',
            'order'        => 1,
        ]);

        $this->bank = QuestionBank::create([
            'title'      => 'TOEIC Bank',
            'slug'       => 'toeic-bank',
            'test_type'  => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Question 1: Look at the photo.',
            'question_type'    => QuestionType::MultipleChoice,
            'points'           => 5,
        ]);
        $this->c1 = QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => '-', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => '-', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q1->id, 'order' => 1]);

        $this->q2 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Question 2: Look at the photo.',
            'question_type'    => QuestionType::MultipleChoice,
            'points'           => 5,
        ]);
        $this->c2 = QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => '-', 'is_correct' => false]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => '-', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q2->id, 'order' => 2]);

        $this->q3 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Question 3: Look at the photo.',
            'question_type'    => QuestionType::MultipleChoice,
            'points'           => 5,
        ]);
        $this->c3 = QuestionChoice::create(['question_id' => $this->q3->id, 'label' => 'A', 'content' => '-', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q3->id, 'label' => 'B', 'content' => '-', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q3->id, 'order' => 3]);
    }

    /**
     * TEST 1: Timer and Countdown JS engine integrity.
     */
    public function test_timer_initialization_and_countdown_engine(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now()->subMinutes(10), // 10 minutes elapsed of 60 mins -> ~50 mins remaining
            'status'     => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Assert countdown elements and script
        $response->assertSee('id="countdown-timer"', false);
        $response->assertSee('function updateTimerDisplay()', false);
        $response->assertSee('remainingSeconds--', false);
        $response->assertSee('setInterval(updateTimerDisplay, 1000)', false);
    }

    /**
     * TEST 2: Final Submit button is initially disabled when 0/3 questions are answered.
     */
    public function test_final_submit_button_is_disabled_when_zero_of_three_questions_answered(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $response->assertSee('id="btn-final-submit"', false);
        $response->assertSee('disabled', false);
        $response->assertSee('Final Submit (0/3)', false);
    }

    /**
     * TEST 3: Final Submit button remains disabled when partially answered (1/3 or 2/3).
     */
    public function test_final_submit_button_is_disabled_when_partially_answered(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        Answer::create([
            'attempt_id'         => $attempt->id,
            'question_id'        => $this->q1->id,
            'selected_choice_id' => $this->c1->id,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $response->assertSee('disabled', false);
        $response->assertSee('Final Submit (1/3)', false);
    }

    /**
     * TEST 4: Final Submit button is enabled when all 3/3 questions are answered.
     */
    public function test_final_submit_button_is_enabled_when_all_questions_answered(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $this->q1->id, 'selected_choice_id' => $this->c1->id]);
        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $this->q2->id, 'selected_choice_id' => $this->c2->id]);
        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $this->q3->id, 'selected_choice_id' => $this->c3->id]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $response->assertSee('Final Submit &rarr;', false);
    }

    /**
     * TEST 5: Server-side submit guard rejects incomplete manual submission.
     */
    public function test_server_rejects_incomplete_manual_submission(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        // Only 1 of 3 answered
        Answer::create([
            'attempt_id'         => $attempt->id,
            'question_id'        => $this->q1->id,
            'selected_choice_id' => $this->c1->id,
        ]);

        $submitRes = $this->actingAs($this->student)->post(route('candidate.exam.submit', $attempt));
        $submitRes->assertRedirect();
        $submitRes->assertSessionHas('error', 'Please answer all questions before submitting the assessment.');

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::InProgress, $attempt->status);
    }

    /**
     * TEST 6: Server-side submit guard accepts complete manual submission.
     */
    public function test_server_accepts_complete_manual_submission(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $this->q1->id, 'selected_choice_id' => $this->c1->id]);
        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $this->q2->id, 'selected_choice_id' => $this->c2->id]);
        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $this->q3->id, 'selected_choice_id' => $this->c3->id]);

        $submitRes = $this->actingAs($this->student)->post(route('candidate.exam.submit', $attempt));
        $submitRes->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
    }

    /**
     * TEST 7: Expired attempt can submit automatically even if questions are unanswered.
     */
    public function test_expired_attempt_submits_even_if_unanswered(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now()->subMinutes(120), // Exceeded 60 min duration
            'status'     => AttemptStatus::InProgress,
        ]);

        $submitRes = $this->actingAs($this->student)->post(route('candidate.exam.submit', $attempt));
        $submitRes->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
    }

    /**
     * TEST 8: Compact question navigation palette rendering.
     */
    public function test_question_palette_renders_compact_status_indicators(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        Answer::create([
            'attempt_id'         => $attempt->id,
            'question_id'        => $this->q1->id,
            'selected_choice_id' => $this->c1->id,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $response->assertSee('Question Navigation');
        $response->assertSee('palette-btn-0', false);
        $response->assertSee('palette-btn-1', false);
        $response->assertSee('palette-btn-2', false);
        // Q1 answered icon
        $response->assertSee('✓', false);
        // Q2/Q3 unanswered icon
        $response->assertSee('—', false);
    }

    /**
     * TEST 9: Dedicated Section Directions screen renders before first question with title, directions, and begin button.
     */
    public function test_dedicated_section_directions_screen_renders_with_begin_action(): void
    {
        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Section directions screen elements
        $response->assertSee('section-intro-card-', false);
        $response->assertSee('Listening Section');
        $response->assertSee('Part 1: Photographs');
        $response->assertSee('Section Directions');
        $response->assertSee('Look at the photograph and select the statement that best describes what you see.');
        $response->assertSee('Begin Part 1: Photographs');
        $response->assertSee('showSectionIntro(', false);
    }

    /**
     * TEST 10: Multi-section assessment renders dedicated directions cards for each section.
     */
    public function test_multi_section_assessment_renders_directions_cards_for_all_sections(): void
    {
        // Add Section 2 (Reading Section)
        $section2 = TestSection::create([
            'test_id'      => $this->test->id,
            'title'        => 'Part 5: Incomplete Sentences',
            'section_type' => SectionType::Reading,
            'instructions' => 'Select the one word or phrase that best completes the sentence.',
            'order'        => 2,
        ]);

        $q4 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt'           => 'Question 4: Complete the sentence.',
            'question_type'    => QuestionType::MultipleChoice,
            'points'           => 5,
        ]);
        QuestionChoice::create(['question_id' => $q4->id, 'label' => 'A', 'content' => 'Option A', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $section2->id, 'question_id' => $q4->id, 'order' => 1]);

        $attempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now(),
            'status'     => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // Section 1 Intro Card
        $response->assertSee('Part 1: Photographs');
        $response->assertSee('Begin Part 1: Photographs');

        // Section 2 Intro Card
        $response->assertSee('Part 5: Incomplete Sentences');
        $response->assertSee('Reading Section');
        $response->assertSee('Select the one word or phrase that best completes the sentence.');
        $response->assertSee('Begin Part 5: Incomplete Sentences');
    }

    /**
     * TEST 11: Candidate portal dashboard lists multiple ongoing sessions ordered by latest started_at first.
     */
    public function test_candidate_portal_shows_multiple_ongoing_sessions_ordered_latest_first(): void
    {
        $test2 = Test::create([
            'title'            => 'TOEIC Full Simulation Test 01',
            'slug'             => 'toeic-full-sim-01',
            'test_type'        => TestType::Toeic,
            'duration_minutes' => 120,
            'pass_score'       => 500,
            'is_published'     => true,
            'created_by'       => $this->teacher->id,
        ]);

        // Old attempt started 30 mins ago
        $oldAttempt = Attempt::create([
            'test_id'    => $test2->id,
            'user_id'    => $this->student->id,
            'started_at' => now()->subMinutes(30),
            'status'     => AttemptStatus::InProgress,
        ]);

        // Recent attempt started 5 mins ago
        $recentAttempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now()->subMinutes(5),
            'status'     => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.portal'));
        $response->assertStatus(200);

        $response->assertSee('Ongoing Sessions (2)');
        $response->assertSee('TOEIC Listening & Reading Hardening');
        $response->assertSee('TOEIC Full Simulation Test 01');

        // Verify both exact resume links exist
        $response->assertSee(route('candidate.exam', $recentAttempt));
        $response->assertSee(route('candidate.exam', $oldAttempt));

        // Verify recent attempt appears before older attempt in the HTML content
        $content = $response->getContent();
        $recentPos = strpos($content, route('candidate.exam', $recentAttempt));
        $oldPos = strpos($content, route('candidate.exam', $oldAttempt));
        $this->assertTrue($recentPos !== false && $oldPos !== false && $recentPos < $oldPos);
    }

    /**
     * TEST 12: Expired in-progress attempts are excluded from ongoing sessions display.
     */
    public function test_expired_in_progress_attempts_are_excluded_from_ongoing_sessions(): void
    {
        // Expired attempt (started 2 hours ago for 60 min test)
        $expiredAttempt = Attempt::create([
            'test_id'    => $this->test->id,
            'user_id'    => $this->student->id,
            'started_at' => now()->subMinutes(120),
            'status'     => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.portal'));
        $response->assertStatus(200);

        // Expired attempt must NOT appear as an active ongoing session
        $response->assertDontSee('Ongoing Sessions');
        $response->assertDontSee(route('candidate.exam', $expiredAttempt));
    }
}
