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
        $response->assertSee('View Commercial Catalog');
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

    // ═══════════════════════════════════════════════════════════════════════════
    // VUI-CONSOLIDATE: Merged Commercial Snapshot + Current Vouchers Section
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_vui_consolidate_01_ra_dashboard_renders_one_commercial_and_voucher_snapshot_parent_section(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('id="commercial-voucher-snapshot"', $content);
        $this->assertStringContainsString('Commercial &amp; Voucher Snapshot', $content);
    }

    public function test_vui_consolidate_02_all_four_voucher_kpi_values_remain_rendered(): void
    {
        Coupon::create([
            'code'        => 'ACTIVE99',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 4,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        Coupon::create([
            'code'        => 'SCHED99',
            'type'        => 'percentage',
            'value'       => 15,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->addDays(2),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        Coupon::create([
            'code'        => 'EXP99',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 6,
            'valid_from'  => now()->subDays(10),
            'valid_until' => now()->subDay(),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee('Active Vouchers');
        $response->assertSee('Scheduled Vouchers');
        $response->assertSee('Expired Vouchers');
        $response->assertSee('Total Redemptions');
    }

    public function test_vui_consolidate_03_current_vouchers_subsection_remains_rendered(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('Current Vouchers', $content);
    }

    public function test_vui_consolidate_04_exactly_one_view_commercial_catalog_cta_in_snapshot_context(): void
    {
        Coupon::create([
            'code'        => 'SINGLECATCTA',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 1,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $html = $response->getContent();

        // Extract the section #commercial-voucher-snapshot
        $start = strpos($html, 'id="commercial-voucher-snapshot"');
        $this->assertNotFalse($start);
        $end = strpos($html, '</section>', $start);
        $this->assertNotFalse($end);

        $sectionHtml = substr($html, $start, $end - $start);
        $ctaCount = substr_count($sectionHtml, 'View Commercial Catalog');

        $this->assertEquals(1, $ctaCount, 'Exactly ONE View Commercial Catalog CTA must exist in the consolidated Commercial Snapshot context');
    }

    public function test_vui_consolidate_05_canonical_cta_points_to_commerce_catalog_route(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $html = $response->getContent();
        $start = strpos($html, 'id="commercial-voucher-snapshot"');
        $end = strpos($html, '</section>', $start);
        $sectionHtml = substr($html, $start, $end - $start);

        $this->assertStringContainsString(route('admin.commerce.index'), $sectionHtml);
    }

    public function test_vui_consolidate_06_voucher_preview_renders_code_discount_status_validity_redemptions(): void
    {
        Coupon::create([
            'code'        => 'PREVIEWDETAIL',
            'type'        => 'percentage',
            'value'       => 30,
            'usage_limit' => 50,
            'used_count'  => 7,
            'valid_from'  => now()->subDays(2),
            'valid_until' => now()->addDays(12),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee('PREVIEWDETAIL');
        $response->assertSee('30% OFF');
        $response->assertSee('ACTIVE');
        $response->assertSee('7 redemptions');
    }

    public function test_vui_consolidate_07_kpi_cards_remain_non_clickable(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $html = $response->getContent();
        $start = strpos($html, 'id="commercial-voucher-snapshot"');
        $end = strpos($html, '</section>', $start);
        $sectionHtml = substr($html, $start, $end - $start);

        // Verify KPI cards are not wrapped in anchor tags
        $this->assertStringNotContainsString('<a href="' . route('admin.commerce.index') . '" class="group block p-5', $sectionHtml);
        $this->assertStringNotContainsString('<a href="' . route('admin.commerce.index') . '" class="p-5 bg-white', $sectionHtml);
    }

    public function test_vui_consolidate_08_zero_current_voucher_state_does_not_introduce_duplicate_cta(): void
    {
        Coupon::query()->forceDelete();

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee('No currently redeemable vouchers.');

        $html = $response->getContent();
        $start = strpos($html, 'id="commercial-voucher-snapshot"');
        $end = strpos($html, '</section>', $start);
        $sectionHtml = substr($html, $start, $end - $start);

        $ctaCount = substr_count($sectionHtml, 'View Commercial Catalog');
        $this->assertEquals(1, $ctaCount, 'Zero state must NOT introduce a second View Commercial Catalog CTA');
    }
}
