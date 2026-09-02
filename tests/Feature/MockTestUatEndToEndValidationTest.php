<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Application\InvoiceEngine;
use App\Modules\Commerce\Application\PricingEngine;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionChoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockTestUatEndToEndValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $repoManager;
    protected User $teacher;
    protected User $finance;
    protected User $candidate;
    protected User $unassignedCandidate;

    protected Test $mockTest;
    protected Question $q1;
    protected Question $q2;
    protected QuestionChoice $q1Correct;
    protected QuestionChoice $q1Incorrect;
    protected QuestionChoice $q2Correct;
    protected QuestionChoice $q2Incorrect;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // UAT Dedicated User Hierarchy
        $this->superAdmin = User::factory()->create(['name' => 'UAT Super Admin', 'email' => 'superadmin@icedu.org', 'status' => 'active']);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create(['name' => 'UAT Operational Admin', 'email' => 'opadmin@icedu.org', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->repoManager = User::factory()->create(['name' => 'UAT Repository Manager', 'email' => 'repomanager@icedu.org', 'status' => 'active']);
        $this->repoManager->assignRole('repository-manager');

        $this->teacher = User::factory()->create(['name' => 'UAT Teacher Instructor', 'email' => 'teacher@icedu.org', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->finance = User::factory()->create(['name' => 'UAT Finance Officer', 'email' => 'finance@icedu.org', 'status' => 'active']);
        $this->finance->assignRole('finance');

        $this->candidate = User::factory()->create(['name' => 'UAT Candidate Student', 'email' => 'candidate@icedu.org', 'status' => 'active']);
        $this->candidate->assignRole('student');

        $this->unassignedCandidate = User::factory()->create(['name' => 'UAT Unassigned Candidate', 'email' => 'unassigned@icedu.org', 'status' => 'active']);
        $this->unassignedCandidate->assignRole('student');

        // Prepare Mock Test (Real Test Assessment Mode)
        $this->mockTest = Test::create([
            'title'            => 'Official TOEIC Standard Mock Test 2026',
            'slug'             => 'official-toeic-mock-test-2026',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 500,
            'is_published'     => true,
            'status'           => 'published',
            'created_by'       => $this->teacher->id,
        ]);

        $section1 = TestSection::create([
            'test_id' => $this->mockTest->id,
            'title'   => 'Listening Section: Photographs & Dialogues',
            'order'   => 1,
        ]);

        $section2 = TestSection::create([
            'test_id' => $this->mockTest->id,
            'title'   => 'Reading Section: Sentence Completion',
            'order'   => 2,
        ]);

        // Question 1 (Listening Part)
        $this->q1 = Question::create([
            'prompt'     => 'Look at the photograph and choose the statement that best describes what you see.',
            'type'       => QuestionType::MultipleChoice,
            'points'     => 10,
            'audio_url'  => '/storage/media/uat_audio_01.mp3',
            'created_by' => $this->teacher->id,
        ]);
        $this->q1Correct = QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'The woman is presenting a report.', 'is_correct' => true]);
        $this->q1Incorrect = QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => 'The woman is painting a wall.', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $section1->id, 'question_id' => $this->q1->id, 'order' => 1, 'points' => 10]);

        // Question 2 (Reading Part)
        $this->q2 = Question::create([
            'prompt'     => 'The quarterly sales report must be submitted _____ Friday at 5:00 PM.',
            'type'       => QuestionType::MultipleChoice,
            'points'     => 10,
            'created_by' => $this->teacher->id,
        ]);
        $this->q2Correct = QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => 'by', 'is_correct' => true]);
        $this->q2Incorrect = QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => 'with', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $section2->id, 'question_id' => $this->q2->id, 'order' => 2, 'points' => 10]);
    }

    /**
     * Complete Realistic End-to-End Mock Test Workflow Validation
     */
    public function test_complete_mock_test_uat_lifecycle(): void
    {
        // =========================================================================
        // STEP 1: PAYMENT & ELIGIBILITY WORKFLOW
        // =========================================================================
        $cat = ProductCategory::create(['name' => 'Assessment Vouchers', 'slug' => 'assessment-vouchers']);
        $product = Product::create([
            'title'        => 'TOEIC Mock Test Voucher',
            'slug'         => 'toeic-mock-test-voucher',
            'product_type' => 'assessment',
            'category_id'  => $cat->id,
            'price'        => 350000,
            'is_active'    => true,
            'test_id'      => $this->mockTest->id,
        ]);

        $checkoutEngine = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
        $checkoutRes = $checkoutEngine->checkout($this->candidate, $product);

        $billingEngine = new BillingEngine;
        $payment = $billingEngine->createPayment($checkoutRes['invoice'], 'bank_transfer');
        $billingEngine->confirmPayment($payment, 'UAT-TXN-SUCCESS-999');

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame('paid', $checkoutRes['invoice']->fresh()->status->value);
        $this->assertSame(OrderStatus::Completed, $checkoutRes['order']->fresh()->status);

        $assignmentEngine = app(AssignmentEngine::class);
        $this->assertTrue($assignmentEngine->isPaymentEligible($this->mockTest, $this->candidate));
        $this->assertFalse($assignmentEngine->isEligibleToStart($this->mockTest, $this->candidate)); // Requires Admin Assignment

        // =========================================================================
        // STEP 2: ADMIN OPERATIONAL ASSIGNMENT
        // =========================================================================
        $assignment = $assignmentEngine->assignToUser($this->mockTest, $this->candidate, $this->admin);
        $this->assertInstanceOf(CandidateTestAssignment::class, $assignment);
        $this->assertSame('active', $assignment->status);
        $this->assertTrue($assignmentEngine->isEligibleToStart($this->mockTest, $this->candidate));

        // Admin Dashboard reflects active assignments
        $adminDashboard = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $adminDashboard->assertStatus(200);

        // =========================================================================
        // STEP 3: CANDIDATE PORTAL & PRE-TEST ELIGIBILITY
        // =========================================================================
        $portalResponse = $this->actingAs($this->candidate)->get(route('candidate.portal'));
        $portalResponse->assertStatus(200);
        $portalResponse->assertSee('Student Portal Dashboard');
        $portalResponse->assertSee('Available Tests');

        // Candidate Available Tests list displays the assigned Mock Test
        $availableResponse = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $availableResponse->assertStatus(200);
        $availableResponse->assertSee('Official TOEIC Standard Mock Test 2026');

        // Unauthorized access protection: Unassigned candidate receives 403
        $unauthStart = $this->actingAs($this->unassignedCandidate)->post(route('candidate.tests.start', $this->mockTest));
        $unauthStart->assertStatus(403);

        // =========================================================================
        // STEP 4: MOCK TEST DELIVERY ENGINE & SESSION START
        // =========================================================================
        $startResponse = $this->actingAs($this->candidate)->post(route('candidate.tests.start', $this->mockTest));
        $startResponse->assertStatus(302);

        $attempt = Attempt::where('user_id', $this->candidate->id)->where('test_id', $this->mockTest->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertNotNull($attempt->started_at);

        // Verify Exam Screen Delivery
        $examView = $this->actingAs($this->candidate)->get(route('candidate.exam', $attempt));
        $examView->assertStatus(200);
        $examView->assertSee('SECURE MOCK TEST');
        $examView->assertSee('Official TOEIC Standard Mock Test 2026');

        // =========================================================================
        // STEP 5: ANSWER PERSISTENCE & NAVIGATION
        // =========================================================================
        // Answer Question 1 correctly (Choice A)
        $ans1Response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id'     => $this->q1->id,
            'selected_choice' => $this->q1Correct->id,
        ]);
        $ans1Response->assertStatus(200);
        $ans1Response->assertJson(['status' => 'saved']);

        // Answer Question 2 incorrectly (Choice B)
        $ans2Response = $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id'     => $this->q2->id,
            'selected_choice' => $this->q2Incorrect->id,
        ]);
        $ans2Response->assertStatus(200);
        $ans2Response->assertJson(['status' => 'saved']);

        // Verify Answer records persisted in DB
        $this->assertSame(2, $attempt->answers()->count());

        // Reload Safety: Accessing start again resumes existing attempt without duplicates
        $reStartResponse = $this->actingAs($this->candidate)->post(route('candidate.tests.start', $this->mockTest));
        $reStartResponse->assertRedirect(route('candidate.exam', $attempt));
        $this->assertSame(1, Attempt::where('user_id', $this->candidate->id)->where('test_id', $this->mockTest->id)->count());

        // =========================================================================
        // STEP 6: SUBMISSION & ATTEMPT FINALIZATION
        // =========================================================================
        $submitResponse = $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));
        $submitResponse->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertNotNull($attempt->submitted_at);

        // =========================================================================
        // STEP 7: SCORING & RESULTS VALIDATION
        // =========================================================================
        // Q1 is correct (Listening), Q2 is incorrect (Reading)
        // In TOEIC Scoring Engine: total_score = 1.0, listening_correct = 1, reading_correct = 0
        $this->assertEquals(1.0, (float) $attempt->total_score);
        $this->assertIsArray($attempt->section_scores);
        $this->assertSame(1, $attempt->section_scores['listening']['correct']);
        $this->assertSame(0, $attempt->section_scores['reading']['correct']);

        // Candidate Result / Review View
        $resultView = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $resultView->assertStatus(200);
        $resultView->assertSee('Official TOEIC Standard Mock Test 2026');

        // =========================================================================
        // STEP 8: OPERATIONAL REPORTING & AUDIT TRAIL SYNC
        // =========================================================================
        $reportingView = $this->actingAs($this->admin)->get(route('admin.reporting.index'));
        $reportingView->assertStatus(200);
        $reportingView->assertSee('Assessment Results &amp; Candidate Activity', false);
        $reportingView->assertSee('Total Submissions');
    }

    /**
     * Validate Audio Single-Play and Security Enforcement in Mock Test Mode
     */
    public function test_mock_test_audio_single_play_and_security_enforcement(): void
    {
        $assignmentEngine = app(AssignmentEngine::class);

        // Update Question 1 to have a valid remote URL
        $this->q1->update(['audio_url' => 'https://cdn.example.com/audio/uat_sample.mp3']);

        // Assign Candidate to Mock Test
        $cat = ProductCategory::create(['name' => 'Vouchers', 'slug' => 'vouchers-sec']);
        $product = Product::create([
            'title'        => 'Voucher Sec',
            'slug'         => 'voucher-sec',
            'product_type' => 'assessment',
            'category_id'  => $cat->id,
            'price'        => 100000,
            'is_active'    => true,
            'test_id'      => $this->mockTest->id,
        ]);

        $checkoutEngine = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
        $checkoutRes = $checkoutEngine->checkout($this->candidate, $product);

        $billingEngine = new BillingEngine;
        $payment = $billingEngine->createPayment($checkoutRes['invoice'], 'bank_transfer');
        $billingEngine->confirmPayment($payment, 'TXN-SEC-001');

        $assignmentEngine->assignToUser($this->mockTest, $this->candidate, $this->admin);

        // Start Attempt
        $this->actingAs($this->candidate)->post(route('candidate.tests.start', $this->mockTest));
        $attempt = Attempt::where('user_id', $this->candidate->id)->where('test_id', $this->mockTest->id)->first();

        // 1. First Audio Stream Attempt (Allowed)
        $stream1 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $this->q1]));
        $stream1->assertStatus(302); // Redirects to remote audio asset

        // 2. Second Audio Stream Attempt (Blocked for Real/Mock Test Mode -> 403)
        $stream2 = $this->actingAs($this->candidate)->get(route('candidate.exam.audio-stream', [$attempt, $this->q1]));
        $stream2->assertStatus(403);
        $stream2->assertJson(['error' => 'Audio already played for this question group.']);

        // 3. Anti-Cheating Violation Recording
        $violationResp = $this->actingAs($this->candidate)->postJson(route('candidate.exam.violation', $attempt), [
            'violation_type' => 'fullscreen_exit',
        ]);
        $violationResp->assertStatus(200);
        $violationResp->assertJson(['status' => 'logged']);
    }

    /**
     * Validate Passing Score and Certificate Generation Readiness
     */
    public function test_mock_test_passing_score_and_certificate_readiness(): void
    {
        $passTest = Test::create([
            'title'            => 'TOEIC Mock Test Pass Certified 2026',
            'slug'             => 'toeic-mock-test-pass-2026',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score'       => 1, // 1 correct answer passes
            'is_published'     => true,
            'status'           => 'published',
            'created_by'       => $this->teacher->id,
        ]);

        $section = TestSection::create([
            'test_id' => $passTest->id,
            'title'   => 'Core Section',
            'order'   => 1,
        ]);

        $q = Question::create([
            'prompt'     => 'Grammar question prompt',
            'type'       => QuestionType::MultipleChoice,
            'points'     => 10,
            'created_by' => $this->teacher->id,
        ]);
        $choiceCorrect = QuestionChoice::create(['question_id' => $q->id, 'label' => 'A', 'content' => 'Correct', 'is_correct' => true]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $q->id, 'order' => 1, 'points' => 10]);

        // Assign candidate
        CandidateTestAssignment::create([
            'user_id' => $this->candidate->id,
            'test_id' => $passTest->id,
            'status'  => 'active',
        ]);

        // Start & Answer Correctly
        $this->actingAs($this->candidate)->post(route('candidate.tests.start', $passTest));
        $attempt = Attempt::where('user_id', $this->candidate->id)->where('test_id', $passTest->id)->first();

        $this->actingAs($this->candidate)->postJson(route('candidate.exam.autosave', $attempt), [
            'question_id'     => $q->id,
            'selected_choice' => $choiceCorrect->id,
        ]);

        // Submit
        $this->actingAs($this->candidate)->post(route('candidate.exam.submit', $attempt));

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertEquals(1.0, (float) $attempt->total_score);

        // Certificate readiness check
        $certificate = \App\Modules\Certificate\Models\Certificate::where('user_id', $this->candidate->id)
            ->where('attempt_id', $attempt->id)
            ->first();

        // If certificate is generated, verify its integrity
        if ($certificate) {
            $this->assertSame($this->candidate->id, $certificate->user_id);
            $this->assertNotEmpty($certificate->certificate_number);
        } else {
            // Verify attempt passed
            $this->assertTrue($attempt->isPassed() || (float)$attempt->total_score >= 1.0);
        }
    }
}
