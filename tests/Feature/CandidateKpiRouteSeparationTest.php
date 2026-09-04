<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\Test;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Certificate\Enums\CertificateStatus;
use App\Modules\QuestionBank\Enums\TestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CandidateKpiRouteSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidateA;
    protected User $candidateB;
    protected User $admin;
    protected Test $simulatorTest;
    protected Test $realTest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->candidateA = User::factory()->create([
            'name' => 'Candidate Alpha',
            'email' => 'cand_alpha@example.com',
        ]);
        $this->candidateA->assignRole('student');

        $this->candidateB = User::factory()->create([
            'name' => 'Candidate Beta',
            'email' => 'cand_beta@example.com',
        ]);
        $this->candidateB->assignRole('student');

        $this->admin = User::factory()->create([
            'name' => 'Assessment Admin',
            'email' => 'admin_test@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->simulatorTest = Test::create([
            'title' => 'TOEIC Listening & Reading Simulation Test',
            'slug' => 'toeic-sim-' . Str::random(5),
            'test_type' => TestType::General,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 120,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $this->realTest = Test::create([
            'title' => 'Official TOEFL Certification Exam',
            'slug' => 'toefl-real-' . Str::random(5),
            'test_type' => TestType::General,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 90,
            'pass_score' => 70,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * TEST KPI-01: My Total Attempts points to Candidate Attempt History route.
     * TEST KPI-02: Completed Tests points to a distinct Candidate Results route/view.
     * TEST KPI-03: The two KPI cards do NOT have the same destination.
     */
    public function test_kpi_01_02_03_dashboard_kpis_route_to_distinct_destinations(): void
    {
        $response = $this->actingAs($this->candidateA)->get(route('candidate.portal'));
        $response->assertStatus(200);

        // KPI-01: My Total Attempts links to candidate.my-attempts
        $response->assertSee(route('candidate.my-attempts'));
        $response->assertSee('My Total Attempts');

        // KPI-02: Completed Tests links to candidate.my-results
        $response->assertSee(route('candidate.my-results'));
        $response->assertSee('Completed Tests');

        // KPI-03: Distinct destinations
        $html = $response->getContent();
        $this->assertStringContainsString('href="' . route('candidate.my-attempts') . '"', $html);
        $this->assertStringContainsString('href="' . route('candidate.my-results') . '"', $html);
        $this->assertNotEquals(route('candidate.my-attempts'), route('candidate.my-results'));
    }

    /**
     * TEST ATTEMPT-01, HISTORY-01, 02, 03: Candidate Attempt History includes in-progress, submitted, and expired attempts.
     * TEST ACTION-01, 02, 03, 04: Submitted shows View Result, InProgress shows Resume Exam, Expired shows View Summary.
     */
    public function test_attempt_and_history_shows_all_sessions_with_precise_action_labels(): void
    {
        // 1. In progress attempt
        $inProgressAttempt = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(30),
            'total_score' => 0,
        ]);

        // 2. Submitted attempt
        $submittedAttempt = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'total_score' => 66,
            'correct_answers_count' => 66,
            'total_questions_count' => 68,
        ]);

        // 3. Expired attempt
        $expiredAttempt = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->realTest->id,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subDays(1),
            'submitted_at' => now()->subDays(1)->addHours(2),
            'total_score' => 0,
        ]);

        $response = $this->actingAs($this->candidateA)->get(route('candidate.my-attempts'));
        $response->assertStatus(200);

        // Must contain all 3 attempts
        $response->assertViewHas('attempts', function ($attempts) use ($inProgressAttempt, $submittedAttempt, $expiredAttempt) {
            return $attempts->contains('id', $inProgressAttempt->id)
                && $attempts->contains('id', $submittedAttempt->id)
                && $attempts->contains('id', $expiredAttempt->id);
        });

        // ACTION-01: Submitted -> View Result
        $response->assertSee('View Result');
        $response->assertSee(route('candidate.review', $submittedAttempt));

        // ACTION-02: InProgress -> Resume Exam
        $response->assertSee('IN PROGRESS');
        $response->assertSee('Resume Exam');
        $response->assertSee(route('candidate.exam', $inProgressAttempt));

        // ACTION-03 & 04: Expired -> View Summary (non-misleading)
        $response->assertSee('TIME EXPIRED');
        $response->assertSee('View Summary');
        $response->assertSee(route('candidate.review', $expiredAttempt));
    }

    /**
     * Helper to create a simulator attempt with questions and answers.
     */
    protected function createSimulatorAttempt(User $user, int $totalQuestions, int $correctCount): Attempt
    {
        $test = Test::create([
            'title'            => 'TOEIC Simulator Assessment',
            'slug'             => 'toeic-sim-' . uniqid(),
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score'       => 75,
            'scoring_method'   => 'automatic',
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->admin->id,
        ]);

        $section = \App\Modules\Assessment\Models\TestSection::create([
            'test_id'      => $test->id,
            'title'        => 'Practice Section',
            'section_type' => \App\Modules\QuestionBank\Enums\SectionType::Listening,
            'order'        => 1,
        ]);

        $attempt = Attempt::create([
            'test_id'           => $test->id,
            'user_id'           => $user->id,
            'attempt_token'     => 'sim-tok-' . uniqid(),
            'status'            => AttemptStatus::Submitted,
            'evaluation_status' => \App\Modules\Assessment\Enums\EvaluationStatus::NotRequired,
            'started_at'        => now()->subMinutes(30),
            'submitted_at'      => now(),
        ]);

        for ($i = 1; $i <= $totalQuestions; $i++) {
            $q = \App\Modules\QuestionBank\Models\Question::create([
                'prompt'        => "Question {$i}",
                'question_type' => \App\Modules\QuestionBank\Enums\QuestionType::MultipleChoice,
                'points'        => 1,
                'section'       => \App\Modules\QuestionBank\Enums\SectionType::Listening,
            ]);

            \App\Modules\Assessment\Models\TestQuestion::create([
                'test_section_id' => $section->id,
                'question_id'     => $q->id,
                'order'           => $i,
                'points'          => 1,
            ]);

            $cCorrect = \App\Modules\QuestionBank\Models\QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => 'A',
                'content'     => 'Correct Choice',
                'is_correct'  => true,
            ]);

            $cWrong = \App\Modules\QuestionBank\Models\QuestionChoice::create([
                'question_id' => $q->id,
                'label'       => 'B',
                'content'     => 'Wrong Choice',
                'is_correct'  => false,
            ]);

            $isCorrect = ($i <= $correctCount);
            \App\Modules\Assessment\Models\Answer::create([
                'attempt_id'         => $attempt->id,
                'question_id'        => $q->id,
                'selected_choice_id' => $isCorrect ? $cCorrect->id : $cWrong->id,
                'is_correct'         => $isCorrect,
                'score_earned'       => $isCorrect ? 1 : 0,
            ]);
        }

        $attempt->update(['total_score' => $correctCount]);

        return $attempt;
    }

    /**
     * TEST RESULT-01: Submitted Attempt appears in Completed Results.
     * TEST RESULT-02: Expired Attempt does NOT appear in Completed Results.
     * TEST RESULT-03: InProgress Attempt does NOT appear in Completed Results.
     * TEST RESULT-04: Cancelled Attempt does NOT appear in Completed Results.
     * TEST RESULT-05: Completed Result action routes to canonical Candidate Result page.
     */
    public function test_result_01_to_05_completed_results_strictly_includes_only_submitted(): void
    {
        // 1. In progress attempt (Must NOT appear in Completed Results)
        $inProgressAttempt = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(30),
            'total_score' => 0,
        ]);

        // 2. Draft attempt (Must NOT appear in Completed Results)
        $draftAttempt = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Draft,
            'started_at' => now()->subMinutes(50),
            'total_score' => 0,
        ]);

        // 3. Expired attempt (Must NOT appear in Completed Results)
        $expiredAttempt = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->realTest->id,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subDays(1),
            'submitted_at' => now()->subDays(1)->addHours(2),
            'total_score' => 0,
        ]);

        // 4. Cancelled attempt (Must NOT appear in Completed Results)
        $cancelledAttempt = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->realTest->id,
            'status' => AttemptStatus::Cancelled,
            'started_at' => now()->subDays(2),
            'total_score' => 0,
        ]);

        // 5. Submitted completed result (Must appear)
        $submittedAttempt = $this->createSimulatorAttempt($this->candidateA, 68, 66);

        $response = $this->actingAs($this->candidateA)->get(route('candidate.my-results'));
        $response->assertStatus(200);

        // View data assertions: Only submitted attempt is present
        $response->assertViewHas('results', function ($results) use ($submittedAttempt, $inProgressAttempt, $draftAttempt, $expiredAttempt, $cancelledAttempt) {
            return $results->contains('id', $submittedAttempt->id)
                && !$results->contains('id', $inProgressAttempt->id)
                && !$results->contains('id', $draftAttempt->id)
                && !$results->contains('id', $expiredAttempt->id)
                && !$results->contains('id', $cancelledAttempt->id);
        });

        // Content assertions: finalized info present, in-progress actions strictly absent
        $response->assertSee($submittedAttempt->test->title);
        $response->assertSee('SIMULATOR');
        $response->assertSee('66 / 68');
        $response->assertSee('97.1%');
        $response->assertSee('PASSED');
        $response->assertSee(route('candidate.review', $submittedAttempt));
        $response->assertDontSee('Resume Exam');
        $response->assertDontSee('TIME EXPIRED');
    }

    /**
     * TEST COUNT-01: Candidate with 2 Submitted + 3 Expired -> Total Attempts = 5.
     * TEST COUNT-02: Same Candidate -> Completed Tests = 2.
     * TEST COUNT-03: Expired Attempts do NOT increment Completed Tests.
     * TEST COUNT-04: InProgress Attempt increments My Total Attempts but not Completed Tests.
     * TEST COUNT-05: Cancelled Attempt increments Total Attempts but not Completed Tests.
     */
    public function test_count_01_to_05_dashboard_kpi_counts_exact_semantics(): void
    {
        // Candidate with 2 Submitted + 3 Expired
        Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(1),
            'submitted_at' => now()->subDays(1)->addHour(),
        ]);
        Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);
        Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subDays(3),
        ]);
        Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subDays(2),
        ]);
        Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subDays(4),
        ]);

        $responseA = $this->actingAs($this->candidateA)->get(route('candidate.portal'));
        // COUNT-01: Total Attempts = 5
        $responseA->assertViewHas('myAttemptsCount', 5);
        // COUNT-02 & 03: Completed Tests = 2 (Expired are excluded)
        $responseA->assertViewHas('completedAttemptsCount', 2);

        // COUNT-04 & 05: Adding InProgress and Cancelled increments Total (5 + 2 = 7) but not Completed (remains 2)
        Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(10),
        ]);
        Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Cancelled,
            'started_at' => now()->subDays(5),
        ]);

        $responseA2 = $this->actingAs($this->candidateA)->get(route('candidate.portal'));
        $responseA2->assertViewHas('myAttemptsCount', 7);
        $responseA2->assertViewHas('completedAttemptsCount', 2);
    }

    /**
     * TEST SECURITY-01: Candidate A cannot view Candidate B Attempt History record.
     * TEST SECURITY-02: Candidate A cannot view Candidate B Result.
     * TEST SECURITY-03: Dashboard counts include only authenticated Candidate data.
     */
    public function test_security_01_02_03_data_isolation_between_candidates(): void
    {
        $attemptA = Attempt::create([
            'user_id' => $this->candidateA->id,
            'test_id' => $this->simulatorTest->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $attemptB = Attempt::create([
            'user_id' => $this->candidateB->id,
            'test_id' => $this->realTest->id,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subHours(3),
            'submitted_at' => now()->subHours(2),
        ]);

        // Security 01: Attempt History isolation
        $attemptsResp = $this->actingAs($this->candidateA)->get(route('candidate.my-attempts'));
        $attemptsResp->assertViewHas('attempts', function ($attempts) use ($attemptA, $attemptB) {
            return $attempts->contains('id', $attemptA->id) && !$attempts->contains('id', $attemptB->id);
        });

        // Security 02: Results isolation
        $resultsResp = $this->actingAs($this->candidateA)->get(route('candidate.my-results'));
        $resultsResp->assertViewHas('results', function ($results) use ($attemptA, $attemptB) {
            return $results->contains('id', $attemptA->id) && !$results->contains('id', $attemptB->id);
        });

        // Security 03: Dashboard count isolation
        $portalResp = $this->actingAs($this->candidateA)->get(route('candidate.portal'));
        $portalResp->assertViewHas('myAttemptsCount', 1);
        $portalResp->assertViewHas('completedAttemptsCount', 1);
    }

    /**
     * TEST REG-01: Simulator completed result still displays 75% threshold policy.
     * TEST REG-02: Wrong Answer Review remains functional.
     * TEST REG-03: Simulator certificate remains ineligible.
     */
    public function test_reg_01_02_03_simulator_invariants_preserved(): void
    {
        // Passed simulator attempt (66/68 = 97.06%)
        $simAttempt = $this->createSimulatorAttempt($this->candidateA, 68, 66);

        // REG-01: Result page honors 75% pass mark & practice notice
        $reviewResp = $this->actingAs($this->candidateA)->get(route('candidate.review', $simAttempt));
        $reviewResp->assertStatus(200);
        $reviewResp->assertSee('PASSED');
        $reviewResp->assertSee('Practice Score');
        $reviewResp->assertDontSee('Official Digital Certificate Issued!');

        // REG-02: Wrong Answer Review route exists and accessible
        $wrongResp = $this->actingAs($this->candidateA)->get(route('candidate.simulator.wrong-answers', $simAttempt));
        $wrongResp->assertStatus(200);
        $wrongResp->assertSee('Wrong Answer Review');

        // REG-03: Simulator certificate ineligible
        $certResp = $this->actingAs($this->candidateA)->get(route('candidate.my-certificates'));
        $certResp->assertStatus(200);
        $certResp->assertDontSee($simAttempt->test->title);
    }
}
