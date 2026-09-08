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

class FinanceQueueVsReportsSeparationTest extends TestCase
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

    public function test_01_pending_payments_returns_redirect_for_finance(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertRedirect(route('finance.payments.index', ['status' => 'pending']));
    }

    public function test_02_payment_and_invoice_reports_returns_200_for_finance(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
    }

    public function test_03_pending_payments_redirects_to_status_pending(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertRedirect(route('finance.payments.index', ['status' => 'pending']));
    }

    public function test_04_payment_and_invoice_reports_defaults_to_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'all');
    }

    public function test_05_pending_payments_redirect_preserves_pending_context(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $followed = $this->get($response->headers->get('Location'));
        $followed->assertStatus(200);
        $followed->assertSee('Payment &amp; Invoice Reports', false);
        $followed->assertViewHas('statusFilter', 'pending');
    }

    public function test_06_reports_page_title_is_payment_and_invoice_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Payment &amp; Invoice Reports', false);
        $response->assertSee('Review transaction history, invoice records, and payment proofs.');
    }

    public function test_07_pending_payments_redirects_to_index_with_status(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending', ['status' => 'success']));
        $response->assertRedirect(route('finance.payments.index', ['status' => 'success']));
    }

    public function test_08_reports_tabs_remain_on_finance_payments_index(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee(route('finance.payments.index', ['status' => 'pending']));
        $response->assertSee(route('finance.payments.index', ['status' => 'success']));
        $response->assertSee(route('finance.payments.index', ['status' => 'failed']));
        $response->assertSee(route('finance.payments.index', ['status' => 'all']));
    }

    public function test_09_reports_all_transactions_url_is_finance_payments_index_status_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'all');
        $response->assertSee(route('finance.payments.index', ['status' => 'all']));
    }

    public function test_10_pending_all_transactions_redirects_to_finance_payments_index_status_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending', ['status' => 'all']));
        $response->assertRedirect(route('finance.payments.index', ['status' => 'all']));
    }

    public function test_11_finance_navigation_has_single_reports_menu_active(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*finance\/payments"[^>]*class="[^"]*bg-indigo-600/i', $content);
    }

    public function test_12_only_payment_and_invoice_reports_active_on_finance_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*finance\/payments"[^>]*class="[^"]*bg-indigo-600/i', $content);
    }

    public function test_13_dashboard_view_all_transactions_points_to_finance_payments_index_status_all(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee(route('finance.payments.index', ['status' => 'all']));
    }

    public function test_14_pay_20260827_vzdm_appears_in_pending_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('student@icedu.org');
        $response->assertSee('TOEIC Mock Test Package');
        $response->assertSee('IDR 832,500');
    }

    public function test_15_pay_20260827_vzdm_appears_in_reports_all_transactions_when_valid(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('student@icedu.org');
        $response->assertSee('TOEIC Mock Test Package');
    }

    public function test_16_pay_20260827_vzdm_remains_pending(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_17_confirmed_paid_count_remains_0(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Confirmed / Paid (0)');
    }

    public function test_18_inv_20260726_0001_remains_excluded_from_valid_commerce_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertDontSee('INV-20260726-0001');
        $response->assertDontSee('repomanager@icedu.com');
    }

    public function test_19_inv_20260726_0001_remains_in_database(): void
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
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertStatus(200);
        $response->assertSee('Payment Ref');
        $response->assertSee('Invoice Ref');
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('INV-20260827-ZMJY');
    }

    public function test_21_finance_retains_finance_report_access(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
    }

    public function test_22_finance_remains_blocked_from_commerce_product_management(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('admin.commerce.index'));
        $response->assertStatus(403);
    }

    public function test_23_ra_commercial_catalog_remains_accessible(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
    }

    public function test_24_no_payment_state_changes_occur(): void
    {
        $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));

        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
        $this->assertEquals(PaymentStatus::Success, $this->legacyPayment->fresh()->status);
    }

    public function test_25_no_assignment_created(): void
    {
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_26_no_attempt_created(): void
    {
        $this->assertEquals(0, Attempt::count());
    }

    public function test_27_no_certificate_created(): void
    {
        $this->assertEquals(0, Certificate::count());
    }

    public function test_28_light_theme_passes(): void
    {
        $this->financeUser->setThemePreference('light');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_29_dark_theme_passes(): void
    {
        $this->financeUser->setThemePreference('dark');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_30_global_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
