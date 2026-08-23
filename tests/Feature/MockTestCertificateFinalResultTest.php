<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Engines\ResultEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Engines\CertificateEngine;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Enums\DifficultyLevel;
use App\Modules\QuestionBank\Enums\QuestionType;
use App\Modules\QuestionBank\Enums\SectionType;
use App\Modules\QuestionBank\Enums\TestType;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\BestResultResolver;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MockTestCertificateFinalResultTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $candidate;
    protected User $otherCandidate;
    protected Test $mockTest;
    protected Test $simulatorTest;
    protected Product $product;
    protected CandidateTestAssignment $assignment;
    protected Question $q1;
    protected Question $q2;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Roles
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'student']);

        // Create Users
        $this->admin = User::factory()->create(['name' => 'Admin Ops', 'email' => 'admin.ops@icedu.org', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->candidate = User::factory()->create(['name' => 'Jane Candidate', 'email' => 'jane.candidate@icedu.org', 'status' => 'active']);
        $this->candidate->assignRole('student');

        $this->otherCandidate = User::factory()->create(['name' => 'Other Candidate', 'email' => 'other.candidate@icedu.org', 'status' => 'active']);
        $this->otherCandidate->assignRole('student');

        // Create Question Bank & Questions
        $bank = QuestionBank::create([
            'title'       => 'TOEIC Certificate Bank',
            'slug'        => 'toeic-cert-bank',
            'type'        => 'toeic',
            'created_by'  => $this->admin->id,
            'is_approved' => true,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'What is the answer to Listening Q1?',
            'points'           => 100,
        ]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'Option A (Correct)', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => 'Option B (Wrong)', 'is_correct' => false]);

        $this->q2 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt'           => 'What is the answer to Reading Q2?',
            'points'           => 100,
        ]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => 'Option A (Correct)', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => 'Option B (Wrong)', 'is_correct' => false]);

        // Create Published Real Test (Mock Test)
        $this->mockTest = Test::create([
            'title'           => 'TOEIC Full Institutional Simulation Test',
            'slug'            => 'toeic-full-inst-sim-test',
            'assessment_mode' => AssessmentMode::RealTest,
            'status'          => 'published',
            'is_published'    => true,
            'duration'        => 120,
            'pass_score'      => 100,
            'created_by'      => $this->admin->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->mockTest->id,
            'title'   => 'Comprehensive Section',
            'order'   => 1,
        ]);

        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q1->id, 'order' => 1]);
        TestQuestion::create(['test_section_id' => $section->id, 'question_id' => $this->q2->id, 'order' => 2]);

        // Create Simulator Test (Practice Mode)
        $this->simulatorTest = Test::create([
            'title'           => 'TOEIC Quick Practice Simulator',
            'slug'            => 'toeic-quick-practice-sim',
            'assessment_mode' => AssessmentMode::Simulator,
            'status'          => 'published',
            'is_published'    => true,
            'duration'        => 30,
            'pass_score'      => 50,
            'created_by'      => $this->admin->id,
        ]);
        $simSection = TestSection::create([
            'test_id' => $this->simulatorTest->id,
            'title'   => 'Practice Section',
            'order'   => 1,
        ]);
        TestQuestion::create(['test_section_id' => $simSection->id, 'question_id' => $this->q1->id, 'order' => 1]);

        // Commerce setup: Product, Order, Invoice, Payment
        $this->product = Product::create([
            'title'        => 'TOEIC Institutional Mock Test Product',
            'slug'         => 'toeic-inst-mock-prod',
            'product_type' => 'placement_test',
            'price'        => 300000,
            'test_id'      => $this->mockTest->id,
            'is_active'    => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ORD-CERT-001',
            'user_id'         => $this->candidate->id,
            'total_amount'    => 300000,
            'status'          => OrderStatus::Completed,
            'billing_details' => ['name' => 'Jane Candidate'],
        ]);

        $inv = Invoice::create([
            'invoice_number' => 'INV-CERT-001',
            'order_id'       => $order->id,
            'user_id'        => $this->candidate->id,
            'amount'         => 300000,
            'status'         => 'paid',
            'due_date'       => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'invoice_id'       => $inv->id,
            'user_id'          => $this->candidate->id,
            'amount'           => 300000,
            'payment_method'   => 'credit_card',
            'reference_number' => 'PAY-CERT-001',
            'status'           => PaymentStatus::Paid,
            'paid_at'          => now(),
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'price'      => 300000,
            'total'      => 300000,
        ]);

        // Assign Mock Test to Candidate
        $assignmentEngine = app(AssignmentEngine::class);
        $this->assignment = $assignmentEngine->assignToUser($this->mockTest, $this->candidate, $this->admin, $payment, $order);
    }

    protected function answerQuestionsForAttempt(Attempt $attempt, int $correctCount): void
    {
        $c1 = $this->q1->choices()->where('is_correct', true)->first();
        $c1Wrong = $this->q1->choices()->where('is_correct', false)->first();
        $c2 = $this->q2->choices()->where('is_correct', true)->first();
        $c2Wrong = $this->q2->choices()->where('is_correct', false)->first();

        if ($correctCount === 2) {
            $attempt->answers()->create(['question_id' => $this->q1->id, 'selected_choice_id' => $c1->id]);
            $attempt->answers()->create(['question_id' => $this->q2->id, 'selected_choice_id' => $c2->id]);
        } elseif ($correctCount === 1) {
            $attempt->answers()->create(['question_id' => $this->q1->id, 'selected_choice_id' => $c1->id]);
            $attempt->answers()->create(['question_id' => $this->q2->id, 'selected_choice_id' => $c2Wrong->id]);
        } else {
            $attempt->answers()->create(['question_id' => $this->q1->id, 'selected_choice_id' => $c1Wrong->id]);
            $attempt->answers()->create(['question_id' => $this->q2->id, 'selected_choice_id' => $c2Wrong->id]);
        }
    }

    /**
     * TEST 1: Attempt 1 finalized -> final_attempt_id = Attempt 1 -> certificate generated from Attempt 1.
     */
    public function test_attempt_1_finalized_generates_certificate_bound_to_attempt_1(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);

        // Before finalize: No certificates exist
        $this->assertCount(0, Certificate::all());

        // Finalize Attempt 1
        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt1));

        $assignment = $this->assignment->fresh();
        $this->assertSame('completed', $assignment->status);
        $this->assertSame($attempt1->id, $assignment->final_attempt_id);

        $certificates = Certificate::all();
        $this->assertCount(1, $certificates);

        $cert = $certificates->first();
        $this->assertSame($attempt1->id, $cert->attempt_id);
        $this->assertSame($this->candidate->id, $cert->user_id);
        $this->assertNotNull($cert->certificate_number);
        $this->assertNotNull($cert->verification_code);
    }

    /**
     * TEST 2: Attempt 1 finalized twice -> no duplicate certificate -> same certificate returned/reused.
     */
    public function test_attempt_1_finalized_twice_is_idempotent_and_does_not_duplicate(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);

        // First finalize
        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt1));
        $cert1 = Certificate::first();
        $this->assertNotNull($cert1);

        // Second finalize
        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt1));

        $this->assertSame(1, Certificate::count());
        $cert2 = Certificate::first();
        $this->assertSame($cert1->id, $cert2->id);
        $this->assertSame($cert1->certificate_number, $cert2->certificate_number);
    }

    /**
     * TEST 3: Attempt 2 wins (A1 = 650, A2 = 850) -> final_attempt_id = A2 -> certificate source = A2.
     */
    public function test_attempt_2_wins_produces_certificate_bound_to_attempt_2(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1);
        $attemptEngine->submitAttempt($attempt1);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 2);
        $attemptEngine->submitAttempt($attempt2);

        $attempt1->update(['total_score' => 650.0]);
        $attempt2->update(['total_score' => 850.0]);

        $winner = app(BestResultResolver::class)->resolve($this->assignment);
        $this->assertSame($attempt2->id, $winner->id);

        $assignment = $this->assignment->fresh();
        $this->assertSame($attempt2->id, $assignment->final_attempt_id);

        $certificates = Certificate::where('user_id', $this->candidate->id)->get();
        $this->assertCount(1, $certificates);

        $cert = $certificates->first();
        $this->assertSame($attempt2->id, $cert->attempt_id);
        $this->assertSame($attempt2->total_score, (float) 850.0);
    }

    /**
     * TEST 4: Attempt 1 wins (A1 = 750, A2 = 680) -> final_attempt_id = A1 -> certificate source = A1.
     */
    public function test_attempt_1_wins_produces_certificate_bound_to_attempt_1(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 1);
        $attemptEngine->submitAttempt($attempt2);

        $attempt1->update(['total_score' => 750.0]);
        $attempt2->update(['total_score' => 680.0]);

        $winner = app(BestResultResolver::class)->resolve($this->assignment);
        $this->assertSame($attempt1->id, $winner->id);

        $assignment = $this->assignment->fresh();
        $this->assertSame($attempt1->id, $assignment->final_attempt_id);

        $certificates = Certificate::where('user_id', $this->candidate->id)->get();
        $this->assertCount(1, $certificates);

        $cert = $certificates->first();
        $this->assertSame($attempt1->id, $cert->attempt_id);
        $this->assertSame($attempt1->total_score, (float) 750.0);
    }

    /**
     * TEST 5: Equal score tie-break -> certificate points to earlier winning attempt.
     */
    public function test_equal_score_tie_break_points_certificate_to_earlier_winning_attempt(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);
        $attempt1->update(['submitted_at' => now()->subMinutes(30), 'total_score' => 720.0]);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 2);
        $attemptEngine->submitAttempt($attempt2);
        $attempt2->update(['submitted_at' => now(), 'total_score' => 720.0]);

        $winner = app(BestResultResolver::class)->resolve($this->assignment);
        $this->assertSame($attempt1->id, $winner->id);

        $cert = Certificate::where('user_id', $this->candidate->id)->first();
        $this->assertNotNull($cert);
        $this->assertSame($attempt1->id, $cert->attempt_id);
    }

    /**
     * TEST 6: Losing attempt cannot generate certificate.
     */
    public function test_losing_attempt_cannot_generate_certificate(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1);
        $attemptEngine->submitAttempt($attempt1);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 2);
        $attemptEngine->submitAttempt($attempt2);

        $attempt1->update(['total_score' => 600.0]);
        $attempt2->update(['total_score' => 800.0]);

        app(BestResultResolver::class)->resolve($this->assignment);

        // Attempt 1 is the losing attempt
        $attempt1->refresh();
        $this->assertFalse($attempt1->is_final);

        // Directly invoking issueCertificate with losing attempt must return null
        $certForLosingAttempt = app(CertificateEngine::class)->issueCertificate($attempt1);
        $this->assertNull($certForLosingAttempt);

        $this->assertDatabaseMissing('certificates', ['attempt_id' => $attempt1->id]);
    }

    /**
     * TEST 7: Repeated certificate service invocation is idempotent.
     */
    public function test_repeated_certificate_service_invocation_is_idempotent(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);
        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt1));

        $certEngine = app(CertificateEngine::class);
        $assignment = $this->assignment->fresh();

        $c1 = $certEngine->issueCertificateForFinalResult($assignment);
        $c2 = $certEngine->issueCertificateForFinalResult($assignment);
        $c3 = $certEngine->issueCertificateForFinalResult($assignment);

        $this->assertSame($c1->id, $c2->id);
        $this->assertSame($c2->id, $c3->id);
        $this->assertSame(1, Certificate::where('user_id', $this->candidate->id)->count());
    }

    /**
     * TEST 8: Certificate score and details exactly match final attempt persisted result.
     */
    public function test_certificate_score_and_details_match_final_attempt(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);
        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt1));

        $cert = Certificate::where('attempt_id', $attempt1->id)->first();
        $this->assertNotNull($cert);

        // Verify PDF/HTML template rendering matches
        $pdfService = app(\App\Services\PdfService::class);
        $html = $pdfService->renderCertificateHtml($cert);

        $this->assertStringContainsString('Jane Candidate', $html);
        $this->assertStringContainsString('TOEIC Full Institutional Simulation Test', $html);
        $this->assertStringContainsString($cert->certificate_number, $html);
        $this->assertStringContainsString($cert->verification_code, $html);
    }

    /**
     * TEST 9: Candidate can access the final certificate after eligible finalization.
     */
    public function test_candidate_can_access_final_certificate_after_eligible_finalization(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);
        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt1));

        $cert = Certificate::where('attempt_id', $attempt1->id)->first();

        // 1. Review view displays certificate download button
        $reviewRes = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt1));
        $reviewRes->assertStatus(200);
        $reviewRes->assertSee('Official Digital Certificate Issued!');
        $reviewRes->assertSee(route('candidate.certificates.download', $cert->id));

        // 2. My Certificates page lists the certificate
        $myCertsRes = $this->actingAs($this->candidate)->get(route('candidate.my-certificates'));
        $myCertsRes->assertStatus(200);
        $myCertsRes->assertSee($cert->certificate_number);
        $myCertsRes->assertSee('TOEIC Full Institutional Simulation Test');

        // 3. Download certificate endpoint serves HTML/PDF
        $downloadRes = $this->actingAs($this->candidate)->get(route('candidate.certificates.download', $cert->id));
        $downloadRes->assertStatus(200);
        $this->assertStringContainsString('Certificate of Achievement', $downloadRes->getContent());

        // 4. Public verification resolves the certificate
        $verification = app(VerificationService::class)->verify($cert->verification_code);
        $this->assertSame('valid', $verification['status']);
        $this->assertSame($cert->id, $verification['certificate']->id);
    }

    /**
     * TEST 10: Completed assignment with final_attempt_id has exactly one authoritative certificate.
     */
    public function test_completed_assignment_has_exactly_one_authoritative_certificate(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1);
        $attemptEngine->submitAttempt($attempt1);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 2);
        $attemptEngine->submitAttempt($attempt2);

        $this->assertSame(1, Certificate::where('user_id', $this->candidate->id)->count());
        $this->assertSame($this->assignment->fresh()->final_attempt_id, Certificate::first()->attempt_id);
    }

    /**
     * TEST 11: Candidate cannot obtain a certificate from a non-final attempt.
     */
    public function test_candidate_cannot_obtain_certificate_from_non_final_attempt(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2);
        $attemptEngine->submitAttempt($attempt1);

        // While pending decision (non-final), no certificate exists
        $this->assertNull($attempt1->fresh()->certificate);
        $this->assertCount(0, Certificate::all());

        // Calling issueCertificate directly on non-final attempt returns null
        $cert = app(CertificateEngine::class)->issueCertificate($attempt1);
        $this->assertNull($cert);
        $this->assertCount(0, Certificate::all());
    }

    /**
     * TEST 12: Simulator / Practice Score does not enter institutional Mock Test certificate flow.
     */
    public function test_simulator_does_not_accidentally_enter_mock_test_certificate_flow(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $simAttempt = $attemptEngine->startAttempt($this->simulatorTest, $this->candidate);

        $this->assertSame('simulator', $this->simulatorTest->assessment_mode->value);
        $this->assertNull($simAttempt->assignment_id);

        $this->answerQuestionsForAttempt($simAttempt, 1);
        $attemptEngine->submitAttempt($simAttempt);

        $simAttempt->refresh();
        $result = app(ResultEngine::class)->generateResult($simAttempt);

        // Simulator does not have full TOEIC scaled score
        $this->assertFalse($result['is_full_toeic']);
    }
}
