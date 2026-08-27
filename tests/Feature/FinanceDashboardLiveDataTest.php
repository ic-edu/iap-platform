<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceDashboardLiveDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $financeUser;
    protected User $candidateUser;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'finance']);
        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'admin']);

        $this->financeUser = User::create([
            'name'     => 'Finance Officer',
            'email'    => 'finance@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->financeUser->assignRole('finance');

        $this->candidateUser = User::create([
            'name'     => 'Candidate Student',
            'email'    => 'student@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidateUser->assignRole('student');

        $this->product = Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);
    }

    protected function createPaymentWithOrder(string $ref, int $amount, PaymentStatus $status = PaymentStatus::Pending): Payment
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(bin2hex(random_bytes(4))),
            'user_id'      => $this->candidateUser->id,
            'total_amount' => $amount,
            'subtotal'     => 750000,
            'tax'          => 82500,
            'grand_total'  => $amount,
            'status'       => $status === PaymentStatus::Success ? 'completed' : 'pending',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'price'      => 750000,
            'quantity'   => 1,
            'total'      => 750000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . strtoupper(bin2hex(random_bytes(4))),
            'order_id'       => $order->id,
            'user_id'        => $this->candidateUser->id,
            'amount'         => $amount,
            'status'         => $status === PaymentStatus::Success ? 'paid' : 'unpaid',
            'due_date'       => now()->addDays(7),
        ]);

        return Payment::create([
            'reference_number' => $ref,
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $amount,
            'payment_gateway'  => 'manual_transfer',
            'status'           => $status,
        ]);
    }

    public function test_01_gross_revenue_comes_from_live_successful_paid_payments(): void
    {
        $this->createPaymentWithOrder('PAY-SUCCESS-001', 750000, PaymentStatus::Success);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Gross Revenue');
        $response->assertSee('IDR 750,000');
    }

    public function test_02_pending_payment_is_not_counted_as_gross_revenue(): void
    {
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Gross Revenue');
        $response->assertSee('IDR 0');
    }

    public function test_03_invoices_issued_comes_from_live_invoice_count(): void
    {
        $this->createPaymentWithOrder('PAY-INV-001', 832500, PaymentStatus::Pending);
        $this->createPaymentWithOrder('PAY-INV-002', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Invoices Issued');
        $response->assertSee('2');
    }

    public function test_04_promotions_comes_from_live_promotion_coupon_count(): void
    {
        Coupon::create([
            'code'        => 'PROMO1',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 100,
            'used_count'  => 0,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Promotions');
        $response->assertSee('1');
    }

    public function test_05_recent_transactions_comes_from_live_payment_data(): void
    {
        $payment = $this->createPaymentWithOrder('PAY-LIVE-001', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('PAY-LIVE-001');
        $response->assertSee('TOEIC Mock Test Package');
        $response->assertSee('IDR 832,500');
    }

    public function test_06_recent_transactions_displays_latest_legitimate_transaction_only(): void
    {
        $oldPayment = $this->createPaymentWithOrder('PAY-OLD-001', 500000, PaymentStatus::Success);
        $oldPayment->timestamps = false;
        $oldPayment->created_at = now()->subDays(2);
        $oldPayment->save();

        $newPayment = $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);
        $newPayment->timestamps = false;
        $newPayment->created_at = now();
        $newPayment->save();

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertDontSee('PAY-OLD-001');
    }

    public function test_07_current_uat_payment_pay_20260827_vzdm_appears(): void
    {
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('student@icedu.org');
    }

    public function test_08_current_uat_payment_shows_idr_832500(): void
    {
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('IDR 832,500');
    }

    public function test_09_static_txn_882194_is_not_present(): void
    {
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('TXN-882194');
    }

    public function test_10_static_txn_882193_is_not_present(): void
    {
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('TXN-882193');
    }

    public function test_11_static_txn_882192_is_not_present(): void
    {
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('TXN-882192');
    }

    public function test_12_no_usd_currency_formatting_exists_on_finance_dashboard(): void
    {
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('$25.00', $content);
        $this->assertStringNotContainsString('$35.00', $content);
        $this->assertStringNotContainsString('$45.00', $content);
        $this->assertStringNotContainsString('$14,850.00', $content);
        $this->assertStringNotContainsString('USD', $content);
        $this->assertStringContainsString('IDR 832,500', $content);
        $this->assertStringContainsString('IDR 0', $content);
    }

    public function test_13_pending_approvals_kpi_is_informational_and_has_no_redundant_cta(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Pending Approvals');
        $response->assertDontSee('Review payment queue');
    }

    public function test_14_pending_payment_alert_still_provides_review_and_verify_action(): void
    {
        $payment = $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Candidate Payments Requiring Review');
        $response->assertSee('Review &amp; Verify', false);
        $response->assertSee(route('finance.payments.show', $payment->id));
    }

    public function test_15_historical_payment_records_are_not_deleted_by_dashboard_cleanup(): void
    {
        $oldPayment = $this->createPaymentWithOrder('PAY-OLD-001', 500000, PaymentStatus::Success);
        $this->createPaymentWithOrder('PAY-20260827-VZDM', 832500, PaymentStatus::Pending);

        $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id]);
    }

    public function test_16_finance_authorization_remains_intact(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
    }

    public function test_17_candidate_cannot_access_finance_dashboard(): void
    {
        $response = $this->actingAs($this->candidateUser)->get(route('finance.dashboard'));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_18_light_theme_finance_contract_passes(): void
    {
        $this->financeUser->setThemePreference('light');

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_19_dark_theme_finance_contract_passes(): void
    {
        $this->financeUser->setThemePreference('dark');

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_20_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
