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

class CommerceVoucherRemoveActionTest extends TestCase
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
        $this->candidateUser = User::factory()->create(['email' => 'candidate.student@icedu.org']);
        $this->candidateUser->assignRole('student');

        // 2. Organization & Coordinator
        $this->organization = Organization::create([
            'name'              => 'iC.edu Assessment University',
            'slug'              => 'icedu-assessment-univ',
            'organization_type' => OrganizationType::University,
            'contact_email'     => 'admin@icedu-assessment.org',
            'status'            => 'active',
        ]);

        $this->coordinatorUser = User::factory()->create(['email' => 'coordinator.head@icedu.org']);
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
            'price'             => 100000,
            'is_active'         => true,
        ]);

        $campaign = CouponCampaign::create([
            'name'              => 'TOEIC Promo Campaign',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 10,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'uses_per_code'     => 50,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(30),
            'is_active'         => true,
        ]);

        $this->coupon = Coupon::create([
            'campaign_id' => $campaign->id,
            'code'        => 'TOEIC-JX3JFS',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 50,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(30),
            'is_active'   => true,
        ]);
    }

    public function test_vui_remove_01_applied_voucher_success_panel_remains_green(): void
    {
        // 1. Organization Checkout View
        $orgResponse = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.create', $this->organization->slug));
        $orgResponse->assertOk();
        $orgResponse->assertSee('bg-emerald-500/10', false);
        $orgResponse->assertSee('border-emerald-500/20', false);
        $orgResponse->assertSee('text-emerald-700 dark:text-emerald-300', false);

        // 2. Candidate Checkout View
        $candResponse = $this->actingAs($this->candidateUser)
            ->get(route('candidate.checkout.show', $this->product->id));
        $candResponse->assertOk();
        $candResponse->assertSee('bg-emerald-500/10', false);
        $candResponse->assertSee('border-emerald-500/20', false);
        $candResponse->assertSee('text-emerald-700 dark:text-emerald-300', false);
    }

    public function test_vui_remove_02_label_is_exactly_remove_voucher(): void
    {
        // 1. Organization Checkout View
        $orgResponse = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.create', $this->organization->slug));
        $orgResponse->assertOk();
        $orgResponse->assertSee('Remove Voucher', false);
        $orgResponse->assertSee('aria-label="Remove Voucher"', false);

        // 2. Candidate Checkout View
        $candResponse = $this->actingAs($this->candidateUser)
            ->get(route('candidate.checkout.show', $this->product->id));
        $candResponse->assertOk();
        $candResponse->assertSee('Remove Voucher', false);
        $candResponse->assertSee('aria-label="Remove Voucher"', false);
    }

    public function test_vui_remove_03_action_uses_small_solid_red_danger_styling(): void
    {
        // 1. Organization Checkout View
        $orgResponse = $this->actingAs($this->coordinatorUser)
            ->get(route('organization.purchases.create', $this->organization->slug));
        $orgResponse->assertOk();
        $orgResponse->assertSee('bg-red-600', false);
        $orgResponse->assertSee('hover:bg-red-700', false);
        $orgResponse->assertSee('text-white', false);
        $orgResponse->assertSee('rounded-lg', false);

        // 2. Candidate Checkout View
        $candResponse = $this->actingAs($this->candidateUser)
            ->get(route('candidate.checkout.show', $this->product->id));
        $candResponse->assertOk();
        $candResponse->assertSee('bg-red-600', false);
        $candResponse->assertSee('hover:bg-red-700', false);
        $candResponse->assertSee('text-white', false);
        $candResponse->assertSee('rounded-lg', false);
    }

    public function test_vui_remove_04_quote_endpoint_without_voucher_recalculates_canonical_quote(): void
    {
        // 1. Organization Quote with Voucher
        $resWithVoucher = $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization->slug), [
            'product_id'   => $this->product->id,
            'quantity'     => 10,
            'voucher_code' => 'TOEIC-JX3JFS',
        ]);
        $resWithVoucher->assertOk();
        $this->assertEquals(1000000, $resWithVoucher->json('base_price'));
        $this->assertEquals(100000, $resWithVoucher->json('discount'));
        $this->assertEquals(900000, $resWithVoucher->json('taxable_amount'));
        $this->assertEquals(99000, $resWithVoucher->json('tax'));
        $this->assertEquals(999000, $resWithVoucher->json('grand_total'));
        $this->assertTrue($resWithVoucher->json('voucher.applied'));

        // 2. Organization Quote removing voucher (voucher_code null/empty)
        $resWithoutVoucher = $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization->slug), [
            'product_id'   => $this->product->id,
            'quantity'     => 10,
            'voucher_code' => null,
        ]);
        $resWithoutVoucher->assertOk();
        $this->assertEquals(1000000, $resWithoutVoucher->json('base_price'));
        $this->assertEquals(0, $resWithoutVoucher->json('discount'));
        $this->assertEquals(1000000, $resWithoutVoucher->json('taxable_amount'));
        $this->assertEquals(110000, $resWithoutVoucher->json('tax'));
        $this->assertEquals(1110000, $resWithoutVoucher->json('grand_total'));
        $this->assertFalse($resWithoutVoucher->json('voucher.applied'));

        // 3. Candidate Quote with Voucher
        $candWithVoucher = $this->actingAs($this->candidateUser)->postJson(route('candidate.checkout.quote', $this->product->id), [
            'voucher_code' => 'TOEIC-JX3JFS',
        ]);
        $candWithVoucher->assertOk();
        $this->assertEquals(100000, $candWithVoucher->json('base_price'));
        $this->assertEquals(10000, $candWithVoucher->json('discount'));
        $this->assertEquals(90000, $candWithVoucher->json('taxable_amount'));
        $this->assertEquals(9900, $candWithVoucher->json('tax'));
        $this->assertEquals(99900, $candWithVoucher->json('grand_total'));

        // 4. Candidate Quote removing voucher
        $candWithoutVoucher = $this->actingAs($this->candidateUser)->postJson(route('candidate.checkout.quote', $this->product->id), [
            'voucher_code' => null,
        ]);
        $candWithoutVoucher->assertOk();
        $this->assertEquals(100000, $candWithoutVoucher->json('base_price'));
        $this->assertEquals(0, $candWithoutVoucher->json('discount'));
        $this->assertEquals(100000, $candWithoutVoucher->json('taxable_amount'));
        $this->assertEquals(11000, $candWithoutVoucher->json('tax'));
        $this->assertEquals(111000, $candWithoutVoucher->json('grand_total'));
    }

    public function test_vui_remove_05_removing_voucher_creates_zero_redemptions_and_zero_orders(): void
    {
        $initialOrderCount = Order::count();

        // Perform quotes with voucher and quotes without voucher
        $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization->slug), [
            'product_id'   => $this->product->id,
            'quantity'     => 5,
            'voucher_code' => 'TOEIC-JX3JFS',
        ]);

        $this->actingAs($this->coordinatorUser)->postJson(route('organization.purchases.quote', $this->organization->slug), [
            'product_id'   => $this->product->id,
            'quantity'     => 5,
            'voucher_code' => null,
        ]);

        $this->actingAs($this->candidateUser)->postJson(route('candidate.checkout.quote', $this->product->id), [
            'voucher_code' => 'TOEIC-JX3JFS',
        ]);

        $this->actingAs($this->candidateUser)->postJson(route('candidate.checkout.quote', $this->product->id), [
            'voucher_code' => '',
        ]);

        // Zero database mutations
        $this->assertEquals($initialOrderCount, Order::count());
        $this->assertEquals(0, $this->coupon->fresh()->used_count);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }
}
