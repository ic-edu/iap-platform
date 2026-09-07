<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Commerce\Application\BillingEngine;
use App\Modules\Commerce\Application\CheckoutEngine;
use App\Modules\Commerce\Domain\Enums\CouponType;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceVoucherEngineCloseoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $financeUser;
    protected User $coordinatorUser;
    protected User $candidateUser;
    protected Organization $organization;
    protected Product $product;
    protected CouponCampaign $campaign;
    protected Coupon $coupon;
    protected CheckoutEngine $checkoutEngine;
    protected BillingEngine $billingEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Roles & Users
        $this->adminUser = User::factory()->create(['name' => 'Operational Admin']);
        $this->adminUser->assignRole('admin');

        $this->superAdminUser = User::factory()->create(['name' => 'Super Admin']);
        $this->superAdminUser->assignRole('super-admin');

        $this->financeUser = User::factory()->create(['name' => 'Finance Officer']);
        $this->financeUser->assignRole('finance');

        $this->coordinatorUser = User::factory()->create(['email' => 'coord@closeout.org', 'name' => 'Org Coordinator']);
        $this->coordinatorUser->assignRole('organization-coordinator');

        $this->candidateUser = User::factory()->create(['email' => 'student@closeout.org', 'name' => 'Standalone Candidate']);
        $this->candidateUser->assignRole('student');

        // 2. Organization Fixture
        $this->organization = Organization::create([
            'name'              => 'Closeout Test University',
            'slug'              => 'closeout-test-university',
            'organization_type' => OrganizationType::University,
            'contact_email'     => 'admin@closeout.org',
            'status'            => 'active',
        ]);

        OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->coordinatorUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        // 3. Product Fixture
        $this->product = Product::create([
            'title'             => 'TOEIC Diagnostic Package',
            'slug'              => 'toeic-diagnostic-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 100000,
            'is_active'         => true,
        ]);

        // 4. Campaign & Coupon Fixture
        $this->campaign = CouponCampaign::create([
            'name'              => 'Closeout Verification Campaign',
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
            'code'        => 'CLOSEOUT10',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active'   => true,
        ]);

        $this->checkoutEngine = app(CheckoutEngine::class);
        $this->billingEngine = app(BillingEngine::class);
    }

    // =========================================================================
    // SECTION A: GOVERNANCE TESTS (V-GOV-01 through V-GOV-06)
    // =========================================================================

    public function test_v_gov_01_operational_admin_can_create_voucher_campaign(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.store'), [
            'name'              => 'RA Governed Campaign',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 15,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'RA15',
            'uses_per_code'     => 50,
            'valid_from'        => now()->format('Y-m-d H:i:s'),
            'valid_until'       => now()->addDays(20)->format('Y-m-d H:i:s'),
            'is_active'         => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('coupon_campaigns', [
            'name'              => 'RA Governed Campaign',
            'assessment_family' => 'toeic',
        ]);
    }

    public function test_v_gov_02_operational_admin_can_generate_voucher_codes(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.store'), [
            'name'              => 'RA Batch Generation Campaign',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'fixed',
            'discount_value'    => 10000,
            'generation_mode'   => 'batch',
            'total_codes'       => 5,
            'uses_per_code'     => 1,
            'valid_from'        => now()->format('Y-m-d H:i:s'),
            'valid_until'       => now()->addDays(15)->format('Y-m-d H:i:s'),
            'is_active'         => 1,
        ]);

        $response->assertRedirect();
        $campaign = CouponCampaign::where('name', 'RA Batch Generation Campaign')->first();
        $this->assertNotNull($campaign);
        $this->assertEquals(5, $campaign->coupons()->count());
    }

    public function test_v_gov_03_finance_cannot_create_edit_generate_campaign(): void
    {
        // 1. Create campaign attempt
        $this->actingAs($this->financeUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        // 2. Store campaign attempt
        $this->actingAs($this->financeUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name' => 'Finance Unauthorized Campaign',
            ])
            ->assertStatus(403);

        // 3. Toggle campaign attempt
        $this->actingAs($this->financeUser)
            ->post(route('admin.commerce.campaigns.toggle', $this->campaign->id))
            ->assertStatus(403);

        // 4. Archive campaign attempt
        $this->actingAs($this->financeUser)
            ->delete(route('admin.commerce.campaigns.destroy', $this->campaign->id))
            ->assertStatus(403);
    }

    public function test_v_gov_04_organization_coordinator_cannot_create_edit_generate_campaign(): void
    {
        $this->actingAs($this->coordinatorUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        $this->actingAs($this->coordinatorUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name' => 'Coordinator Unauthorized Campaign',
            ])
            ->assertStatus(403);
    }

    public function test_v_gov_05_candidate_cannot_create_edit_generate_campaign(): void
    {
        $this->actingAs($this->candidateUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        $this->actingAs($this->candidateUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name' => 'Candidate Unauthorized Campaign',
            ])
            ->assertStatus(403);
    }

    public function test_v_gov_06_super_admin_behavior_matches_locked_oversight_policy(): void
    {
        // Super admin has visibility into commercial catalog
        $response = $this->actingAs($this->superAdminUser)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee($this->campaign->name);
    }

    // =========================================================================
    // SECTION B: APPLY/QUOTE MUTATION BOUNDARY (V-BOUNDARY-01 to V-BOUNDARY-05)
    // =========================================================================

    public function test_v_boundary_01_apply_valid_voucher_leaves_redemptions_count_unchanged(): void
    {
        $initialRedemptionCount = CouponRedemption::count();
        $initialOrderCount = Order::count();
        $initialInvoiceCount = Invoice::count();
        $initialPaymentCount = Payment::count();

        // 1. Candidate quote / apply
        $candidateQuote = $this->actingAs($this->candidateUser)->postJson(route('candidate.checkout.quote', $this->product), [
            'product_id'   => $this->product->id,
            'quantity'     => 1,
            'voucher_code' => 'CLOSEOUT10',
        ]);
        $candidateQuote->assertOk();
        $candidateQuote->assertJsonPath('valid', true);
        $candidateQuote->assertJsonPath('voucher.applied', true);

        // 2. Organization quote / apply
        $orgQuote = $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization), [
            'product_id'   => $this->product->id,
            'quantity'     => 5,
            'voucher_code' => 'CLOSEOUT10',
        ]);
        $orgQuote->assertOk();
        $orgQuote->assertJsonPath('valid', true);
        $orgQuote->assertJsonPath('voucher.applied', true);

        // Verification: 0 mutations occurred
        $this->assertEquals($initialRedemptionCount, CouponRedemption::count(), 'Applying/quoting voucher must create 0 redemptions');
        $this->assertEquals($initialOrderCount, Order::count(), 'Applying/quoting voucher must create 0 orders');
        $this->assertEquals($initialInvoiceCount, Invoice::count(), 'Applying/quoting voucher must create 0 invoices');
        $this->assertEquals($initialPaymentCount, Payment::count(), 'Applying/quoting voucher must create 0 payments');
    }

    public function test_v_boundary_02_repeated_quote_requests_leaves_redemptions_count_unchanged(): void
    {
        $initialRedemptions = CouponRedemption::count();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->candidateUser)->postJson(route('candidate.checkout.quote', $this->product), [
                'product_id'   => $this->product->id,
                'quantity'     => 1,
                'voucher_code' => 'CLOSEOUT10',
            ])->assertOk();
        }

        $this->assertEquals($initialRedemptions, CouponRedemption::count());
    }

    public function test_v_boundary_03_quote_leaves_used_count_unchanged(): void
    {
        $initialUsedCount = $this->coupon->used_count;

        $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization), [
            'product_id'   => $this->product->id,
            'quantity'     => 2,
            'voucher_code' => 'CLOSEOUT10',
        ])->assertOk();

        $this->assertEquals($initialUsedCount, $this->coupon->fresh()->used_count);
    }

    public function test_v_boundary_04_create_voucher_order_creates_exactly_one_reserved_redemption(): void
    {
        $initialCount = CouponRedemption::count();

        $response = $this->actingAs($this->candidateUser)->post(route('candidate.checkout.process', $this->product), [
            'voucher_code' => 'CLOSEOUT10',
        ]);

        $response->assertRedirect();

        $this->assertEquals($initialCount + 1, CouponRedemption::count());

        $order = Order::where('user_id', $this->candidateUser->id)->latest()->first();
        $this->assertNotNull($order);

        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertNotNull($redemption);
        $this->assertEquals('reserved', $redemption->status);
        $this->assertEquals($this->coupon->id, $redemption->coupon_id);
        $this->assertEquals(10000, $redemption->discount_amount);
        $this->assertNull($redemption->consumed_at);
        $this->assertNull($redemption->released_at);
    }

    public function test_v_boundary_05_repeated_order_submit_idempotent_retry_cannot_create_duplicate_reservation(): void
    {
        // 1. Initial checkout
        $result1 = $this->checkoutEngine->checkout(
            $this->candidateUser,
            $this->product,
            1,
            $this->coupon
        );

        $order1 = $result1['order'];
        $this->assertEquals(1, CouponRedemption::where('order_id', $order1->id)->count());

        // 2. Repeated checkout for same pending order / product
        $result2 = $this->checkoutEngine->checkout(
            $this->candidateUser,
            $this->product,
            1,
            $this->coupon
        );

        $order2 = $result2['order'];
        $this->assertEquals($order1->id, $order2->id, 'Idempotent checkout must return existing pending order');
        $this->assertEquals(1, CouponRedemption::where('order_id', $order1->id)->count(), 'Duplicate reservation must not be created');
    }

    // =========================================================================
    // SECTION C: PAYMENT LIFECYCLE & IDEMPOTENCY
    // =========================================================================

    public function test_v_pay_01_payment_approval_consumes_redemption_and_increments_used_count_once(): void
    {
        $result = $this->checkoutEngine->checkout($this->candidateUser, $this->product, 1, $this->coupon);
        $order = $result['order'];
        $invoice = $result['invoice'];

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $order->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-CLOSEOUT-001',
        ]);

        $this->assertEquals(0, $this->coupon->fresh()->used_count);

        // Approve payment
        $this->billingEngine->confirmPayment($payment, 'TXN-BANK-001');

        $this->assertEquals(1, $this->coupon->fresh()->used_count);
        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertEquals('consumed', $redemption->status);
        $this->assertNotNull($redemption->consumed_at);
    }

    public function test_v_pay_02_payment_cancellation_releases_redemption_and_restores_capacity(): void
    {
        $result = $this->checkoutEngine->checkout($this->candidateUser, $this->product, 1, $this->coupon);
        $order = $result['order'];
        $invoice = $result['invoice'];

        $this->assertEquals(9, $this->coupon->fresh()->getAvailableUses());

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $order->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-CLOSEOUT-002',
        ]);

        // Reject / Cancel payment
        $this->billingEngine->cancelPayment($payment, 'Invalid bank proof');

        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertEquals('released', $redemption->status);
        $this->assertNotNull($redemption->released_at);

        // Capacity restored
        $this->assertEquals(10, $this->coupon->fresh()->getAvailableUses());
        $this->assertEquals(0, $this->coupon->fresh()->used_count);
    }

    public function test_v_pay_03_payment_approval_retry_is_strictly_idempotent(): void
    {
        $result = $this->checkoutEngine->checkout($this->candidateUser, $this->product, 1, $this->coupon);
        $order = $result['order'];
        $invoice = $result['invoice'];

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->candidateUser->id,
            'amount'           => $order->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-CLOSEOUT-003',
        ]);

        // 1st confirmation
        $this->billingEngine->confirmPayment($payment, 'TXN-BANK-003');
        $this->assertEquals(1, $this->coupon->fresh()->used_count);

        // 2nd confirmation retry
        $this->billingEngine->confirmPayment($payment, 'TXN-BANK-003');
        $this->assertEquals(1, $this->coupon->fresh()->used_count, 'Approval retry must not double-increment used_count');
    }

    // =========================================================================
    // SECTION D: O2 ENTITLEMENT PRESERVATION & O3 ISOLATION
    // =========================================================================

    public function test_v_o2_entitlement_provisioned_and_idempotent_on_voucher_order_payment(): void
    {
        $result = $this->checkoutEngine->checkout(
            $this->coordinatorUser,
            $this->product,
            4,
            $this->coupon,
            $this->organization
        );

        $order = $result['order'];
        $invoice = $result['invoice'];

        $payment = Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->coordinatorUser->id,
            'amount'           => $order->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-ORG-CLOSEOUT',
        ]);

        // 1st confirmation
        $this->billingEngine->confirmPayment($payment, 'TXN-ORG-001');

        $entitlements = OrganizationEntitlement::where('organization_id', $this->organization->id)->get();
        $this->assertCount(1, $entitlements);
        $this->assertEquals(4, $entitlements->first()->total_seats);

        // 2nd confirmation retry
        $this->billingEngine->confirmPayment($payment, 'TXN-ORG-001');
        $this->assertEquals(1, OrganizationEntitlement::where('organization_id', $this->organization->id)->count());
    }

    public function test_v_o3_zero_candidate_test_assignments_created_during_voucher_lifecycle(): void
    {
        $initialAssignmentsCount = CandidateTestAssignment::count();

        // 1. Quote
        $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization), [
            'product_id'   => $this->product->id,
            'quantity'     => 3,
            'voucher_code' => 'CLOSEOUT10',
        ])->assertOk();

        // 2. Checkout
        $result = $this->checkoutEngine->checkout(
            $this->coordinatorUser,
            $this->product,
            3,
            $this->coupon,
            $this->organization
        );

        // 3. Payment confirmation
        $payment = Payment::create([
            'invoice_id'       => $result['invoice']->id,
            'user_id'          => $this->coordinatorUser->id,
            'amount'           => $result['order']->grand_total,
            'payment_gateway'  => 'bank_transfer',
            'status'           => 'pending',
            'reference_number' => 'PAY-O3-CHECK',
        ]);
        $this->billingEngine->confirmPayment($payment, 'TXN-O3');

        // Verification: CandidateTestAssignment must remain strictly 0 new records
        $this->assertEquals($initialAssignmentsCount, CandidateTestAssignment::count(), 'Voucher and commerce flows must create 0 CandidateTestAssignment records');
    }
}
