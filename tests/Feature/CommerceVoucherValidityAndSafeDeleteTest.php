<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Commerce\Application\CouponEngine;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Models\Coupon;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommerceVoucherValidityAndSafeDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $superAdminUser;
    protected User $financeUser;
    protected User $candidateUser;
    protected User $teacherUser;
    protected User $repoManagerUser;
    protected CouponEngine $couponEngine;

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

        $this->couponEngine = new CouponEngine;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FOCUSED VALIDITY TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_voucher_val_01_new_active_voucher_cannot_be_saved_without_valid_until(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.vouchers.store'), [
            'code'        => 'NOEXPIRY2026',
            'discount'    => 20,
            'valid_from'  => now()->format('Y-m-d H:i:s'),
            // valid_until missing
        ]);

        $response->assertSessionHasErrors('valid_until');
        $this->assertDatabaseMissing('coupons', ['code' => 'NOEXPIRY2026']);
    }

    public function test_voucher_val_02_valid_until_must_be_after_valid_from(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.commerce.vouchers.store'), [
            'code'        => 'INVRANGE2026',
            'discount'    => 15,
            'valid_from'  => now()->addDays(5)->format('Y-m-d H:i:s'),
            'valid_until' => now()->addDays(2)->format('Y-m-d H:i:s'), // Earlier than valid_from
        ]);

        $response->assertSessionHasErrors('valid_until');
        $this->assertDatabaseMissing('coupons', ['code' => 'INVRANGE2026']);
    }

    public function test_voucher_val_03_voucher_before_valid_from_is_scheduled_and_cannot_redeem(): void
    {
        $coupon = Coupon::create([
            'code'        => 'FUTURE2026',
            'type'        => 'percentage',
            'value'       => 25,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->addDays(3),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        $this->assertEquals('SCHEDULED', $coupon->getEffectiveState());
        $this->assertTrue($coupon->isScheduled());
        $this->assertFalse($coupon->isEffectiveActive());

        $result = $this->couponEngine->validateCoupon('FUTURE2026');
        $this->assertFalse($result['valid']);
        $this->assertEquals('This voucher is not active yet.', $result['reason']);
    }

    public function test_voucher_val_04_voucher_within_validity_period_is_active_and_can_redeem(): void
    {
        $coupon = Coupon::create([
            'code'        => 'ACTIVE2026',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active'   => true,
        ]);

        $this->assertEquals('ACTIVE', $coupon->getEffectiveState());
        $this->assertTrue($coupon->isEffectiveActive());
        $this->assertFalse($coupon->isExpired());
        $this->assertFalse($coupon->isScheduled());

        $result = $this->couponEngine->validateCoupon('ACTIVE2026');
        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
    }

    public function test_voucher_val_05_voucher_after_valid_until_is_expired_and_cannot_redeem(): void
    {
        $coupon = Coupon::create([
            'code'        => 'EXPIRED2026',
            'type'        => 'percentage',
            'value'       => 30,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDays(10),
            'valid_until' => now()->subDay(),
            'is_active'   => true,
        ]);

        $this->assertEquals('EXPIRED', $coupon->getEffectiveState());
        $this->assertTrue($coupon->isExpired());
        $this->assertFalse($coupon->isEffectiveActive());

        $result = $this->couponEngine->validateCoupon('EXPIRED2026');
        $this->assertFalse($result['valid']);
        $this->assertEquals('This voucher has expired.', $result['reason']);
    }

    public function test_voucher_val_06_inactive_voucher_cannot_redeem_even_within_validity_range(): void
    {
        $coupon = Coupon::create([
            'code'        => 'INACTIVE2026',
            'type'        => 'percentage',
            'value'       => 15,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => false,
        ]);

        $this->assertEquals('INACTIVE', $coupon->getEffectiveState());
        $this->assertFalse($coupon->isEffectiveActive());

        $result = $this->couponEngine->validateCoupon('INACTIVE2026');
        $this->assertFalse($result['valid']);
        $this->assertEquals('This voucher is inactive.', $result['reason']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LEGACY VOUCHER TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_voucher_legacy_01_legacy_active_voucher_with_null_expiry_is_not_redeemable(): void
    {
        $coupon = Coupon::create([
            'code'        => 'LEGACYNULL',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 50,
            'used_count'  => 0,
            'valid_from'  => null,
            'valid_until' => null,
            'expires_at'  => null,
            'is_active'   => true, // even if active flag is true
        ]);

        $this->assertEquals('INACTIVE', $coupon->getEffectiveState());
        $this->assertFalse($coupon->isEffectiveActive());

        $result = $this->couponEngine->validateCoupon('LEGACYNULL');
        $this->assertFalse($result['valid']);
        $this->assertEquals('This voucher requires validity configuration before use.', $result['reason']);
    }

    public function test_voucher_legacy_02_legacy_voucher_record_is_preserved_when_deactivated(): void
    {
        $coupon = Coupon::create([
            'code'        => 'PROMO-UAT-LEGACY',
            'type'        => 'percentage',
            'value'       => 100,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_until' => null,
            'is_active'   => false,
        ]);

        $this->assertDatabaseHas('coupons', [
            'id'        => $coupon->id,
            'code'      => 'PROMO-UAT-LEGACY',
            'is_active' => false,
        ]);
        $this->assertNull($coupon->deleted_at);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_voucher_del_01_active_unused_voucher_can_be_deleted(): void
    {
        $coupon = Coupon::create([
            'code'        => 'DELACTIVE',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $this->assertTrue($coupon->isDeletable());

        $response = $this->actingAs($this->adminUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
        $response->assertRedirect(route('admin.commerce.index'));

        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'VOUCHER_DELETED',
        ]);
    }

    public function test_voucher_del_02_inactive_unused_voucher_can_be_deleted(): void
    {
        $coupon = Coupon::create([
            'code'        => 'DELINACTIVE',
            'type'        => 'percentage',
            'value'       => 15,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => false,
        ]);

        $this->assertTrue($coupon->isDeletable());

        $response = $this->actingAs($this->adminUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
        $response->assertRedirect(route('admin.commerce.index'));

        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
    }

    public function test_voucher_del_03_expired_unused_voucher_can_be_deleted(): void
    {
        $coupon = Coupon::create([
            'code'        => 'DELEXPIRED',
            'type'        => 'percentage',
            'value'       => 15,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDays(10),
            'valid_until' => now()->subDay(),
            'is_active'   => true,
        ]);

        $this->assertTrue($coupon->isDeletable());

        $response = $this->actingAs($this->adminUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
        $response->assertRedirect(route('admin.commerce.index'));

        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
    }

    public function test_voucher_del_04_voucher_with_redemption_history_cannot_be_deleted(): void
    {
        $coupon = Coupon::create([
            'code'        => 'USEDVOUCHER',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 10,
            'used_count'  => 3, // Already redeemed 3 times
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $this->assertFalse($coupon->isDeletable());

        $response = $this->actingAs($this->adminUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
        $response->assertSessionHasErrors('error');

        $this->assertDatabaseHas('coupons', [
            'id'         => $coupon->id,
            'deleted_at' => null,
        ]);
    }

    public function test_voucher_del_05_deleted_voucher_cannot_be_redeemed(): void
    {
        $coupon = Coupon::create([
            'code'        => 'SOFTDEL10',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 5,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $coupon->delete();

        $result = $this->couponEngine->validateCoupon('SOFTDEL10');
        $this->assertFalse($result['valid']);
        $this->assertEquals('This voucher is no longer available.', $result['reason']);
    }

    public function test_voucher_del_06_deleting_voucher_does_not_delete_order_or_payment_history(): void
    {
        $product = Product::create([
            'title'        => 'Test Package',
            'slug'         => 'test-pkg',
            'product_type' => 'assessment',
            'price'        => 500000,
            'is_active'    => true,
        ]);

        $order = Order::create([
            'user_id'      => $this->candidateUser->id,
            'order_number' => 'ORD-TEST-999',
            'status'       => OrderStatus::Completed,
            'subtotal'     => 500000,
            'discount'     => 50000,
            'tax'          => 49500,
            'grand_total'  => 499500,
        ]);

        $coupon = Coupon::create([
            'code'        => 'AUDITVOUCHER',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $coupon->delete();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHORIZATION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_voucher_auth_01_ra_can_create_update_and_delete_vouchers(): void
    {
        // 1. Create
        $resCreate = $this->actingAs($this->adminUser)->post(route('admin.commerce.vouchers.store'), [
            'code'        => 'RAVOUCHER',
            'discount'    => 20,
            'valid_from'  => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addMonth()->format('Y-m-d H:i:s'),
            'is_active'   => 1,
        ]);
        $resCreate->assertRedirect(route('admin.commerce.index'));

        $coupon = Coupon::where('code', 'RAVOUCHER')->first();
        $this->assertNotNull($coupon);

        // 2. Update
        $resUpdate = $this->actingAs($this->adminUser)->put(route('admin.commerce.vouchers.update', $coupon->id), [
            'discount'    => 25,
            'valid_from'  => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addMonths(2)->format('Y-m-d H:i:s'),
            'is_active'   => 1,
        ]);
        $resUpdate->assertRedirect(route('admin.commerce.index'));
        $this->assertEquals(25, $coupon->fresh()->value);

        // 3. Delete
        $resDel = $this->actingAs($this->adminUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
        $resDel->assertRedirect(route('admin.commerce.index'));
        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
    }

    public function test_voucher_auth_02_super_admin_has_oversight_authority_and_routine_mutations_restricted(): void
    {
        $coupon = Coupon::create([
            'code'        => 'SAVOUCHER',
            'type'        => 'percentage',
            'value'       => 50,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(10),
            'is_active'   => true,
        ]);

        // Oversight: Super Admin has view access
        $this->actingAs($this->superAdminUser)
            ->get(route('admin.commerce.index'))
            ->assertOk();

        // Routine Mutation: Super Admin is restricted (RA only) -> 403 Forbidden
        $resUpdate = $this->actingAs($this->superAdminUser)->put(route('admin.commerce.vouchers.update', $coupon->id), [
            'discount'    => 40,
            'valid_from'  => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addDays(15)->format('Y-m-d H:i:s'),
            'is_active'   => 1,
        ]);
        $resUpdate->assertStatus(403);

        $resDel = $this->actingAs($this->superAdminUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id));
        $resDel->assertStatus(403);
    }

    public function test_voucher_auth_03_finance_cannot_create_update_or_delete_voucher(): void
    {
        $coupon = Coupon::create([
            'code'        => 'FINVOUCHER',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $this->actingAs($this->financeUser)->post(route('admin.commerce.vouchers.store'), [
            'code'        => 'FINFAIL',
            'discount'    => 10,
            'valid_until' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ])->assertStatus(403);

        $this->actingAs($this->financeUser)->put(route('admin.commerce.vouchers.update', $coupon->id), [
            'discount'    => 15,
            'valid_until' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ])->assertStatus(403);

        $this->actingAs($this->financeUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id))
            ->assertStatus(403);
    }

    public function test_voucher_auth_04_rm_cannot_manage_vouchers(): void
    {
        $coupon = Coupon::create([
            'code'        => 'RMVOUCHER',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $this->actingAs($this->repoManagerUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id))
            ->assertStatus(403);
    }

    public function test_voucher_auth_05_teacher_cannot_manage_vouchers(): void
    {
        $coupon = Coupon::create([
            'code'        => 'TEACHVOUCHER',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $this->actingAs($this->teacherUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id))
            ->assertStatus(403);
    }

    public function test_voucher_auth_06_candidate_cannot_manage_vouchers(): void
    {
        $coupon = Coupon::create([
            'code'        => 'CANDVOUCHER',
            'type'        => 'percentage',
            'value'       => 10,
            'usage_limit' => 10,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addDays(5),
            'is_active'   => true,
        ]);

        $this->actingAs($this->candidateUser)->delete(route('admin.commerce.vouchers.destroy', $coupon->id))
            ->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // UI TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_voucher_ui_01_voucher_card_shows_validity(): void
    {
        Coupon::create([
            'code'        => 'UISHOW2026',
            'type'        => 'percentage',
            'value'       => 20,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_from'  => Carbon::create(2026, 9, 5, 10, 0, 0),
            'valid_until' => Carbon::create(2026, 9, 30, 23, 59, 59),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee('UISHOW2026');
        $response->assertSee('05 Sep 2026');
        $response->assertSee('30 Sep 2026');
    }

    public function test_voucher_ui_02_active_eligible_voucher_shows_delete_action(): void
    {
        Coupon::create([
            'code'        => 'UIDEL2026',
            'type'        => 'percentage',
            'value'       => 25,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_from'  => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee('confirmDeleteVoucher');
        $response->assertSee('Delete');
    }

    public function test_voucher_ui_03_deletion_triggers_iap_modal_script(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee('window.iapConfirm', false);
        $response->assertSee('Delete Promotional Voucher');
    }

    public function test_voucher_ui_04_expired_voucher_displays_expired_badge(): void
    {
        Coupon::create([
            'code'        => 'UIEXP2026',
            'type'        => 'percentage',
            'value'       => 30,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_from'  => now()->subMonths(2),
            'valid_until' => now()->subMonth(),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee('EXPIRED');
        $response->assertSee('Expired');
    }

    public function test_voucher_ui_05_scheduled_voucher_displays_scheduled_badge(): void
    {
        Coupon::create([
            'code'        => 'UISCHED2026',
            'type'        => 'percentage',
            'value'       => 15,
            'usage_limit' => 100,
            'used_count'  => 0,
            'valid_from'  => now()->addDays(5),
            'valid_until' => now()->addDays(20),
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.commerce.index'));
        $response->assertOk();
        $response->assertSee('SCHEDULED');
        $response->assertSee('Starts');
    }
}
