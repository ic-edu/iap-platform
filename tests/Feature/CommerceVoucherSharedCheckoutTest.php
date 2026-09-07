<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceVoucherSharedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidateUser;
    protected User $coordinatorUser;
    protected Organization $organization;
    protected Product $product;
    protected Coupon $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Standalone Candidate
        $this->candidateUser = User::factory()->create(['email' => 'student@icedu.org']);
        $this->candidateUser->assignRole('student');

        // 2. Organization & Coordinator
        $this->organization = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'contact_email'     => 'uat@icedu.org',
            'status'            => 'active',
        ]);

        $this->coordinatorUser = User::factory()->create(['email' => 'coordinator@icedu.org']);
        $this->coordinatorUser->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->coordinatorUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        // 3. Product & 10% Voucher
        $this->product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $campaign = CouponCampaign::create([
            'name'              => 'Institution 10% Discount',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 10,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'uses_per_code'     => 100,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(30),
            'is_active'         => true,
        ]);

        $this->coupon = Coupon::create([
            'campaign_id' => $campaign->id,
            'code'        => 'TOEIC10',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active'   => true,
        ]);
    }

    public function test_vchk_01_candidate_quote_and_checkout_with_voucher_and_vat_parity(): void
    {
        // 1. Quote endpoint
        $quoteResponse = $this->actingAs($this->candidateUser)->postJson(route('candidate.checkout.quote', $this->product), [
            'product_id'   => $this->product->id,
            'quantity'     => 1,
            'voucher_code' => 'TOEIC10',
        ]);

        $quoteResponse->assertOk();
        $quoteResponse->assertJson([
            'valid'          => true,
            'base_price'     => 85000,
            'discount'       => 8500,  // 10% of 85,000
            'taxable_amount' => 76500,  // 85,000 - 8,500
            'tax'            => 8415,   // 11% of 76,500
            'grand_total'    => 84915,  // 76,500 + 8,415
            'voucher'        => [
                'applied'       => true,
                'code'          => 'TOEIC10',
                'campaign_name' => 'Institution 10% Discount',
            ],
        ]);

        // 2. Process candidate checkout
        $checkoutResponse = $this->actingAs($this->candidateUser)->post(route('candidate.checkout.process', $this->product), [
            'voucher_code' => 'TOEIC10',
        ]);

        $checkoutResponse->assertRedirect();

        $order = Order::where('user_id', $this->candidateUser->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->coupon->id, $order->coupon_id);
        $this->assertEquals(85000, $order->subtotal);
        $this->assertEquals(8500, $order->discount);
        $this->assertEquals(8415, $order->tax);
        $this->assertEquals(84915, $order->grand_total);
    }

    public function test_vchk_02_organization_quote_and_checkout_with_voucher_and_vat_parity(): void
    {
        // 1. Quote endpoint
        $quoteResponse = $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization), [
            'product_id'   => $this->product->id,
            'quantity'     => 3,
            'voucher_code' => 'TOEIC10',
        ]);

        $quoteResponse->assertOk();
        $quoteResponse->assertJson([
            'valid'          => true,
            'base_price'     => 255000, // 85,000 * 3
            'discount'       => 25500,  // 10% of 255,000
            'taxable_amount' => 229500, // 255,000 - 25,500
            'tax'            => 25245,  // 11% of 229,500
            'grand_total'    => 254745, // 229,500 + 25,245
            'voucher'        => [
                'applied'       => true,
                'code'          => 'TOEIC10',
                'campaign_name' => 'Institution 10% Discount',
            ],
        ]);

        // 2. Process organization purchase
        $purchaseResponse = $this->actingAs($this->coordinatorUser)->post(route('organization.purchases.store', $this->organization), [
            'product_id'   => $this->product->id,
            'quantity'     => 3,
            'voucher_code' => 'TOEIC10',
        ]);

        $purchaseResponse->assertRedirect();

        $order = Order::where('organization_id', $this->organization->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->coupon->id, $order->coupon_id);
        $this->assertEquals(255000, $order->subtotal);
        $this->assertEquals(25500, $order->discount);
        $this->assertEquals(25245, $order->tax);
        $this->assertEquals(254745, $order->grand_total);
    }

    public function test_vchk_03_invalid_voucher_in_checkout_returns_clean_error(): void
    {
        $response = $this->actingAs($this->candidateUser)->post(route('candidate.checkout.process', $this->product), [
            'voucher_code' => 'INVALID_CODE_999',
        ]);

        $response->assertSessionHasErrors(['voucher_code']);
    }
}
