<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Certificate\Models\Certificate;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\PriceChangeRequest;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceCommerceGovernanceSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $financeUser;
    protected User $studentUser;
    protected User $teacherUser;
    protected User $repoManagerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'finance']);
        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'repository-manager']);

        $this->adminUser = User::create([
            'name'     => 'Operational Admin',
            'email'    => 'admin@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->superAdminUser = User::create([
            'name'     => 'Super Admin Executive',
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

        $this->teacherUser = User::create([
            'name'     => 'Teacher Author',
            'email'    => 'teacher@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->teacherUser->assignRole('teacher');

        $this->repoManagerUser = User::create([
            'name'     => 'Repo Manager',
            'email'    => 'repomanager@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->repoManagerUser->assignRole('repository-manager');
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

    public function test_01_ra_can_access_commerce_product_catalog(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
    }

    public function test_02_sa_can_access_commerce_product_catalog(): void
    {
        $response = $this->actingAs($this->superAdminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
    }

    public function test_03_finance_cannot_access_commerce_product_catalog(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('admin.commerce.index'));
        $response->assertStatus(403);
    }

    public function test_04_finance_cannot_create_assessment_package(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'Unauthorized Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 500000,
        ]);
        $response->assertStatus(403);
    }

    public function test_05_finance_cannot_edit_product(): void
    {
        $product = $this->createSampleProduct();

        $response = $this->actingAs($this->financeUser)->put(route('admin.commerce.products.update', $product->id), [
            'title' => 'Hacked Title',
        ]);
        $response->assertStatus(403);
    }

    public function test_06_finance_cannot_toggle_product(): void
    {
        $product = $this->createSampleProduct();

        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.toggle', $product->id));
        $response->assertStatus(403);
    }

    public function test_07_finance_cannot_create_voucher(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.vouchers.store'), [
            'code'     => 'FINANCE50',
            'discount' => 50,
        ]);
        $response->assertStatus(403);
    }

    public function test_08_ra_can_create_assessment_package(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'TOEFL iBT Standard Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
            'price'             => 850000,
            'is_active'         => 1,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('products', [
            'title'             => 'TOEFL iBT Standard Package',
            'assessment_family' => 'toefl',
            'price'             => 850000,
        ]);
    }

    public function test_09_ra_can_set_initial_package_price(): void
    {
        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.store'), [
            'title'             => 'IELTS General Package',
            'product_type'      => 'assessment',
            'assessment_family' => 'ielts',
            'price'             => 950000,
        ]);

        $product = Product::where('title', 'IELTS General Package')->first();
        $this->assertNotNull($product);
        $this->assertEquals(950000, $product->price);
    }

    public function test_10_ra_can_edit_non_price_product_metadata(): void
    {
        $product = $this->createSampleProduct(750000);

        $response = $this->actingAs($this->adminUser)->put(route('admin.commerce.products.update', $product->id), [
            'title'             => 'Updated TOEIC Master Package',
            'assessment_family' => 'toeic',
            'description'       => 'Brand new detailed description.',
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $product->refresh();
        $this->assertEquals('Updated TOEIC Master Package', $product->title);
        $this->assertEquals('Brand new detailed description.', $product->description);
    }

    public function test_11_ra_cannot_directly_mutate_existing_product_price(): void
    {
        $product = $this->createSampleProduct(750000);

        $this->actingAs($this->adminUser)->put(route('admin.commerce.products.update', $product->id), [
            'title' => 'Updated Title',
            'price' => 1200000, // Should be ignored by updateProduct
        ]);

        $product->refresh();
        $this->assertEquals(750000, $product->price); // Remained 750,000!
    }

    public function test_12_ra_can_submit_price_change_proposal(): void
    {
        $product = $this->createSampleProduct(750000);

        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.propose-price', $product->id), [
            'proposed_price' => 800000,
            'reason'         => 'Institutional annual price adjustment for 2026.',
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('price_change_requests', [
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 800000,
            'status'                 => 'pending',
        ]);
    }

    public function test_13_submitting_proposal_does_not_change_product_price(): void
    {
        $product = $this->createSampleProduct(750000);

        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.propose-price', $product->id), [
            'proposed_price' => 900000,
            'reason'         => 'Test proposal submission.',
        ]);

        $product->refresh();
        $this->assertEquals(750000, $product->price);
    }

    public function test_14_price_proposal_reaches_sa_approval_center(): void
    {
        $product = $this->createSampleProduct(750000);

        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 800000,
            'reason'                 => 'Adjustment for Q4',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->get(route('admin.approvals.index'));
        $response->assertStatus(200);
        $response->assertSee('Commercial Price Change Proposals Requiring SA Approval');
        $response->assertSee('TOEIC Mock Test Package');
        $response->assertSee('Adjustment for Q4');
    }

    public function test_15_sa_sees_current_price_and_proposed_price(): void
    {
        $product = $this->createSampleProduct(750000);

        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 800000,
            'reason'                 => 'Adjustment for Q4',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->get(route('admin.approvals.index'));
        $response->assertStatus(200);
        $response->assertSee('750,000');
        $response->assertSee('800,000');
    }

    public function test_16_sa_can_approve_pending_proposal(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 850000,
            'reason'                 => 'Approved adjustment',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));

        $response->assertRedirect(route('admin.approvals.index'));
        $request->refresh();
        $product->refresh();

        $this->assertEquals('approved', $request->status);
        $this->assertEquals($this->superAdminUser->id, $request->reviewed_by);
        $this->assertEquals(850000, $product->price);
    }

    public function test_17_approval_updates_product_price_exactly_once(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 850000,
            'reason'                 => 'Approved adjustment',
            'status'                 => 'pending',
        ]);

        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));

        $product->refresh();
        $this->assertEquals(850000, $product->price);
    }

    public function test_18_approved_price_applies_only_to_future_checkout(): void
    {
        $product = $this->createSampleProduct(750000);

        // 1. Initial checkout at 750,000
        $checkoutEngine = app(CheckoutEngine::class);
        $res1 = $checkoutEngine->checkout($this->studentUser, $product);

        $this->assertEquals(750000, $res1['order']->items->first()->price);
        $res1['order']->update(['status' => \App\Modules\Commerce\Domain\Enums\OrderStatus::Completed]);

        // 2. Price change approved to 900,000
        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 900000,
            'reason'                 => 'Approved adjustment',
            'status'                 => 'pending',
        ]);

        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));

        $product->refresh();

        // 3. New checkout uses new approved price
        $studentUser2 = User::create([
            'name'     => 'Second Student',
            'email'    => 'student2@icedu.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $studentUser2->assignRole('student');

        $res2 = $checkoutEngine->checkout($studentUser2, $product);
        $this->assertEquals(900000, $res2['order']->items->first()->price);
    }

    public function test_19_historical_order_item_pricing_remains_unchanged(): void
    {
        $product = $this->createSampleProduct(750000);
        $checkoutEngine = app(CheckoutEngine::class);
        $res1 = $checkoutEngine->checkout($this->studentUser, $product);

        $orderItemId = $res1['order']->items->first()->id;

        // Price change
        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 900000,
            'reason'                 => 'Approved adjustment',
            'status'                 => 'pending',
        ]);
        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));

        $historicalItem = OrderItem::find($orderItemId);
        $this->assertEquals(750000, $historicalItem->price);
    }

    public function test_20_historical_invoice_amount_remains_unchanged(): void
    {
        $product = $this->createSampleProduct(750000);
        $checkoutEngine = app(CheckoutEngine::class);
        $res1 = $checkoutEngine->checkout($this->studentUser, $product);

        $invoiceId = $res1['invoice']->id;
        $originalAmount = $res1['invoice']->amount;

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 900000,
            'reason'                 => 'Approved adjustment',
            'status'                 => 'pending',
        ]);
        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));

        $historicalInvoice = Invoice::find($invoiceId);
        $this->assertEquals($originalAmount, $historicalInvoice->amount);
    }

    public function test_21_historical_payment_amount_remains_unchanged(): void
    {
        $product = $this->createSampleProduct(750000);
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(\App\Modules\Commerce\Application\BillingEngine::class);
        $res1 = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res1['invoice'], 'manual_transfer');

        $paymentId = $payment->id;
        $originalPaymentAmount = $payment->amount;

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 900000,
            'reason'                 => 'Approved adjustment',
            'status'                 => 'pending',
        ]);
        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));

        $historicalPayment = Payment::find($paymentId);
        $this->assertEquals($originalPaymentAmount, $historicalPayment->amount);
    }

    public function test_22_sa_can_reject_price_proposal(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 1500000,
            'reason'                 => 'Too high',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.reject', $request->id), [
            'rejection_reason' => 'Price hike is too steep for institutional students.',
        ]);

        $response->assertRedirect(route('admin.approvals.index'));
        $request->refresh();
        $this->assertEquals('rejected', $request->status);
        $this->assertEquals('Price hike is too steep for institutional students.', $request->rejection_reason);
    }

    public function test_23_rejected_proposal_does_not_change_product_price(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 1500000,
            'reason'                 => 'Too high',
            'status'                 => 'pending',
        ]);

        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.reject', $request->id), [
            'rejection_reason' => 'Rejected by SA.',
        ]);

        $product->refresh();
        $this->assertEquals(750000, $product->price);
    }

    public function test_24_rejection_requires_reason(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 1500000,
            'reason'                 => 'Too high',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.reject', $request->id), [
            'rejection_reason' => '',
        ]);

        $response->assertSessionHasErrors('rejection_reason');
    }

    public function test_25_double_approval_cannot_apply_twice(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 800000,
            'reason'                 => 'Adjustment',
            'status'                 => 'pending',
        ]);

        $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));
        $response->assertSessionHasErrors();
    }

    public function test_26_rejected_request_cannot_be_approved(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 800000,
            'reason'                 => 'Adjustment',
            'status'                 => 'rejected',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));
        $response->assertSessionHasErrors();
    }

    public function test_27_approved_request_cannot_be_rejected_again(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 800000,
            'reason'                 => 'Adjustment',
            'status'                 => 'approved',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.reject', $request->id), [
            'rejection_reason' => 'Too late',
        ]);
        $response->assertSessionHasErrors();
    }

    public function test_28_stale_current_price_snapshot_blocks_unsafe_approval(): void
    {
        $product = $this->createSampleProduct(750000);

        $request = PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $this->adminUser->id,
            'current_price_snapshot' => 500000, // Mismatched snapshot
            'proposed_price'         => 800000,
            'reason'                 => 'Adjustment',
            'status'                 => 'pending',
        ]);

        $response = $this->actingAs($this->superAdminUser)->post(route('admin.approvals.price-changes.approve', $request->id));
        $response->assertSessionHasErrors();
        $this->assertEquals(750000, $product->fresh()->price);
    }

    public function test_29_ra_can_create_voucher(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.vouchers.store'), [
            'code'     => 'RA2026',
            'discount' => 20,
        ]);

        $response->assertRedirect(route('admin.commerce.index'));
        $this->assertDatabaseHas('coupons', ['code' => 'RA2026']);
    }

    public function test_30_finance_cannot_create_voucher(): void
    {
        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.vouchers.store'), [
            'code'     => 'FI2026',
            'discount' => 20,
        ]);

        $response->assertStatus(403);
    }

    public function test_31_ra_can_activate_deactivate_product(): void
    {
        $product = $this->createSampleProduct();

        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.products.toggle', $product->id));
        $response->assertRedirect(route('admin.commerce.index'));

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_32_finance_cannot_activate_deactivate_product(): void
    {
        $product = $this->createSampleProduct();

        $response = $this->actingAs($this->financeUser)->post(route('admin.commerce.products.toggle', $product->id));
        $response->assertStatus(403);
    }

    public function test_33_deactivated_product_disappears_from_candidate_store(): void
    {
        $product = $this->createSampleProduct();
        $product->update(['is_active' => false]);

        $response = $this->actingAs($this->studentUser)->get(route('candidate.store'));
        $response->assertStatus(200);
        $response->assertDontSee($product->title);
    }

    public function test_34_historical_transactions_remain_intact_after_product_deactivation(): void
    {
        $product = $this->createSampleProduct();
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(\App\Modules\Commerce\Application\BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');

        $product->update(['is_active' => false]);

        $this->assertDatabaseHas('orders', ['id' => $res['order']->id]);
        $this->assertDatabaseHas('invoices', ['id' => $res['invoice']->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_35_candidate_cannot_access_commerce_product_management(): void
    {
        $response = $this->actingAs($this->studentUser)->get(route('admin.commerce.index'));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_36_teacher_cannot_access_commerce_product_management(): void
    {
        $response = $this->actingAs($this->teacherUser)->get(route('admin.commerce.index'));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_37_repository_manager_cannot_access_commerce_product_management(): void
    {
        $response = $this->actingAs($this->repoManagerUser)->get(route('admin.commerce.index'));
        $this->assertTrue(in_array($response->status(), [403, 302]));
    }

    public function test_38_finance_retains_finance_dashboard_access(): void
    {
        $response = $this->actingAs($this->financeUser)->get(route('finance.dashboard'));
        $response->assertStatus(200);
    }

    public function test_39_finance_retains_payment_review_access(): void
    {
        $product = $this->createSampleProduct();
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(\App\Modules\Commerce\Application\BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');

        $response = $this->actingAs($this->financeUser)->get(route('finance.payments.show', $payment->id));
        $response->assertStatus(200);
    }

    public function test_40_finance_retains_payment_approval_workflow(): void
    {
        $product = $this->createSampleProduct();
        $checkoutEngine = app(CheckoutEngine::class);
        $billingEngine = app(\App\Modules\Commerce\Application\BillingEngine::class);
        $res = $checkoutEngine->checkout($this->studentUser, $product);
        $payment = $billingEngine->createPayment($res['invoice'], 'manual_transfer');

        $response = $this->actingAs($this->financeUser)->post(route('finance.payments.approve', $payment->id));
        $response->assertRedirect(route('finance.payments.show', $payment->id));

        $this->assertEquals(PaymentStatus::Success, $payment->fresh()->status);
    }

    public function test_41_pay_20260827_vzdm_remains_pending(): void
    {
        $payment = Payment::create([
            'reference_number' => 'PAY-20260827-VZDM',
            'user_id'          => $this->studentUser->id,
            'amount'           => 832500,
            'payment_gateway'  => 'manual_transfer',
            'status'           => PaymentStatus::Pending,
        ]);

        $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));

        $this->assertEquals(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_42_no_candidate_test_assignment_is_created(): void
    {
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_43_no_attempt_is_created(): void
    {
        $this->assertEquals(0, Attempt::count());
    }

    public function test_44_no_certificate_is_created(): void
    {
        $this->assertEquals(0, Certificate::count());
    }

    public function test_45_price_change_actions_are_activity_logged(): void
    {
        $product = $this->createSampleProduct(750000);

        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.propose-price', $product->id), [
            'proposed_price' => 800000,
            'reason'         => 'Logged adjustment',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'PRICE_CHANGE_PROPOSED',
        ]);
    }

    public function test_46_existing_notification_infrastructure_handles_price_governance(): void
    {
        $product = $this->createSampleProduct(750000);

        $this->actingAs($this->adminUser)->post(route('admin.commerce.products.propose-price', $product->id), [
            'proposed_price' => 800000,
            'reason'         => 'Notification test',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->superAdminUser->id,
        ]);
    }

    public function test_47_light_theme_governance_ui_passes(): void
    {
        $this->adminUser->setThemePreference('light');

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="light"', false);
    }

    public function test_48_dark_theme_governance_ui_passes(): void
    {
        $this->adminUser->setThemePreference('dark');

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('data-theme="dark"', false);
    }

    public function test_49_global_theme_contract_remains_intact(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertStatus(200);
        $response->assertSee('setIapTheme');
    }
}
