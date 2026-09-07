<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Application\CouponEngine;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceVoucherProductScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $studentUser;
    protected Product $toeicProduct;
    protected Product $toeflProduct;
    protected Product $ieltsProduct;
    protected CouponEngine $couponEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->studentUser = User::factory()->create();
        $this->studentUser->assignRole('student');

        $this->toeicProduct = Product::create([
            'title'             => 'TOEIC Standard Test',
            'slug'              => 'toeic-standard-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $this->toeflProduct = Product::create([
            'title'             => 'TOEFL ITP Standard Test',
            'slug'              => 'toefl-itp-standard-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
            'price'             => 120000,
            'is_active'         => true,
        ]);

        $this->ieltsProduct = Product::create([
            'title'             => 'IELTS Academic Preparation',
            'slug'              => 'ielts-academic-preparation',
            'product_type'      => 'assessment',
            'assessment_family' => 'ielts',
            'price'             => 200000,
            'is_active'         => true,
        ]);

        $this->couponEngine = new CouponEngine;
    }

    public function test_vscp_01_family_scoped_voucher_fails_on_incompatible_product(): void
    {
        $campaign = CouponCampaign::create([
            'name'              => 'TOEIC Family Only Promo',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 20,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'uses_per_code'     => 50,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(10),
            'is_active'         => true,
        ]);

        $coupon = Coupon::create([
            'campaign_id' => $campaign->id,
            'code'        => 'TOEICONLY20',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 50,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        // Validate against TOEFL product -> should FAIL
        $validationToefl = $this->couponEngine->validateCoupon('TOEICONLY20', $this->toeflProduct);
        $this->assertFalse($validationToefl['valid']);
        $this->assertStringContainsString('TOEIC packages', $validationToefl['reason']);

        // Validate against TOEIC product -> should SUCCEED
        $validationToeic = $this->couponEngine->validateCoupon('TOEICONLY20', $this->toeicProduct);
        $this->assertTrue($validationToeic['valid']);
    }

    public function test_vscp_02_product_scoped_voucher_fails_on_unselected_products(): void
    {
        $toeicProduct2 = Product::create([
            'title'             => 'TOEIC Advanced Prep',
            'slug'              => 'toeic-advanced-prep',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 150000,
            'is_active'         => true,
        ]);

        $campaign = CouponCampaign::create([
            'name'              => 'Selected TOEIC Product Promo',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'selected_products',
            'discount_type'     => 'fixed',
            'discount_value'    => 30000,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'uses_per_code'     => 20,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(10),
            'is_active'         => true,
        ]);

        // Attach only first TOEIC product
        $campaign->products()->attach($this->toeicProduct->id);

        $coupon = Coupon::create([
            'campaign_id' => $campaign->id,
            'code'        => 'TOEICPROD1',
            'type'        => 'fixed',
            'value'       => 30000,
            'usage_limit' => 20,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        // Validate against toeicProduct2 (unattached) -> should FAIL
        $validationUnattached = $this->couponEngine->validateCoupon('TOEICPROD1', $toeicProduct2);
        $this->assertFalse($validationUnattached['valid']);
        $this->assertStringContainsString('not applicable to the selected package', $validationUnattached['reason']);

        // Validate against attached product -> should SUCCEED
        $validationAttached = $this->couponEngine->validateCoupon('TOEICPROD1', $this->toeicProduct);
        $this->assertTrue($validationAttached['valid']);
    }

    public function test_vscp_03_legacy_unscoped_voucher_maintains_backward_compatibility(): void
    {
        $coupon = Coupon::create([
            'campaign_id' => null, // No campaign governance
            'code'        => 'GLOBALPROMO10',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        // Legacy coupon is valid for TOEIC, TOEFL, and IELTS
        $valToeic = $this->couponEngine->validateCoupon('GLOBALPROMO10', $this->toeicProduct);
        $this->assertTrue($valToeic['valid']);

        $valToefl = $this->couponEngine->validateCoupon('GLOBALPROMO10', $this->toeflProduct);
        $this->assertTrue($valToefl['valid']);

        $valIelts = $this->couponEngine->validateCoupon('GLOBALPROMO10', $this->ieltsProduct);
        $this->assertTrue($valIelts['valid']);
    }
}
