<?php

namespace Tests\Feature;

use App\Models\QuestionBankArchiveRequest;
use App\Models\User;
use App\Models\UserCreationRequest;
use App\Models\UserDeletionRequest;
use App\Modules\Assessment\Models\Test as AssessmentTest;
use App\Modules\Commerce\Domain\Models\PriceChangeRequest;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\QuestionBank\Models\QuestionBank;
use App\Services\ApprovalEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExecutiveDashboardApprovalSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_approval_engine_aggregates_all_seven_pending_categories_accurately(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create 2 pending question banks
        QuestionBank::create(['title' => 'QB 1', 'slug' => 'qb-1', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'pending_approval']);
        QuestionBank::create(['title' => 'QB 2', 'slug' => 'qb-2', 'created_by' => $teacher->id, 'test_type' => 'toefl', 'status' => 'pending_approval']);

        // Create 1 pending test
        AssessmentTest::create(['title' => 'Test 1', 'slug' => 'test-1', 'created_by' => $teacher->id, 'test_type' => 'ielts', 'status' => 'pending']);

        // Create 1 pending staff creation request
        $staff = User::factory()->create(['status' => 'pending_approval']);
        UserCreationRequest::create(['user_id' => $staff->id, 'requested_by' => $admin->id, 'requested_role' => 'teacher', 'status' => 'pending']);

        // Create 1 pending user deletion request
        $targetUser = User::factory()->create(['status' => 'pending_delete_approval']);
        UserDeletionRequest::create(['user_id' => $targetUser->id, 'requested_by' => $admin->id, 'reason' => 'Duplicate account', 'status' => 'pending']);

        // Create 1 pending restoration request
        QuestionBank::create(['title' => 'Restore QB', 'slug' => 'restore-qb', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'pending_restore_approval']);

        // Create 1 pending price change request
        $product = Product::create([
            'title'             => 'Test Package',
            'slug'              => 'test-pkg',
            'product_type'      => 'assessment_package',
            'assessment_family' => 'toeic',
            'price'             => 750000,
            'is_active'         => true,
        ]);
        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $admin->id,
            'current_price_snapshot' => 750000,
            'proposed_price'         => 85000,
            'reason'                 => 'Promotional campaign',
            'status'                 => 'pending',
        ]);

        $pendingCounts = ApprovalEngine::getPendingCounts();
        $totalPending = ApprovalEngine::getTotalPendingCount();

        $this->assertEquals(2, $pendingCounts['question_banks']);
        $this->assertEquals(1, $pendingCounts['tests']);
        $this->assertEquals(1, $pendingCounts['user_creations']);
        $this->assertEquals(1, $pendingCounts['user_deletions']);
        $this->assertEquals(1, $pendingCounts['question_bank_restorations']);
        $this->assertEquals(1, $pendingCounts['price_changes']);
        $this->assertEquals(7, $totalPending);
    }

    public function test_01_approval_engine_contains_price_changes_key(): void
    {
        $pendingCounts = ApprovalEngine::getPendingCounts();
        $this->assertArrayHasKey('price_changes', $pendingCounts);
    }

    public function test_02_zero_pending_price_changes_produces_zero(): void
    {
        $pendingCounts = ApprovalEngine::getPendingCounts();
        $this->assertEquals(0, $pendingCounts['price_changes']);
    }

    public function test_03_and_04_one_pending_price_change_increments_total(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'title'             => 'Product Alpha',
            'slug'              => 'prod-alpha',
            'product_type'      => 'assessment_package',
            'assessment_family' => 'toeic',
            'price'             => 500000,
            'is_active'         => true,
        ]);

        $initialTotal = ApprovalEngine::getTotalPendingCount();

        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $admin->id,
            'current_price_snapshot' => 500000,
            'proposed_price'         => 450000,
            'reason'                 => 'Discount test',
            'status'                 => 'pending',
        ]);

        $pendingCounts = ApprovalEngine::getPendingCounts();
        $this->assertEquals(1, $pendingCounts['price_changes']);
        $this->assertEquals($initialTotal + 1, ApprovalEngine::getTotalPendingCount());
    }

    public function test_05_and_06_approved_and_rejected_price_changes_do_not_contribute(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::create([
            'title'             => 'Product Beta',
            'slug'              => 'prod-beta',
            'product_type'      => 'assessment_package',
            'assessment_family' => 'toeic',
            'price'             => 500000,
            'is_active'         => true,
        ]);

        // Approved request
        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $admin->id,
            'current_price_snapshot' => 500000,
            'proposed_price'         => 400000,
            'reason'                 => 'Approved change',
            'status'                 => 'approved',
        ]);

        // Rejected request
        PriceChangeRequest::create([
            'product_id'             => $product->id,
            'requested_by'           => $admin->id,
            'current_price_snapshot' => 500000,
            'proposed_price'         => 300000,
            'reason'                 => 'Rejected change',
            'status'                 => 'rejected',
        ]);

        $pendingCounts = ApprovalEngine::getPendingCounts();
        $this->assertEquals(0, $pendingCounts['price_changes']);
        $this->assertEquals(0, ApprovalEngine::getTotalPendingCount());
    }

    public function test_07_multiple_pending_price_changes_counted_correctly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $p1 = Product::create(['title' => 'P1', 'slug' => 'p-1', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 100, 'is_active' => true]);
        $p2 = Product::create(['title' => 'P2', 'slug' => 'p-2', 'product_type' => 'assessment_package', 'assessment_family' => 'toefl', 'price' => 200, 'is_active' => true]);
        $p3 = Product::create(['title' => 'P3', 'slug' => 'p-3', 'product_type' => 'assessment_package', 'assessment_family' => 'ielts', 'price' => 300, 'is_active' => true]);

        PriceChangeRequest::create(['product_id' => $p1->id, 'requested_by' => $admin->id, 'current_price_snapshot' => 100, 'proposed_price' => 90, 'reason' => 'R1', 'status' => 'pending']);
        PriceChangeRequest::create(['product_id' => $p2->id, 'requested_by' => $admin->id, 'current_price_snapshot' => 200, 'proposed_price' => 180, 'reason' => 'R2', 'status' => 'pending']);
        PriceChangeRequest::create(['product_id' => $p3->id, 'requested_by' => $admin->id, 'current_price_snapshot' => 300, 'proposed_price' => 270, 'reason' => 'R3', 'status' => 'pending']);

        $pendingCounts = ApprovalEngine::getPendingCounts();
        $this->assertEquals(3, $pendingCounts['price_changes']);
        $this->assertEquals(3, ApprovalEngine::getTotalPendingCount());
    }

    public function test_08_and_09_combined_categories_aggregate_correctly(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // 1 Question Bank
        QuestionBank::create(['title' => 'Combined QB', 'slug' => 'comb-qb', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'pending_approval']);

        // 1 Assessment Test
        AssessmentTest::create(['title' => 'Combined Test', 'slug' => 'comb-test', 'created_by' => $teacher->id, 'test_type' => 'toeic', 'status' => 'pending']);

        // 1 Price Change
        $prod = Product::create(['title' => 'Combined Prod', 'slug' => 'comb-prod', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 1000, 'is_active' => true]);
        PriceChangeRequest::create(['product_id' => $prod->id, 'requested_by' => $admin->id, 'current_price_snapshot' => 1000, 'proposed_price' => 800, 'reason' => 'Comb', 'status' => 'pending']);

        $pendingCounts = ApprovalEngine::getPendingCounts();
        $this->assertEquals(1, $pendingCounts['question_banks']);
        $this->assertEquals(1, $pendingCounts['tests']);
        $this->assertEquals(1, $pendingCounts['price_changes']);
        $this->assertEquals(3, ApprovalEngine::getTotalPendingCount());
    }

    public function test_10_and_11_dashboard_renders_pending_approvals_count_with_only_price_change(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $prod = Product::create(['title' => 'Solo Prod', 'slug' => 'solo-prod', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 1000, 'is_active' => true]);
        PriceChangeRequest::create(['product_id' => $prod->id, 'requested_by' => $admin->id, 'current_price_snapshot' => 1000, 'proposed_price' => 800, 'reason' => 'Solo', 'status' => 'pending']);

        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $response->assertOk();
        $response->assertViewHas('pendingApprovalsCount', 1);
        $response->assertSee(route('admin.approvals.index'));
    }

    public function test_12_approval_command_center_still_renders_pending_price_changes_count(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $prod = Product::create(['title' => 'Command Center Prod', 'slug' => 'cc-prod', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 1000, 'is_active' => true]);
        PriceChangeRequest::create(['product_id' => $prod->id, 'requested_by' => $admin->id, 'current_price_snapshot' => 1000, 'proposed_price' => 800, 'reason' => 'CC', 'status' => 'pending']);

        $response = $this->actingAs($superAdmin)->get(route('admin.approvals.index'));
        $response->assertOk();
        $response->assertViewHas('pendingPriceChangeCount', 1);
        $response->assertSee('Pending Price Changes');
        $response->assertSee('Command Center Prod');
    }

    public function test_13_semantic_contract_dashboard_equals_approval_engine_total(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $prod = Product::create(['title' => 'Sync Prod', 'slug' => 'sync-prod', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 500, 'is_active' => true]);
        PriceChangeRequest::create(['product_id' => $prod->id, 'requested_by' => $admin->id, 'current_price_snapshot' => 500, 'proposed_price' => 450, 'reason' => 'Sync', 'status' => 'pending']);

        $expectedTotal = ApprovalEngine::getTotalPendingCount();
        $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));
        $response->assertOk();
        $this->assertEquals($expectedTotal, $response->viewData('pendingApprovalsCount'));
    }

    public function test_14_through_18_price_proposal_lifecycle_and_role_authorization(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $prod = Product::create(['title' => 'Lifecycle Prod', 'slug' => 'lc-prod', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 1000, 'is_active' => true]);

        // TEST 14: RA can propose price change
        $this->actingAs($admin)->post(route('admin.commerce.products.propose-price', $prod->id), [
            'proposed_price' => 750,
            'reason'         => 'Market adjustment',
        ])->assertRedirect();

        $pcr = PriceChangeRequest::where('product_id', $prod->id)->firstOrFail();
        $this->assertEquals('pending', $pcr->status);

        // TEST 17: RA cannot approve price change (403)
        $this->actingAs($admin)->post(route('admin.approvals.price-changes.approve', $pcr->id))->assertStatus(403);

        // Teacher cannot approve price change (403)
        $this->actingAs($teacher)->post(route('admin.approvals.price-changes.approve', $pcr->id))->assertStatus(403);

        // TEST 15 & 18: SA can approve price change
        $this->actingAs($superAdmin)->post(route('admin.approvals.price-changes.approve', $pcr->id))->assertRedirect(route('admin.approvals.index'));
        $pcr->refresh();
        $prod->refresh();
        $this->assertEquals('approved', $pcr->status);
        $this->assertEquals(750, $prod->price);
    }

    public function test_16_price_proposal_rejection_behavior(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $prod = Product::create(['title' => 'Reject Prod', 'slug' => 'rej-prod', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 1000, 'is_active' => true]);

        $pcr = PriceChangeRequest::create([
            'product_id'             => $prod->id,
            'requested_by'           => $admin->id,
            'current_price_snapshot' => 1000,
            'proposed_price'         => 500,
            'reason'                 => 'Reject test',
            'status'                 => 'pending',
        ]);

        $this->actingAs($superAdmin)->post(route('admin.approvals.price-changes.reject', $pcr->id), [
            'rejection_reason' => 'Too high discount',
        ])->assertRedirect(route('admin.approvals.index'));

        $pcr->refresh();
        $prod->refresh();
        $this->assertEquals('rejected', $pcr->status);
        $this->assertEquals(1000, $prod->price); // Price unchanged
    }

    public function test_19_and_20_rendering_dashboard_causes_no_mutations_or_notifications(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $prod = Product::create(['title' => 'Safe Prod', 'slug' => 'safe-prod', 'product_type' => 'assessment_package', 'assessment_family' => 'toeic', 'price' => 1000, 'is_active' => true]);
        PriceChangeRequest::create([
            'product_id'             => $prod->id,
            'requested_by'           => $admin->id,
            'current_price_snapshot' => 1000,
            'proposed_price'         => 800,
            'reason'                 => 'Readonly check',
            'status'                 => 'pending',
        ]);

        $initialNotificationCount = DB::table('notifications')->count();

        $this->actingAs($superAdmin)->get(route('super-admin.dashboard'))->assertOk();

        $prod->refresh();
        $this->assertEquals(1000, $prod->price);
        $this->assertEquals($initialNotificationCount, DB::table('notifications')->count());
    }
}
