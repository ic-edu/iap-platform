<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceVoucherRedemptionLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidateUser;
    protected User $financeUser;
    protected Product $product;
    protected Coupon $coupon;
    protected CheckoutEngine $checkoutEngine;
    protected BillingEngine $billingEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->candidateUser = User::factory()->create();
        $this->candidateUser->assignRole('student');

        $this->financeUser = User::factory()->create();
        $this->financeUser->assignRole('finance');

        $this->product = Product::create([
            'title'             => 'TOEIC Exam Package',
            'slug'              => 'toeic-exam-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 100000,
            'is_active'         => true,
        ]);

        $this->coupon = Coupon::create([
            'code'        => 'CAPACITY2',
            'type'        => 'fixed',
            'value'       => 20000,
            'usage_limit' => 2, // Only 2 usages allowed
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        $this->checkoutEngine = app(CheckoutEngine::class);
        $this->billingEngine = app(BillingEngine::class);
    }

    public function test_vred_01_checkout_creates_reserved_redemption_and_decrements_available_capacity(): void
    {
        $this->assertEquals(2, $this->coupon->getAvailableUses());

        $result = $this->checkoutEngine->checkout(
            $this->candidateUser,
            $this->product,
            1,
            $this->coupon
        );

        $order = $result['order'];
        $this->assertNotNull($order->coupon_id);
        $this->assertEquals($this->coupon->id, $order->coupon_id);
        $this->assertEquals(20000, $order->discount);

        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertNotNull($redemption);
        $this->assertEquals('reserved', $redemption->status);
        $this->assertEquals(20000, $redemption->discount_amount);

        // Available capacity is now 1 (2 limit - 1 reserved)
        $this->assertEquals(1, $this->coupon->fresh()->getAvailableUses());
    }

    public function test_vred_02_capacity_exhaustion_prevents_further_reservations(): void
    {
        // 1st candidate & checkout
        $cand1 = User::factory()->create();
        $cand1->assignRole('student');
        $result1 = $this->checkoutEngine->checkout($cand1, $this->product, 1, $this->coupon);
        $this->assertNotNull($result1['order']);

        // 2nd candidate & checkout
        $cand2 = User::factory()->create();
        $cand2->assignRole('student');
        $result2 = $this->checkoutEngine->checkout($cand2, $this->product, 1, $this->coupon);
        $this->assertNotNull($result2['order']);

        $this->assertEquals(0, $this->coupon->fresh()->getAvailableUses());

        // 3rd candidate attempt should throw Exception / fail reservation
        $cand3 = User::factory()->create();
        $cand3->assignRole('student');

        $this->expectException(\InvalidArgumentException::class);
        $this->checkoutEngine->checkout($cand3, $this->product, 1, $this->coupon);
    }

    public function test_vred_03_payment_confirmation_consumes_redemption_and_increments_used_count(): void
    {
        $result = $this->checkoutEngine->checkout(
            $this->candidateUser,
            $this->product,
            1,
            $this->coupon
        );
        $order = $result['order'];
        $invoice = $result['invoice'];

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $order->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-TEST-001',
        ]);

        $this->assertEquals(0, $this->coupon->fresh()->used_count);

        // Finance confirms payment
        $this->billingEngine->confirmPayment($payment, 'TXN-CONFIRMED-001');

        $this->coupon->refresh();
        $this->assertEquals(1, $this->coupon->used_count);

        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertEquals('consumed', $redemption->status);
        $this->assertNotNull($redemption->consumed_at);
    }

    public function test_vred_04_payment_rejection_releases_redemption_and_restores_capacity(): void
    {
        $result = $this->checkoutEngine->checkout(
            $this->candidateUser,
            $this->product,
            1,
            $this->coupon
        );
        $order = $result['order'];
        $invoice = $result['invoice'];

        $this->assertEquals(1, $this->coupon->fresh()->getAvailableUses());

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $order->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-TEST-002',
        ]);

        // Cancel / Reject payment
        $this->billingEngine->cancelPayment($payment, 'Invalid transfer slip');

        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertEquals('released', $redemption->status);
        $this->assertNotNull($redemption->released_at);

        // Available capacity is fully restored to 2
        $this->assertEquals(2, $this->coupon->fresh()->getAvailableUses());
        $this->assertEquals(0, $this->coupon->fresh()->used_count);
    }

    public function test_vred_05_idempotent_payment_confirmation(): void
    {
        $result = $this->checkoutEngine->checkout(
            $this->candidateUser,
            $this->product,
            1,
            $this->coupon
        );
        $order = $result['order'];
        $invoice = $result['invoice'];

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $order->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-TEST-003',
        ]);

        $this->billingEngine->confirmPayment($payment, 'TXN-001');
        $this->assertEquals(1, $this->coupon->fresh()->used_count);

        // Call again -> should be safely idempotent
        $this->billingEngine->confirmPayment($payment, 'TXN-001');
        $this->assertEquals(1, $this->coupon->fresh()->used_count);
    }
}
