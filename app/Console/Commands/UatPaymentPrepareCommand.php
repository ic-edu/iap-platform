<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Console\Command;

class UatPaymentPrepareCommand extends Command
{
    public const TARGET_CANDIDATE_EMAIL = 'student@icedu.org';
    public const TARGET_TEST_TITLE = 'TOEIC Mock Test — SMK Perhotelan (UAT)';
    public const UAT_PRICE = 750000.0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iap:uat-payment-prepare
                            {--force : Force execution in non-interactive mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and idempotently prepare local UAT payment and commerce chain for student@icedu.org';

    /**
     * Execute the console command.
     */
    public function handle(
        CheckoutEngine $checkoutEngine,
        BillingEngine $billingEngine,
        AssignmentEngine $assignmentEngine
    ): int {
        $this->info('===============================================================');
        $this->info('  iC.edu Assessment Platform (IAP) — Local UAT Payment Setup');
        $this->info('===============================================================');

        // 1. Enforce local environment guard
        if (!app()->environment('local', 'testing')) {
            $this->error('UAT Payment Preparation can ONLY run in local environment (APP_ENV=local).');
            return self::FAILURE;
        }

        // 2. Validate target candidate
        $candidate = User::where('email', self::TARGET_CANDIDATE_EMAIL)->first();
        if (!$candidate) {
            $this->error("Target candidate '" . self::TARGET_CANDIDATE_EMAIL . "' not found in database.");
            return self::FAILURE;
        }

        if (!$candidate->hasRole('student')) {
            $this->error("User '" . self::TARGET_CANDIDATE_EMAIL . "' does not have the 'student' candidate role.");
            return self::FAILURE;
        }

        $this->info("Candidate verified: {$candidate->name} ({$candidate->email}) [ID: {$candidate->id}]");

        // 3. Inspect persistent UAT Mock Test
        $test = Test::where('title', self::TARGET_TEST_TITLE)->first();
        if (!$test) {
            $this->error('Persistent UAT Mock Test missing — Phase 5B dataset exists only in test DB.');
            $this->warn("Please create and publish '" . self::TARGET_TEST_TITLE . "' via the real governance workflow first.");
            return self::FAILURE;
        }

        if (!$test->isRealTest()) {
            $this->error("Test '{$test->title}' is not configured with assessment_mode = real_test.");
            return self::FAILURE;
        }

        if (!$test->is_published && !in_array($test->status, ['published', 'approved'], true)) {
            $this->error("Test '{$test->title}' is not published/approved (Status: {$test->status}).");
            return self::FAILURE;
        }

        $this->info("UAT Mock Test verified: {$test->title} [ID: {$test->id}, Status: {$test->status}]");

        // 4. Product lookup / creation
        $product = Product::where('test_id', $test->id)->first();
        if (!$product) {
            $product = Product::create([
                'title'        => 'TOEIC Mock Test — SMK Perhotelan (UAT Access)',
                'slug'         => 'toeic-mock-smk-perhotelan-uat-access',
                'product_type' => 'assessment',
                'price'        => self::UAT_PRICE,
                'is_active'    => true,
                'is_featured'  => false,
                'test_id'      => $test->id,
            ]);
            $this->info("Created UAT Product: {$product->title} [ID: {$product->id}, Price: {$product->price}]");
        } else {
            $this->info("Existing UAT Product found: {$product->title} [ID: {$product->id}]");
        }

        // 5. Idempotency Check: Existing valid payment chain
        $existingPayment = Payment::where('user_id', $candidate->id)
            ->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid])
            ->whereHas('invoice.order.items', fn($q) => $q->where('product_id', $product->id))
            ->first();

        if ($existingPayment) {
            $order = $existingPayment->invoice?->order;
            $this->info('---------------------------------------------------------------');
            $this->info('  Existing valid UAT payment chain detected. Reusing records:');
            $this->line("  Order ID:   {$order?->id} ({$order?->order_number})");
            $this->line("  Invoice ID: {$existingPayment->invoice?->id} ({$existingPayment->invoice?->invoice_number})");
            $this->line("  Payment ID: {$existingPayment->id} (Status: {$existingPayment->status->value})");
            $this->info('---------------------------------------------------------------');

            // Verify eligibility
            $isEligible = $assignmentEngine->isPaymentEligible($test, $candidate, $existingPayment, $order);
            if ($isEligible) {
                $this->info('Payment eligibility verified: TRUE (Candidate is Paid & Eligible).');
                return self::SUCCESS;
            }
        }

        // 6. Execute Commerce flow via CheckoutEngine + BillingEngine
        $this->info('Creating commerce chain via CheckoutEngine and BillingEngine...');

        $checkoutResult = $checkoutEngine->checkout($candidate, $product);
        $order = $checkoutResult['order'];
        $invoice = $checkoutResult['invoice'];

        $payment = $billingEngine->createPayment($invoice, 'manual_transfer');
        $payment = $billingEngine->confirmPayment($payment, 'TXN-UAT-' . now()->timestamp);

        $this->info('---------------------------------------------------------------');
        $this->info('  UAT Commerce & Payment Chain Created:');
        $this->line("  Order ID:    {$order->id} ({$order->order_number})");
        $this->line("  Invoice ID:  {$invoice->id} ({$invoice->invoice_number})");
        $this->line("  Payment ID:  {$payment->id} (Status: {$payment->status->value})");
        $this->line("  Amount:      {$payment->amount}");
        $this->info('---------------------------------------------------------------');

        // 7. Verify eligibility checks
        $raGateCheck = User::where('id', $candidate->id)
            ->whereHas('orders.invoice.payments', fn($p) => $p->whereIn('status', [PaymentStatus::Success, PaymentStatus::Paid]))
            ->exists();

        $assignmentEngineCheck = $assignmentEngine->isPaymentEligible($test, $candidate, $payment, $order);

        $this->info("AssessmentRequestController RA Gate Check: " . ($raGateCheck ? 'PASSED ✅' : 'FAILED ❌'));
        $this->info("AssignmentEngine Mock Test Eligibility Check: " . ($assignmentEngineCheck ? 'PASSED ✅' : 'FAILED ❌'));

        if ($raGateCheck && $assignmentEngineCheck) {
            $this->info('===============================================================');
            $this->info('  HUMAN UAT PAYMENT READY: candidate student@icedu.org is PAID.');
            $this->info('===============================================================');
            return self::SUCCESS;
        }

        $this->error('Payment eligibility verification failed.');
        return self::FAILURE;
    }
}
