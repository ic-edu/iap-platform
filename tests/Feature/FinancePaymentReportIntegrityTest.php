<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancePaymentReportIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $financeUser;
    protected User $studentUser;
    protected User $repoManagerUser;
    protected Product $sampleProduct;
    protected Order $sampleOrder;
    protected Invoice $sampleInvoice;
    protected Payment $uatPayment;
    protected Payment $legacyPayment;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'finance']);
        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'repository-manager']);

        $this->adminUser = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->financeUser = User::create([
            'name'     => 'Finance Officer',
            'email'    => 'finance@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->financeUser->assignRole('finance');

        $this->studentUser = User::create([
            'name'     => 'Candidate Student',
            'email'    => 'student@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->studentUser->assignRole('student');

        $this->repoManagerUser = User::create([
            'name'     => 'Repository Manager',
            'email'    => 'repomanager@icedu.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->repoManagerUser->assignRole('repository-manager');

        // Valid Commerce Hierarchy for UAT Payment
        $this->sampleProduct = Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
            'test_id'           => null,
        ]);

        $this->sampleOrder = Order::create([
            'user_id'      => $this->studentUser->id,
            'order_number' => 'ORD-20260827-YIT4',
            'status'       => OrderStatus::Pending,
            'subtotal'     => 750000,
            'discount'     => 0,
            'tax'          => 82500,
            'grand_total'  => 832500,
        ]);

        OrderItem::create([
            'order_id'   => $this->sampleOrder->id,
            'product_id' => $this->sampleProduct->id,
            'quantity'   => 1,
            'price'      => 750000,
            'total'      => 750000,
        ]);

        $this->sampleInvoice = Invoice::create([
            'order_id'       => $this->sampleOrder->id,
            'user_id'        => $this->studentUser->id,
            'invoice_number' => 'INV-20260827-ZMJY',
            'status'         => InvoiceStatus::Unpaid,
            'amount'         => 832500,
            'due_date'       => now()->addDays(2),
        ]);

        $this->uatPayment = Payment::create([
            'invoice_id'          => $this->sampleInvoice->id,
            'user_id'             => $this->studentUser->id,
            'reference_number'    => 'PAY-20260827-VZDM',
            'payment_gateway'     => 'manual_transfer',
            'status'              => PaymentStatus::Pending,
            'amount'              => 832500,
            'proof_path'          => 'payment-proofs/sample.png',
            'proof_original_name' => 'receipt.png',
            'proof_uploaded_at'   => now(),
        ]);

        // Legacy Orphan Record (No valid Invoice / Order / OrderItem / Product hierarchy)
        $this->legacyPayment = Payment::create([
            'invoice_id'       => null, // Orphan / no invoice
            'user_id'          => $this->repoManagerUser->id,
            'reference_number' => 'INV-20260726-0001',
            'payment_gateway'  => 'manual_transfer',
            'status'           => PaymentStatus::Success,
            'amount'           => 750000,
        ]);
    }

    public function test_01_current_uat_payment_appears_in_payment_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('student@icedu.org');
        $response->assertSee('TOEIC Mock Test Package');
    }

    public function test_02_current_uat_payment_status_remains_pending(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_03_current_uat_amount_remains_idr_832500(): void
    {
        $this->assertEquals(832500, $this->uatPayment->fresh()->amount);
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertSee('IDR 832,500');
    }

    public function test_04_current_uat_invoice_reference_is_inv_20260827_zmjy(): void
    {
        $this->assertEquals('INV-20260827-ZMJY', $this->uatPayment->invoice?->invoice_number);
    }

    public function test_05_payment_reference_displays_pay_20260827_vzdm(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertSee('PAY-20260827-VZDM');
    }

    public function test_06_invoice_reference_displays_inv_20260827_zmjy_separately(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertSee('INV-20260827-ZMJY');
    }

    public function test_07_legacy_orphan_does_not_appear_as_normal_commerce_payment_report_transaction(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertDontSee('INV-20260726-0001');
        $response->assertDontSee('repomanager@icedu.com');

        $successResponse = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'success']));
        $successResponse->assertStatus(200);
        $successResponse->assertDontSee('INV-20260726-0001');
    }

    public function test_08_legacy_record_still_exists_in_database(): void
    {
        $this->assertDatabaseHas('payments', [
            'id'               => $this->legacyPayment->id,
            'reference_number' => 'INV-20260726-0001',
            'amount'           => 750000,
            'status'           => PaymentStatus::Success->value,
        ]);
    }

    public function test_09_legacy_record_is_not_modified_by_report_filtering(): void
    {
        $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $freshLegacy = $this->legacyPayment->fresh();
        $this->assertNotNull($freshLegacy);
        $this->assertEquals('INV-20260726-0001', $freshLegacy->reference_number);
        $this->assertEquals(750000, $freshLegacy->amount);
        $this->assertEquals(PaymentStatus::Success, $freshLegacy->status);
    }

    public function test_10_pending_review_count_is_based_on_valid_commerce_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Pending Review (1)');
    }

    public function test_11_confirmed_paid_count_excludes_orphan_legacy_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Confirmed / Paid (0)');
    }

    public function test_12_cancelled_rejected_count_excludes_orphan_legacy_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Cancelled / Rejected (0)');
    }

    public function test_13_recent_transactions_uses_the_same_valid_commerce_transaction_rules(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('INV-20260827-ZMJY');
        $response->assertDontSee('INV-20260726-0001');
    }

    public function test_14_no_usd_formatting_is_reintroduced(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('$25.00');
        $response->assertDontSee('$35.00');
        $response->assertDontSee('$45.00');
        $response->assertDontSee('$14,850');
        $response->assertDontSee('USD');

        $reportResponse = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $reportResponse->assertStatus(200);
        $reportResponse->assertDontSee('$25.00');
        $reportResponse->assertDontSee('USD');
    }

    public function test_15_idr_formatting_remains_correct(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('IDR 832,500');
    }

    public function test_16_finance_still_has_access_to_payment_and_invoice_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Payment &amp; Invoice Reports', false);
    }

    public function test_17_finance_remains_blocked_from_product_catalog(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('admin.commerce.index'));
        $response->assertStatus(403);
    }

    public function test_18_finance_remains_blocked_from_product_crud(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Finance Illegal Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 500000,
        ]);
        $response->assertStatus(403);
    }

    public function test_19_finance_remains_blocked_from_voucher_crud(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.vouchers.store'), [
            'code'     => 'FINANCE50',
            'discount' => 50,
        ]);
        $response->assertStatus(403);
    }

    public function test_20_ra_commercial_catalog_remains_available(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
    }

    public function test_21_no_current_uat_payment_is_approved_or_rejected(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_22_no_assignment_created(): void
    {
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_23_no_attempt_created(): void
    {
        $this->assertEquals(0, Attempt::count());
    }

    public function test_24_no_certificate_created(): void
    {
        $this->assertEquals(0, Certificate::count());
    }

    public function test_25_light_theme_contract_passes(): void
    {
        $this->financeUser->setThemePreference('light');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_26_dark_theme_contract_passes(): void
    {
        $this->financeUser->setThemePreference('dark');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_27_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
