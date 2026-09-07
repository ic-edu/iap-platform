<?php

namespace Tests\Feature;

use App\Models\User;
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
use App\Modules\Commerce\Events\PaymentConfirmed;
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
use App\Modules\Organization\Services\OrganizationEntitlementProvisioner;
use App\Modules\Organization\Services\OrganizationSeatAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationCommerceAndSeatEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organizationA;
    protected Organization $organizationB;
    protected User $coordinatorA;
    protected User $coordinatorB;
    protected User $candidateA1;
    protected User $candidateA2;
    protected User $candidateB1;
    protected OrganizationMembership $membershipCoordA;
    protected OrganizationMembership $membershipCandA1;
    protected OrganizationMembership $membershipCandA2;
    protected OrganizationMembership $membershipCandB1;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('finance', 'web');
        Role::findOrCreate('student', 'web');
        Role::findOrCreate('organization-coordinator', 'web');

        // 1. Create Organization A
        $this->organizationA = Organization::create([
            'name' => 'iC.edu UAT University',
            'legal_name' => 'PT iC.edu UAT University',
            'slug' => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
            'email' => 'contact@uat.edu',
        ]);

        // 2. Create Organization B (for tenant isolation tests)
        $this->organizationB = Organization::create([
            'name' => 'Other Campus Organization',
            'legal_name' => 'PT Other Campus Org',
            'slug' => 'other-campus-org',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
            'email' => 'contact@other.edu',
        ]);

        // 3. Create Users
        $this->coordinatorA = User::factory()->create([
            'name' => 'Indra Wahyudi',
            'email' => 'coordinator.a@uat.org',
            'status' => 'active',
        ]);

        $this->coordinatorB = User::factory()->create([
            'name' => 'Coordinator B',
            'email' => 'coordinator.b@other.org',
            'status' => 'active',
        ]);

        $this->candidateA1 = User::factory()->create([
            'name' => 'Candidate A1',
            'email' => 'ca01@uat.org',
            'status' => 'active',
        ]);
        $this->candidateA1->assignRole('student');

        $this->candidateA2 = User::factory()->create([
            'name' => 'Candidate A2',
            'email' => 'ca02@uat.org',
            'status' => 'active',
        ]);
        $this->candidateA2->assignRole('student');

        $this->candidateB1 = User::factory()->create([
            'name' => 'Candidate B1',
            'email' => 'cb01@other.org',
            'status' => 'active',
        ]);
        $this->candidateB1->assignRole('student');

        // 4. Create Memberships
        $this->membershipCoordA = OrganizationMembership::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'role' => MembershipRole::Coordinator,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        OrganizationMembership::create([
            'organization_id' => $this->organizationB->id,
            'user_id' => $this->coordinatorB->id,
            'role' => MembershipRole::Coordinator,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->membershipCandA1 = OrganizationMembership::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->candidateA1->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
            'member_identifier' => 'M-A1',
            'joined_at' => now(),
        ]);

        $this->membershipCandA2 = OrganizationMembership::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->candidateA2->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
            'member_identifier' => 'M-A2',
            'joined_at' => now(),
        ]);

        $this->membershipCandB1 = OrganizationMembership::create([
            'organization_id' => $this->organizationB->id,
            'user_id' => $this->candidateB1->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
            'member_identifier' => 'M-B1',
            'joined_at' => now(),
        ]);

        // 5. Create Test & Product
        $test = Test::create([
            'title' => 'Academic Aptitude Assessment',
            'slug' => 'academic-aptitude-assessment',
            'type' => 'academic',
            'status' => 'published',
            'is_active' => true,
            'duration_minutes' => 90,
            'passing_score' => 70,
            'created_by' => $this->coordinatorA->id,
        ]);

        $this->product = Product::create([
            'title' => 'Standard Assessment Package',
            'slug' => 'standard-assessment-package',
            'product_type' => 'assessment_test',
            'price' => 150000.0,
            'is_active' => true,
            'test_id' => $test->id,
            'assessment_family' => AssessmentFamily::General,
        ]);
    }

    protected function createTestEntitlement(Organization $org, Product $prod, int $totalSeats): OrganizationEntitlement
    {
        $order = Order::create([
            'organization_id' => $org->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-' . uniqid(),
            'status' => OrderStatus::Completed,
            'subtotal' => $prod->price * $totalSeats,
            'tax' => ($prod->price * $totalSeats) * 0.11,
            'grand_total' => ($prod->price * $totalSeats) * 1.11,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $prod->id,
            'product_name' => $prod->title,
            'price' => $prod->price,
            'quantity' => $totalSeats,
            'total' => $prod->price * $totalSeats,
        ]);

        return OrganizationEntitlement::create([
            'organization_id' => $org->id,
            'order_item_id' => $item->id,
            'product_id' => $prod->id,
            'total_seats' => $totalSeats,
            'status' => EntitlementStatus::Active,
        ]);
    }

    public function test_o2_order_01_coordinator_can_access_purchase_form_and_create_institutional_order(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.purchases.create', $this->organizationA->slug));

        $response->assertOk();
        $response->assertSee('Standard Assessment Package');

        // Create order with 10 seats
        $storeResponse = $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.store', $this->organizationA->slug), [
                'product_id' => $this->product->id,
                'quantity' => 10,
                'notes' => 'Batch 2026 Testing',
            ]);

        $storeResponse->assertRedirect();

        $order = Order::where('organization_id', $this->organizationA->id)->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->isInstitutional());
        $this->assertEquals($this->organizationA->id, $order->organization_id);
        $this->assertEquals($this->coordinatorA->id, $order->user_id);
        $this->assertEquals(OrderStatus::Pending, $order->status);
        $this->assertEquals(1500000.0, (float)$order->subtotal);
        $this->assertEquals(1665000.0, (float)$order->grand_total);
        $this->assertCount(1, $order->items);
        $this->assertEquals(10, $order->items->first()->quantity);
    }

    public function test_o2_order_02_unauthenticated_or_non_member_cannot_create_order(): void
    {
        // Unauthenticated
        $this->post(route('organization.purchases.store', $this->organizationA->slug), [
            'product_id' => $this->product->id,
            'quantity' => 5,
        ])->assertRedirect(route('login'));

        // Candidate Member (not coordinator/admin) cannot create institutional order
        $this->actingAs($this->candidateA1)
            ->post(route('organization.purchases.store', $this->organizationA->slug), [
                'product_id' => $this->product->id,
                'quantity' => 5,
            ])->assertForbidden();

        // Coordinator B (from Org B) cannot create order in Org A
        $this->actingAs($this->coordinatorB)
            ->post(route('organization.purchases.store', $this->organizationA->slug), [
                'product_id' => $this->product->id,
                'quantity' => 5,
            ])->assertForbidden();
    }

    public function test_o2_pay_01_pending_order_provisions_zero_entitlements_and_zero_seats(): void
    {
        $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.store', $this->organizationA->slug), [
                'product_id' => $this->product->id,
                'quantity' => 10,
            ]);

        // Zero entitlements should exist
        $this->assertEquals(0, OrganizationEntitlement::where('organization_id', $this->organizationA->id)->count());
        $this->assertEquals(0, OrganizationSeatAllocation::count());
    }

    public function test_o2_pay_02_coordinator_can_upload_payment_proof(): void
    {
        Storage::fake('public');

        $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.store', $this->organizationA->slug), [
                'product_id' => $this->product->id,
                'quantity' => 5,
            ]);

        $order = Order::where('organization_id', $this->organizationA->id)->firstOrFail();

        $file = UploadedFile::fake()->create('transfer_receipt.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->coordinatorA)
            ->post(route('organization.purchases.proof', [$this->organizationA->slug, $order]), [
                'payment_proof' => $file,
                'sender_bank' => 'Mandiri',
                'sender_name' => 'Indra Wahyudi',
                'payment_reference' => 'TRX-123456',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $payment = Payment::where('invoice_id', $order->invoice->id)->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->proof_path);
        $this->assertStringContainsString('Mandiri', $payment->proof_notes);

        // Uploading proof still leaves order pending verification / pending payment -> ZERO entitlements
        $this->assertEquals(0, OrganizationEntitlement::where('organization_id', $this->organizationA->id)->count());
    }

    public function test_o2_pay_03_payment_confirmation_idempotently_provisions_entitlements(): void
    {
        // 1. Create institutional order
        $order = Order::create([
            'organization_id' => $this->organizationA->id,
            'user_id' => $this->coordinatorA->id,
            'order_number' => 'ORD-TEST-001',
            'status' => OrderStatus::Pending,
            'subtotal' => 450000.0,
            'grand_total' => 450000.0,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->title,
            'price' => $this->product->price,
            'quantity' => 3,
            'total' => 450000.0,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'user_id' => $this->coordinatorA->id,
            'invoice_number' => 'INV-TEST-001',
            'amount' => 450000.0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $this->coordinatorA->id,
            'reference_number' => 'PAY-TEST-001',
            'payment_method' => 'bank_transfer',
            'payment_gateway' => 'manual',
            'amount' => 450000.0,
            'status' => PaymentStatus::Success,
        ]);

        // Trigger authoritative payment confirmation event
        event(new PaymentConfirmed($payment));

        // Verify entitlement provisioned
        $entitlement = OrganizationEntitlement::where('organization_id', $this->organizationA->id)->first();
        $this->assertNotNull($entitlement);
        $this->assertEquals($item->id, $entitlement->order_item_id);
        $this->assertEquals($this->product->id, $entitlement->product_id);
        $this->assertEquals(3, $entitlement->total_seats);
        $this->assertEquals(EntitlementStatus::Active, $entitlement->status);
        $this->assertEquals(3, $entitlement->availableSeatsCount());
        $this->assertEquals(0, $entitlement->allocatedSeatsCount());

        // Test Idempotency: Replaying event or running provisioner again does NOT create duplicate entitlements
        $provisioner = app(OrganizationEntitlementProvisioner::class);
        $result = $provisioner->provisionFromPayment($payment);

        $this->assertCount(1, $result);
        $this->assertEquals(1, OrganizationEntitlement::where('organization_id', $this->organizationA->id)->count());
        $this->assertEquals(3, $entitlement->fresh()->total_seats);
    }

    public function test_o2_seat_01_allocator_can_allocate_and_release_seats(): void
    {
        // 1. Create entitlement with 2 seats
        $entitlement = $this->createTestEntitlement($this->organizationA, $this->product, 2);

        $allocator = app(OrganizationSeatAllocator::class);

        // 2. Allocate seat to Candidate A1
        $allocation1 = $allocator->allocate(
            entitlement: $entitlement,
            membership: $this->membershipCandA1,
            actor: $this->coordinatorA
        );

        $this->assertEquals(SeatAllocationStatus::Active, $allocation1->status);
        $this->assertEquals($this->membershipCandA1->id, $allocation1->organization_membership_id);
        $this->assertEquals(1, $entitlement->allocatedSeatsCount());
        $this->assertEquals(1, $entitlement->availableSeatsCount());

        // 3. Duplicate allocation of same candidate in same entitlement pool is blocked
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already has an active seat allocation');
        $allocator->allocate(
            entitlement: $entitlement,
            membership: $this->membershipCandA1,
            actor: $this->coordinatorA
        );
    }

    public function test_o2_seat_02_capacity_enforcement_and_release(): void
    {
        $entitlement = $this->createTestEntitlement($this->organizationA, $this->product, 1);

        $allocator = app(OrganizationSeatAllocator::class);

        // Allocate the 1 and only seat
        $allocation = $allocator->allocate(
            entitlement: $entitlement,
            membership: $this->membershipCandA1,
            actor: $this->coordinatorA
        );

        $this->assertEquals(0, $entitlement->availableSeatsCount());
        $this->assertFalse($entitlement->hasAvailableSeats());

        // Attempting to allocate 2nd candidate when capacity = 1 throws InvalidArgumentException
        try {
            $allocator->allocate(
                entitlement: $entitlement,
                membership: $this->membershipCandA2,
                actor: $this->coordinatorA
            );
            $this->fail('Should have thrown InvalidArgumentException for exhausted seat capacity');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('No available seats remaining', $e->getMessage());
        }

        // Release the seat
        $allocator->release(
            allocation: $allocation,
            actor: $this->coordinatorA
        );

        $this->assertEquals(SeatAllocationStatus::Released, $allocation->fresh()->status);
        $this->assertEquals(1, $entitlement->availableSeatsCount());
        $this->assertTrue($entitlement->hasAvailableSeats());

        // Now Candidate A2 can be allocated into the released capacity
        $allocation2 = $allocator->allocate(
            entitlement: $entitlement,
            membership: $this->membershipCandA2,
            actor: $this->coordinatorA
        );

        $this->assertEquals(SeatAllocationStatus::Active, $allocation2->status);
        $this->assertEquals(0, $entitlement->availableSeatsCount());
    }

    public function test_o2_seat_03_coordinator_or_non_candidate_cannot_be_allocated_seats(): void
    {
        $entitlement = $this->createTestEntitlement($this->organizationA, $this->product, 5);

        $allocator = app(OrganizationSeatAllocator::class);

        // Attempt to allocate Coordinator A as seat recipient
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Seats may only be allocated to Candidate Members');

        $allocator->allocate(
            entitlement: $entitlement,
            membership: $this->membershipCoordA,
            actor: $this->coordinatorA
        );
    }

    public function test_o2_tenant_01_tenant_isolation_on_seat_allocation(): void
    {
        $entitlementA = $this->createTestEntitlement($this->organizationA, $this->product, 5);

        $allocator = app(OrganizationSeatAllocator::class);

        // Attempting to allocate Candidate from Org B into Org A entitlement throws InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Candidate does not belong to the entitlement organization');

        $allocator->allocate(
            entitlement: $entitlementA,
            membership: $this->membershipCandB1,
            actor: $this->coordinatorA
        );
    }

    public function test_o2_o3_01_seat_allocation_strictly_creates_zero_test_assignments(): void
    {
        $entitlement = $this->createTestEntitlement($this->organizationA, $this->product, 5);

        $allocator = app(OrganizationSeatAllocator::class);

        // Pre-condition
        $this->assertEquals(0, CandidateTestAssignment::count());

        // Allocate seat to Candidate A1
        $allocator->allocate(
            entitlement: $entitlement,
            membership: $this->membershipCandA1,
            actor: $this->coordinatorA
        );

        // HARD O2/O3 INVARIANT: CandidateTestAssignment must remain ZERO
        $this->assertEquals(0, CandidateTestAssignment::count());
        $this->assertEquals(0, CandidateTestAssignment::where('user_id', $this->candidateA1->id)->count());
    }

    public function test_o2_o3_02_candidate_available_tests_remain_unchanged_by_seat_allocation(): void
    {
        $entitlement = $this->createTestEntitlement($this->organizationA, $this->product, 5);

        $allocator = app(OrganizationSeatAllocator::class);

        // Allocate seat
        $allocator->allocate(
            entitlement: $entitlement,
            membership: $this->membershipCandA1,
            actor: $this->coordinatorA
        );

        // Log in as candidate A1 and view candidate portal
        $response = $this->actingAs($this->candidateA1)
            ->get(route('candidate.portal'));

        $response->assertOk();
        // Candidate dashboard shows 0 active test assignments because O3 is not yet connected
        $this->assertEquals(0, CandidateTestAssignment::where('user_id', $this->candidateA1->id)->count());
    }

    public function test_o2_copy_01_and_02_purchase_page_cta_and_empty_state_copy_normalized(): void
    {
        // View purchases index with empty state
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.purchases', $this->organizationA->slug));

        $response->assertOk();
        // O2-COPY-01: CTA no longer says "Purchase Assessment Seats", uses "Purchase Seats"
        $response->assertDontSee('Purchase Assessment Seats');
        $response->assertSee('Purchase Seats');

        // O2-COPY-02: Empty state does not state that seat purchase allocates tests
        $response->assertDontSee('Purchase assessment seats to allocate tests to candidate members.');
        $response->assertSee('Purchase package seats and allocate them to eligible candidate members. Assessment access is assigned separately.');

        // View entitlements index with empty state
        $entitlementsRes = $this->actingAs($this->coordinatorA)
            ->get(route('organization.entitlements', $this->organizationA->slug));

        $entitlementsRes->assertOk();
        $entitlementsRes->assertDontSee('Purchase Assessment Seats');
        $entitlementsRes->assertSee('Purchase Seats');
        $entitlementsRes->assertSee('Purchase package seats and allocate them to eligible candidate members. Assessment access is assigned separately.');
    }
}
