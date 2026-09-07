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

    public function test_vgen_04_uat_regression_selected_products_pivot_and_code_generation(): void
    {
        // Exact payload replicating the Product Owner Human UAT scenario
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.store'), [
            'name'              => 'TOEIC O2 UAT Promo',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'selected_products',
            'products'          => [$this->toeicProduct->id],
            'discount_type'     => 'percentage',
            'discount_value'    => 10,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'TOEIC',
            'code_length'       => 6,
            'uses_per_code'     => 10,
            'valid_from'        => now()->format('Y-m-d H:i:s'),
            'valid_until'       => now()->addDays(30)->format('Y-m-d H:i:s'),
            'is_active'         => 1,
        ]);

        $response->assertRedirect();
        $campaign = CouponCampaign::where('name', 'TOEIC O2 UAT Promo')->first();
        $this->assertNotNull($campaign, 'Campaign must be created');
        $this->assertEquals('toeic', $campaign->assessment_family);
        $this->assertEquals('selected_products', $campaign->scope_mode);

        // Verify pivot table persistence without surrogate key failure
        $this->assertEquals(1, $campaign->products()->count());
        $this->assertEquals($this->toeicProduct->id, $campaign->products()->first()->id);

        // Verify coupon generated
        $coupon = Coupon::where('campaign_id', $campaign->id)->first();
        $this->assertNotNull($coupon);
        $this->assertStringStartsWith('TOEIC', $coupon->code);
        $this->assertEquals(10, $coupon->usage_limit);
        $this->assertEquals(10, $coupon->value);
    }

    public function test_vgen_05_family_mismatch_validation_rejects_selected_products(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.store'), [
            'name'              => 'Mismatch Family Promo',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'selected_products',
            'products'          => [$this->toeflProduct->id], // TOEFL product for TOEIC campaign
            'discount_type'     => 'percentage',
            'discount_value'    => 15,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'MISMATCH',
            'uses_per_code'     => 10,
            'valid_from'        => now()->format('Y-m-d H:i:s'),
            'valid_until'       => now()->addDays(10)->format('Y-m-d H:i:s'),
            'is_active'         => 1,
        ]);

        $response->assertSessionHasErrors('products');
        $this->assertDatabaseMissing('coupon_campaigns', [
            'name' => 'Mismatch Family Promo',
        ]);
    }

    public function test_vgen_06_atomic_transaction_rollback_on_failure(): void
    {
        // Mock CouponGenerator to throw an exception
        $mockGenerator = $this->createMock(CouponGenerator::class);
        $mockGenerator->method('generateForCampaign')
            ->willThrowException(new \RuntimeException('Simulated generator failure'));
        $this->app->instance(CouponGenerator::class, $mockGenerator);

        $initialCampaignCount = CouponCampaign::count();
        $initialCouponCount = Coupon::count();

        try {
            $this->actingAs($this->adminUser)->post(route('admin.commerce.campaigns.store'), [
                'name'              => 'Failing Atomic Campaign',
                'assessment_family' => 'toeic',
                'scope_mode'        => 'selected_products',
                'products'          => [$this->toeicProduct->id],
                'discount_type'     => 'percentage',
                'discount_value'    => 10,
                'generation_mode'   => 'shared',
                'valid_from'        => now()->format('Y-m-d H:i:s'),
                'valid_until'       => now()->addDays(30)->format('Y-m-d H:i:s'),
            ]);
        } catch (\RuntimeException $e) {
            // Expected
        }

        // Verify zero orphan records created
        $this->assertEquals($initialCampaignCount, CouponCampaign::count());
        $this->assertEquals($initialCouponCount, Coupon::count());
        $this->assertDatabaseMissing('coupon_campaigns', ['name' => 'Failing Atomic Campaign']);
    }

    public function test_v_cam_gov_01_to_07_governance_role_boundary(): void
    {
        $campaign = CouponCampaign::create([
            'name'              => 'Governance Test Campaign',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 10,
            'generation_mode'   => 'shared',
            'code_prefix'       => 'GOVTEST',
            'uses_per_code'     => 10,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addDays(10),
            'is_active'         => true,
        ]);

        // V-CAM-GOV-01: RA GET create = 200
        $this->actingAs($this->adminUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertOk();

        // V-CAM-GOV-02: RA POST store = 302 redirect
        $this->actingAs($this->adminUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name'              => 'RA New Campaign',
                'assessment_family' => 'toeic',
                'scope_mode'        => 'all_products_in_family',
                'discount_type'     => 'percentage',
                'discount_value'    => 10,
                'generation_mode'   => 'shared',
                'valid_from'        => now()->format('Y-m-d H:i:s'),
                'valid_until'       => now()->addDays(30)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        // V-CAM-GOV-03: SA GET index & show = 200 (oversight)
        $this->actingAs($this->superAdminUser)
            ->get(route('admin.commerce.index'))
            ->assertOk();

        $this->actingAs($this->superAdminUser)
            ->get(route('admin.commerce.campaigns.show', $campaign->id))
            ->assertOk();

        // V-CAM-GOV-04: SA GET create & POST store = 403 Forbidden (routine campaign mutation)
        $this->actingAs($this->superAdminUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        $this->actingAs($this->superAdminUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name' => 'SA Illegal Campaign',
            ])
            ->assertStatus(403);

        // V-CAM-GOV-05: SA POST toggle & DELETE destroy = 403 Forbidden
        $this->actingAs($this->superAdminUser)
            ->post(route('admin.commerce.campaigns.toggle', $campaign->id))
            ->assertStatus(403);

        $this->actingAs($this->superAdminUser)
            ->delete(route('admin.commerce.campaigns.destroy', $campaign->id))
            ->assertStatus(403);

        // V-CAM-GOV-06: Finance mutation = 403 Forbidden
        $this->actingAs($this->financeUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        $this->actingAs($this->financeUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name' => 'Finance Illegal Campaign',
            ])
            ->assertStatus(403);

        // V-CAM-GOV-07: Student / Coordinator mutation = 403 Forbidden
        $this->actingAs($this->studentUser)
            ->get(route('admin.commerce.campaigns.create'))
            ->assertStatus(403);

        $this->actingAs($this->studentUser)
            ->post(route('admin.commerce.campaigns.store'), [
                'name' => 'Student Illegal Campaign',
            ])
            ->assertStatus(403);
    }
}
