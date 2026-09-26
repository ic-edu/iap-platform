<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\EvaluationStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecureCbtFullscreenAndViolationClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected Test $realTest;
    protected Test $simulatorTest;
    protected Question $question1;
    protected QuestionChoice $choice1A;
    protected CandidateTestAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->candidate = User::factory()->create([
            'name' => 'Secure Candidate',
            'email' => 'secure.candidate@iap.test',
            'status' => 'active',
        ]);
        $this->candidate->assignRole('student');

        // 1. Real Test
        $this->realTest = Test::create([
            'title' => 'TOEIC Secure Mock Test Class 9A',
            'slug' => 'toeic-secure-mock-test-9a',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidate->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->realTest->id,
            'title' => 'Part 1: Photograph',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 45,
            'order' => 1,
        ]);

        $this->question1 = Question::create([
            'prompt' => 'Photograph Question 1',
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

        // 2. Simulator Test
        $this->simulatorTest = Test::create([
            'title' => 'TOEIC Practice Simulator',
            'slug' => 'toeic-practice-sim',
            'test_type' => TestType::Toeic,
            'assessment_mode' => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score' => 75,
            'is_published' => true,
            'status' => 'published',
            'created_by' => $this->candidate->id,
        ]);

        $simSection = TestSection::create([
            'test_id' => $this->simulatorTest->id,
            'title' => 'Listening Practice',
            'section_type' => SectionType::Listening,
            'duration_minutes' => 30,
            'order' => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $simSection->id,
            'question_id' => $this->question1->id,
            'order' => 1,
            'points' => 10,
        ]);
    }

    // ==========================================
    // 1. EVENT CLASSIFICATION TESTS
    // ==========================================

    public function test_fullscreen_enter_does_not_increment_violations_count(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'violations_count' => 0,
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'fullscreen_enter',
        ]);

        $response->assertStatus(200);
        $attempt->refresh();
        $this->assertEquals(0, $attempt->violations_count);
    }

    public function test_repeated_fullscreen_enter_preserves_zero_violations_count(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'violations_count' => 0,
            'seed' => 'seed123',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
                'violation_type' => 'fullscreen_enter',
            ]);
        }

        $attempt->refresh();
        $this->assertEquals(0, $attempt->violations_count);
    }

    public function test_fullscreen_exit_increments_violations_count_exactly_once(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'violations_count' => 0,
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'fullscreen_exit',
        ]);

        $response->assertStatus(200);
        $attempt->refresh();
        $this->assertEquals(1, $attempt->violations_count);
    }

    public function test_repeated_distinct_fullscreen_exit_events_increment_violations_count_accurately(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'violations_count' => 0,
            'seed' => 'seed123',
        ]);

        // Exit #1
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), ['violation_type' => 'fullscreen_exit']);
        $attempt->refresh();
        $this->assertEquals(1, $attempt->violations_count);

        // Re-enter (operational - 0 increment)
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), ['violation_type' => 'fullscreen_enter']);
        $attempt->refresh();
        $this->assertEquals(1, $attempt->violations_count);

        // Exit #2
        $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), ['violation_type' => 'fullscreen_exit']);
        $attempt->refresh();
        $this->assertEquals(2, $attempt->violations_count);
    }

    public function test_window_blur_increments_violations_count(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'violations_count' => 0,
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'window_blur',
        ]);

        $response->assertStatus(200);
        $attempt->refresh();
        $this->assertEquals(1, $attempt->violations_count);
    }

    public function test_audio_completed_preserves_completion_and_does_not_increment_violations_count(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'violations_count' => 0,
            'seed' => 'seed123',
        ]);

        $audioPlay = AttemptAudioPlay::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->question1->id,
            'play_count' => 1,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'audio_completed',
            'question_id' => $this->question1->id,
        ]);

        $response->assertStatus(200);
        $attempt->refresh();
        $this->assertEquals(0, $attempt->violations_count);

        $audioPlay->refresh();
        $this->assertNotNull($audioPlay->completed_at);
    }

    public function test_unknown_violation_type_rejected_with_validation_error(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->realTest->id,
            'user_id' => $this->candidate->id,
            'assignment_id' => $this->assignment->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::PendingEvaluation,
            'started_at' => now(),
            'violations_count' => 0,
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'some_arbitrary_unauthorized_type',
        ]);

        $response->assertStatus(422);
        $attempt->refresh();
        $this->assertEquals(0, $attempt->violations_count);
    }

    // ==========================================
    // 2. FULLSCREEN UX & VIEW ASSERTIONS
    // ==========================================

    public function test_real_test_cbt_view_removes_persistent_fullscreen_button_and_renders_required_overlays(): void
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

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Header has SECURE MOCK TEST and timer
        $this->assertStringContainsString('SECURE MOCK TEST', $content);
        $this->assertStringContainsString('id="countdown-timer"', $content);

        // 2. Header does NOT render persistent Fullscreen button
        $this->assertStringNotContainsString('<span>⛶</span> Fullscreen', $content);

        // 3. Directions card renders Begin Part button
        $this->assertStringContainsString('Begin Part 1: Photograph', $content);
        $this->assertStringContainsString('onclick="startSectionQuestions(0)"', $content);

        // 4. Overlays exist
        $this->assertStringContainsString('id="fullscreen-warning-overlay"', $content);
        $this->assertStringContainsString('Secure Fullscreen Exited', $content);
        $this->assertStringContainsString('Return to Fullscreen', $content);

        $this->assertStringContainsString('id="fullscreen-required-overlay"', $content);
        $this->assertStringContainsString('Fullscreen Required', $content);
        $this->assertStringContainsString('Enter Fullscreen &amp; Continue', $content);
    }

    public function test_simulator_cbt_view_does_not_render_secure_fullscreen_overlays(): void
    {
        $attempt = Attempt::create([
            'test_id' => $this->simulatorTest->id,
            'user_id' => $this->candidate->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'evaluation_status' => EvaluationStatus::NotRequired,
            'started_at' => now(),
            'seed' => 'seed123',
        ]);

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Simulator badge
        $this->assertStringContainsString('TEST SIMULATOR', $content);

        // 2. No secure fullscreen overlays
        $this->assertStringNotContainsString('id="fullscreen-warning-overlay"', $content);
        $this->assertStringNotContainsString('id="fullscreen-required-overlay"', $content);
    }

    public function test_real_test_cbt_view_contains_unified_navigate_and_render_delivery_unit_gates_and_palette_direction_guard(): void
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

        $response = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Canonical gate functions defined
        $this->assertStringContainsString('function renderDeliveryUnit(unitIdx, targetQIndex = null)', $content);
        $this->assertStringContainsString('function navigateDeliveryUnit(unitIdx, targetQIndex = null)', $content);

        // 2. Directions screen palette guard exists
        $this->assertStringContainsString('if (currentUnitIdx === -1)', $content);
        $this->assertStringContainsString('Please begin the section to start the assessment in secure fullscreen mode.', $content);

        // 3. Hash preservation of pending target question index
        $this->assertStringContainsString('let pendingTargetQIndex = null;', $content);
        $this->assertStringContainsString('pendingTargetQIndex = resolvedQIdx;', $content);
        $this->assertStringContainsString('renderDeliveryUnit(targetU, targetQ);', $content);
    }
}

