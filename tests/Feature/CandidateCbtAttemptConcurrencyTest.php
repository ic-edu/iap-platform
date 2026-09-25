<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CandidateCbtAttemptConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected Test $realTest;
    protected Question $question1;
    protected QuestionChoice $choice1A;
    protected QuestionChoice $choice1B;
    protected CandidateTestAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->candidate = User::factory()->create([
            'name' => 'Concurrency Candidate',
            'email' => 'concurrency.candidate@iap.test',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        $this->realTest = Test::create([
            'title' => 'TOEIC Concurrency Test',
            'slug' => 'toeic-concurrency-test',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidate->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->realTest->id,
            'title' => 'Listening Section',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 30,
            'order' => 1,
        ]);

        $this->question1 = Question::create([
            'prompt' => 'Concurrency Question 1',
            'section' => SectionType::Listening,
            'question_type' => QuestionType::MultipleChoice,
            'points' => 10,
        ]);

        $this->choice1A = QuestionChoice::create([
            'question_id' => $this->question1->id,
            'label' => 'A',
            'content' => 'Choice A',
            'is_correct' => true,
        ]);

        $this->choice1B = QuestionChoice::create([
            'question_id' => $this->question1->id,
            'label' => 'B',
            'content' => 'Choice B',
            'is_correct' => false,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $this->question1->id,
            'order' => 1,
            'points' => 10,
        ]);

        $this->assignment = CandidateTestAssignment::create([
            'user_id' => $this->candidate->id,
            'test_id' => $this->realTest->id,
            'status' => 'active',
            'max_attempts' => 2,
            'attempts_count' => 0,
            'assigned_at' => now(),
        ]);
    }

    public function test_concurrent_start_attempt_returns_single_in_progress_attempt(): void
    {
        $engine = app(AttemptEngine::class);

        // First start call
        $attempt1 = $engine->startAttempt($this->realTest, $this->candidate);
        $this->assertNotNull($attempt1);
        $this->assertEquals(AttemptStatus::InProgress, $attempt1->status);

        // Immediate subsequent start call should return the existing active in-progress attempt
        $attempt2 = $engine->startAttempt($this->realTest, $this->candidate);
        $this->assertEquals($attempt1->id, $attempt2->id);

        $this->assertEquals(1, Attempt::where('user_id', $this->candidate->id)->where('test_id', $this->realTest->id)->count());
    }

    public function test_concurrent_submit_is_serialized_and_evaluated_once(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'seed' => 'seed123',
        ]);

        // Autosave answer to meet completion requirement
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->question1->id,
            'selected_choice' => $this->choice1A->id,
        ]);

        // Submit via endpoint first time
        $response1 = $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $response1->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);
        $submittedAt = $attempt->submitted_at;
        $this->assertNotNull($submittedAt);

        // Second submit attempt (e.g. duplicate click / race condition)
        $response2 = $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $response2->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertEquals($submittedAt->toIso8601String(), $attempt->submitted_at->toIso8601String());
    }

    public function test_autosave_rejected_when_attempt_is_submitted_during_concurrent_race(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'seed' => 'seed123',
        ]);

        // Answer question
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->question1->id,
            'selected_choice' => $this->choice1A->id,
        ]);

        // Submit attempt first
        $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Submitted, $attempt->status);

        // Concurrent autosave after status has transitioned to submitted
        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id' => $this->question1->id,
            'selected_choice' => $this->choice1B->id,
        ]);

        $response->assertStatus(403);

        // Verify choice was NOT updated to B
        $answer = Answer::where('attempt_id', $attempt->id)->where('question_id', $this->question1->id)->first();
        $this->assertEquals($this->choice1A->id, $answer->selected_choice_id);
    }

    public function test_concurrent_retry_attempt_creates_only_single_attempt_two(): void
    {
        $attempt1 = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(10),
            'score' => 450,
            'seed' => 'seed1',
        ]);
        $this->assignment->update(['attempts_count' => 1]);

        // First retry request
        $response1 = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));
        $attempt1->refresh();
        $this->assertEquals('retried', $attempt1->decision_status);

        $attempt2 = Attempt::where('user_id', $this->candidate->id)
            ->where('assignment_id', $this->assignment->id)
            ->where('attempt_number', 2)
            ->first();
        $this->assertNotNull($attempt2);
        $response1->assertRedirect(route('candidate.exam', $attempt2));

        // Concurrent duplicate retry request
        $response2 = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));
        $response2->assertRedirect(route('candidate.exam', $attempt2));

        $this->assertEquals(2, Attempt::where('assignment_id', $this->assignment->id)->count());
    }

    public function test_finalize_vs_retry_race_condition_handled_safely(): void
    {
        $attempt1 = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'evaluation_status' => EvaluationStatus::Evaluated,
            'decision_status' => 'pending_decision',
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now()->subMinutes(10),
            'score' => 600,
            'seed' => 'seed1',
        ]);
        $this->assignment->update(['attempts_count' => 1]);

        // Finalize first
        $responseFinal = $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt1));
        $attempt1->refresh();
        $this->assertEquals('finalized', $attempt1->decision_status);
        $this->assertTrue($attempt1->is_final);

        // Concurrent retry request after finalize
        $responseRetry = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));
        $responseRetry->assertRedirect(route('candidate.review', $attempt1));

        // No attempt 2 created
        $this->assertEquals(1, Attempt::where('assignment_id', $this->assignment->id)->count());
    }

    public function test_real_test_audio_stream_single_play_reservation(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'seed' => 'seed123',
        ]);

        // Record first play directly as in streamAudio
        AttemptAudioPlay::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->question1->id,
            'play_count' => 1,
            'started_at' => now(),
        ]);

        // Attempting to stream again should be blocked with 403
        $response = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [
            'attempt' => $attempt,
            'question' => $this->question1,
        ]));

        $response->assertStatus(403);
    }
}
