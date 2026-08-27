<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Models\Certificate;
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

class FinanceQueueAndReportRoutingTest extends TestCase
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

        // Legacy Orphan Record
        $this->legacyPayment = Payment::create([
            'invoice_id'       => null,
            'user_id'          => $this->repoManagerUser->id,
            'reference_number' => 'INV-20260726-0001',
            'payment_gateway'  => 'manual_transfer',
            'status'           => PaymentStatus::Success,
            'amount'           => 750000,
        ]);
    }

    public function test_01_pending_payments_route_returns_http_200_for_finance(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
    }

    public function test_02_payment_and_invoice_reports_route_returns_http_200_for_finance(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
    }

    public function test_03_pending_payments_defaults_to_status_pending(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'pending');
    }

    public function test_04_payment_and_invoice_reports_defaults_to_status_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'all');
    }

    public function test_05_pending_payments_title_is_payment_review_and_approval_queue(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
        $response->assertSee('Payment Review &amp; Approval Queue', false);
        $response->assertSee('Review candidate payment proofs and confirm or reject pending transactions.');
    }

    public function test_06_payment_and_invoice_reports_title_is_payment_and_invoice_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Payment &amp; Invoice Reports', false);
        $response->assertSee('Review transaction history, invoice records, and payment activity.');
    }

    public function test_07_pending_payments_tabs_remain_on_finance_payments_pending(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
        $response->assertSee(route('finance.payments.pending', ['status' => 'pending']));
        $response->assertSee(route('finance.payments.pending', ['status' => 'success']));
        $response->assertSee(route('finance.payments.pending', ['status' => 'failed']));
        $response->assertSee(route('finance.payments.pending', ['status' => 'all']));
    }

    public function test_08_payment_and_invoice_reports_tabs_remain_on_finance_payments_index(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee(route('finance.payments.index', ['status' => 'pending']));
        $response->assertSee(route('finance.payments.index', ['status' => 'success']));
        $response->assertSee(route('finance.payments.index', ['status' => 'failed']));
        $response->assertSee(route('finance.payments.index', ['status' => 'all']));
    }

    public function test_09_reports_all_transactions_resolves_to_finance_payments_index_status_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'all');
        $response->assertSee(route('finance.payments.index', ['status' => 'all']));
    }

    public function test_10_pending_all_transactions_resolves_to_finance_payments_pending_status_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'all');
        $response->assertSee(route('finance.payments.pending', ['status' => 'all']));
    }

    public function test_11_only_pending_payments_sidebar_item_is_active_on_finance_payments_pending(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);

        $content = $response->getContent();
        
        // Match link with bg-indigo-600 containing Pending Payments
        $this->assertMatchesRegularExpression('/href="[^"]*finance\/payments\/pending[^"]*"[^>]*class="[^"]*bg-indigo-600/i', $content);
        // Ensure Payment & Invoice Reports is NOT styled with bg-indigo-600
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*finance\/payments"[^>]*class="[^"]*bg-indigo-600/i', $content);
    }

    public function test_12_only_payment_and_invoice_reports_sidebar_item_is_active_on_finance_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Match link with bg-indigo-600 containing finance/payments (Reports)
        $this->assertMatchesRegularExpression('/href="[^"]*finance\/payments"[^>]*class="[^"]*bg-indigo-600/i', $content);
        // Ensure Pending Payments is NOT styled with bg-indigo-600
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*finance\/payments\/pending[^"]*"[^>]*class="[^"]*bg-indigo-600/i', $content);
    }

    public function test_13_finance_dashboard_view_all_transactions_resolves_to_finance_payments_index_status_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee(route('finance.payments.index', ['status' => 'all']));
    }

    public function test_14_current_uat_payment_pay_20260827_vzdm_remains_visible_in_pending(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('student@icedu.org');
        $response->assertSee('TOEIC Mock Test Package');
    }

    public function test_15_current_uat_payment_remains_pending(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_16_current_uat_payment_amount_remains_idr_832500(): void
    {
        $this->assertEquals(832500, $this->uatPayment->fresh()->amount);
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertSee('IDR 832,500');
    }

    public function test_17_confirmed_paid_count_remains_0_when_no_valid_confirmed_payment_exists(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Confirmed / Paid (0)');
    }

    public function test_18_legacy_orphan_inv_20260726_0001_remains_excluded_from_valid_commerce_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertDontSee('INV-20260726-0001');
        $response->assertDontSee('repomanager@icedu.com');
    }

    public function test_19_legacy_orphan_record_remains_in_database(): void
    {
        $this->assertDatabaseHas('payments', [
            'id'               => $this->legacyPayment->id,
            'reference_number' => 'INV-20260726-0001',
            'status'           => PaymentStatus::Success->value,
            'amount'           => 750000,
        ]);
    }

    public function test_20_payment_ref_and_invoice_ref_remain_separate(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
        $response->assertSee('Payment Ref');
        $response->assertSee('Invoice Ref');
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('INV-20260827-ZMJY');
    }

    public function test_21_finance_still_accesses_pending_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
    }

    public function test_22_finance_still_accesses_payment_and_invoice_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
    }

    public function test_23_finance_still_cannot_access_product_catalog(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('admin.commerce.index'));
        $response->assertStatus(403);
    }

    public function test_24_finance_still_cannot_create_product(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Finance Illegal Product',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 500000,
        ]);
        $response->assertStatus(403);
    }

    public function test_25_finance_still_cannot_create_voucher(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.vouchers.store'), [
            'code'     => 'FINANCE50',
            'discount' => 50,
        ]);
        $response->assertStatus(403);
    }

    public function test_26_ra_commerce_remains_accessible(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
    }

    public function test_27_ra_finance_kpi_leakage_remains_absent(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertDontSee('Gross Revenue');
        $response->assertDontSee('Payment Transactions');
        $response->assertDontSee('Invoices Issued');
    }

    public function test_28_no_current_uat_payment_is_approved_or_rejected(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_29_no_assignment_created(): void
    {
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_30_no_attempt_created(): void
    {
        $this->assertEquals(0, Attempt::count());
    }

    public function test_31_no_certificate_created(): void
    {
        $this->assertEquals(0, Certificate::count());
    }

    public function test_32_light_theme_passes(): void
    {
        $this->financeUser->setThemePreference('light');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_33_dark_theme_passes(): void
    {
        $this->financeUser->setThemePreference('dark');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_34_global_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
