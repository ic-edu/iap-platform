<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Assessment\Engines\AssignmentEngine;
use App\Modules\Assessment\Enums\AssessmentMode;
use App\Modules\Assessment\Models\Attempt;
use App\Modules\Assessment\Models\CandidateTestAssignment;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\InvoiceStatus;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Enums\PaymentStatus;
use App\Modules\Commerce\Domain\Models\Invoice;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Payment;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\EntitlementStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use App\Modules\Organization\Services\OrganizationSeatAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstitutionalAssessmentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organizationA;
    protected Organization $organizationB;
    protected User $coordinatorA;
    protected User $coordinatorB;
    protected User $candidateA1;
    protected User $adminUser;
    protected OrganizationMembership $membershipA1;
    protected OrganizationEntitlement $entitlementA;
    protected OrganizationSeatAllocation $allocationA1;
    protected Product $productA;
    protected Test $publishedToeicTest;
    protected Test $draftToeicTest;
    protected Test $publishedToeflTest;
    protected Test $simulatorToeicTest;
    protected Order $orderA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('organization-coordinator', 'web');
        Role::findOrCreate('student', 'web');

        // 1. Create Organization A
        $this->organizationA = Organization::create([
            'name' => 'iC.edu UAT University',
            'legal_name' => 'PT iC.edu UAT University',
            'slug' => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
            'email' => 'contact@uat.edu',
        ]);

        // 2. Create Organization B
        $this->organizationB = Organization::create([
            'name' => 'Other Campus Organization',
            'legal_name' => 'PT Other Campus Org',
            'slug' => 'other-campus-org',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
            'email' => 'contact@other.edu',
        ]);

        // 3. Create Users
        $this->adminUser = User::factory()->create([
            'name' => 'Operational Admin',
            'email' => 'admin@icedu.test',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->coordinatorA = User::factory()->create([
            'name' => 'Indra Wahyudi',
            'email' => 'coordinator.a@uat.org',
            'status' => 'active',
        ]);
        $this->coordinatorA->assignRole('organization-coordinator');

        $this->coordinatorB = User::factory()->create([
            'name' => 'Coordinator B',
            'email' => 'coordinator.b@other.org',
            'status' => 'active',
        ]);
        $this->coordinatorB->assignRole('organization-coordinator');

        $this->candidateA1 = User::factory()->create([
            'name' => 'Candidate CA01',
            'email' => 'ca01@uat.org',
            'status' => 'active',
        ]);
        $this->candidateA1->assignRole('student');

        // 4. Create Memberships
        OrganizationMembership::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'role' => MembershipRole::Coordinator,
            'status' => MembershipStatus::Active,
            'is_primary_coordinator' => true,
        ]);

        OrganizationMembership::create([
            'organization_id' => $this->organizationB->id,
            'user_id' => $this->coordinatorB->id,
            'role' => MembershipRole::Coordinator,
            'status' => MembershipStatus::Active,
            'is_primary_coordinator' => true,
        ]);

        $this->membershipA1 = OrganizationMembership::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->candidateA1->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
            'member_identifier' => 'NIM01',
        ]);

        // 5. Create Commercial Product & Order
        $this->productA = Product::create([
            'title' => 'TOEIC Mock Test Package',
            'slug' => 'toeic-mock-test-package',
            'product_type' => 'assessment',
            'assessment_family' => 'toeic',
            'price' => 85000,
            'is_active' => true,
        ]);

        $this->orderA = Order::create([
            'order_number' => 'ORD-20260907-3DWQ',
            'user_id' => $this->coordinatorA->id,
            'organization_id' => $this->organizationA->id,
            'status' => OrderStatus::Completed,
            'subtotal' => 255000,
            'discount_amount' => 25500,
            'tax_amount' => 25245,
            'grand_total' => 254745,
            'currency' => 'IDR',
        ]);

        $orderItemA = OrderItem::create([
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 3,
            'price' => 85000,
            'total' => 255000,
        ]);

        $invoiceA = Invoice::create([
            'invoice_number' => 'INV-20260907-DEZK',
            'order_id' => $this->orderA->id,
            'user_id' => $this->coordinatorA->id,
            'status' => InvoiceStatus::Paid,
            'amount' => 254745,
            'paid_at' => now(),
        ]);

        Payment::create([
            'reference_number' => 'PAY-20260907-0NGF',
            'invoice_id' => $invoiceA->id,
            'user_id' => $this->coordinatorA->id,
            'amount' => 254745,
            'status' => PaymentStatus::Success,
            'payment_gateway' => 'manual_transfer',
            'confirmed_at' => now(),
        ]);

        // 6. Create Entitlement Pool (Total 3, Allocated 1, Available 2)
        $this->entitlementA = OrganizationEntitlement::create([
            'organization_id' => $this->organizationA->id,
            'product_id' => $this->productA->id,
            'order_item_id' => $orderItemA->id,
            'total_seats' => 3,
            'allocated_seats' => 1,
            'available_seats' => 2,
            'status' => EntitlementStatus::Active,
        ]);

        // 7. Create Active Seat Allocation for CA01
        $this->allocationA1 = OrganizationSeatAllocation::create([
            'organization_entitlement_id' => $this->entitlementA->id,
            'organization_membership_id' => $this->membershipA1->id,
            'allocated_by' => $this->coordinatorA->id,
            'status' => SeatAllocationStatus::Active,
            'allocated_at' => now(),
        ]);

        // 8. Create Test Fixtures
        $this->publishedToeicTest = Test::create([
            'title' => 'TOEIC Official Practice Test 1',
            'slug' => 'toeic-official-practice-test-1',
            'description' => 'Standard TOEIC assessment test',
            'test_type' => 'toeic',
            'assessment_mode' => AssessmentMode::RealTest,
            'is_published' => true,
            'status' => 'published',
            'duration_minutes' => 120,
            'passing_score' => 500,
            'created_by' => $this->adminUser->id,
        ]);

        $this->draftToeicTest = Test::create([
            'title' => 'TOEIC Draft Test',
            'slug' => 'toeic-draft-test',
            'description' => 'Unpublished test in draft',
            'test_type' => 'toeic',
            'assessment_mode' => AssessmentMode::RealTest,
            'is_published' => false,
            'status' => 'draft',
            'duration_minutes' => 120,
            'passing_score' => 500,
            'created_by' => $this->adminUser->id,
        ]);

        $this->publishedToeflTest = Test::create([
            'title' => 'TOEFL Practice Test 1',
            'slug' => 'toefl-practice-test-1',
            'description' => 'Standard TOEFL assessment test',
            'test_type' => 'toefl',
            'assessment_mode' => AssessmentMode::RealTest,
            'is_published' => true,
            'status' => 'published',
            'duration_minutes' => 120,
            'passing_score' => 500,
            'created_by' => $this->adminUser->id,
        ]);

        $this->simulatorToeicTest = Test::create([
            'title' => 'TOEIC Simulator Test',
            'slug' => 'toeic-simulator-test',
            'description' => 'Self-service simulator',
            'test_type' => 'toeic',
            'assessment_mode' => AssessmentMode::Simulator,
            'is_published' => true,
            'status' => 'published',
            'duration_minutes' => 60,
            'passing_score' => 500,
            'created_by' => $this->adminUser->id,
        ]);
    }

    /**
     * O3-01: Operational Admin / RA successfully assigns published compatible test to active institutional seat allocation.
     */
    public function test_o3_01_ra_can_assign_published_test_to_active_institutional_seat_allocation(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('admin.institutional-seats.assign', $this->allocationA1->id),
            ['test_id' => $this->publishedToeicTest->id]
        );

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('candidate_test_assignments', [
            'user_id' => $this->candidateA1->id,
            'test_id' => $this->publishedToeicTest->id,
            'organization_seat_allocation_id' => $this->allocationA1->id,
            'status' => 'active',
            'assigned_by' => $this->adminUser->id,
        ]);
    }

    /**
     * O3-02: Engine rejects assignment without valid active seat allocation.
     */
    public function test_o3_02_engine_rejects_assignment_with_invalid_allocation(): void
    {
        $engine = app(AssignmentEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid candidate user for seat allocation.');

        // Pass a dummy allocation without ID / non-persisted
        $dummyAllocation = new OrganizationSeatAllocation();
        $engine->assignFromOrganizationSeat($dummyAllocation, $this->publishedToeicTest, $this->adminUser);
    }

    /**
     * O3-03: Cannot assign to released or inactive seat allocation.
     */
    public function test_o3_03_cannot_assign_to_released_or_inactive_seat_allocation(): void
    {
        $this->allocationA1->update(['status' => SeatAllocationStatus::Released]);

        $engine = app(AssignmentEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot assign assessment. Seat allocation is released.');

        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);
    }

    /**
     * O3-04: Cannot assign incompatible assessment family (e.g. TOEIC package assigned TOEFL test).
     */
    public function test_o3_04_cannot_assign_incompatible_assessment_family(): void
    {
        $engine = app(AssignmentEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Incompatible Assessment: Package family 'toeic' does not match test family 'toefl'.");

        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeflTest, $this->adminUser);
    }

    /**
     * O3-05: Cannot assign unpublished / draft test.
     */
    public function test_o3_05_cannot_assign_unpublished_test(): void
    {
        $engine = app(AssignmentEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot assign unpublished Mock Test 'TOEIC Draft Test'. Mock Test must be published first.");

        $engine->assignFromOrganizationSeat($this->allocationA1, $this->draftToeicTest, $this->adminUser);
    }

    /**
     * O3-06: Cannot assign simulator test mode (only real_test).
     */
    public function test_o3_06_cannot_assign_simulator_test(): void
    {
        $engine = app(AssignmentEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Test Simulator 'TOEIC Simulator Test' uses open candidate access and does not support manual candidate assignment.");

        $engine->assignFromOrganizationSeat($this->allocationA1, $this->simulatorToeicTest, $this->adminUser);
    }

    /**
     * O3-07: Organization Coordinator cannot assign assessment directly (only admin/super-admin has authority).
     */
    public function test_o3_07_coordinator_cannot_assign_assessment_directly(): void
    {
        $response = $this->actingAs($this->coordinatorA)->post(
            route('admin.institutional-seats.assign', $this->allocationA1->id),
            ['test_id' => $this->publishedToeicTest->id]
        );

        $response->assertStatus(403);
    }

    /**
     * O3-08: Non-candidate user cannot be assigned (student role guard).
     */
    public function test_o3_08_non_student_role_membership_fails_assignment(): void
    {
        // Remove student role
        $this->candidateA1->removeRole('student');

        $engine = app(AssignmentEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("does not have candidate/student role.");

        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);
    }

    /**
     * O3-09: Candidate Test Assignment records organization_seat_allocation_id provenance correctly.
     */
    public function test_o3_09_candidate_test_assignment_records_seat_allocation_provenance(): void
    {
        $engine = app(AssignmentEngine::class);
        $assignment = $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        $this->assertNotNull($assignment->organization_seat_allocation_id);
        $this->assertEquals($this->allocationA1->id, $assignment->organization_seat_allocation_id);
        $this->assertTrue($assignment->seatAllocation->is($this->allocationA1));
        $this->assertTrue($this->allocationA1->fresh()->activeTestAssignment->is($assignment));
    }

    /**
     * O3-10: Duplicate active assignment protection.
     */
    public function test_o3_10_duplicate_active_assignment_protection(): void
    {
        $engine = app(AssignmentEngine::class);
        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Active assessment assignment already exists for this institutional seat allocation.');

        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);
    }

    /**
     * O3-11: Entitlement seat pool capacity and counts remain unchanged upon RA assignment.
     */
    public function test_o3_11_entitlement_seat_counts_remain_immutable_upon_ra_assignment(): void
    {
        $entitlementBefore = $this->entitlementA->fresh();
        $this->assertEquals(3, $entitlementBefore->total_seats);
        $this->assertEquals(1, $entitlementBefore->allocated_seats);
        $this->assertEquals(2, $entitlementBefore->available_seats);

        $engine = app(AssignmentEngine::class);
        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        $entitlementAfter = $this->entitlementA->fresh();
        $this->assertEquals(3, $entitlementAfter->total_seats);
        $this->assertEquals(1, $entitlementAfter->allocated_seats);
        $this->assertEquals(2, $entitlementAfter->available_seats);
    }

    /**
     * O3-12: Candidate portal displays the assigned test under institutional access.
     */
    public function test_o3_12_candidate_portal_displays_assigned_institutional_assessment(): void
    {
        $engine = app(AssignmentEngine::class);
        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        $response = $this->actingAs($this->candidateA1)->get(route('candidate.available-tests'));

        $response->assertStatus(200);
        $response->assertSee($this->publishedToeicTest->title);
    }

    /**
     * O3-13: Candidate can start attempt for the assigned institutional test.
     */
    public function test_o3_13_candidate_can_start_attempt_for_assigned_test(): void
    {
        $engine = app(AssignmentEngine::class);
        $assignment = $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        $response = $this->actingAs($this->candidateA1)->post(
            route('candidate.tests.start', $this->publishedToeicTest->id)
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('attempts', [
            'assignment_id' => $assignment->id,
            'user_id' => $this->candidateA1->id,
            'test_id' => $this->publishedToeicTest->id,
        ]);
    }

    /**
     * O3-14: Commercial immutability preserved across Order, Invoice, Payment, Voucher.
     */
    public function test_o3_14_commercial_immutability_preserved(): void
    {
        $engine = app(AssignmentEngine::class);
        $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        $this->assertDatabaseHas('orders', [
            'id' => $this->orderA->id,
            'status' => OrderStatus::Completed->value,
            'grand_total' => 254745,
        ]);

        $this->assertDatabaseHas('invoices', [
            'order_id' => $this->orderA->id,
            'status' => InvoiceStatus::Paid->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'amount' => 254745,
            'status' => PaymentStatus::Success->value,
        ]);
    }

    /**
     * O3-15: Seat release before any attempts unassigns candidate assignment and frees seat.
     */
    public function test_o3_15_seat_release_before_attempt_unassigns_candidate_assignment(): void
    {
        $engine = app(AssignmentEngine::class);
        $assignment = $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        $allocator = app(OrganizationSeatAllocator::class);
        $releasedAllocation = $allocator->release($this->allocationA1, $this->coordinatorA);

        $this->assertEquals(SeatAllocationStatus::Released, $releasedAllocation->status);
        $this->assertNotNull($releasedAllocation->released_at);

        // Assignment marked unassigned
        $this->assertDatabaseHas('candidate_test_assignments', [
            'id' => $assignment->id,
            'status' => 'unassigned',
        ]);

        // Entitlement pool freed
        $entitlement = $this->entitlementA->fresh();
        $this->assertEquals(0, $entitlement->allocated_seats);
        $this->assertEquals(3, $entitlement->available_seats);
    }

    /**
     * O3-16: Seat release after attempt has started is strictly blocked.
     */
    public function test_o3_16_seat_release_after_attempt_started_is_strictly_blocked(): void
    {
        $engine = app(AssignmentEngine::class);
        $assignment = $engine->assignFromOrganizationSeat($this->allocationA1, $this->publishedToeicTest, $this->adminUser);

        // Create an attempt
        Attempt::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->candidateA1->id,
            'test_id' => $this->publishedToeicTest->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $allocator = app(OrganizationSeatAllocator::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot release seat allocation: candidate has already started or completed assessment attempts.');

        $allocator->release($this->allocationA1, $this->coordinatorA);

        // Verify allocation remains active
        $this->assertEquals(SeatAllocationStatus::Active, $this->allocationA1->fresh()->status);
        $this->assertEquals(1, $this->entitlementA->fresh()->allocated_seats);
    }

    /**
     * O3-17: Tenant isolation prevents cross-organization seat release.
     */
    public function test_o3_17_tenant_isolation_prevents_cross_org_release(): void
    {
        $allocator = app(OrganizationSeatAllocator::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Actor is not authorized to release seats for this organization.');

        $allocator->release($this->allocationA1, $this->coordinatorB);
    }

    /**
     * O3-18: RA Operational Dashboard displays institutional awaiting queue and clears after assignment.
     */
    public function test_o3_18_ra_operational_dashboard_displays_institutional_awaiting_queue(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Institutional Seats Awaiting Assessment Assignment');
        $response->assertSee('Candidate CA01');
        $response->assertSee('NIM01');
        $response->assertSee('iC.edu UAT University');
        $response->assertSee('TOEIC Mock Test Package');
        $response->assertSee('ORD-20260907-3DWQ');

        // Assign assessment
        $this->actingAs($this->adminUser)->post(
            route('admin.institutional-seats.assign', $this->allocationA1->id),
            ['test_id' => $this->publishedToeicTest->id]
        );

        // Check dashboard again
        $responseAfter = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $responseAfter->assertStatus(200);
        $responseAfter->assertSee('No active institutional seats are currently waiting for assessment assignment.');
        $responseAfter->assertSee('Candidate CA01'); // In recent active assignments
    }
}
