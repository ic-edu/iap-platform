<?php

namespace Tests\Feature;

use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\ScoringMethod;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\QuestionBank\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateCbtSubmitAndDashboardInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $otherCandidate;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $this->candidate = User::factory()->create([
            'email' => 'candidate_uat@example.com',
            'name' => 'Candidate Tester',
        ]);
        $this->candidate->assignRole('student');

        $this->otherCandidate = User::factory()->create([
            'email' => 'other_candidate@example.com',
            'name' => 'Other Candidate',
        ]);
        $this->otherCandidate->assignRole('student');
    }

    protected function createSimulatorTest(int $questionCount = 4): array
    {
        $test = Test::create([
            'title' => 'TOEIC Listening & Reading Practice Simulator',
            'slug' => 'toeic-simulator-' . \Illuminate\Support\Str::random(6),
            'description' => 'Test Simulator Description',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 120,
            'pass_score' => 600,
            'is_published' => true,
            'created_by' => $this->candidate->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'order' => 1,
            'duration_minutes' => 60,
        ]);

        $questions = [];
        for ($i = 1; $i <= $questionCount; $i++) {
            $q = Question::create([
                'prompt' => "Test Question {$i} Prompt",
                'part_number' => 1,
                'section' => 'listening',
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'points' => 5,
            ]);

            for ($c = 1; $c <= 4; $c++) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => chr(64 + $c),
                    'content' => "Option {$c}",
                    'is_correct' => $c === 1,
                    'order' => $c,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $section->id,
                'question_id' => $q->id,
                'order' => $i,
                'points' => 5,
            ]);

            $questions[] = $q;
        }

        return [$test, $section, $questions];
    }

    protected function createMockTest(): array
    {
        $test = Test::create([
            'title' => 'TOEIC Official Secure Mock Test',
            'slug' => 'toeic-mock-' . \Illuminate\Support\Str::random(6),
            'description' => 'Mock Test Description',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 120,
            'pass_score' => 600,
            'is_published' => true,
            'created_by' => $this->candidate->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Listening Section',
            'order' => 1,
            'duration_minutes' => 60,
        ]);

        $q = Question::create([
            'prompt' => 'Mock Question 1 Prompt',
            'part_number' => 1,
            'section' => 'listening',
            'question_type' => 'multiple_choice',
            'difficulty' => 'medium',
            'points' => 5,
        ]);

        for ($c = 1; $c <= 4; $c++) {
            QuestionChoice::create([
                'question_id' => $q->id,
                'label' => chr(64 + $c),
                'content' => "Option {$c}",
                'is_correct' => $c === 1,
                'order' => $c,
            ]);
        }

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $q->id,
            'order' => 1,
            'points' => 5,
        ]);

        return [$test, $section, [$q]];
    }

    /**
     * TEST 01, 02, 03: CBT Header does not have permanent submit, retains timer and title.
     */
    public function test_cbt_simulator_header_retains_title_and_timer_without_permanent_final_submit(): void
    {
        [$test, , ] = $this->createSimulatorTest(4);

        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'total_score' => 0,
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));

        $response->assertStatus(200);

        // Header elements check
        $response->assertSee('CBT Examination Session');
        $response->assertSee('TEST SIMULATOR');
        $response->assertSee($test->title);
        $response->assertSee('Time Remaining');
        $response->assertSee('countdown-timer');

        // Permanent header submit button is NOT present in the header
        $response->assertDontSee('id="btn-final-submit"', false);
        $response->assertDontSee('Final Submit (0/4)');
    }

    /**
     * TEST 14, 15, 16, 17, 18: Simulator renders Back to Dashboard; Mock Test does NOT.
     */
    public function test_simulator_renders_back_to_dashboard_while_mock_test_does_not(): void
    {
        // 1. Simulator Attempt
        [$simTest, , ] = $this->createSimulatorTest(4);
        $simAttempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $simTest->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'total_score' => 0,
        ]);

        $simResponse = $this->actingAs($this->candidate)->get(route('candidate.exam', $simAttempt));
        $simResponse->assertStatus(200);
        $simResponse->assertSee('id="btn-simulator-back-to-dashboard"', false);
        $simResponse->assertSee('&larr; Back to Dashboard', false);
        $simResponse->assertSee('confirmExitSimulator()', false);

        // 2. Mock Test Attempt
        [$mockTest, , ] = $this->createMockTest();
        $mockAttempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $mockTest->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'total_score' => 0,
        ]);

        $mockResponse = $this->actingAs($this->candidate)->get(route('candidate.exam', $mockAttempt));
        $mockResponse->assertStatus(200);
        $mockResponse->assertSee('SECURE MOCK TEST');
        $mockResponse->assertDontSee('id="btn-simulator-back-to-dashboard"', false);
        $mockResponse->assertDontSee('&larr; Back to Dashboard', false);
    }

    /**
     * TEST 19, 20, 21, 22, 23, 24, 25, 26, 27: Exiting Simulator preserves in-progress state and answers.
     */
    public function test_simulator_progress_is_preserved_and_resumable_after_returning_to_dashboard(): void
    {
        [$test, , $questions] = $this->createSimulatorTest(4);

        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'total_score' => 0,
        ]);

        $firstChoice = $questions[0]->choices->first();

        // Simulate candidate answering Question 1
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $questions[0]->id,
            'selected_choice_id' => $firstChoice->id,
            'is_correct' => true,
            'awarded_marks' => 5,
        ]);

        // Candidate navigates to Portal Dashboard
        $dashboardResponse = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Ongoing Sessions (1)');
        $dashboardResponse->assertSee($test->title);
        $dashboardResponse->assertSee(route('candidate.exam', $attempt));

        // Attempt in database remains IN_PROGRESS, not submitted, answer intact
        $freshAttempt = $attempt->fresh();
        $this->assertEquals(AttemptStatus::InProgress, $freshAttempt->status);
        $this->assertEquals(1, $freshAttempt->answers()->count());
        $this->assertEquals($firstChoice->id, $freshAttempt->answers->first()->selected_choice_id);

        // Reopening exam resumes the exact same Attempt
        $resumeResponse = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $resumeResponse->assertStatus(200);
        $resumeResponse->assertSee('1/4'); // Answered 1 out of 4 questions
    }

    /**
     * TEST 04, 05, 06, 07, 08, 12: Completion-aware submit flow.
     */
    public function test_completion_aware_submit_flow_renders_review_unanswered_or_final_submit(): void
    {
        [$test, , $questions] = $this->createSimulatorTest(4);

        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'total_score' => 0,
        ]);

        // 1. Incomplete attempt (1 of 4 answered)
        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $questions[0]->id,
            'selected_choice_id' => $questions[0]->choices->first()->id,
        ]);

        $response1 = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response1->assertStatus(200);
        $response1->assertSee('btn-review-unanswered');
        $response1->assertSee('Review Unanswered');
        $response1->assertSee('reviewFirstUnanswered()', false);

        // 2. All 4 answered
        for ($i = 1; $i < 4; $i++) {
            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $questions[$i]->id,
                'selected_choice_id' => $questions[$i]->choices->first()->id,
            ]);
        }

        $response2 = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response2->assertStatus(200);
        $response2->assertSee('btn-final-review-submit');
        $response2->assertSee('Final Review &amp; Submit', false);
        $response2->assertSee('triggerFinalSubmitModal()', false);
        $response2->assertSee('id="palette-completion-container"', false);

        // Attempt is NOT automatically submitted merely because 4/4 questions are answered
        $this->assertEquals(AttemptStatus::InProgress, $attempt->fresh()->status);
    }

    /**
     * TEST 33, 34, 35, 36, 37, 42, 43, 44: Candidate Dashboard KPI cards and top nav affordances.
     */
    public function test_candidate_dashboard_kpi_cards_and_top_nav_affordances(): void
    {
        [$test, , ] = $this->createSimulatorTest(4);

        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'total_score' => 650,
        ]);

        $certificate = Certificate::create([
            'user_id' => $this->candidate->id,
            'attempt_id' => $attempt->id,
            'certificate_number' => 'CERT-UAT-001',
            'issued_at' => now(),
            'status' => 'valid',
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $response->assertStatus(200);

        // Top nav link
        $response->assertSee(route('candidate.portal'));
        $response->assertSee('aria-label="Dashboard"', false);

        // 1. Available Tests KPI
        $response->assertSee(route('candidate.available-tests'));
        $response->assertSee('Available Tests');

        // 2. My Total Attempts KPI
        $response->assertSee(route('candidate.my-attempts'));
        $response->assertSee('My Total Attempts');

        // 3. Completed Tests KPI
        $response->assertSee(route('candidate.my-attempts', ['filter' => 'completed']));
        $response->assertSee('Completed Tests');

        // 4. My Certificates KPI
        $response->assertSee(route('candidate.my-certificates'));
        $response->assertSee('My Certificates');
    }

    /**
     * TEST 38, 39, 40, 41: Candidate data isolation across attempts and certificates.
     */
    public function test_candidate_kpi_destinations_isolate_authenticated_user_data(): void
    {
        [$test, , ] = $this->createSimulatorTest(4);

        // Candidate 1 attempt
        $cand1Attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'total_score' => 750,
        ]);

        // Candidate 2 attempt
        $cand2Attempt = Attempt::create([
            'user_id' => $this->otherCandidate->id,
            'test_id' => $test->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'total_score' => 850,
        ]);

        // 1. Candidate 1 views attempts
        $cand1Resp = $this->actingAs($this->candidate)->get(route('candidate.my-attempts'));
        $cand1Resp->assertStatus(200);
        $cand1Resp->assertViewHas('attempts', function ($attempts) use ($cand1Attempt, $cand2Attempt) {
            return $attempts->contains('id', $cand1Attempt->id) && !$attempts->contains('id', $cand2Attempt->id);
        });

        // 2. Candidate 1 views filtered completed attempts
        $cand1FilteredResp = $this->actingAs($this->candidate)->get(route('candidate.my-attempts', ['filter' => 'completed']));
        $cand1FilteredResp->assertStatus(200);
        $cand1FilteredResp->assertViewHas('attempts', function ($attempts) use ($cand1Attempt) {
            return $attempts->contains('id', $cand1Attempt->id);
        });

        // 3. Available tests access
        $availResp = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $availResp->assertStatus(200);

        // 4. Certificates access
        $certResp = $this->actingAs($this->candidate)->get(route('candidate.my-certificates'));
        $certResp->assertStatus(200);
    }

    /**
     * TEST 37, 41: Zero-count KPI cards remain navigable and display valid empty states without 403.
     */
    public function test_zero_count_kpis_remain_clickable_and_accessible(): void
    {
        // New candidate with 0 attempts, 0 certificates
        $freshUser = User::factory()->create([
            'email' => 'zero_candidate@example.com',
            'name' => 'Zero Candidate',
        ]);
        $freshUser->assignRole('student');

        $response = $this->actingAs($freshUser)->get(route('candidate.portal'));
        $response->assertStatus(200);

        // All 4 KPI links must still be present as anchors
        $response->assertSee(route('candidate.available-tests'));
        $response->assertSee(route('candidate.my-attempts'));
        $response->assertSee(route('candidate.my-attempts', ['filter' => 'completed']));
        $response->assertSee(route('candidate.my-certificates'));

        // Visiting each destination returns 200 (not 403 or 500)
        $this->actingAs($freshUser)->get(route('candidate.available-tests'))->assertStatus(200);
        $this->actingAs($freshUser)->get(route('candidate.my-attempts'))->assertStatus(200);
        $this->actingAs($freshUser)->get(route('candidate.my-attempts', ['filter' => 'completed']))->assertStatus(200);
        $this->actingAs($freshUser)->get(route('candidate.my-certificates'))->assertStatus(200);
    }

    /**
     * TEST 09, 10, 11, 12, 13: Grouped audio/passage questions count individually and flags do not block submission.
     */
    public function test_grouped_questions_contribute_individual_counts_and_flags_dont_block(): void
    {
        $test = Test::create([
            'title' => 'TOEIC Full Structure Simulator',
            'slug' => 'toeic-full-sim-' . \Illuminate\Support\Str::random(6),
            'description' => 'Test Simulator Description',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'scoring_method' => ScoringMethod::Automatic,
            'duration_minutes' => 120,
            'pass_score' => 600,
            'is_published' => true,
            'created_by' => $this->candidate->id,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title' => 'Part 3 Conversations',
            'order' => 1,
            'duration_minutes' => 60,
        ]);

        $audioGroup = \App\Modules\QuestionBank\Models\AudioGroup::create([
            'title' => 'Conversation at Airport',
            'part_number' => 3,
        ]);

        $questions = [];
        for ($i = 1; $i <= 3; $i++) {
            $q = Question::create([
                'prompt' => "Part 3 Question {$i} Prompt",
                'audio_group_id' => $audioGroup->id,
                'part_number' => 3,
                'section' => 'listening',
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium',
                'points' => 5,
            ]);

            for ($c = 1; $c <= 4; $c++) {
                QuestionChoice::create([
                    'question_id' => $q->id,
                    'label' => chr(64 + $c),
                    'content' => "Option {$c}",
                    'is_correct' => $c === 1,
                    'order' => $c,
                ]);
            }

            TestQuestion::create([
                'test_section_id' => $section->id,
                'question_id' => $q->id,
                'order' => $i,
                'points' => 5,
            ]);

            $questions[] = $q;
        }

        $attempt = Attempt::create([
            'user_id' => $this->candidate->id,
            'test_id' => $test->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'total_score' => 0,
        ]);

        // Answer all 3 child questions of the AudioGroup
        foreach ($questions as $q) {
            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'selected_choice_id' => $q->choices->first()->id,
                'is_flagged' => true, // Flag all questions
            ]);
        }

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        // All 3 answered questions are counted (3/3)
        $response->assertSee('3/3');
        $response->assertSee('btn-final-review-submit');
        $response->assertSee('Final Review &amp; Submit', false);

        // Submit attempt via POST
        $submitResponse = $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $submitResponse->assertRedirect();

        $freshAttempt = $attempt->fresh();
        $this->assertEquals(AttemptStatus::Submitted, $freshAttempt->status);
    }
}
