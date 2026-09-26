<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateCbtServerAuthoritativeTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $otherStudent;

    protected User $teacher;

    protected Test $test;

    protected QuestionBank $bank;

    protected Question $q1;

    protected Question $q2;

    protected QuestionChoice $c1;

    protected QuestionChoice $c2;

    protected CandidateTestAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->student = User::factory()->create(['name' => 'Timeout Candidate', 'status' => 'active']);
        $this->student->assignRole('student');

        $this->otherStudent = User::factory()->create(['name' => 'Other Candidate', 'status' => 'active']);
        $this->otherStudent->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Teacher Timeout', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->test = Test::create([
            'title' => 'TOEIC Mock Timeout Test',
            'slug' => 'toeic-mock-timeout-test',
            'test_type' => TestType::Toeic,
            'duration_minutes' => 60,
            'pass_score' => 500,
            'is_published' => true,
            'status' => 'approved',
            'assessment_mode' => AssessmentMode::RealTest,
            'created_by' => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->test->id,
            'title' => 'Part 1: Photographs',
            'section_type' => SectionType::Listening,
            'instructions' => 'Listen and select.',
            'order' => 1,
        ]);

        $this->bank = QuestionBank::create([
            'title' => 'Timeout Bank',
            'slug' => 'timeout-bank',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Timeout Question 1',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        $this->c1 = QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'Choice 1A', 'is_correct' => true]);

        $this->q2 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Timeout Question 2',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        $this->c2 = QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => 'Choice 2B', 'is_correct' => true]);

        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q2->id, 'order' => 2]);

        $this->assignment = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'assigned_by' => $this->teacher->id,
            'assigned_at' => now(),
            'max_attempts' => 2,
            'attempts_count' => 0,
            'status' => 'active',
        ]);
    }

    public function test_submit_before_deadline_with_all_answered_becomes_submitted(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(10), // 50 mins remaining
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->c1->id,
            'is_correct' => true,
            'points_awarded' => 5,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_choice_id' => $this->c2->id,
            'is_correct' => true,
            'points_awarded' => 5,
        ]);

        $response = $this->actingAs($this->student)->post(route('candidate.exam.submit', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_submit_after_deadline_becomes_expired_not_submitted(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(65), // 5 mins expired (60m duration)
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->c1->id,
            'is_correct' => true,
            'points_awarded' => 5,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q2->id,
            'selected_choice_id' => $this->c2->id,
            'is_correct' => true,
            'points_awarded' => 5,
        ]);

        $response = $this->actingAs($this->student)->post(route('candidate.exam.submit', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Expired, $attempt->status);
        $this->assertNotEquals(AttemptStatus::Submitted, $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_submit_after_deadline_with_unanswered_questions_expires_successfully(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(70), // expired
        ]);

        // Only Q1 answered, Q2 unanswered
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->c1->id,
            'is_correct' => true,
            'points_awarded' => 5,
        ]);

        $response = $this->actingAs($this->student)->post(route('candidate.exam.submit', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Expired, $attempt->status);
        $this->assertEquals(1, $attempt->answers()->count());
    }

    public function test_expired_attempt_two_resolves_best_result_and_completes_assignment(): void
    {
        // Attempt 1 expired with 0 answers
        $attempt1 = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subHours(5),
            'submitted_at' => now()->subHours(3),
            'decision_status' => 'retried',
        ]);

        // Attempt 2 in progress but expired server-side with 1 answer
        $attempt2 = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(125), // 120+ mins
            'decision_status' => 'pending_decision',
        ]);

        Answer::create([
            'attempt_id' => $attempt2->id,
            'question_id' => $this->q1->id,
            'selected_choice_id' => $this->c1->id,
            'is_correct' => true,
            'points_awarded' => 5,
        ]);

        $this->assignment->update(['attempts_count' => 2]);

        $response = $this->actingAs($this->student)->post(route('candidate.exam.submit', $attempt2));

        $response->assertRedirect(route('candidate.review', $attempt2));

        $attempt2->refresh();
        $this->assertEquals(AttemptStatus::Expired, $attempt2->status);

        $this->assignment->refresh();
        $this->assertEquals('completed', $this->assignment->status);
        $this->assertEquals($attempt2->id, $this->assignment->final_attempt_id);
    }

    public function test_autosave_returns_machine_readable_attempt_expired_when_timed_out(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(75), // Expired
        ]);

        $response = $this->actingAs($this->student)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->q1->id,
            'selected_choice' => $this->c1->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ATTEMPT_EXPIRED',
            'message' => 'Assessment attempt duration has expired.',
        ]);

        $this->assertDatabaseMissing('answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $this->q1->id,
        ]);
    }

    public function test_toggle_flag_returns_machine_readable_attempt_expired_when_timed_out(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(75), // Expired
        ]);

        $response = $this->actingAs($this->student)->postJson(route('candidate.exam.flag', $attempt), [
            'question_id' => $this->q1->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ATTEMPT_EXPIRED',
        ]);
    }

    public function test_stream_audio_returns_machine_readable_attempt_expired_when_timed_out(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(75), // Expired
        ]);

        $response = $this->actingAs($this->student)->getJson(route('candidate.exam.audio-stream', [$attempt, $this->q1]));

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ATTEMPT_EXPIRED',
        ]);
    }

    public function test_ownership_violation_returns_403_without_attempt_expired_code(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->otherStudent)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->q1->id,
            'selected_choice' => $this->c1->id,
        ]);

        $response->assertStatus(403);
        $this->assertNotEquals('ATTEMPT_EXPIRED', $response->json('error_code'));
    }

    public function test_exam_blade_contains_wall_clock_timer_deadline_and_expiry_flow(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

        $response->assertOk();
        $response->assertSee('timerDeadlineMs');
        $response->assertSee('triggerExpiryFlow');
        $response->assertSee('expiryFlowTriggered');
        $response->assertSee('ATTEMPT_EXPIRED');
        $response->assertSee('initial_zero_time');
    }

    public function test_exam_blade_initial_load_when_already_expired_auto_expires_and_redirects_to_review(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(90), // Expired
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

        $response->assertRedirect(route('candidate.review', $attempt));
        $this->assertEquals(AttemptStatus::Expired, $attempt->fresh()->status);
    }

    public function test_persisted_expired_attempt_autosave_returns_attempt_expired(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->student)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->q1->id,
            'selected_choice' => $this->c1->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ATTEMPT_EXPIRED',
            'message' => 'Assessment attempt duration has expired.',
        ]);
    }

    public function test_persisted_expired_attempt_flag_returns_attempt_expired(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->student)->postJson(route('candidate.exam.flag', $attempt), [
            'question_id' => $this->q1->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ATTEMPT_EXPIRED',
        ]);
    }

    public function test_persisted_submitted_attempt_autosave_returns_attempt_not_in_progress(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->student)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->q1->id,
            'selected_choice' => $this->c1->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ATTEMPT_NOT_IN_PROGRESS',
            'message' => 'Assessment attempt is no longer in progress.',
        ]);
        $this->assertNotEquals('ATTEMPT_EXPIRED', $response->json('error_code'));
    }

    public function test_persisted_cancelled_attempt_autosave_returns_attempt_not_in_progress(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Cancelled,
            'started_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($this->student)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->q1->id,
            'selected_choice' => $this->c1->id,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error_code' => 'ATTEMPT_NOT_IN_PROGRESS',
            'message' => 'Assessment attempt is no longer in progress.',
        ]);
        $this->assertNotEquals('ATTEMPT_EXPIRED', $response->json('error_code'));
    }

    public function test_exam_blade_contains_single_flight_guard_and_correct_modal_bindings(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

        $response->assertOk();
        $response->assertSee('expirySubmissionStarted');
        $response->assertSee('submitExpiredAttemptOnce');
        $response->assertSee('iap-modal-confirm-btn');
        $response->assertSee('onConfirm');
        $response->assertDontSee('onOk:');
    }
}
