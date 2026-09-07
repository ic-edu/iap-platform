<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Coupon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RaDashboardVoucherOperationsSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $financeUser;
    protected User $candidateUser;
    protected User $teacherUser;
    protected User $repoManagerUser;

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

        $this->candidateUser = User::factory()->create();
        $this->candidateUser->assignRole('student');

        $this->teacherUser = User::factory()->create();
        $this->teacherUser->assignRole('teacher');

        $this->repoManagerUser = User::factory()->create();
        $this->repoManagerUser->assignRole('repository-manager');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FOCUSED KPI TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_dash_voucher_01_currently_valid_voucher_counts_as_active(): void
    {
        Coupon::create([
            'code'        => 'ACTIVE01',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 2,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('activeVouchersCount', 1);
        $response->assertSee('Active Vouchers');
        $response->assertSee('Currently redeemable');
    }

    public function test_dash_voucher_02_future_start_voucher_counts_as_scheduled(): void
    {
        Coupon::create([
            'code'        => 'SCHED01',
            'type'        => 'percentage',
            'value'       => 25,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->addDays(5),
            'valid_until' => now()->addDays(15),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('scheduledVouchersCount', 1);
        $response->assertSee('Scheduled Vouchers');
        $response->assertSee('Not active yet');
    }

    public function test_dash_voucher_03_past_end_voucher_counts_as_expired(): void
    {
        Coupon::create([
            'code'        => 'EXP01',
            'type'        => 'percentage',
            'value'       => 30,
            'usage_limit' => 10,
            'used_count'  => 5,
            'valid_from'  => now()->subDays(20),
            'valid_until' => now()->subDays(2),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('expiredVouchersCount', 1);
        $response->assertSee('Expired Vouchers');
        $response->assertSee('Validity ended');
    }

    public function test_dash_voucher_04_manually_inactive_voucher_does_not_count_as_scheduled(): void
    {
        // Future valid_from, but manually is_active = false
        Coupon::create([
            'code'        => 'INACT01',
            'type'        => 'percentage',
            'value'       => 15,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->addDays(5),
            'valid_until' => now()->addDays(15),
            'is_active'   => false,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('scheduledVouchersCount', 0);
        $response->assertViewHas('inactiveVouchersCount', 1);
        $response->assertSee('Inactive: 1');
    }

    public function test_dash_voucher_05_deleted_voucher_is_excluded_from_operational_status_counts(): void
    {
        $coupon = Coupon::create([
            'code'        => 'DELETED01',
            'type'        => 'percentage',
            'value'       => 50,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        $coupon->delete(); // Soft delete

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('activeVouchersCount', 0);
        $response->assertViewHas('scheduledVouchersCount', 0);
        $response->assertViewHas('expiredVouchersCount', 0);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // REDEMPTION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_dash_red_01_total_redemptions_uses_authoritative_voucher_usage_source(): void
    {
        Coupon::create([
            'code'        => 'RED01',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 100,
            'used_count'  => 12,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        Coupon::create([
            'code'        => 'RED02',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 100,
            'used_count'  => 8,
            'valid_from'  => now()->subDays(10),
            'valid_until' => now()->subDay(),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('totalVoucherRedemptions', 20);
        $response->assertSee('20');
        $response->assertSee('Total Redemptions');
    }

    public function test_dash_red_02_voucher_activity_row_displays_correct_redemption_count(): void
    {
        Coupon::create([
            'code'        => 'ACTIVITYREDEMP',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 50,
            'used_count'  => 7,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('ACTIVITYREDEMP');
        $response->assertSee('7 redemptions');
    }

    public function test_dash_red_03_historical_valid_redemption_remains_counted_after_voucher_deletion(): void
    {
        $coupon = Coupon::create([
            'code'        => 'HISTORICALREDEMP',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 100,
            'used_count'  => 15,
            'valid_from'  => now()->subDays(20),
            'valid_until' => now()->subDays(5),
            'is_active'   => true,
        ]);

        $coupon->delete(); // Soft-deleted

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        // Total redemptions includes trashed historical records
        $response->assertViewHas('totalVoucherRedemptions', 15);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ACTIVITY PANEL TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_dash_act_01_operational_vouchers_appear_in_limited_preview(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            Coupon::create([
                'code'        => "VOUCHER{$i}",
                'type'        => 'percentage',
                'value'       => 10 + $i,
                'usage_limit' => 100,
                'used_count'  => $i,
                'valid_from'  => now()->subDay(),
                'valid_until' => now()->addDays(10),
                'is_active'   => true,
            ]);
        }

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $recent = $response->viewData('recentVouchers');
        $this->assertCount(5, $recent);
    }

    public function test_dash_act_02_correct_effective_status_is_rendered(): void
    {
        Coupon::create([
            'code'        => 'TOEICGRP2026',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 100,
            'used_count'  => 8,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(20),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('TOEICGRP2026');
        $response->assertSee('20% OFF');
        $response->assertSee('ACTIVE');
    }

    public function test_dash_act_03_correct_validity_date_is_rendered(): void
    {
        Coupon::create([
            'code'        => 'VALIDITYTEST',
            'type'        => 'percentage',
            'value'       => 15,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_from'  => Carbon::create(2026, 9, 5, 0, 0, 0),
            'valid_until' => Carbon::create(2026, 9, 30, 23, 59, 59),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('VALIDITYTEST');
        $response->assertSee('Valid until 30 Sep 2026');
    }

    public function test_dash_act_04_view_commercial_catalog_cta_routes_to_canonical_ra_catalog(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee(route('admin.commerce.index'));
        $response->assertSee('View Commercial Catalog');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECURITY TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_dash_auth_01_ra_can_view_voucher_operations_summary(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Commercial &amp; Voucher Snapshot', false);
    }

    public function test_dash_auth_02_candidate_cannot_access_ra_dashboard_data(): void
    {
        $response = $this->actingAs($this->candidateUser)->get(route('admin.dashboard'));
        $response->assertStatus(403);
    }

    public function test_dash_auth_03_rm_only_user_is_redirected_to_rm_dashboard(): void
    {
        $response = $this->actingAs($this->repoManagerUser)->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.repository-manager.dashboard'));
    }

    public function test_dash_empty_01_zero_state_rendering_when_no_vouchers_exist(): void
    {
        Coupon::query()->forceDelete();

        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('No currently redeemable vouchers.');
        $response->assertViewHas('activeVouchersCount', 0);
        $response->assertViewHas('scheduledVouchersCount', 0);
        $response->assertViewHas('expiredVouchersCount', 0);
        $response->assertViewHas('totalVoucherRedemptions', 0);
    }
}
