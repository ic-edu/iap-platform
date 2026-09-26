<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidatePostUatUxSemanticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $teacher;

    protected Test $test;

    protected TestSection $section1;

    protected TestSection $section2;

    protected QuestionBank $bank;

    protected CandidateTestAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->student = User::factory()->create(['name' => 'CA01 Candidate', 'status' => 'active']);
        $this->student->assignRole('student');

        $this->teacher = User::factory()->create(['name' => 'Teacher Evaluator', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->test = Test::create([
            'title' => 'TOEIC Official Simulation Test',
            'slug' => 'toeic-official-simulation-test',
            'test_type' => TestType::Toeic,
            'duration_minutes' => 120,
            'pass_score' => 650,
            'is_published' => true,
            'status' => 'approved',
            'assessment_mode' => AssessmentMode::RealTest,
            'created_by' => $this->teacher->id,
        ]);

        $this->section1 = TestSection::create([
            'test_id' => $this->test->id,
            'title' => 'Part 1: Photographs',
            'section_type' => SectionType::Listening,
            'instructions' => 'Directions for Part 1',
            'order' => 1,
        ]);

        $this->section2 = TestSection::create([
            'test_id' => $this->test->id,
            'title' => 'Part 2: Question-Response',
            'section_type' => SectionType::Listening,
            'instructions' => 'Directions for Part 2',
            'order' => 2,
        ]);

        $this->bank = QuestionBank::create([
            'title' => 'Post-UAT Bank',
            'slug' => 'post-uat-bank',
            'test_type' => TestType::Toeic,
            'created_by' => $this->teacher->id,
        ]);

        $q1 = Question::create([
            'question_bank_id' => $this->bank->id,
            'prompt' => 'Q1 Prompt',
            'question_type' => QuestionType::MultipleChoice,
            'points' => 5,
        ]);
        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'A', 'is_correct' => true]);

        TestQuestion::create(['test_section_id' => $this->section1->id, 'question_id' => $q1->id, 'order' => 1]);

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

    public function test_show_section_intro_contains_viewport_scroll_and_cta_focus(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));

        $response->assertOk();
        $response->assertSee("window.scrollTo({ top: 0, behavior: 'smooth' })", false);
        $response->assertSee('btn-begin-section-');
        $response->assertSee('showPassageTypeTransition');
        $response->assertSee('btn-begin-transition-passage');
    }

    public function test_submitted_result_review_renders_submitted_at_label(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->parse('2026-09-26 10:00:00'),
            'submitted_at' => now()->parse('2026-09-26 11:30:00'),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertOk();
        $response->assertSee('Submitted at 26 Sep 2026, 11:30');
        $response->assertDontSee('Time expired at');
        $response->assertDontSee('2026-09-26T');
    }

    public function test_expired_result_review_renders_time_expired_at_with_canonical_deadline(): void
    {
        // Started at 13:45:09 with 120m duration -> canonical deadline is 15:45:09
        // submitted_at occurred later at 16:20:00 upon browser refresh
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->parse('2026-09-26 13:45:09'),
            'submitted_at' => now()->parse('2026-09-26 16:20:00'),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertOk();
        $response->assertSee('Time expired at 26 Sep 2026, 15:45');
        $response->assertDontSee('Submitted at');
        $response->assertDontSee('16:20');
        $response->assertDontSee('2026-09-26T');
    }

    public function test_attempt_model_completion_helpers(): void
    {
        $submitted = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->parse('2026-09-26 10:00:00'),
            'submitted_at' => now()->parse('2026-09-26 11:30:00'),
        ]);

        $this->assertEquals('Submitted at', $submitted->getCompletionLabel());
        $this->assertEquals('2026-09-26 11:30:00', $submitted->getCanonicalCompletionTimestamp()->format('Y-m-d H:i:s'));
        $this->assertEquals('Submitted at 26 Sep 2026, 11:30', $submitted->getFormattedCompletionDisplay());

        $expired = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->parse('2026-09-26 13:45:09'),
            'submitted_at' => now()->parse('2026-09-26 16:20:00'),
        ]);

        $this->assertEquals('Time expired at', $expired->getCompletionLabel());
        $this->assertEquals('2026-09-26 15:45:09', $expired->getCanonicalCompletionTimestamp()->format('Y-m-d H:i:s'));
        $this->assertEquals('Time expired at 26 Sep 2026, 15:45', $expired->getFormattedCompletionDisplay());

        $cancelled = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 3,
            'status' => AttemptStatus::Cancelled,
            'started_at' => now()->parse('2026-09-26 14:00:00'),
        ]);

        $this->assertEquals('Cancelled at', $cancelled->getCompletionLabel());
        $this->assertEquals($cancelled->updated_at->format('Y-m-d H:i:s'), $cancelled->getCanonicalCompletionTimestamp()->format('Y-m-d H:i:s'));
        $this->assertEquals("Cancelled at {$cancelled->updated_at->format('d M Y, H:i')}", $cancelled->getFormattedCompletionDisplay());
    }

    public function test_cancelled_result_review_renders_cancelled_at_label(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Cancelled,
            'started_at' => now()->parse('2026-09-26 10:00:00'),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.review', $attempt));

        $response->assertOk();
        $response->assertSee('Cancelled at '.$attempt->updated_at->format('d M Y, H:i'));
        $response->assertDontSee('Submitted at');
        $response->assertDontSee('Time expired at');
    }

    public function test_attempt_history_score_visibility_and_no_pass_score_denominator(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'total_score' => 755,
            'is_final' => true,
            'started_at' => now()->parse('2026-09-26 13:45:09'),
            'submitted_at' => now()->parse('2026-09-26 16:20:00'),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.my-attempts'));

        $response->assertOk();
        $response->assertSee('TIME EXPIRED');
        $response->assertSee('755');
        $response->assertSee('Final Result');
        // Must NOT use pass_score (650) as denominator!
        $response->assertDontSee('/ 650');
        $response->assertSee('26 Sep 2026, 15:45');
    }

    public function test_completed_tests_kpi_deduplicates_by_assignment_lifecycle(): void
    {
        // Scenario 1: Same assignment with 2 completed attempts -> Completed Tests = 1
        Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subHours(5),
            'submitted_at' => now()->subHours(4),
        ]);

        Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subHours(3),
            'submitted_at' => now()->subHours(1),
            'is_final' => true,
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('Completed Tests');
        $response->assertSee('aria-label="Completed Tests: 1"', false);
        $response->assertSee('aria-label="My Total Attempts: 2"', false);
    }

    public function test_completed_tests_kpi_counts_distinct_assignments_as_separate(): void
    {
        // Assignment 1 with completed attempt
        Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2)->addHour(),
        ]);

        // Assignment 2 with completed attempt
        $assignment2 = CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'assigned_by' => $this->teacher->id,
            'assigned_at' => now()->subDay(),
            'max_attempts' => 2,
            'attempts_count' => 1,
            'status' => 'completed',
        ]);

        Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $assignment2->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'started_at' => now()->subDay(),
            'submitted_at' => now()->subDay()->addHour(),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('aria-label="Completed Tests: 2"', false);
        $response->assertSee('aria-label="My Total Attempts: 2"', false);
    }

    public function test_completed_tests_kpi_deduplicates_multiple_simulator_attempts_for_same_test(): void
    {
        $simTest = Test::create([
            'title' => 'TOEIC Simulator Quick Test',
            'slug' => 'toeic-simulator-quick-test',
            'test_type' => TestType::Toeic,
            'duration_minutes' => 45,
            'pass_score' => 70,
            'is_published' => true,
            'status' => 'approved',
            'assessment_mode' => AssessmentMode::Simulator,
            'created_by' => $this->teacher->id,
        ]);

        // 3 completed attempts for the same simulator test
        for ($i = 1; $i <= 3; $i++) {
            Attempt::create([
                'test_id' => $simTest->id,
                'user_id' => $this->student->id,
                'assignment_id' => null,
                'attempt_number' => $i,
                'status' => AttemptStatus::Submitted,
                'started_at' => now()->subHours(10 - $i),
                'submitted_at' => now()->subHours(10 - $i)->addMinutes(30),
            ]);
        }

        $response = $this->actingAs($this->student)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('aria-label="Completed Tests: 1"', false);
        $response->assertSee('aria-label="My Total Attempts: 3"', false);
    }

    public function test_digital_certificates_card_clean_information_architecture(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->test->id,
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 2,
            'status' => AttemptStatus::Expired,
            'total_score' => 755,
            'is_final' => true,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        Certificate::create([
            'user_id' => $this->student->id,
            'attempt_id' => $attempt->id,
            'certificate_number' => 'CERT-2026-TEST-001',
            'verification_code' => 'VERIFY-001',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($this->student)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('Digital Certificates');
        $response->assertSee('Open Certificates');
        $response->assertSee('1 Certificate(s) Issued');
        $response->assertSee('aria-label="My Certificates: 1"', false);

        // Verify Attempt History link was removed from Digital Certificates card
        $content = $response->getContent();
        $this->assertStringNotContainsString('hover:text-amber-600 dark:hover:text-amber-400 font-medium hover:underline">Attempt History', $content);
    }
}
