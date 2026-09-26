<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Engines\AttemptEngine;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestQuestion;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Modules\QuestionBank\Models\QuestionChoice;
use App\Services\BestResultResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MockTestTwoAttemptLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $candidate;

    protected User $otherCandidate;

    protected Test $mockTest;

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
        $this->admin = User::factory()->create(['email' => 'admin.ops@icedu.org', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->candidate = User::factory()->create(['name' => 'Jane Candidate', 'email' => 'jane.candidate@icedu.org', 'status' => 'active']);
        $this->candidate->assignRole('student');

        $this->otherCandidate = User::factory()->create(['name' => 'Other Candidate', 'email' => 'other.candidate@icedu.org', 'status' => 'active']);
        $this->otherCandidate->assignRole('student');

        // Create Question Bank & 2 Questions
        $bank = QuestionBank::create([
            'title' => 'TOEIC Bank Lifecycle',
            'slug' => 'toeic-bank-lifecycle',
            'type' => 'toeic',
            'created_by' => $this->admin->id,
            'is_approved' => true,
        ]);

        $this->q1 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'What is the correct answer for Q1?',
            'points' => 100,
        ]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'A', 'content' => 'Option A (Correct)', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q1->id, 'label' => 'B', 'content' => 'Option B (Wrong)', 'is_correct' => false]);

        $this->q2 = Question::create([
            'question_bank_id' => $bank->id,
            'prompt' => 'What is the correct answer for Q2?',
            'points' => 100,
        ]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'A', 'content' => 'Option A (Correct)', 'is_correct' => true]);
        QuestionChoice::create(['question_id' => $this->q2->id, 'label' => 'B', 'content' => 'Option B (Wrong)', 'is_correct' => false]);

        // Create Published Real Test (Mock Test)
        $this->mockTest = Test::create([
            'title' => 'TOEIC Full Simulation Test 2026',
            'slug' => 'toeic-full-sim-2026',
            'assessment_mode' => 'real_test',
            'status' => 'published',
            'is_published' => true,
            'duration' => 120,
            'pass_score' => 700,
            'created_by' => $this->admin->id,
        ]);

        $section = TestSection::create([
            'test_id' => $this->mockTest->id,
            'title' => 'Core Section',
            'order' => 1,
        ]);

        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $this->q1->id,
            'order' => 1,
        ]);
        TestQuestion::create([
            'test_section_id' => $section->id,
            'question_id' => $this->q2->id,
            'order' => 2,
        ]);

        // Commerce setup: Product, Order, Invoice, Payment
        $this->product = Product::create([
            'title' => 'TOEIC Simulation Product',
            'slug' => 'toeic-sim-prod',
            'product_type' => 'placement_test',
            'price' => 250000,
            'test_id' => $this->mockTest->id,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-MOCK-001',
            'user_id' => $this->candidate->id,
            'total_amount' => 250000,
            'status' => OrderStatus::Completed,
            'billing_details' => ['name' => 'Jane Candidate'],
        ]);

        $inv = Invoice::create([
            'invoice_number' => 'INV-MOCK-001',
            'order_id' => $order->id,
            'user_id' => $this->candidate->id,
            'amount' => 250000,
            'status' => 'paid',
            'due_date' => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'invoice_id' => $inv->id,
            'user_id' => $this->candidate->id,
            'amount' => 250000,
            'payment_method' => 'credit_card',
            'reference_number' => 'PAY-MOCK-001',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 250000,
            'total' => 250000,
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
     * TEST 1: Attempt 1 completes and enters pending decision.
     */
    public function test_attempt_1_completes_and_enters_pending_decision(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);

        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame($this->assignment->id, $attempt->assignment_id);

        $this->answerQuestionsForAttempt($attempt, 2);
        $attemptEngine->submitAttempt($attempt);

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertSame('pending_decision', $attempt->decision_status);
        $this->assertFalse($attempt->is_final);
        $this->assertSame('active', $this->assignment->fresh()->status);
        $this->assertCount(0, Certificate::all());

        // Review page renders Finalize and Retry buttons
        $response = $this->actingAs($this->candidate)->get(route('candidate.review', $attempt));
        $response->assertStatus(200);
        $response->assertSee('Mock Test Result Decision');
        $response->assertSee('Finalize Result &amp; Release Final Score', false);
        $response->assertSee('Retry Second Attempt');
    }

    /**
     * TEST 2: Candidate Finalizes Attempt 1.
     */
    public function test_candidate_finalizes_attempt_1(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt, 2);
        $attemptEngine->submitAttempt($attempt);

        $response = $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt));
        $response->assertRedirect(route('candidate.review', $attempt));

        $attempt->refresh();
        $assignment = $this->assignment->fresh();

        $this->assertTrue($attempt->is_final);
        $this->assertSame('finalized', $attempt->decision_status);
        $this->assertSame('completed', $assignment->status);
        $this->assertSame($attempt->id, $assignment->final_attempt_id);
        $this->assertNotNull($assignment->completed_at);

        // Third or restart attempt is blocked
        $this->expectException(\InvalidArgumentException::class);
        $attemptEngine->startAttempt($this->mockTest, $this->candidate);
    }

    /**
     * TEST 3: Candidate chooses Retry.
     */
    public function test_candidate_chooses_retry_and_starts_attempt_2(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1);
        $attemptEngine->submitAttempt($attempt1);

        $response = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt1->refresh();
        $this->assertSame('retried', $attempt1->decision_status);
        $this->assertFalse($attempt1->is_final);

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)
            ->where('attempt_number', 2)
            ->first();

        $this->assertNotNull($attempt2);
        $this->assertSame(AttemptStatus::InProgress, $attempt2->status);
        $this->assertSame($this->candidate->id, $attempt2->user_id);
        $this->assertSame(2, $this->assignment->fresh()->attempts_count);

        $response->assertRedirect(route('candidate.exam', $attempt2));
    }

    /**
     * TEST 4: Attempt 2 beats Attempt 1 (A1 = 650, A2 = 850).
     */
    public function test_attempt_2_beats_attempt_1(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1); // 100 pts
        $attemptEngine->submitAttempt($attempt1);

        // Candidate retries
        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 2); // 200 pts
        $attemptEngine->submitAttempt($attempt2);

        $attempt1->refresh();
        $attempt2->refresh();
        $assignment = $this->assignment->fresh();

        $this->assertTrue($attempt2->is_final);
        $this->assertSame('finalized', $attempt2->decision_status);

        $this->assertFalse($attempt1->is_final);
        $this->assertSame('retried', $attempt1->decision_status);

        $this->assertSame('completed', $assignment->status);
        $this->assertSame($attempt2->id, $assignment->final_attempt_id);

        // Explicit unit validation of BestResultResolver with 650 vs 850 scores
        $attempt1->update(['total_score' => 650.0]);
        $attempt2->update(['total_score' => 850.0]);
        $winner = app(BestResultResolver::class)->resolve($assignment);
        $this->assertSame($attempt2->id, $winner->id);
        $this->assertTrue($winner->is_final);
    }

    /**
     * TEST 5: Attempt 1 beats Attempt 2 (A1 = 750, A2 = 680).
     */
    public function test_attempt_1_beats_attempt_2(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 2); // 200 pts
        $attemptEngine->submitAttempt($attempt1);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 1); // 100 pts
        $attemptEngine->submitAttempt($attempt2);

        $attempt1->refresh();
        $attempt2->refresh();
        $assignment = $this->assignment->fresh();

        $this->assertTrue($attempt1->is_final);
        $this->assertSame('finalized', $attempt1->decision_status);

        $this->assertFalse($attempt2->is_final);
        $this->assertSame('retried', $attempt2->decision_status);

        $this->assertSame('completed', $assignment->status);
        $this->assertSame($attempt1->id, $assignment->final_attempt_id);

        // Explicit unit validation of BestResultResolver with 750 vs 680 scores
        $attempt1->update(['total_score' => 750.0]);
        $attempt2->update(['total_score' => 680.0]);
        $winner = app(BestResultResolver::class)->resolve($assignment);
        $this->assertSame($attempt1->id, $winner->id);
        $this->assertTrue($winner->is_final);
    }

    /**
     * TEST 6: Equal score tie-break (Earlier submitted Attempt 1 wins).
     */
    public function test_equal_score_tie_break_selects_earlier_attempt(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1);
        $attemptEngine->submitAttempt($attempt1);
        $attempt1->update(['submitted_at' => now()->subMinutes(30), 'total_score' => 720.0]);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 1);
        $attemptEngine->submitAttempt($attempt2);
        $attempt2->update(['submitted_at' => now(), 'total_score' => 720.0]);

        // Re-resolve to test equal 720.0 score tie-breaker
        $winner = app(BestResultResolver::class)->resolve($this->assignment);

        $attempt1->refresh();
        $attempt2->refresh();
        $assignment = $this->assignment->fresh();

        // Deterministic rule: Earlier completed attempt (Attempt 1) wins the tie-break
        $this->assertSame($attempt1->id, $winner->id);
        $this->assertTrue($attempt1->is_final);
        $this->assertSame('finalized', $attempt1->decision_status);
        $this->assertFalse($attempt2->is_final);
        $this->assertSame($attempt1->id, $assignment->final_attempt_id);
    }

    /**
     * TEST 7: Third attempt blocked server-side.
     */
    public function test_third_attempt_is_blocked_server_side(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1);
        $attemptEngine->submitAttempt($attempt1);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 2);
        $attemptEngine->submitAttempt($attempt2);

        // Attempting to retry or start Attempt 3 directly via controller/engine must fail
        $retryResponse = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt2));
        $retryResponse->assertRedirect(route('candidate.review', $attempt2));
        $retryResponse->assertSessionHas('error');

        $this->expectException(\InvalidArgumentException::class);
        $attemptEngine->startAttempt($this->mockTest, $this->candidate);
    }

    /**
     * TEST 8: Double-finalize protection (idempotency).
     */
    public function test_double_finalize_protection_is_idempotent(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt, 2);
        $attemptEngine->submitAttempt($attempt);

        // First finalize
        $res1 = $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt));
        $res1->assertRedirect(route('candidate.review', $attempt));

        $this->assertTrue($attempt->fresh()->is_final);
        $this->assertSame('completed', $this->assignment->fresh()->status);

        // Second finalize request must not corrupt state
        $res2 = $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt));
        $res2->assertRedirect(route('candidate.review', $attempt));

        $this->assertTrue($attempt->fresh()->is_final);
        $this->assertSame('completed', $this->assignment->fresh()->status);
    }

    /**
     * TEST 9: Double-retry protection (idempotency).
     */
    public function test_double_retry_protection_does_not_create_duplicate_attempt_2(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1);
        $attemptEngine->submitAttempt($attempt1);

        // First retry request
        $res1 = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->assertNotNull($attempt2);
        $res1->assertRedirect(route('candidate.exam', $attempt2));

        // Second retry request while Attempt 2 is in progress
        $res2 = $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));
        $res2->assertRedirect(route('candidate.exam', $attempt2));

        $this->assertSame(1, Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->count());
    }

    /**
     * TEST 10: Unauthorized candidate cannot finalize or retry another candidate's attempt.
     */
    public function test_unauthorized_candidate_cannot_finalize_or_retry_another_attempt(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt, 2);
        $attemptEngine->submitAttempt($attempt);

        // Other candidate attempts to finalize Jane's attempt
        $res1 = $this->actingAs($this->otherCandidate)->post(route('candidate.exam.finalize', $attempt));
        $res1->assertStatus(403);

        // Other candidate attempts to retry Jane's attempt
        $res2 = $this->actingAs($this->otherCandidate)->post(route('candidate.exam.retry', $attempt));
        $res2->assertStatus(403);

        $this->assertFalse($attempt->fresh()->is_final);
        $this->assertSame('pending_decision', $attempt->fresh()->decision_status);
    }

    /**
     * TEST 11: Certificate regression (submitAttempt does not automatically generate certificate).
     */
    public function test_submit_attempt_does_not_issue_certificate(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);

        // Perfect score (200 pts)
        $this->answerQuestionsForAttempt($attempt, 2);
        $attemptEngine->submitAttempt($attempt);

        // Zero certificates issued
        $this->assertCount(0, Certificate::all());
    }

    /**
     * TEST 12: Assignment disappearance from active candidate portal upon completion.
     */
    public function test_completed_assignment_disappears_from_active_candidate_portal(): void
    {
        // While active, Mock Test is visible in candidate available tests
        $portalRes1 = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $portalRes1->assertStatus(200);
        $portalRes1->assertSee('TOEIC Full Simulation Test 2026');

        // Complete assignment via finalization
        $attemptEngine = app(AttemptEngine::class);
        $attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt, 2);
        $attemptEngine->submitAttempt($attempt);

        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $attempt));

        // After completion, Mock Test no longer appears in candidate available tests
        $portalRes2 = $this->actingAs($this->candidate)->get(route('candidate.available-tests'));
        $portalRes2->assertStatus(200);
        $portalRes2->assertDontSee('TOEIC Full Simulation Test 2026');
    }

    /**
     * TEST 13: Historical preservation of both attempts after Attempt 2 resolution.
     */
    public function test_both_attempts_are_preserved_historically_after_resolution(): void
    {
        $attemptEngine = app(AttemptEngine::class);
        $attempt1 = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($attempt1, 1); // 100 pts
        $attemptEngine->submitAttempt($attempt1);

        $this->actingAs($this->candidate)->post(route('candidate.exam.retry', $attempt1));

        $attempt2 = Attempt::where('assignment_id', $this->assignment->id)->where('attempt_number', 2)->first();
        $this->answerQuestionsForAttempt($attempt2, 2); // 200 pts
        $attemptEngine->submitAttempt($attempt2);

        $allAttempts = Attempt::where('assignment_id', $this->assignment->id)->orderBy('attempt_number')->get();
        $this->assertCount(2, $allAttempts);

        $this->assertSame(100.0, (float) $allAttempts[0]->total_score);
        $this->assertFalse($allAttempts[0]->is_final);
        $this->assertSame('retried', $allAttempts[0]->decision_status);

        $this->assertSame(200.0, (float) $allAttempts[1]->total_score);
        $this->assertTrue($allAttempts[1]->is_final);
        $this->assertSame('finalized', $allAttempts[1]->decision_status);
    }

    /**
     * TEST 14: New paid assignment cycle remains possible without mutating historical completed attempts.
     */
    public function test_new_paid_assignment_creates_new_independent_lifecycle(): void
    {
        // 1. Complete Cycle 1
        $attemptEngine = app(AttemptEngine::class);
        $cycle1Attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->answerQuestionsForAttempt($cycle1Attempt, 2);
        $attemptEngine->submitAttempt($cycle1Attempt);
        $this->actingAs($this->candidate)->post(route('candidate.exam.finalize', $cycle1Attempt));

        $this->assertSame('completed', $this->assignment->fresh()->status);
        $this->assertTrue($cycle1Attempt->fresh()->is_final);

        // 2. New Paid Order for Cycle 2
        $order2 = Order::create([
            'order_number' => 'ORD-MOCK-002',
            'user_id' => $this->candidate->id,
            'total_amount' => 250000,
            'status' => OrderStatus::Completed,
            'billing_details' => ['name' => 'Jane Candidate'],
        ]);
        $inv2 = Invoice::create([
            'invoice_number' => 'INV-MOCK-002',
            'order_id' => $order2->id,
            'user_id' => $this->candidate->id,
            'amount' => 250000,
            'status' => 'paid',
            'due_date' => now()->addDays(7),
        ]);
        $payment2 = Payment::create([
            'invoice_id' => $inv2->id,
            'user_id' => $this->candidate->id,
            'amount' => 250000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'PAY-MOCK-002',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 250000,
            'total' => 250000,
        ]);

        $assignmentEngine = app(AssignmentEngine::class);
        $assignment2 = $assignmentEngine->assignToUser($this->mockTest, $this->candidate, $this->admin, $payment2, $order2);

        $this->assertNotSame($this->assignment->id, $assignment2->id);
        $this->assertSame('active', $assignment2->status);

        // Start attempt in Cycle 2
        $cycle2Attempt = $attemptEngine->startAttempt($this->mockTest, $this->candidate);
        $this->assertSame($assignment2->id, $cycle2Attempt->assignment_id);
        $this->assertSame(1, $cycle2Attempt->attempt_number);

        // Historical attempt remains completely intact
        $this->assertTrue($cycle1Attempt->fresh()->is_final);
        $this->assertSame('completed', $this->assignment->fresh()->status);
    }
}
