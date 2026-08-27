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
use App\Modules\Commerce\Domain\Models\PriceChangeRequest;
use App\Modules\Commerce\Domain\Models\Product;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceNavigationConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
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

        $this->superAdminUser = User::create([
            'name'     => 'Super Admin Officer',
            'email'    => 'superadmin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->superAdminUser->assignRole('super-admin');

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

    public function test_01_finance_dashboard_remains_accessible(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
    }

    public function test_02_payment_and_invoice_reports_remains_accessible(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
    }

    public function test_03_finance_navigation_contains_dashboard(): void
    {
        $this->actingAs($this->financeUser);
        $menu = NavigationService::getMenuItems();
        $labels = array_column($menu, 'label');

        $this->assertContains('Dashboard', $labels);
    }

    public function test_04_finance_navigation_contains_payment_and_invoice_reports(): void
    {
        $this->actingAs($this->financeUser);
        $menu = NavigationService::getMenuItems();
        $labels = array_column($menu, 'label');

        $this->assertContains('Payment & Invoice Reports', $labels);
    }

    public function test_05_finance_navigation_does_not_contain_pending_payments(): void
    {
        $this->actingAs($this->financeUser);
        $menu = NavigationService::getMenuItems();
        $labels = array_column($menu, 'label');

        $this->assertNotContains('Pending Payments', $labels);

        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('>Pending Payments<', false);
    }

    public function test_06_finance_payments_pending_route_remains_registered(): void
    {
        $this->assertTrue(Route::has('finance.payments.pending'));
    }

    public function test_07_direct_access_to_finance_payments_pending_remains_safe(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertRedirect(route('finance.payments.index', ['status' => 'pending']));
    }

    public function test_08_finance_payments_pending_preserves_pending_context(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertRedirect();
        
        $followed = $this->get($response->headers->get('Location'));
        $followed->assertStatus(200);
        $followed->assertViewHas('statusFilter', 'pending');
        $followed->assertSee('PAY-20260827-VZDM');
    }

    public function test_09_legacy_pending_route_redirects_or_resolves_to_canonical_reports_workspace_safely(): void
    {
        $response = $this->actingAs($this->financeUser)->get('/finance/payments/pending');
        $response->assertRedirect(route('finance.payments.index', ['status' => 'pending']));
    }

    public function test_10_dashboard_view_full_queue_points_to_finance_payments_index_status_pending(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee(route('finance.payments.index', ['status' => 'pending']));
    }

    public function test_11_payment_and_invoice_reports_pending_tab_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'pending');
        $response->assertSee('PAY-20260827-VZDM');
    }

    public function test_12_payment_and_invoice_reports_all_tab_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'all');
        $response->assertSee('PAY-20260827-VZDM');
    }

    public function test_13_payment_and_invoice_reports_confirmed_paid_tab_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'success']));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'success');
        $response->assertSee('No payment records found');
    }

    public function test_14_payment_and_invoice_reports_cancelled_rejected_tab_works(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'failed']));
        $response->assertStatus(200);
        $response->assertViewHas('statusFilter', 'failed');
        $response->assertSee('No payment records found');
    }

    public function test_15_current_uat_payment_remains_visible_under_pending_context(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('student@icedu.org');
        $response->assertSee('TOEIC Mock Test Package');
        $response->assertSee('IDR 832,500');
    }

    public function test_16_pay_20260827_vzdm_remains_pending(): void
    {
        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
    }

    public function test_17_pay_20260827_vzdm_amount_remains_idr_832500(): void
    {
        $this->assertEquals(832500, $this->uatPayment->fresh()->amount);
    }

    public function test_18_inv_20260726_0001_remains_in_database(): void
    {
        $this->assertDatabaseHas('payments', [
            'id'               => $this->legacyPayment->id,
            'reference_number' => 'INV-20260726-0001',
            'status'           => PaymentStatus::Success->value,
            'amount'           => 750000,
        ]);
    }

    public function test_19_inv_20260726_0001_remains_excluded_from_valid_commerce_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertDontSee('INV-20260726-0001');
        $response->assertDontSee('repomanager@icedu.com');
    }

    public function test_20_finance_remains_blocked_from_product_catalog(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('admin.commerce.index'));
        $response->assertStatus(403);
    }

    public function test_21_finance_remains_blocked_from_product_crud(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Finance Illegal Product',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 500000,
        ]);
        $response->assertStatus(403);
    }

    public function test_22_finance_remains_blocked_from_voucher_crud(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.vouchers.store'), [
            'code'     => 'FINANCE50',
            'discount' => 50,
        ]);
        $response->assertStatus(403);
    }

    public function test_23_ra_commercial_catalog_remains_available(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
    }

    public function test_24_sa_price_approval_remains_available(): void
    {
        $request = PriceChangeRequest::create([
            'product_id'             => $this->sampleProduct->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 850000,
            'reason'                 => 'Yearly adjustment',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));
        $response->assertRedirect(route('admin.approvals.index'));
        $this->assertEquals(850000, $this->sampleProduct->fresh()->price);
    }

    public function test_25_no_payment_state_mutation(): void
    {
        $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));

        $this->assertEquals(PaymentStatus::Pending, $this->uatPayment->fresh()->status);
        $this->assertEquals(PaymentStatus::Success, $this->legacyPayment->fresh()->status);
    }

    public function test_26_no_assignment_created(): void
    {
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_27_no_attempt_created(): void
    {
        $this->assertEquals(0, Attempt::count());
    }

    public function test_28_no_certificate_created(): void
    {
        $this->assertEquals(0, Certificate::count());
    }

    public function test_29_light_theme_passes(): void
    {
        $this->financeUser->setThemePreference('light');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_30_dark_theme_passes(): void
    {
        $this->financeUser->setThemePreference('dark');
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_31_global_theme_contract_passes(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
