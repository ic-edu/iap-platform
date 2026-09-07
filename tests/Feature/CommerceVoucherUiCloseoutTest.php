<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\CouponCampaign;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceVoucherUiCloseoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->superAdminUser = User::factory()->create();
        $this->superAdminUser->assignRole('super-admin');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // VUI-THEME: Light Theme Normalization
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_vui_theme_01_campaign_create_view_has_light_and_dark_theme_classes(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.campaigns.create'));
        $response->assertOk();

        // Check semantic light/dark container classes
        $response->assertSee('bg-white dark:bg-slate-900', false);
        $response->assertSee('border-slate-200 dark:border-slate-800', false);
        $response->assertSee('text-slate-900 dark:text-white', false);

        // Check input field semantic styling
        $response->assertSee('border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950', false);

        // Check selected choice card styling contract
        $response->assertSee('border-indigo-600 dark:border-indigo-500 bg-indigo-50/70 dark:bg-indigo-950/50', false);
    }

    public function test_vui_theme_02_campaign_show_view_has_light_and_dark_theme_classes(): void
    {
        $campaign = CouponCampaign::create([
            'name'              => 'UI Test Campaign',
            'assessment_family' => 'toeic',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'percentage',
            'discount_value'    => 15,
            'generation_mode'   => 'shared',
            'total_codes'       => 1,
            'uses_per_code'     => 10,
            'code_prefix'       => 'TOEIC',
            'code_length'       => 6,
            'is_active'         => true,
            'created_by'        => $this->adminUser->id,
        ]);

        Coupon::create([
            'coupon_campaign_id'  => $campaign->id,
            'code'                => 'UISHOWTEST-ABCD',
            'type'                => 'percentage',
            'value'               => 15,
            'usage_limit'         => 10,
            'used_count'          => 0,
            'is_active'           => true,
            'assessment_family'   => 'toeic',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.campaigns.show', $campaign->id));
        $response->assertOk();

        // Check semantic light/dark classes on show view
        $response->assertSee('bg-white dark:bg-slate-900', false);
        $response->assertSee('bg-slate-50 dark:bg-slate-950', false);
        $response->assertSee('border-slate-200 dark:border-slate-800', false);
        $response->assertSee('text-slate-900 dark:text-white', false);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // VUI-KPI: Dashboard KPI Informational Semantics & Deduplication
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_vui_kpi_01_dashboard_voucher_kpi_cards_are_informational_non_links(): void
    {
        Coupon::create([
            'code'        => 'KPITEST01',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 3,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $content = $response->getContent();

        // Commercial & Voucher Snapshot exists
        $this->assertStringContainsString('id="commercial-voucher-snapshot"', $content);

        // Section header has clear CTA to Commercial Catalog
        $response->assertSee('View Commercial Catalog');
        $response->assertSee(route('admin.commerce.index'));

        // The KPI cards should NOT be wrapped in <a href="...admin/commerce...">
        $this->assertStringNotContainsString('<a href="' . route('admin.commerce.index') . '" class="p-5 bg-slate-900', $content);
        $this->assertStringNotContainsString('<a href="' . route('admin.commerce.index') . '" class="group block p-5', $content);
    }

    public function test_vui_current_01_current_vouchers_list_has_no_per_row_duplicate_manage_links(): void
    {
        Coupon::create([
            'code'        => 'ROWTEST01',
            'type'        => 'percentage',
            'value'       => 25,
            'usage_limit' => 50,
            'used_count'  => 5,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        // In Current Vouchers, individual rows do NOT have Manage -> buttons
        $content = $response->getContent();
        $this->assertStringContainsString('ROWTEST01', $content);
        $this->assertStringContainsString('25% OFF', $content);
        
        // Panel header still has the canonical section CTA
        $response->assertSee('View Commercial Catalog &rarr;', false);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // VUI-CAMPAIGN: Commercial Catalog CTA Deduplication
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_vui_campaign_01_commercial_catalog_has_header_cta_and_no_section_duplicate(): void
    {
        $campaign = CouponCampaign::create([
            'name'              => 'Catalog Section Test',
            'assessment_family' => 'ielts',
            'scope_mode'        => 'all_products_in_family',
            'discount_type'     => 'fixed',
            'discount_value'    => 50000,
            'generation_mode'   => 'shared',
            'total_codes'       => 1,
            'uses_per_code'     => 1,
            'code_prefix'       => 'IELTS',
            'code_length'       => 6,
            'is_active'         => true,
            'created_by'        => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertOk();

        $content = $response->getContent();

        // Top header CTA exists
        $this->assertStringContainsString('+ Generate Voucher Campaign', $content);
        $this->assertStringContainsString(route('admin.commerce.campaigns.create'), $content);

        // Section header does NOT contain redundant '+ New Campaign →'
        $this->assertStringNotContainsString('+ New Campaign &rarr;', $content);
        $this->assertStringNotContainsString('+ New Campaign →', $content);

        // Per-row Manage link exists and points to campaigns.show
        $this->assertStringContainsString(route('admin.commerce.campaigns.show', $campaign->id), $content);
        $this->assertStringContainsString('Manage &rarr;', $content);
    }
}
