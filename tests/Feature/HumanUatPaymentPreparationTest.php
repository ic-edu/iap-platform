<?php

namespace Tests\Feature;

use App\Console\Commands\UatPaymentPrepareCommand;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Assessment\Services\TestBuilderService;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Phase 5C — Persistent Human UAT Payment Preparation Tests
 *
 * Validates the local UAT Commerce & Payment preparation mechanism
 * ensuring student@icedu.org receives legitimate paid eligibility
 * through the canonical CheckoutEngine → BillingEngine chain.
 */
class HumanUatPaymentPreparationTest extends \Tests\TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $unpaidCandidate;
    protected User $rm;
    protected User $teacher;
    protected Test $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Create designated target candidate
        $this->candidate = User::create([
            'name'     => 'Candidate Student',
            'email'    => 'student@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate->assignRole('student');

        // Create second unpaid candidate for negative testing
        $this->unpaidCandidate = User::create([
            'name'     => 'Unpaid Student',
            'email'    => 'unpaid@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->unpaidCandidate->assignRole('student');

        // Staff users
        $this->rm = User::create([
            'name'     => 'Repository Manager',
            'email'    => 'repomanager@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->rm->assignRole('repository-manager');

        $this->teacher = User::create([
            'name'     => 'Teacher Instructor',
            'email'    => 'teacher@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->teacher->assignRole('teacher');

        // Build canonical 3-question TOEIC Part 5 Mock Test fixture
        $this->test = $this->buildUatMockTest();
    }

    private function buildUatMockTest(): Test
    {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test — SMK Perhotelan (UAT)',
            'slug'             => 'toeic-mock-smk-perhotelan-uat-' . Str::random(5),
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'scoring_method'   => 'automatic',
            'duration_minutes' => 30,
            'pass_score'       => 0,
            'created_by'       => $this->rm->id,
            'assigned_to'      => $this->teacher->id,
            'status'           => 'published',
            'is_published'     => true,
        ]);

        $section = TestSection::create([
            'test_id' => $test->id,
            'title'   => 'Section 1: Reading — Part 5 (Incomplete Sentences)',
            'order'   => 1,
        ]);

        $service = app(TestBuilderService::class);
        $prompts = [
            'The hotel manager _____ staff to adhere to all hospitality protocols.',
            'New vocational students at SMK Perhotelan must _____ front desk procedures by day one.',
            'Room service supervisors are _____ for maintaining guest satisfaction ratings.',
        ];

        foreach ($prompts as $prompt) {
            $service->createAssessmentQuestion($section, [
                'prompt'        => $prompt,
                'section'       => 'reading',
                'part_number'   => 5,
                'question_type' => 'multiple_choice',
                'difficulty'    => 'medium',
                'points'        => 5,
                'choices'       => [
                    ['label' => 'A', 'content' => 'remind',    'is_correct' => false],
                    ['label' => 'B', 'content' => 'reminds',   'is_correct' => true],
                    ['label' => 'C', 'content' => 'reminded',  'is_correct' => false],
                    ['label' => 'D', 'content' => 'reminding', 'is_correct' => false],
                ],
            ]);
        }

        return $test;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 1: Target UAT candidate exists with student role
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_01_target_uat_candidate_exists(): void
    {
        $user = User::where('email', 'student@icedu.org')->first();

        $this->assertNotNull($user);
        $this->assertEquals('student@icedu.org', $user->email);
        $this->assertEquals('active', $user->status);
        $this->assertTrue($user->hasRole('student'));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 2: UAT Mock Test exists in test fixture with 3 questions
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_02_uat_mock_test_exists_in_fixture(): void
    {
        $test = Test::where('title', 'TOEIC Mock Test — SMK Perhotelan (UAT)')->first();

        $this->assertNotNull($test);
        $this->assertTrue($test->isRealTest());
        $this->assertTrue((bool) $test->is_published);
        $this->assertEquals('published', $test->status);

        $test->load('sections.testQuestions');
        $this->assertEquals(3, $test->sections->sum(fn($s) => $s->testQuestions->count()));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 3: UAT Product links to UAT Mock Test
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_03_uat_product_links_to_uat_mock_test(): void
    {
        Artisan::call('iap:uat-payment-prepare');

        $product = Product::where('test_id', $this->test->id)->first();

        $this->assertNotNull($product);
        $this->assertEquals('TOEIC Mock Test — SMK Perhotelan (UAT Access)', $product->title);
        $this->assertEquals($this->test->id, $product->test_id);
        $this->assertEquals('assessment', $product->product_type);
        $this->assertEquals(750000.0, $product->price);
        $this->assertTrue((bool) $product->is_active);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 4: Checkout/Billing creates valid Order → OrderItem → Invoice → Payment chain
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_04_checkout_billing_creates_valid_commerce_chain(): void
    {
        $exitCode = Artisan::call('iap:uat-payment-prepare');
        $this->assertEquals(0, $exitCode);

        // Check Order
        $order = Order::where('user_id', $this->candidate->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('completed', is_object($order->status) ? $order->status->value : $order->status);

        // Check OrderItem
        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertNotNull($item);
        $this->assertNotNull($item->product);
        $this->assertEquals($this->test->id, $item->product->test_id);

        // Check Invoice
        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('paid', is_object($invoice->status) ? $invoice->status->value : $invoice->status);

        // Check Payment
        $payment = Payment::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($this->candidate->id, $payment->user_id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 5: Payment status is success/paid
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_05_payment_status_is_success_paid(): void
    {
        Artisan::call('iap:uat-payment-prepare');

        $payment = Payment::where('user_id', $this->candidate->id)->first();

        $this->assertNotNull($payment);
        $this->assertEquals('success', is_object($payment->status) ? $payment->status->value : $payment->status);
        $this->assertEquals(832500.0, $payment->amount); // 750,000 + 11% standard commerce tax
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 6: AssignmentEngine.isPaymentEligible() returns true
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_06_assignment_engine_is_payment_eligible_returns_true(): void
    {
        Artisan::call('iap:uat-payment-prepare');

        $engine = app(AssignmentEngine::class);

        $isEligible = $engine->isPaymentEligible($this->test, $this->candidate);
        $this->assertTrue($isEligible, 'Candidate must be payment-eligible for UAT Mock Test.');

        // Verify RA eligibility query also passes
        $raCheck = User::where('id', $this->candidate->id)
            ->whereHas('orders.invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
            ->exists();
        $this->assertTrue($raCheck, 'RA Assessment Request eligibility gate must pass.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 7: Unpaid candidate returns false
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_07_unpaid_candidate_returns_false(): void
    {
        Artisan::call('iap:uat-payment-prepare');

        $engine = app(AssignmentEngine::class);

        $isEligible = $engine->isPaymentEligible($this->test, $this->unpaidCandidate);
        $this->assertFalse($isEligible, 'Unpaid candidate must NOT be payment-eligible.');

        // Verify assignment exception on unpaid candidate
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/requires a confirmed PAID transaction/i');
        $engine->assignToUser($this->test, $this->unpaidCandidate, $this->rm);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 8: Running preparation twice is idempotent (zero duplicate records)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_08_running_preparation_twice_is_idempotent(): void
    {
        // First run
        $code1 = Artisan::call('iap:uat-payment-prepare');
        $this->assertEquals(0, $code1);

        $ordersCount1   = Order::count();
        $invoicesCount1 = Invoice::count();
        $paymentsCount1 = Payment::count();
        $productsCount1 = Product::count();

        // Second run
        $code2 = Artisan::call('iap:uat-payment-prepare');
        $this->assertEquals(0, $code2);

        $this->assertEquals($ordersCount1, Order::count(), 'Orders count must not change on repeated run.');
        $this->assertEquals($invoicesCount1, Invoice::count(), 'Invoices count must not change on repeated run.');
        $this->assertEquals($paymentsCount1, Payment::count(), 'Payments count must not change on repeated run.');
        $this->assertEquals($productsCount1, Product::count(), 'Products count must not change on repeated run.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 9: Unrelated finance records remain untouched
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_09_unrelated_finance_records_remain_untouched(): void
    {
        // Create an unrelated payment belonging to another user
        $unrelatedPayment = Payment::create([
            'user_id'          => $this->rm->id,
            'reference_number' => 'REF-UNRELATED-001',
            'payment_gateway'  => 'manual_transfer',
            'status'           => 'success',
            'amount'           => 500000,
        ]);

        Artisan::call('iap:uat-payment-prepare');

        $unrelatedPayment->refresh();
        $this->assertEquals('REF-UNRELATED-001', $unrelatedPayment->reference_number);
        $this->assertEquals($this->rm->id, $unrelatedPayment->user_id);
        $this->assertEquals(500000, $unrelatedPayment->amount);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 10: StudentApplication remains untouched
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_10_student_application_remains_untouched(): void
    {
        $app = StudentApplication::create([
            'registration_id' => 'APP-UAT-TEST',
            'student_name'    => 'Test Student',
            'selected_program'=> 'TOEIC',
        ]);

        Artisan::call('iap:uat-payment-prepare');

        $app->refresh();
        $this->assertEquals('APP-UAT-TEST', $app->registration_id);
        $this->assertNull($app->user_id);
        $this->assertEquals(1, StudentApplication::count());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TEST 11: UAT payment preparation refuses when target candidate is missing
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_11_uat_payment_preparation_fails_if_candidate_missing(): void
    {
        // Delete candidate to test guard
        $this->candidate->delete();

        $exitCode = Artisan::call('iap:uat-payment-prepare');
        $this->assertEquals(1, $exitCode);
    }
}
