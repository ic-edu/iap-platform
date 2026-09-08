<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Commerce\Domain\Services\VoucherOperationalSummary;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CrossRoleCommercialVoucherDashboardAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminRA;
    protected User $superAdmin;
    protected User $finance;
    protected User $teacher;
    protected User $rm;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminRA = User::factory()->create(['name' => 'Operational Admin Demo', 'email' => 'admin.ra@iap.test']);
        $this->adminRA->assignRole('admin');

        $this->superAdmin = User::factory()->create(['name' => 'Super Admin Demo', 'email' => 'superadmin@iap.test']);
        $this->superAdmin->assignRole('super-admin');

        $this->finance = User::factory()->create(['name' => 'Finance Demo', 'email' => 'finance@iap.test']);
        $this->finance->assignRole('finance');

        $this->teacher = User::factory()->create(['name' => 'Teacher Demo', 'email' => 'teacher@iap.test']);
        $this->teacher->assignRole('teacher');

        $this->rm = User::factory()->create(['name' => 'Repo Manager Demo', 'email' => 'rm@iap.test']);
        $this->rm->assignRole('repository-manager');

        $this->student = User::factory()->create(['name' => 'Student Demo', 'email' => 'student@iap.test']);
        $this->student->assignRole('student');
    }

    public function test_cv_01_to_04_counts_are_identical_on_ra_sa_fi(): void
    {
        // Active Voucher: enabled, now within validity
        Coupon::create([
            'code'        => 'ACTIVE2026',
            'type'        => 'percentage',
            'value'       => 20.0,
            'usage_limit' => 100,
            'used_count'  => 5,
            'valid_from'  => now()->subDays(5),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        // Scheduled Voucher: enabled, starts in the future
        Coupon::create([
            'code'        => 'SCHEDULED2026',
            'type'        => 'percentage',
            'value'       => 15.0,
            'usage_limit' => 50,
            'used_count'  => 0,
            'valid_from'  => now()->addDays(5),
            'valid_until' => now()->addDays(20),
            'is_active'   => true,
        ]);

        // Expired Voucher: enabled, ended in past
        Coupon::create([
            'code'        => 'EXPIRED2026',
            'type'        => 'percentage',
            'value'       => 10.0,
            'usage_limit' => 50,
            'used_count'  => 12,
            'valid_from'  => now()->subDays(20),
            'valid_until' => now()->subDays(5),
            'is_active'   => true,
        ]);

        // Inactive Voucher: is_active = false
        Coupon::create([
            'code'        => 'INACTIVE2026',
            'type'        => 'percentage',
            'value'       => 30.0,
            'usage_limit' => 50,
            'used_count'  => 2,
            'valid_from'  => now()->subDays(2),
            'valid_until' => now()->addDays(10),
            'is_active'   => false,
        ]);

        $summary = VoucherOperationalSummary::get();
        $this->assertEquals(1, $summary['activeVouchersCount']);
        $this->assertEquals(1, $summary['scheduledVouchersCount']);
        $this->assertEquals(1, $summary['expiredVouchersCount']);
        $this->assertEquals(1, $summary['inactiveVouchersCount']);
        $this->assertEquals(19, $summary['totalVoucherRedemptions']); // 5 + 0 + 12 + 2

        // RA Dashboard
        $raResponse = $this->actingAs($this->adminRA)->get(route('admin.dashboard'));
        $raResponse->assertOk();
        $raResponse->assertViewHas('activeVouchersCount', 1);
        $raResponse->assertViewHas('scheduledVouchersCount', 1);
        $raResponse->assertViewHas('expiredVouchersCount', 1);
        $raResponse->assertViewHas('totalVoucherRedemptions', 19);

        // SA Dashboard
        $saResponse = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
        $saResponse->assertOk();
        $saResponse->assertViewHas('activeVouchersCount', 1);
        $saResponse->assertViewHas('scheduledVouchersCount', 1);
        $saResponse->assertViewHas('expiredVouchersCount', 1);
        $saResponse->assertViewHas('totalVoucherRedemptions', 19);

        // FI Dashboard
        $fiResponse = $this->actingAs($this->finance)->get(route('finance.dashboard'));
        $fiResponse->assertOk();
        $fiResponse->assertViewHas('activeVouchersCount', 1);
        $fiResponse->assertViewHas('scheduledVouchersCount', 1);
        $fiResponse->assertViewHas('expiredVouchersCount', 1);
        $fiResponse->assertViewHas('totalVoucherRedemptions', 19);
    }

    public function test_cv_05_deleted_vouchers_excluded_from_active_scheduled_expired_status_counts(): void
    {
        $deletedCoupon = Coupon::create([
            'code'        => 'DELETED2026',
            'type'        => 'percentage',
            'value'       => 50.0,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDays(1),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);
        $deletedCoupon->delete(); // Soft delete

        $summary = VoucherOperationalSummary::get();
        $this->assertEquals(0, $summary['activeVouchersCount']);
        $this->assertEquals(0, $summary['scheduledVouchersCount']);
        $this->assertEquals(0, $summary['expiredVouchersCount']);
    }

    public function test_cv_06_manually_inactive_vouchers_are_not_misclassified_as_scheduled(): void
    {
        Coupon::create([
            'code'        => 'FUTUREINACTIVE',
            'type'        => 'percentage',
            'value'       => 25.0,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->addDays(5),
            'valid_until' => now()->addDays(20),
            'is_active'   => false, // Manually disabled
        ]);

        $summary = VoucherOperationalSummary::get();
        $this->assertEquals(0, $summary['scheduledVouchersCount']);
        $this->assertEquals(1, $summary['inactiveVouchersCount']);
    }

    public function test_ra_cv_01_commercial_voucher_snapshot_renders_before_candidate_testing_metrics(): void
    {
        $response = $this->actingAs($this->adminRA)->get(route('admin.dashboard'));
        $response->assertOk();
        $html = $response->getContent();

        $contentHeadingPos = strpos($html, '<h1 class="text-2xl font-black');
        $this->assertNotFalse($contentHeadingPos);
        $mainContent = substr($html, $contentHeadingPos);

        $voucherSnapshotPos = strpos($mainContent, 'id="commercial-voucher-snapshot"');
        $candidateMetricsPos = strpos($mainContent, 'Platform Candidate &amp; Testing Metrics');
        if ($candidateMetricsPos === false) {
            $candidateMetricsPos = strpos($mainContent, 'Platform Candidate & Testing Metrics');
        }
        $institutionalOpsPos = strpos($mainContent, 'id="institutional-operations"');
        $actionQueuePos = strpos($mainContent, 'id="action-queue"');

        $this->assertNotFalse($voucherSnapshotPos, 'Commercial snapshot missing');
        $this->assertNotFalse($candidateMetricsPos, 'Candidate metrics missing');
        $this->assertNotFalse($institutionalOpsPos, 'Institutional operations missing');
        $this->assertNotFalse($actionQueuePos, 'Action queue missing');

        // Verify strictly ordered hierarchy
        $this->assertTrue($voucherSnapshotPos < $candidateMetricsPos, 'Voucher Snapshot must be ABOVE Candidate & Testing Metrics');
        $this->assertTrue($candidateMetricsPos < $institutionalOpsPos, 'Candidate & Testing Metrics must be ABOVE Institutional Operations');
        $this->assertTrue($institutionalOpsPos < $actionQueuePos, 'Institutional Operations must be ABOVE Action Queue');
    }

    public function test_ra_cv_02_current_voucher_preview_renders_on_ra_dashboard(): void
    {
        Coupon::create([
            'code'        => 'PREVIEW2026',
            'type'        => 'percentage',
            'value'       => 20.0,
            'usage_limit' => 10,
            'used_count'  => 1,
            'valid_from'  => now()->subDays(1),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('PREVIEW2026');
        $response->assertSee('20% OFF');
        $response->assertSee('ACTIVE');
        $response->assertSee('View Commercial Catalog');
    }

    public function test_ra_cv_03_ra_commercial_catalog_cta_remains_functional(): void
    {
        $response = $this->actingAs($this->adminRA)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee('Assessment Package &amp; Commercial Catalog', false);
    }

    public function test_sa_cv_01_and_02_sa_dashboard_renders_commercial_voucher_snapshot_near_top(): void
    {
        Coupon::create([
            'code'        => 'SACHECK2026',
            'type'        => 'percentage',
            'value'       => 25.0,
            'usage_limit' => 50,
            'used_count'  => 3,
            'valid_from'  => now()->subDays(1),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('super-admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Commercial &amp; Voucher Snapshot', false);
        $response->assertSee('View Commercial Oversight');
        $response->assertSee('Live promotional discounts');

        $html = $response->getContent();
        $voucherSnapshotPos = strpos($html, 'id="commercial-voucher-snapshot"');
        $userBreakdownPos = strpos($html, 'Platform User Breakdown KPIs');

        $this->assertNotFalse($voucherSnapshotPos);
        $this->assertNotFalse($userBreakdownPos);
        $this->assertTrue($voucherSnapshotPos < $userBreakdownPos, 'SA Voucher snapshot must appear near top before user breakdown');
    }

    public function test_sa_cv_03_sa_can_access_commercial_oversight_catalog(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee('Commercial Governance &amp; Approvals', false);
        $response->assertSee('Package Catalog Oversight (Read-Only)');
    }

    public function test_fi_cv_01_and_02_fi_dashboard_renders_commercial_voucher_snapshot_read_only(): void
    {
        Coupon::create([
            'code'        => 'FIVOUCHER2026',
            'type'        => 'percentage',
            'value'       => 15.0,
            'usage_limit' => 50,
            'used_count'  => 7,
            'valid_from'  => now()->subDays(2),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->finance)->get(route('finance.dashboard'));
        $response->assertOk();
        $response->assertSee('Commercial &amp; Voucher Snapshot', false);
        $response->assertSee('Read-Only Awareness');
        $response->assertSee('Currently applied at checkout');
        $response->assertSee('Upcoming promotional impact');
        $response->assertSee('Past discount campaigns');
        $response->assertSee('Total voucher discounts utilized');

        // Ensure no voucher-management links or mutation forms are exposed on FI dashboard
        $response->assertDontSee('View Commercial Catalog');
        $response->assertDontSee('Create New Voucher');
    }

    public function test_fi_cv_03_to_07_finance_has_strictly_no_voucher_or_pricing_management_authority(): void
    {
        $coupon = Coupon::create([
            'code'        => 'FIRESTRICTED',
            'type'        => 'percentage',
            'value'       => 10.0,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDays(1),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        // 1. Finance cannot access commercial index / management
        $response = $this->actingAs($this->finance)->get(route('admin.commerce.index'));
        $response->assertForbidden();

        // 2. Finance cannot store vouchers
        $response = $this->actingAs($this->finance)->post(route('admin.commerce.vouchers.store'), [
            'code'        => 'FIBIDDEN',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'valid_until' => now()->addDays(5)->toDateTimeString(),
        ]);
        $response->assertForbidden();

        // 3. Finance cannot update vouchers
        $response = $this->actingAs($this->finance)->put(route('admin.commerce.vouchers.update', $coupon->id), [
            'code'        => 'FIRESTRICTED2',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 20,
            'valid_until' => now()->addDays(10)->toDateTimeString(),
        ]);
        $response->assertForbidden();

        // 4. Finance cannot toggle vouchers
        $response = $this->actingAs($this->finance)->post(route('admin.commerce.vouchers.toggle', $coupon->id));
        $response->assertForbidden();

        // 5. Finance cannot delete vouchers
        $response = $this->actingAs($this->finance)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
        $response->assertForbidden();
    }

    public function test_authorization_regression_other_roles_cannot_manage_vouchers(): void
    {
        $coupon = Coupon::create([
            'code'        => 'SECURITYCHECK',
            'type'        => 'percentage',
            'value'       => 10.0,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDays(1),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        foreach ([$this->teacher, $this->rm, $this->student] as $unauthorizedUser) {
            $response = $this->actingAs($unauthorizedUser)->get(route('admin.commerce.index'));
            $response->assertForbidden();

            $response = $this->actingAs($unauthorizedUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
            $response->assertForbidden();
        }
    }
}
