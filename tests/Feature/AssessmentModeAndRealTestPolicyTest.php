<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\AttemptAudioPlay;
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
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Models\ProductCategory;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Modules\QuestionBank\Models\Question;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentModeAndRealTestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $rm;
    protected User $admin;
    protected User $student;
    protected Test $simulatorTest;
    protected Test $realTest;
    protected Question $audioQuestion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->teacher = User::factory()->create(['name' => 'Teacher One', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->rm = User::factory()->create(['name' => 'RM Vance', 'status' => 'active']);
        $this->rm->assignRole('repository-manager');

        $this->admin = User::factory()->create(['name' => 'Admin Op', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->student = User::factory()->create(['name' => 'Student A', 'status' => 'active']);
        $this->student->assignRole('student');

        // Create Simulator Test
        $this->simulatorTest = Test::create([
            'title'            => 'TOEIC Simulator Practice 01',
            'slug'             => 'toeic-sim-01',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::Simulator,
            'duration_minutes' => 60,
            'pass_score'       => 400,
            'is_published'     => true,
            'status'           => 'approved',
            'created_by'       => $this->teacher->id,
        ]);

        $simSection = TestSection::create([
            'test_id' => $this->simulatorTest->id,
            'title'   => 'Part 1: Photographs',
            'order'   => 1,
        ]);

        $q1 = Question::create([
            'prompt'        => 'Look at the photo and choose the best statement.',
            'type'          => QuestionType::MultipleChoice,
            'points'        => 5,
            'audio_url'     => '/storage/media/sim_audio_01.mp3',
            'created_by'    => $this->teacher->id,
        ]);
        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => 'Option A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q1->id, 'label' => 'B', 'content' => 'Option B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $simSection->id, 'question_id' => $q1->id, 'order' => 1]);

        $q2 = Question::create([
            'prompt'        => 'Question 2 prompt text.',
            'type'          => QuestionType::MultipleChoice,
            'points'        => 5,
            'created_by'    => $this->teacher->id,
        ]);
        QuestionChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => 'Option A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $q2->id, 'label' => 'B', 'content' => 'Option B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $simSection->id, 'question_id' => $q2->id, 'order' => 2]);

        // Create Real Test
        $this->realTest = Test::create([
            'title'            => 'Official TOEIC Real Certification Test',
            'slug'             => 'official-toeic-real-01',
            'test_type'        => TestType::Toeic,
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 600,
            'is_published'     => true,
            'status'           => 'approved',
            'created_by'       => $this->rm->id,
        ]);

        $realSection = TestSection::create([
            'test_id' => $this->realTest->id,
            'title'   => 'Part 1: Real Photographs',
            'order'   => 1,
        ]);

        $this->audioQuestion = Question::create([
            'prompt'        => 'Listen to the audio once and choose the correct answer.',
            'type'          => QuestionType::MultipleChoice,
            'points'        => 10,
            'audio_url'     => '/storage/media/real_audio_01.mp3',
            'created_by'    => $this->teacher->id,
        ]);
        QuestionChoice::create(['question_id' => $this->audioQuestion->id, 'label' => 'A', 'content' => 'Choice A', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->audioQuestion->id, 'label' => 'B', 'content' => 'Choice B', 'is_correct' => false]);
        TestQuestion::create(['test_section_id' => $realSection->id, 'question_id' => $this->audioQuestion->id, 'order' => 1]);
    }

    /**
     * 1. Assessment Mode Recognized
     */
    public function test_assessment_mode_is_recognized_correctly(): void
    {
        $this->assertTrue($this->simulatorTest->isSimulator());
        $this->assertFalse($this->simulatorTest->isRealTest());
        $this->assertSame('Test Simulator', $this->simulatorTest->assessment_mode->label());

        $this->assertTrue($this->realTest->isRealTest());
        $this->assertFalse($this->realTest->isSimulator());
        $this->assertSame('Mock Test', $this->realTest->assessment_mode->label());
    }

    /**
     * 2. Active Attempt Uniqueness (No duplicates on re-start)
     */
    public function test_start_attempt_resumes_active_in_progress_attempt_without_creating_duplicates(): void
    {
        // First start creates attempt 1
        $response1 = $this->actingAs($this->student)->post(route('candidate.tests.start', $this->simulatorTest));
        $response1->assertStatus(302);

        $this->assertSame(1, Attempt::where('user_id', $this->student->id)->where('test_id', $this->simulatorTest->id)->count());
        $firstAttempt = Attempt::where('user_id', $this->student->id)->where('test_id', $this->simulatorTest->id)->first();

        // Second start (e.g. candidate navigated Back and clicked Start again)
        $response2 = $this->actingAs($this->student)->post(route('candidate.tests.start', $this->simulatorTest));
        $response2->assertRedirect(route('candidate.exam', $firstAttempt));

        // Must still be exactly 1 attempt
        $this->assertSame(1, Attempt::where('user_id', $this->student->id)->where('test_id', $this->simulatorTest->id)->count());
    }

    /**
     * 3. Simulator Mode Navigation & Policy
     */
    public function test_simulator_policy_allows_skipping_and_previous(): void
    {
        $policy = $this->simulatorTest->policy();
        $this->assertTrue($policy->canSkipQuestion());
        $this->assertTrue($policy->canGoPrevious());
        $this->assertTrue($policy->canJumpToQuestion());
        $this->assertTrue($policy->shouldRevisitUnanswered());
        $this->assertTrue($policy->canReplayAudio());
        $this->assertFalse($policy->requiresFullscreen());

        $response = $this->actingAs($this->student)->post(route('candidate.tests.start', $this->simulatorTest));
        $attempt = Attempt::where('user_id', $this->student->id)->first();

        $examView = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $examView->assertStatus(200);
        $examView->assertSee('TEST SIMULATOR');
        $examView->assertSee('Previous (P)');
    }

    /**
     * 4. Real Test Mode Navigation & Policy
     */
    public function test_real_test_policy_forbids_previous_and_skipping(): void
    {
        $policy = $this->realTest->policy();
        $this->assertFalse($policy->canSkipQuestion());
        $this->assertFalse($policy->canGoPrevious());
        $this->assertFalse($policy->canJumpToQuestion());
        $this->assertTrue($policy->requiresAnswerBeforeNext());
        $this->assertFalse($policy->shouldRevisitUnanswered());
        $this->assertFalse($policy->canReplayAudio());
        $this->assertTrue($policy->requiresFullscreen());

        // Assign Real Test to student
        CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->realTest->id,
            'status'  => 'active',
        ]);

        $this->actingAs($this->student)->post(route('candidate.tests.start', $this->realTest));
        $attempt = Attempt::where('user_id', $this->student->id)->where('test_id', $this->realTest->id)->first();

        $examView = $this->actingAs($this->student)->get(route('candidate.exam', $attempt));
        $examView->assertStatus(200);
        $examView->assertSee('SECURE MOCK TEST');
        $examView->assertDontSee('Previous (P)');
    }

    /**
     * 5. Available Tests Visibility (Simulator visible, unassigned Real Test excluded)
     */
    public function test_available_tests_shows_simulator_and_excludes_unassigned_real_test(): void
    {
        $response = $this->actingAs($this->student)->get(route('candidate.available-tests'));
        $response->assertStatus(200);

        // Simulator appears
        $response->assertSee('TOEIC Simulator Practice 01');

        // Unassigned Real Test is completely excluded from the list
        $response->assertDontSee('Official TOEIC Real Certification Test');
    }

    /**
     * 6. Real Test Payment Gating & Direct Start Rejection
     */
    public function test_real_test_requires_paid_assignment_and_rejects_unauthorized_start(): void
    {
        // Student attempts direct start without assignment -> 403 Forbidden
        $response = $this->actingAs($this->student)->post(route('candidate.tests.start', $this->realTest));
        $response->assertStatus(403);

        // Attempt was NOT created
        $this->assertSame(0, Attempt::where('user_id', $this->student->id)->where('test_id', $this->realTest->id)->count());

        // Instructions endpoint also rejects unauthorized access -> 403
        $instResponse = $this->actingAs($this->student)->get(route('candidate.tests.instructions', $this->realTest));
        $instResponse->assertStatus(403);

        // Admin attempts to assign unpaid student to Real Test -> Throws InvalidArgumentException
        $assignmentEngine = app(AssignmentEngine::class);
        $this->expectException(\InvalidArgumentException::class);
        $assignmentEngine->assignToUser($this->realTest, $this->student, $this->admin);
    }

    /**
     * 7. Payment Confirmation Makes Candidate Eligible Without Auto-Assigning Real Test
     */
    public function test_payment_paid_does_not_auto_assign_real_test_but_makes_candidate_eligible(): void
    {
        $cat = ProductCategory::create(['name' => 'Assessments', 'slug' => 'assessments']);
        $product = Product::create([
            'title'        => 'Official Real TOEIC Exam Voucher',
            'slug'         => 'real-toeic-voucher',
            'product_type' => 'assessment',
            'category_id'  => $cat->id,
            'price'        => 500000,
            'is_active'    => true,
            'test_id'      => $this->realTest->id,
        ]);

        $checkoutEngine = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
        $checkoutRes = $checkoutEngine->checkout($this->student, $product);

        $billingEngine = new BillingEngine;
        $payment = $billingEngine->createPayment($checkoutRes['invoice'], 'manual_transfer');
        $billingEngine->confirmPayment($payment, 'TXN-CONFIRM-001');

        // Verify Payment is PAID, Invoice is PAID, Order is COMPLETED
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame('paid', $checkoutRes['invoice']->fresh()->status->value);
        $this->assertSame(OrderStatus::Completed, $checkoutRes['order']->fresh()->status);

        // IMPORTANT BUSINESS RULE: CandidateTestAssignment is NOT automatically created for Real Test
        $this->assertFalse(CandidateTestAssignment::where('user_id', $this->student->id)->where('test_id', $this->realTest->id)->exists());

        // BUT Candidate is now ELIGIBLE for assignment
        $assignmentEngine = app(AssignmentEngine::class);
        $this->assertTrue($assignmentEngine->isPaymentEligible($this->realTest, $this->student));
        $this->assertFalse($assignmentEngine->isEligibleToStart($this->realTest, $this->student)); // Not startable yet (needs Admin assignment)

        // Real Test is NOT yet visible in Candidate Available Tests
        $availResp = $this->actingAs($this->student)->get(route('candidate.available-tests'));
        $availResp->assertDontSee('Official TOEIC Real Certification Test');
    }

    /**
     * 8. Admin Cannot Assign Unpaid Candidate to Real Test, But Can Assign Paid Eligible Candidate
     */
    public function test_admin_can_assign_paid_eligible_candidate_to_real_test(): void
    {
        $cat = ProductCategory::create(['name' => 'Assessments', 'slug' => 'assessments']);
        $product = Product::create([
            'title'        => 'Official Real TOEIC Exam Voucher',
            'slug'         => 'real-toeic-voucher',
            'product_type' => 'assessment',
            'category_id'  => $cat->id,
            'price'        => 500000,
            'is_active'    => true,
            'test_id'      => $this->realTest->id,
        ]);

        $otherUnpaidStudent = User::factory()->create(['name' => 'Unpaid Student', 'status' => 'active']);
        $otherUnpaidStudent->assignRole('student');

        // Admin attempts to assign unpaid student -> Fails with error
        $adminFailResponse = $this->actingAs($this->admin)->post(route('admin.tests.assign-candidate', $this->realTest), [
            'candidate_id' => $otherUnpaidStudent->id,
        ]);
        $adminFailResponse->assertSessionHas('error');
        $this->assertFalse(CandidateTestAssignment::where('user_id', $otherUnpaidStudent->id)->where('test_id', $this->realTest->id)->exists());

        // Now student pays for the test
        $checkoutEngine = new CheckoutEngine(new PricingEngine, new InvoiceEngine);
        $checkoutRes = $checkoutEngine->checkout($this->student, $product);

        $billingEngine = new BillingEngine;
        $payment = $billingEngine->createPayment($checkoutRes['invoice'], 'manual_transfer');
        $billingEngine->confirmPayment($payment, 'TXN-CONFIRM-002');

        // Admin explicitly assigns the eligible paid candidate
        $adminAssignResponse = $this->actingAs($this->admin)->post(route('admin.tests.assign-candidate', $this->realTest), [
            'candidate_id' => $this->student->id,
        ]);
        $adminAssignResponse->assertSessionHas('status');

        // Verify CandidateTestAssignment is now active
        $assignment = CandidateTestAssignment::where('user_id', $this->student->id)->where('test_id', $this->realTest->id)->first();
        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->isActive());
        $this->assertSame($this->admin->id, $assignment->assigned_by);

        // Candidate can now see the Real Test in Available Tests
        $availResp = $this->actingAs($this->student)->get(route('candidate.available-tests'));
        $availResp->assertSee('Official TOEIC Real Certification Test');

        // Candidate can now start the exam
        $startResp = $this->actingAs($this->student)->post(route('candidate.tests.start', $this->realTest));
        $startResp->assertStatus(302);
        $this->assertSame(1, Attempt::where('user_id', $this->student->id)->where('test_id', $this->realTest->id)->count());
    }

    /**
     * 9. Real Test Single Play Audio Enforcement
     */
    public function test_real_test_audio_enforces_single_play_per_attempt(): void
    {
        CandidateTestAssignment::create([
            'user_id' => $this->student->id,
            'test_id' => $this->realTest->id,
            'status'  => 'active',
        ]);

        $this->actingAs($this->student)->post(route('candidate.tests.start', $this->realTest));
        $attempt = Attempt::where('user_id', $this->student->id)->where('test_id', $this->realTest->id)->first();

        // Create temporary dummy mp3 file
        $audioPath = storage_path('app/public/media/real_audio_01.mp3');
        if (!file_exists(dirname($audioPath))) {
            mkdir(dirname($audioPath), 0777, true);
        }
        file_put_contents($audioPath, 'ID3DUMMYAUDIOCONTENT');

        // First play stream request -> Succeeds (200 / file response)
        $streamResponse1 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $this->audioQuestion]));
        $this->assertContains($streamResponse1->getStatusCode(), [200, 206]);

        // Verify AttemptAudioPlay record was created with play_count = 1
        $this->assertSame(1, AttemptAudioPlay::where('attempt_id', $attempt->id)->where('question_id', $this->audioQuestion->id)->count());

        // Second play stream request (replay attempt) -> Blocked with 403 Forbidden
        $streamResponse2 = $this->actingAs($this->student)->get(route('candidate.exam.audio-stream', [$attempt, $this->audioQuestion]));
        $streamResponse2->assertStatus(403);

        // Clean up dummy audio file
        if (file_exists($audioPath)) {
            unlink($audioPath);
        }
    }

    /**
     * 10. RM Intake Origin Enforcement for Real Tests
     */
    public function test_teacher_cannot_directly_create_real_test_draft_without_rm_intake(): void
    {
        // Teacher attempts to create Real Test directly
        $response = $this->actingAs($this->teacher)->post(route('admin.tests.store'), [
            'title'            => 'Teacher Direct Real Test',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 90,
            'pass_score'       => 500,
        ]);
        $response->assertStatus(403);

        // Teacher CAN create Simulator draft
        $simResponse = $this->actingAs($this->teacher)->post(route('admin.tests.store'), [
            'title'            => 'Teacher Direct Simulator',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 90,
            'pass_score'       => 500,
        ]);
        $simResponse->assertRedirect(route('admin.tests.index'));
        $this->assertTrue(Test::where('title', 'Teacher Direct Simulator')->exists());

        // RM creates Real Test draft from Assessment Request
        $request = AssessmentRequest::create([
            'title'        => 'SMK Real Certification Brief',
            'test_type'    => 'toeic',
            'requested_by' => $this->admin->id,
            'status'       => 'pending',
        ]);

        $rmDraftResponse = $this->actingAs($this->rm)->post(route('admin.repository-manager.assessment-requests.create-draft', $request), [
            'teacher_id'       => $this->teacher->id,
            'title'            => 'SMK Official TOEIC Real Exam',
            'test_type'        => 'toeic',
            'duration_minutes' => 120,
            'pass_score'       => 650,
        ]);

        $rmDraftResponse->assertRedirect(route('admin.repository-manager.assessment-requests.index'));
        $createdRealTest = Test::where('title', 'SMK Official TOEIC Real Exam')->first();
        $this->assertNotNull($createdRealTest);
        $this->assertTrue($createdRealTest->isRealTest());
        $this->assertSame($this->teacher->id, $createdRealTest->assigned_to);
    }
}
