<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\CouponRedemption;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationOrderFinancialBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinatorUser;
    protected User $financeUser;
    protected Organization $organization;
    protected Product $product;
    protected CouponCampaign $campaign;
    protected Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Organization & Coordinator
        $this->organization = Organization::create([
            'name'              => 'iC.edu UAT University',
            'legal_name'        => 'PT iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'email'             => 'contact@uat.edu',
            'status'            => OrganizationStatus::Active,
        ]);

        $this->coordinatorUser = User::factory()->create([
            'name'   => 'Indra Wahyudi',
            'email'  => 'coordinator@uat.edu',
            'status' => 'active',
        ]);
        $this->coordinatorUser->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->coordinatorUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        // 2. Finance User
        $this->financeUser = User::factory()->create([
            'name'   => 'Finance Auditor',
            'email'  => 'finance@icedu.test',
            'status' => 'active',
        ]);
        $this->financeUser->assignRole('finance');

        // 3. Product (TOEIC Mock Test Package @ 85,000)
        $this->product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        // 4. Campaign & Voucher (10% Discount)
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

    protected function createVoucherOrderFixture(int $quantity = 3): Order
    {
        $unitPrice = 85000;
        $subtotal = $unitPrice * $quantity; // 255,000
        $discount = (float) round($subtotal * 0.10); // 25,500
        $taxable = $subtotal - $discount; // 229,500
        $tax = (float) round($taxable * 0.11); // 25,245
        $grandTotal = $taxable + $tax; // 254,745

        $order = Order::create([
            'user_id'         => $this->coordinatorUser->id,
            'organization_id' => $this->organization->id,
            'coupon_id'       => $this->coupon->id,
            'order_number'    => 'ORD-20260907-3DWQ',
            'status'          => OrderStatus::Pending,
            'subtotal'        => $subtotal,
            'discount'        => $discount,
            'tax'             => $tax,
            'grand_total'     => $grandTotal,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->product->id,
            'quantity'   => $quantity,
            'price'      => $unitPrice,
            'total'      => $grandTotal,
        ]);

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'user_id'        => $this->coordinatorUser->id,
            'invoice_number' => 'INV-20260907-DEZK',
            'amount'         => $grandTotal,
            'status'         => 'unpaid',
            'due_date'       => now()->addDay(),
        ]);

        Payment::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $this->coordinatorUser->id,
            'reference_number' => 'PAY-20260907-0NGF',
            'amount'           => $grandTotal,
            'currency'         => 'IDR',
            'payment_method'   => 'bank_transfer',
            'payment_gateway'  => 'manual_transfer',
            'status'           => 'pending',
        ]);

        CouponRedemption::create([
            'coupon_id'       => $this->coupon->id,
            'order_id'        => $order->id,
            'user_id'         => $this->coordinatorUser->id,
            'organization_id' => $this->organization->id,
            'status'          => 'reserved',
            'discount_amount' => $discount,
            'reserved_at'     => now(),
        ]);

        return $order;
    }

    public function test_oinv_01_voucher_order_renders_complete_financial_breakdown(): void
    {
        $order = $this->createVoucherOrderFixture(3);

        $response = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.show', [$this->organization->slug, $order->id]));

        $response->assertOk();

        // 1. Subtotal: Rp255.000
        $response->assertSee('255.000', false);

        // 2. Voucher Discount: -Rp25.500
        $response->assertSee('-Rp 25.500', false);

        // 3. Taxable Subtotal: Rp229.500
        $response->assertSee('Taxable Subtotal', false);
        $response->assertSee('229.500', false);

        // 4. VAT / PPN: Rp25.245
        $response->assertSee('VAT / PPN (11%)', false);
        $response->assertSee('25.245', false);

        // 5. Total Amount Due: Rp254.745
        $response->assertSee('Total Amount Due', false);
        $response->assertSee('254.745', false);
    }

    public function test_oinv_02_and_03_order_detail_displays_voucher_code_and_campaign(): void
    {
        $order = $this->createVoucherOrderFixture(3);

        $response = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.show', [$this->organization->slug, $order->id]));

        $response->assertOk();

        // Voucher code displayed
        $response->assertSee('TOEIC-JX3JFS', false);

        // Campaign name displayed
        $response->assertSee('TOEIC O2 UAT Promo', false);
    }

    public function test_oinv_04_order_item_row_displays_line_subtotal_not_grand_total(): void
    {
        $order = $this->createVoucherOrderFixture(3);

        $response = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.show', [$this->organization->slug, $order->id]));

        $response->assertOk();

        // Line Subtotal column header exists
        $response->assertSee('Line Subtotal', false);

        // Line Subtotal is 3 * 85,000 = 255,000 (rendered in items table)
        $response->assertSee('Rp 255.000', false);

        // Primary package title rendered cleanly
        $response->assertSee('TOEIC Mock Test Package', false);
    }

    public function test_oinv_05_order_detail_label_is_seat_quantity_not_allocated_seats(): void
    {
        $order = $this->createVoucherOrderFixture(3);

        $response = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.show', [$this->organization->slug, $order->id]));

        $response->assertOk();

        // Must display Seat Quantity
        $response->assertSee('Seat Quantity', false);

        // Must NOT display Allocated Seats
        $response->assertDontSee('Allocated Seats', false);
    }

    public function test_oinv_06_snapshot_immutability_when_product_and_voucher_change(): void
    {
        $order = $this->createVoucherOrderFixture(3);

        // Mutate current product price and voucher discount in DB
        $this->product->update(['price' => 120000]);
        $this->coupon->update(['value' => 50]);
        $this->campaign->update(['name' => 'Renamed Promo Campaign']);

        // Historical order presentation must remain completely immutable
        $response = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.show', [$this->organization->slug, $order->id]));

        $response->assertOk();

        // Persisted historical order numbers: 255,000 / 25,500 / 229,500 / 25,245 / 254,745
        $response->assertSee('255.000', false);
        $response->assertSee('-Rp 25.500', false);
        $response->assertSee('229.500', false);
        $response->assertSee('25.245', false);
        $response->assertSee('254.745', false);
    }

    public function test_oinv_07_reservation_state_and_zero_mutations_on_view(): void
    {
        $order = $this->createVoucherOrderFixture(3);

        $initialRedemption = CouponRedemption::where('order_id', $order->id)->first();
        $this->assertEquals('reserved', $initialRedemption->status);
        $this->assertEquals(0, $this->coupon->fresh()->used_count);

        // View order details
        $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.show', [$this->organization->slug, $order->id]))
            ->assertOk();

        // Verify zero mutations occurred
        $this->assertEquals('reserved', $initialRedemption->fresh()->status);
        $this->assertEquals(0, $this->coupon->fresh()->used_count);
        $this->assertEquals(0, OrganizationEntitlement::where('organization_id', $this->organization->id)->count());
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    public function test_oinv_08_finance_presentation_parity(): void
    {
        $order = $this->createVoucherOrderFixture(3);
        $payment = $order->invoice->payments->first();

        $response = $this->actingAs($this->financeUser)
            ->get(route('finance.payments.show', $payment->id));

        $response->assertOk();

        // Parity on breakdown values
        $response->assertSee('255,000', false); // Subtotal
        $response->assertSee('25,500', false);  // Discount
        $response->assertSee('Taxable Subtotal', false);
        $response->assertSee('229,500', false); // Taxable Subtotal
        $response->assertSee('25,245', false);  // VAT
        $response->assertSee('254,745', false); // Grand Total

        // Voucher & Campaign identity
        $response->assertSee('TOEIC-JX3JFS', false);
        $response->assertSee('TOEIC O2 UAT Promo', false);
    }
}
