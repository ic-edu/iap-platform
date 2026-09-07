<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePaymentVoucherAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $financeUser;
    protected User $candidateUser;
    protected Product $product;
    protected CouponCampaign $campaign;
    protected Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->financeUser = User::factory()->create();
        $this->financeUser->assignRole('finance');

        $this->candidateUser = User::factory()->create();
        $this->candidateUser->assignRole('student');

        $this->product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $this->campaign = CouponCampaign::create([
            'name'              => 'Scholarship Grant Promo',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 20,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'uses_per_code'     => 10,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(30),
            'is_active'         => true,
        ]);

        $this->coupon = Coupon::create([
            'campaign_id' => $this->campaign->id,
            'code'        => 'SCHOLAR20',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active'   => true,
        ]);
    }

    public function test_vfin_01_finance_payment_show_displays_voucher_audit_breakdown(): void
    {
        $checkoutEngine = app(CheckoutEngine::class);
        $result = $checkoutEngine->checkout(
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
            'reference_number' => 'PAY-VOUCHER-001',
        ]);

        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.show', $payment->id));
        $response->assertOk();

        $response->assertSee('SCHOLAR20');
        $response->assertSee('Scholarship Grant Promo');
        $response->assertSee('Promotional Voucher Discount');
        $response->assertSee(number_format($order->subtotal));
        $response->assertSee(number_format($order->discount));
        $response->assertSee(number_format($order->tax));
        $response->assertSee(number_format($order->grand_total));
    }

    public function test_vfin_02_finance_approval_transitions_payment_and_consumes_redemption(): void
    {
        $checkoutEngine = app(CheckoutEngine::class);
        $result = $checkoutEngine->checkout(
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
            'reference_number' => 'PAY-VOUCHER-002',
        ]);

        $response = $this->actingAs($this->financeUser)->post(route('finance.payments.approve', $payment->id), [
            'transaction_id' => 'BANK-TXN-998877',
        ]);

        $response->assertRedirect(route('finance.payments.show', $payment->id));

        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertEquals('consumed', $redemption->status);
        $this->assertEquals(1, $this->coupon->fresh()->used_count);
    }
}
