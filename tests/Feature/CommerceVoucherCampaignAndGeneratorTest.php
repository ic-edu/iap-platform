<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Application\CouponGenerator;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceVoucherCampaignAndGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $financeUser;
    protected User $studentUser;
    protected Product $toeicProduct;
    protected Product $toeflProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->superAdminUser = User::factory()->create();
        $this->superAdminUser->assignRole('super-admin');

        $this->financeUser = User::factory()->create();
        $this->financeUser->assignRole('finance');

        $this->studentUser = User::factory()->create();
        $this->studentUser->assignRole('student');

        $this->toeicProduct = Product::create([
            'title'             => 'TOEIC Official Practice Test',
            'slug'              => 'toeic-official-practice-test',
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 85000,
            'is_active'         => true,
        ]);

        $this->toeflProduct = Product::create([
            'title'             => 'TOEFL ITP Diagnostic Simulation',
            'slug'              => 'toefl-itp-diagnostic-simulation',
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
            'price'             => 120000,
            'is_active'         => true,
        ]);
    }

    public function test_vgen_01_shared_campaign_generates_single_coupon_with_family_prefix_and_charset(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.store'), [
            'name'              => 'Fall Semester TOEIC Promo',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'selected_products',
            'products'          => [$this->toeicProduct->id],
            'discount_type'     => 'percentage',
            'discount_value'    => 20,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEICFALL20',
            'uses_per_code'     => 100,
            'valid_from'        => now()->format('Y-m-d H:i:s'),
            'valid_until'       => now()->addDays(30)->format('Y-m-d H:i:s'),
            'is_active'         => 1,
        ]);

        $response->assertRedirect();
        $campaign = CouponCampaign::where('name', 'Fall Semester TOEIC Promo')->first();
        $this->assertNotNull($campaign);
        $this->assertEquals('toeic', $campaign->assessment_family);
        $this->assertEquals('shared', $campaign->generation_mode);
        $this->assertTrue($campaign->products->contains($this->toeicProduct->id));

        $coupon = Coupon::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($coupon);
        $this->assertStringStartsWith('TOEICFALL20', $coupon->code);
        $this->assertEquals(100, $coupon->usage_limit);
        $this->assertEquals(20, $coupon->value);
    }

    public function test_vgen_02_batch_campaign_generates_n_unique_random_codes_with_family_prefix(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.store'), [
            'name'              => 'TOEFL Institution Single-Use Batch',
            'assessment_family' => 'toefl',
            'scope_mode'        => 'selected_products',
            'products'          => [$this->toeflProduct->id],
            'discount_type'     => 'fixed',
            'discount_value'    => 25000,
            'generation_mode'   => 'batch',
            'total_codes'       => 25,
            'uses_per_code'     => 1,
            'valid_from'        => now()->format('Y-m-d H:i:s'),
            'valid_until'       => now()->addDays(14)->format('Y-m-d H:i:s'),
            'is_active'         => 1,
        ]);

        $response->assertRedirect();
        $campaign = CouponCampaign::where('name', 'TOEFL Institution Single-Use Batch')->first();
        $this->assertNotNull($campaign);
        $this->assertEquals(25, $campaign->coupons()->count());

        $codes = $campaign->coupons()->pluck('code')->all();
        $this->assertCount(25, array_unique($codes), 'All generated batch codes must be strictly unique');

        foreach ($codes as $code) {
            $this->assertStringStartsWith('TOEFL-', $code);
            // Charset check: 23456789ABCDEFGHJKLMNPQRSTUVWXYZ (no 0, 1, I, O)
            $suffix = substr($code, 6);
            $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{6}$/', $suffix);
        }
    }

    public function test_vgen_03_campaign_lifecycle_status_and_toggle(): void
    {
        $campaign = CouponCampaign::create([
            'name'              => 'Lifecycle Test Campaign',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 15,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'uses_per_code'     => 50,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(10),
            'is_active'         => true,
        ]);

        $this->assertEquals('ACTIVE', $campaign->getEffectiveState());
        $this->assertTrue($campaign->isEffectiveActive());

        // Toggle to inactive
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.toggle', $campaign->id));
        $response->assertRedirect();
        $this->assertFalse($campaign->fresh()->is_active);
        $this->assertEquals('INACTIVE', $campaign->fresh()->getEffectiveState());

        // Toggle back to active
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.toggle', $campaign->id));
        $response->assertRedirect();
        $this->assertTrue($campaign->fresh()->is_active);
    }

    public function test_vgen_04_authorization_governance_for_campaign_management(): void
    {
        // Student cannot access campaign creation or store
        $this->actingAs($this->studentUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name' => 'Unauthorized Campaign',
            ])
            ->assertStatus(403);

        // Finance cannot create campaigns (RA / Super-admin only)
        $this->actingAs($this->financeUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        // Super-admin can create and manage
        $this->actingAs($this->superAdminUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertOk();
    }
}
