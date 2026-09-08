<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Events\PaymentConfirmed;
use App\Modules\Commerce\Events\PaymentCreated;
use App\Modules\Organization\Enums\EntitlementStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FinanceInstitutionalPaymentApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $financeUser;
    protected User $raUser;
    protected User $purchaserUser;
    protected User $candidateUser;
    protected Organization $organization;
    protected OrganizationMembership $coordinatorMembership;
    protected Product $product;
    protected Test $test;
    protected CouponCampaign $campaign;
    protected Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Finance Officer
        $this->financeUser = User::factory()->create([
            'name'   => 'Finance Officer',
            'email'  => 'finance@icedu.org',
            'status' => 'active',
        ]);
        $this->financeUser->assignRole('finance');

        // 2. Operational Admin (RA)
        $this->raUser = User::factory()->create([
            'name'   => 'Operational Admin',
            'email'  => 'admin@icedu.org',
            'status' => 'active',
        ]);
        $this->raUser->assignRole('admin');

        // 3. Purchaser / Coordinator
        $this->purchaserUser = User::factory()->create([
            'name'   => 'Indra Wahyudi',
            'email'  => 'indra@uat.org',
            'status' => 'active',
        ]);
        $this->purchaserUser->assignRole('organization-coordinator');

        // 4. B2C Candidate Student
        $this->candidateUser = User::factory()->create([
            'name'   => 'Budi Candidate',
            'email'  => 'budi@candidate.org',
            'status' => 'active',
        ]);
        $this->candidateUser->assignRole('student');

        // 5. Organization
        $this->organization = Organization::create([
            'name'              => 'iC.edu UAT University',
            'legal_name'        => 'PT iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'email'             => 'contact@uat.edu',
        ]);

        $this->coordinatorMembership = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->purchaserUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        // 6. Test & Product
        $this->test = Test::create([
            'title'             => 'TOEIC Official Mock Assessment',
            'slug'              => 'toeic-official-mock-assessment',
            'code'              => 'TOEIC-MOCK-01',
            'assessment_family' => AssessmentFamily::Toeic->value,
            'passing_score'     => 700,
            'duration_minutes'  => 120,
            'is_published'      => true,
            'created_by'        => $this->financeUser->id,
        ]);

        $this->product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'test_id'           => $this->test->id,
            'price'             => 85000,
            'is_active'         => true,
        ]);

        // 7. Voucher Campaign & Coupon
        $this->campaign = CouponCampaign::create([
            'name'              => 'TOEIC O2 UAT Promo',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 10,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'uses_per_code'     => 10,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(30),
            'is_active'         => true,
        ]);

        $this->coupon = Coupon::create([
            'campaign_id' => $this->campaign->id,
            'code'        => 'TOEIC-JX3JFS',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active'   => true,
        ]);
    }

    /**
     * Helper to create an institutional order with applied voucher and pending payment.
     */
    protected function createInstitutionalOrderAndPayment(int $quantity = 3): array
    {
        $uniqueSuffix = strtoupper(\Illuminate\Support\Str::random(4));
        $unitPrice = 85000;
        $subtotal = $unitPrice * $quantity; // 255,000
        $discount = (float) round($subtotal * 0.10); // 25,500
        $taxable = $subtotal - $discount; // 229,500
        $tax = (float) round($taxable * 0.11); // 25,245
        $grandTotal = $taxable + $tax; // 254,745

        $order = Order::create([
            'order_number'    => 'ORD-20260907-' . $uniqueSuffix,
            'organization_id' => $this->organization->id,
            'user_id'         => $this->purchaserUser->id,
            'coupon_id'       => $this->coupon->id,
            'subtotal'        => $subtotal,
            'discount'        => $discount,
            'tax'             => $tax,
            'grand_total'     => $grandTotal,
            'status'          => OrderStatus::Pending,
        ]);

        $item = OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'quantity'   => $quantity,
            'price'      => $unitPrice,
            'total'      => $subtotal,
        ]);

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'user_id'        => $this->purchaserUser->id,
            'invoice_number' => 'INV-20260907-' . $uniqueSuffix,
            'amount'         => $grandTotal,
            'status'         => InvoiceStatus::Unpaid,
            'due_date'       => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->purchaserUser->id,
            'amount'           => $grandTotal,
            'payment_method'   => 'manual_bank_transfer',
            'payment_gateway'  => 'manual_transfer',
            'reference_number' => 'PAY-20260907-' . $uniqueSuffix,
            'status'           => PaymentStatus::Pending,
        ]);

        // Reserve voucher
        $redemption = CouponRedemption::create([
            'coupon_id'        => $this->coupon->id,
            'user_id'          => $this->purchaserUser->id,
            'order_id'         => $order->id,
            'discount_applied' => $discount,
            'status'           => 'reserved',
            'reserved_at'      => now(),
        ]);

        return compact('order', 'item', 'invoice', 'payment', 'redemption');
    }

    /**
     * Helper to create an individual B2C candidate order with pending payment.
     */
    protected function createCandidateOrderAndPayment(): array
    {
        $unitPrice = 85000;
        $tax = (float) round($unitPrice * 0.11);
        $grandTotal = $unitPrice + $tax;

        $order = Order::create([
            'order_number'    => 'ORD-B2C-TEST',
            'organization_id' => null,
            'user_id'         => $this->candidateUser->id,
            'coupon_id'       => null,
            'subtotal'        => $unitPrice,
            'discount'        => 0,
            'tax'             => $tax,
            'grand_total'     => $grandTotal,
            'status'          => OrderStatus::Pending,
        ]);

        $item = OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'quantity'   => 1,
            'price'      => $unitPrice,
            'total'      => $unitPrice,
        ]);

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'user_id'        => $this->candidateUser->id,
            'invoice_number' => 'INV-B2C-TEST',
            'amount'         => $grandTotal,
            'status'         => InvoiceStatus::Unpaid,
            'due_date'       => now()->addDays(7),
        ]);

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $grandTotal,
            'payment_method'   => 'manual_bank_transfer',
            'payment_gateway'  => 'manual_transfer',
            'reference_number' => 'PAY-B2C-TEST',
            'status'           => PaymentStatus::Pending,
        ]);

        return compact('order', 'item', 'invoice', 'payment');
    }

    public function test_fa_01_institutional_http_approval_provisions_organization_entitlement(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(3);
        $payment = $fixture['payment'];
        $item = $fixture['item'];

        $this->assertEquals(0, OrganizationEntitlement::where('organization_id', $this->organization->id)->count());

        $response = $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id), [
                'transaction_id' => 'BCA-UAT-998811',
            ]);

        $response->assertRedirect(route('finance.payments.show', $payment->id));
        $response->assertSessionHas('status', "Payment {$payment->reference_number} confirmed successfully. An entitlement pool with 3 seats has been provisioned for {$this->organization->name}.");

        // Assert exactly 1 entitlement created
        $entitlements = OrganizationEntitlement::where('organization_id', $this->organization->id)->get();
        $this->assertCount(1, $entitlements);

        $entitlement = $entitlements->first();
        $this->assertEquals($item->id, $entitlement->order_item_id);
        $this->assertEquals($this->product->id, $entitlement->product_id);
        $this->assertEquals(3, $entitlement->total_seats);
        $this->assertEquals(0, $entitlement->allocatedSeatsCount());
        $this->assertEquals(3, $entitlement->availableSeatsCount());
        $this->assertEquals(EntitlementStatus::Active, $entitlement->status);
    }

    public function test_fa_02_institutional_http_approval_creates_zero_candidate_test_assignments(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(3);
        $payment = $fixture['payment'];

        $this->assertEquals(0, CandidateTestAssignment::count());

        $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id), [
                'transaction_id' => 'BCA-UAT-998811',
            ]);

        // Zero candidate test assignments must exist
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_fa_03_purchaser_candidate_test_access_remains_untouched(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(3);
        $payment = $fixture['payment'];

        $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id), [
                'transaction_id' => 'BCA-UAT-998811',
            ]);

        // Purchaser has zero candidate assignments
        $this->assertEquals(0, CandidateTestAssignment::where('candidate_id', $this->purchaserUser->id)->count());
    }

    public function test_fa_04_voucher_lifecycle_consumes_and_updates_used_count(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(3);
        $payment = $fixture['payment'];
        $redemption = $fixture['redemption'];

        $this->assertEquals(0, $this->coupon->fresh()->used_count);
        $this->assertEquals('reserved', $redemption->fresh()->status);

        $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id), [
                'transaction_id' => 'BCA-UAT-998811',
            ]);

        $this->coupon->refresh();
        $this->assertEquals(1, $this->coupon->used_count);
        $this->assertEquals(9, $this->coupon->usage_limit - $this->coupon->used_count);

        $redemption->refresh();
        $this->assertEquals('consumed', $redemption->status);
        $this->assertNotNull($redemption->consumed_at);
    }

    public function test_fa_05_re_approval_is_blocked_idempotently(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(3);
        $payment = $fixture['payment'];

        // First approval
        $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id), [
                'transaction_id' => 'BCA-UAT-998811',
            ]);

        $this->assertEquals(1, OrganizationEntitlement::where('organization_id', $this->organization->id)->count());
        $this->assertEquals(1, $this->coupon->fresh()->used_count);

        // Second approval attempt
        $response = $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id), [
                'transaction_id' => 'BCA-UAT-DUPLICATE',
            ]);

        $response->assertSessionHasErrors(['error']);

        // Assert no duplicate entitlement or double coupon consumption
        $this->assertEquals(1, OrganizationEntitlement::where('organization_id', $this->organization->id)->count());
        $this->assertEquals(1, $this->coupon->fresh()->used_count);
    }

    public function test_fa_06_institutional_show_view_renders_normalized_semantics(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(3);
        $payment = $fixture['payment'];

        $response = $this->actingAs($this->financeUser)
            ->get(route('finance.payments.show', $payment->id));

        $response->assertOk();
        $response->assertSee('Institutional Purchaser');
        $response->assertSee('iC.edu UAT University');
        $response->assertSee('Indra Wahyudi');
        $response->assertSee('Approving confirms the payment, marks the invoice as paid and the order as completed, and provisions the purchased seat entitlements for the organization.');
        $response->assertSee('Approve Payment &amp; Provision Entitlements', false);
        $response->assertDontSee('Approve Payment &amp; Grant Eligibility', false);
    }

    public function test_fa_07_candidate_show_view_renders_b2c_semantics(): void
    {
        $fixture = $this->createCandidateOrderAndPayment();
        $payment = $fixture['payment'];

        $response = $this->actingAs($this->financeUser)
            ->get(route('finance.payments.show', $payment->id));

        $response->assertOk();
        $response->assertSee('Candidate / Payer');
        $response->assertSee('Budi Candidate');
        $response->assertSee('Approving confirms the payment, marks the invoice as paid and the order as completed, and activates the candidate as Paid &amp; Eligible according to the existing Candidate commerce workflow.', false);
        $response->assertSee('Approve Payment &amp; Grant Eligibility', false);
        $response->assertDontSee('Approve Payment &amp; Provision Entitlements', false);
    }

    public function test_fa_08_notification_text_branches_for_institutional_vs_candidate(): void
    {
        // 1. Institutional Notification on PaymentCreated
        $fixtureOrg = $this->createInstitutionalOrderAndPayment(3);
        $paymentOrg = $fixtureOrg['payment'];

        $listener = new \App\Listeners\SendCommercePaymentNotifications();
        $listener->handlePaymentCreated(new PaymentCreated($paymentOrg));

        $this->financeUser->refresh();
        $financeNotif = $this->financeUser->notifications()->first();
        $this->assertNotNull($financeNotif);
        $this->assertEquals('💳 New Institutional Payment Pending Review', $financeNotif->data['title']);
        $this->assertStringContainsString('Organization iC.edu UAT University (Purchaser: Indra Wahyudi', $financeNotif->data['message']);

        // 2. Institutional Notification on PaymentConfirmed
        $listener->handlePaymentConfirmed(new PaymentConfirmed($paymentOrg));

        $this->purchaserUser->refresh();
        $purchaserNotif = $this->purchaserUser->notifications()->first();
        $this->assertNotNull($purchaserNotif);
        $this->assertEquals('🎉 Payment Confirmed', $purchaserNotif->data['title']);
        $this->assertStringContainsString('Seat entitlements have been provisioned for iC.edu UAT University', $purchaserNotif->data['message']);
        $this->assertEquals(route('organization.purchases.show', [$this->organization->slug, $fixtureOrg['order']->id]), $purchaserNotif->data['target_url']);

        $this->raUser->refresh();
        $raNotif = $this->raUser->notifications()->first();
        $this->assertNotNull($raNotif);
        $this->assertEquals('✅ Institutional Payment Confirmed — Entitlements Provisioned', $raNotif->data['title']);
        $this->assertStringContainsString("Institutional payment #{$paymentOrg->reference_number} for iC.edu UAT University (TOEIC Mock Test Package) has been confirmed and seat entitlements are provisioned.", $raNotif->data['message']);

        // 3. Candidate Notification on PaymentCreated & PaymentConfirmed
        $fixtureB2c = $this->createCandidateOrderAndPayment();
        $paymentB2c = $fixtureB2c['payment'];

        // Clear notifications to test B2C cleanly
        \Illuminate\Support\Facades\DB::table('notifications')->truncate();

        $listener->handlePaymentCreated(new PaymentCreated($paymentB2c));
        $this->financeUser->refresh();
        $b2cFinanceNotif = $this->financeUser->notifications()->first();
        $this->assertEquals('💳 New Candidate Payment Pending Review', $b2cFinanceNotif->data['title']);
        $this->assertStringContainsString('Candidate Budi Candidate', $b2cFinanceNotif->data['message']);

        $listener->handlePaymentConfirmed(new PaymentConfirmed($paymentB2c));
        $this->candidateUser->refresh();
        $candidateNotif = $this->candidateUser->notifications()->first();
        $this->assertEquals('🎉 Payment Confirmed', $candidateNotif->data['title']);
        $this->assertEquals(route('candidate.payments.show', $paymentB2c->id), $candidateNotif->data['target_url']);

        $this->raUser->refresh();
        $b2cRaNotif = $this->raUser->notifications()->first();
        $this->assertEquals('✅ Candidate Payment Confirmed — Paid & Eligible', $b2cRaNotif->data['title']);
        $this->assertStringContainsString('Candidate Budi Candidate has paid', $b2cRaNotif->data['message']);
    }

    public function test_fa_09_finance_index_view_renders_normalized_terminology(): void
    {
        $response = $this->actingAs($this->financeUser)
            ->get(route('finance.payments.index'));

        $response->assertOk();
        $response->assertSee('Review transaction history, invoice records, and payment proofs.');
        $response->assertSee('Customer, Ref #, Invoice, TXN ID...');
        $response->assertSee('Customer / Payer');
    }

    public function test_fa_10_singular_flash_message_for_one_seat(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(1);
        $payment = $fixture['payment'];

        $response = $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id), [
                'transaction_id' => 'BCA-SINGULAR-01',
            ]);

        $response->assertRedirect(route('finance.payments.show', $payment->id));
        $response->assertSessionHas('status', "Payment {$payment->reference_number} confirmed successfully. An entitlement pool with 1 seat has been provisioned for {$this->organization->name}.");
    }

    public function test_o2_prov_01_entitlement_list_renders_source_order_reference(): void
    {
        $fixture = $this->createInstitutionalOrderAndPayment(3);
        $payment = $fixture['payment'];
        $order = $fixture['order'];

        // Approve payment to create entitlement
        $this->actingAs($this->financeUser)
            ->post(route('finance.payments.approve', $payment->id));

        $entitlement = OrganizationEntitlement::where('organization_id', $this->organization->id)->first();
        $this->assertNotNull($entitlement);
        $this->assertEquals($order->order_number, $entitlement->order?->order_number);

        // View Entitlement List as Coordinator
        $response = $this->actingAs($this->purchaserUser)
            ->get(route('organization.entitlements', $this->organization->slug));

        $response->assertOk();
        $response->assertSee($order->order_number);
        $response->assertSee(route('organization.purchases.show', [$this->organization->slug, $order->id]));

        // View Entitlement Detail as Coordinator
        $showResponse = $this->actingAs($this->purchaserUser)
            ->get(route('organization.entitlements.show', [$this->organization->slug, $entitlement->id]));

        $showResponse->assertOk();
        $showResponse->assertSee($order->order_number);
        $showResponse->assertSee(route('organization.purchases.show', [$this->organization->slug, $order->id]));
    }

    public function test_o2_prov_02_multiple_orders_retain_independent_order_references(): void
    {
        // 1. Order A
        $fixtureA = $this->createInstitutionalOrderAndPayment(5);
        $paymentA = $fixtureA['payment'];
        $orderA = $fixtureA['order'];
        $this->actingAs($this->financeUser)->post(route('finance.payments.approve', $paymentA->id));

        // 2. Order B
        $fixtureB = $this->createInstitutionalOrderAndPayment(10);
        $paymentB = $fixtureB['payment'];
        $orderB = $fixtureB['order'];
        // Ensure distinct order number
        $orderB->update(['order_number' => 'ORD-20260907-DISTINCT']);
        $this->actingAs($this->financeUser)->post(route('finance.payments.approve', $paymentB->id));

        $entitlements = OrganizationEntitlement::where('organization_id', $this->organization->id)->get();
        $this->assertCount(2, $entitlements);

        // View Entitlements List
        $response = $this->actingAs($this->purchaserUser)
            ->get(route('organization.entitlements', $this->organization->slug));

        $response->assertOk();
        $response->assertSee($orderA->order_number);
        $response->assertSee('ORD-20260907-DISTINCT');
    }

    public function test_o2_prov_03_coordinator_cannot_access_other_organization_order_detail(): void
    {
        $otherOrg = Organization::create([
            'name'              => 'Other University',
            'legal_name'        => 'PT Other University',
            'slug'              => 'other-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'email'             => 'contact@other.edu',
        ]);

        $otherOrder = Order::create([
            'order_number'    => 'ORD-OTHER-9999',
            'organization_id' => $otherOrg->id,
            'user_id'         => $this->purchaserUser->id,
            'subtotal'        => 85000,
            'discount'        => 0,
            'tax'             => 9350,
            'grand_total'     => 94350,
            'status'          => OrderStatus::Pending,
        ]);

        // Purchaser belongs to Organization A, attempts to view Organization B's order in Org A tenant context
        $response = $this->actingAs($this->purchaserUser)
            ->get(route('organization.purchases.show', [$this->organization->slug, $otherOrder->id]));

        $response->assertStatus(403);
    }
}
