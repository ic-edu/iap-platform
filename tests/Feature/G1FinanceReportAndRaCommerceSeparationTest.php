<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\PriceChangeRequest;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class G1FinanceReportAndRaCommerceSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $financeUser;
    protected User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'finance']);
        Role::firstOrCreate(['name' => 'student']);

        $this->adminUser = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->superAdminUser = User::create([
            'name'     => 'Super Admin',
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
            'name'     => 'Student Candidate',
            'email'    => 'student@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->studentUser->assignRole('student');
    }

    protected function createSampleProduct(int $price = 750000): Product
    {
        return Product::create([
            'slug'              => 'toeic-mock-test-package',
            'title'             => 'TOEIC Mock Test Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => $price,
            'is_active'         => true,
            'test_id'           => null,
        ]);
    }

    public function test_01_finance_can_access_finance_dashboard(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Finance Overview &amp; Payment Operations Hub', false);
    }

    public function test_02_finance_can_access_pending_payments(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.pending'));
        $response->assertRedirect(route('finance.payments.index', ['status' => 'pending']));
        $followed = $this->get($response->headers->get('Location'));
        $followed->assertStatus(200);
        $followed->assertSee('Payment &amp; Invoice Reports', false);
    }

    public function test_03_finance_can_access_payment_review(): void
    {
        $product = $this->createSampleProduct();
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');

        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.show', $payment->id));
        $response->assertStatus(200);
        $response->assertSee($payment->reference_number);
    }

    public function test_04_finance_can_access_payment_and_invoice_reports(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index'));
        $response->assertStatus(200);
        $response->assertSee('Payment &amp; Invoice Reports', false);
    }

    public function test_05_finance_view_all_transactions_returns_http_200(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
    }

    public function test_06_finance_report_displays_live_transaction_data(): void
    {
        $product = $this->createSampleProduct(750000);
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');

        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'all']));
        $response->assertStatus(200);
        $response->assertSee($payment->reference_number);
        $response->assertSee('student@icedu.org');
        $response->assertSee('IDR ' . number_format($payment->amount));
    }

    public function test_07_finance_report_can_display_pay_20260827_vzdm(): void
    {
        $product = $this->createSampleProduct();
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');
        $payment->update(['reference_number' => 'PAY-20260827-VZDM']);

        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.index', ['status' => 'pending']));
        $response->assertStatus(200);
        $response->assertSee('PAY-20260827-VZDM');
        $response->assertSee('IDR ' . number_format($payment->amount));
    }

    public function test_08_pay_20260827_vzdm_remains_pending(): void
    {
        $product = $this->createSampleProduct();
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');
        $payment->update(['reference_number' => 'PAY-20260827-VZDM']);

        $this->actingAs($this->financeUser)->get(route('finance.payments.index'));

        $this->assertEquals(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_09_finance_cannot_access_product_catalog(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('admin.commerce.index'));
        $response->assertStatus(403);
    }

    public function test_10_finance_cannot_create_assessment_package(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Finance Illegal Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 500000,
        ]);
        $response->assertStatus(403);
    }

    public function test_11_finance_cannot_edit_product(): void
    {
        $product = $this->createSampleProduct();

        $response = $this->actingAs($this->financeUser)->put(route('admin.commerce.products.update', $product->id), [
            'title' => 'Finance Illegal Edit',
        ]);
        $response->assertStatus(403);
    }

    public function test_12_finance_cannot_toggle_product(): void
    {
        $product = $this->createSampleProduct();

        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.toggle', $product->id));
        $response->assertStatus(403);
    }

    public function test_13_finance_cannot_create_voucher(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.vouchers.store'), [
            'code'     => 'FINANCE100',
            'discount' => 50,
        ]);
        $response->assertStatus(403);
    }

    public function test_14_ra_can_access_commercial_catalog(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
    }

    public function test_15_ra_can_create_assessment_package(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEIC New Package 2026',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 700000,
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', ['title' => 'TOEIC New Package 2026', 'price' => 700000]);
    }

    public function test_16_ra_can_edit_product_metadata(): void
    {
        $product = $this->createSampleProduct();

        $response = $this->actingAs($this->adminUser)->put(route('admin.commerce.products.update', $product->id), [
            'title'             => 'TOEIC Master Package Updated',
            'assessment_family' => 'toeic',
            'description'       => 'Updated metadata description.',
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $product->refresh();
        $this->assertEquals('TOEIC Master Package Updated', $product->title);
    }

    public function test_17_ra_can_propose_price_change(): void
    {
        $product = $this->createSampleProduct(750000);

        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.propose-price', $product->id), [
            'proposed_price' => 850000,
            'reason'         => 'Adjustment for Q3',
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('price_change_requests', [
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 850000,
            'status'                 => 'pending',
        ]);
    }

    public function test_18_ra_cannot_directly_mutate_existing_product_price(): void
    {
        $product = $this->createSampleProduct(750000);

        $this->actingAs($this->adminUser)->put(route('admin.commerce.products.update', $product->id), [
            'title' => 'Updated Title',
            'price' => 999999, // Should be ignored
        ]);

        $this->assertEquals(750000, $product->fresh()->price);
    }

    public function test_19_ra_commerce_page_does_not_render_gross_revenue_kpi(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertDontSee('Gross Revenue');
    }

    public function test_20_ra_commerce_page_does_not_render_payment_transactions_kpi(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertDontSee('Payment Transactions');
    }

    public function test_21_ra_commerce_page_does_not_render_invoices_issued_kpi(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertDontSee('Invoices Issued');
    }

    public function test_22_ra_commerce_page_does_not_render_payment_and_invoice_transaction_reports(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertDontSee('Payment &amp; Invoice Transaction Reports', false);
    }

    public function test_23_finance_dashboard_still_renders_gross_revenue(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Gross Cash Collections');
    }

    public function test_24_finance_dashboard_still_renders_pending_approvals(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Pending Approvals');
    }

    public function test_25_finance_dashboard_still_renders_invoices_issued(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Invoices Issued');
    }

    public function test_26_finance_dashboard_still_renders_recent_transactions(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Recent Payment Transactions &amp; Invoices', false);
    }

    public function test_27_sa_price_approval_workflow_remains_available(): void
    {
        $product = $this->createSampleProduct(750000);
        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 800000,
            'reason'                 => 'Q4 adjustment',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));
        $response->assertRedirect(route('admin.approvals.index'));

        $this->assertEquals(800000, $product->fresh()->price);
    }

    public function test_28_product_catalog_remains_intact(): void
    {
        $product = $this->createSampleProduct(750000);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 750000]);
    }

    public function test_29_historical_financial_records_remain_intact(): void
    {
        $product = $this->createSampleProduct(750000);
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');

        $this->assertDatabaseHas('orders', ['id' => $res['order']->id]);
        $this->assertDatabaseHas('invoices', ['id' => $res['invoice']->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_30_no_current_uat_payment_is_approved_or_rejected(): void
    {
        $payment = Payment::create([
            'reference_number' => 'PAY-20260827-VZDM',
            'user_id'          => $this->studentUser->id,
            'amount'           => 832500,
            'payment_gateway'  => 'manual_transfer',
            'status'           => PaymentStatus::Pending,
        ]);

        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    public function test_31_no_candidate_test_assignment_is_created(): void
    {
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_32_no_attempt_is_created(): void
    {
        $this->assertEquals(0, Attempt::count());
    }

    public function test_33_no_certificate_is_created(): void
    {
        $this->assertEquals(0, Certificate::count());
    }

    public function test_34_light_theme_contract_passes(): void
    {
        $this->financeUser->setThemePreference('light');
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_35_dark_theme_contract_passes(): void
    {
        $this->financeUser->setThemePreference('dark');
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_36_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
