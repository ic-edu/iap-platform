<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Enums\AssessmentMode;
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
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use App\Notifications\EnterpriseSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalDashboardInstitutionalRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $rmUser;
    protected User $teacherUser;
    protected User $candidate1;
    protected User $candidate2;
    protected Organization $organization;
    protected OrganizationGroup $group9A;
    protected OrganizationGroup $group9B;
    protected OrganizationMembership $membership1;
    protected OrganizationMembership $membership2;
    protected OrganizationEntitlement $entitlement;
    protected OrganizationSeatAllocation $allocation1;
    protected OrganizationSeatAllocation $allocation2;
    protected Product $productToeic;
    protected Product $productToefl;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('repository-manager', 'web');
        Role::findOrCreate('teacher', 'web');
        Role::findOrCreate('student', 'web');

        // Users
        $this->adminUser = User::factory()->create([
            'name'   => 'Operational Admin',
            'email'  => 'admin@icedu.test',
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('admin');

        $this->rmUser = User::factory()->create([
            'name'   => 'Repository Manager',
            'email'  => 'rm@icedu.test',
            'status' => 'active',
        ]);
        $this->rmUser->assignRole('repository-manager');

        $this->teacherUser = User::factory()->create([
            'name'   => 'Teacher Author',
            'email'  => 'teacher@icedu.test',
            'status' => 'active',
        ]);
        $this->teacherUser->assignRole('teacher');

        $this->candidate1 = User::factory()->create([
            'name'   => 'Candidate CA01',
            'email'  => 'ca01@uat.org',
            'status' => 'active',
        ]);
        $this->candidate1->assignRole('student');

        $this->candidate2 = User::factory()->create([
            'name'   => 'Candidate CA02',
            'email'  => 'ca02@uat.org',
            'status' => 'active',
        ]);
        $this->candidate2->assignRole('student');

        // Organization
        $this->organization = Organization::create([
            'name'              => 'iC.edu UAT University',
            'legal_name'        => 'PT iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'email'             => 'contact@uat.edu',
        ]);

        $this->group9A = OrganizationGroup::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Class 9A',
            'slug'            => 'class-9a',
        ]);

        $this->group9B = OrganizationGroup::create([
            'organization_id' => $this->organization->id,
            'name'            => 'Class 9B',
            'slug'            => 'class-9b',
        ]);

        // Memberships
        $this->membership1 = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->candidate1->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
            'member_id'       => 'NIM-CA01',
            'joined_at'       => now(),
        ]);
        $this->group9A->addMembership($this->membership1);

        $this->membership2 = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->candidate2->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
            'member_id'       => 'NIM-CA02',
            'joined_at'       => now(),
        ]);
        $this->group9B->addMembership($this->membership2);

        // Products
        $this->productToeic = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package',
            'sku'               => 'PKG-TOEIC-01',
            'price'             => 150000,
            'currency'          => 'IDR',
            'is_active'         => true,
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
        ]);

        $this->productToefl = Product::create([
            'title'             => 'TOEFL Mock Test Package',
            'slug'              => 'toefl-mock-test-package',
            'sku'               => 'PKG-TOEFL-01',
            'price'             => 150000,
            'currency'          => 'IDR',
            'is_active'         => true,
            'product_type'      => 'assessment',
            'assessment_family' => 'toefl',
        ]);

        // Order & OrderItem
        $order = Order::create([
            'order_number'    => 'ORD-20260901-UAT1',
            'user_id'         => $this->adminUser->id,
            'organization_id' => $this->organization->id,
            'status'          => OrderStatus::Completed,
            'subtotal'        => 1500000,
            'grand_total'     => 1500000,
            'currency'        => 'IDR',
        ]);

        $orderItem = OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $this->productToeic->id,
            'quantity'   => 10,
            'price'      => 150000,
            'total'      => 1500000,
        ]);

        // Entitlement & Allocations
        $this->entitlement = OrganizationEntitlement::create([
            'organization_id' => $this->organization->id,
            'product_id'      => $this->productToeic->id,
            'order_item_id'   => $orderItem->id,
            'total_seats'     => 10,
            'allocated_seats' => 2,
            'status'          => EntitlementStatus::Active,
            'valid_from'      => now()->subDays(1),
            'valid_until'     => now()->addYear(),
        ]);

        $this->allocation1 = OrganizationSeatAllocation::create([
            'organization_entitlement_id' => $this->entitlement->id,
            'organization_membership_id'  => $this->membership1->id,
            'status'                      => SeatAllocationStatus::Active,
            'allocated_at'                => now(),
        ]);

        $this->allocation2 = OrganizationSeatAllocation::create([
            'organization_entitlement_id' => $this->entitlement->id,
            'organization_membership_id'  => $this->membership2->id,
            'status'                      => SeatAllocationStatus::Active,
            'allocated_at'                => now(),
        ]);
    }

    /**
     * TEST: When no active request exists and no published tests, dashboard displays "Request Mock Test".
     */
    public function test_dashboard_displays_request_mock_test_when_no_active_request_exists(): void
    {
        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('No published TOEIC tests');
        $res->assertSee('Request Mock Test');
        $res->assertSee('candidate_id=' . $this->candidate1->id, false);
        $res->assertSee('test_type=toeic', false);
    }

    /**
     * TEST: When request is pending, dashboard replaces button with "Awaiting Repository Manager" + "View Request".
     */
    public function test_dashboard_displays_awaiting_rm_when_request_is_pending(): void
    {
        AssessmentRequest::create([
            'title'           => 'TOEIC Mock Test',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'pending',
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Awaiting Repository Manager');
        $res->assertSee('View Request');
        $res->assertDontSee('candidate_id=' . $this->candidate1->id, false);
    }

    /**
     * TEST: When linked test is in authoring (draft), dashboard displays "In Authoring" + "View Assessment".
     */
    public function test_dashboard_displays_in_authoring_when_test_is_in_draft(): void
    {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test Draft 9A',
            'slug'             => 'toeic-mock-test-draft-9a',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->rmUser->id,
            'assigned_to'      => $this->teacherUser->id,
        ]);

        $req = AssessmentRequest::create([
            'title'           => 'TOEIC Mock Test',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'draft_created',
            'test_id'         => $test->id,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('In Authoring');
        $res->assertSee('View Assessment');
        $res->assertSee(route('admin.tests.show', $test->id), false);
    }

    /**
     * TEST: When linked test is submitted for review, dashboard displays "Awaiting RM Review".
     */
    public function test_dashboard_displays_awaiting_rm_review_when_test_is_pending_approval(): void
    {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test Submitted 9A',
            'slug'             => 'toeic-mock-test-submitted-9a',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'pending_approval',
            'is_published'     => false,
            'created_by'       => $this->rmUser->id,
            'assigned_to'      => $this->teacherUser->id,
        ]);

        AssessmentRequest::create([
            'title'           => 'TOEIC Mock Test',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'draft_created',
            'test_id'         => $test->id,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Awaiting RM Review');
        $res->assertSee('View Assessment');
    }

    /**
     * TEST: When linked test needs revision, dashboard displays "Revision in Progress".
     */
    public function test_dashboard_displays_revision_in_progress_when_test_needs_revision(): void
    {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test Revision 9A',
            'slug'             => 'toeic-mock-test-revision-9a',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'needs_revision',
            'is_published'     => false,
            'created_by'       => $this->rmUser->id,
            'assigned_to'      => $this->teacherUser->id,
        ]);

        AssessmentRequest::create([
            'title'           => 'TOEIC Mock Test',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'draft_created',
            'test_id'         => $test->id,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Revision in Progress');
        $res->assertSee('View Assessment');
    }

    /**
     * TEST: When linked test is approved but unpublished, dashboard displays "Awaiting Publication".
     */
    public function test_dashboard_displays_awaiting_publication_when_test_is_approved_unpublished(): void
    {
        $test = Test::create([
            'title'            => 'TOEIC Mock Test Approved 9A',
            'slug'             => 'toeic-mock-test-approved-9a',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'approved',
            'is_published'     => false,
            'created_by'       => $this->rmUser->id,
            'assigned_to'      => $this->teacherUser->id,
        ]);

        AssessmentRequest::create([
            'title'           => 'TOEIC Mock Test',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'draft_created',
            'test_id'         => $test->id,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Awaiting Publication');
        $res->assertSee('View Assessment');
    }

    /**
     * TEST: When an eligible test is published, dashboard preserves the "Assign Assessment" dropdown and button.
     */
    public function test_dashboard_preserves_assign_assessment_when_test_is_published(): void
    {
        $publishedTest = Test::create([
            'title'            => 'TOEIC Master Canonical 2026',
            'slug'             => 'toeic-master-canonical-2026',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->rmUser->id,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Assign Assessment');
        $res->assertSee('TOEIC Master Canonical 2026');
        $res->assertDontSee('No published TOEIC tests');
    }

    /**
     * TEST: Human UAT Regression - Legacy abstract assessment package (assessment_family=null, test_id=null)
     * resolves effective family and displays canonical published assessment assignment controls on RA Dashboard.
     */
    public function test_legacy_abstract_toeic_package_with_null_family_resolves_and_shows_published_assignment_controls(): void
    {
        // 1. Create legacy abstract product matching UAT persistent state
        $legacyProduct = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package-legacy',
            'product_type'      => 'assessment',
            'assessment_family' => null,
            'test_id'           => null,
            'price'             => 85000,
            'currency'          => 'IDR',
            'is_active'         => true,
        ]);

        // 2. Link entitlement to this legacy product
        $this->entitlement->update([
            'product_id' => $legacyProduct->id,
        ]);

        // 3. Create published real test matching RM published test
        $publishedTest = Test::create([
            'title'            => 'TOEIC Mock Test Group - UAT Class 9A',
            'slug'             => 'toeic-mock-test-group-uat-class-9a',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 120,
            'pass_score'       => 650,
            'status'           => 'published',
            'is_published'     => true,
            'created_by'       => $this->rmUser->id,
        ]);

        // 4. Request dashboard
        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Assign Assessment');
        $res->assertSee('TOEIC Mock Test Group - UAT Class 9A');
        $res->assertDontSee('No published TOEIC tests');
        $res->assertDontSee('Request Mock Test');
    }

    /**
     * TEST: Fail-Closed UI - Unresolved institutional package family displays configuration-incomplete warning
     * and strictly does NOT expose "Request Mock Test", test_type=general, or assignment controls.
     */
    public function test_unresolved_legacy_package_family_fails_closed_in_dashboard_ui(): void
    {
        // 1. Create abstract product with unrecognized title/slug and null family
        $unresolvedProduct = Product::create([
            'title'             => 'Custom Unconfigured Package Bundle',
            'slug'              => 'custom-unconfigured-package-bundle',
            'product_type'      => 'assessment',
            'assessment_family' => null,
            'test_id'           => null,
            'price'             => 85000,
            'currency'          => 'IDR',
            'is_active'         => true,
        ]);

        // 2. Link entitlement to this unresolved product
        $this->entitlement->update([
            'product_id' => $unresolvedProduct->id,
        ]);

        // 3. Request dashboard
        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));

        $res->assertStatus(200);
        $res->assertSee('Assessment package configuration incomplete');
        $res->assertSee('Assessment family could not be resolved. Please configure the package before requesting or assigning an assessment.');
        $res->assertDontSee('Request Mock Test');
        $res->assertDontSee('test_type=general', false);
        $res->assertDontSee('No published GENERAL tests');
        $res->assertDontSee('Assign Assessment');
    }

    /**
     * TEST: Server-Side Duplicate Prevention - Repeated submission returns existing request without duplicate record or extra notification.
     */
    public function test_server_side_duplicate_prevention_on_repeated_submission(): void
    {
        Notification::fake();

        // 1. Initial submission
        $res1 = $this->actingAs($this->adminUser)->post(route('admin.assessment-requests.store'), [
            'title'           => 'TOEIC Placement Class 9A',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
        ]);

        $res1->assertRedirect(route('admin.assessment-requests.index'));
        $this->assertEquals(1, AssessmentRequest::count());

        Notification::assertSentTo($this->rmUser, EnterpriseSystemNotification::class);

        // Reset notification fake
        Notification::fake();

        // 2. Second submission with exact same requirement
        $res2 = $this->actingAs($this->adminUser)->post(route('admin.assessment-requests.store'), [
            'title'           => 'TOEIC Placement Class 9A',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
        ]);

        $res2->assertRedirect(route('admin.assessment-requests.index'));
        $res2->assertSessionHas('info');

        // Verify count remains 1 and NO second notification was dispatched
        $this->assertEquals(1, AssessmentRequest::count());
        Notification::assertNothingSent();
    }

    /**
     * TEST: Server-Side Duplicate Prevention - Changing only the title still prevents duplicate active request.
     */
    public function test_server_side_duplicate_prevention_when_title_is_changed(): void
    {
        Notification::fake();

        // Initial submission
        AssessmentRequest::create([
            'title'           => 'TOEIC Original Brief Title',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'pending',
        ]);

        $this->assertEquals(1, AssessmentRequest::count());

        // Submission with modified title but same candidate, type, context
        $res = $this->actingAs($this->adminUser)->post(route('admin.assessment-requests.store'), [
            'title'           => 'Completely Different Title for Same Candidate Need',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
        ]);

        $res->assertRedirect(route('admin.assessment-requests.index'));
        $res->assertSessionHas('info');

        $this->assertEquals(1, AssessmentRequest::count());
        Notification::assertNothingSent();
    }

    /**
     * TEST: Distinct candidates, test types, and contexts create separate active requests.
     */
    public function test_distinct_candidate_and_type_and_context_create_separate_requests(): void
    {
        // 1. Candidate 1 TOEIC
        $this->actingAs($this->adminUser)->post(route('admin.assessment-requests.store'), [
            'title'           => 'TOEIC Candidate 1',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
        ]);

        // 2. Candidate 2 TOEIC (different candidate)
        $this->actingAs($this->adminUser)->post(route('admin.assessment-requests.store'), [
            'title'           => 'TOEIC Candidate 2',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate2->id,
            'program_context' => 'iC.edu UAT University — Class 9B',
        ]);

        // 3. Candidate 1 TOEFL (different test type)
        $this->actingAs($this->adminUser)->post(route('admin.assessment-requests.store'), [
            'title'           => 'TOEFL Candidate 1',
            'test_type'       => 'toefl',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
        ]);

        $this->assertEquals(3, AssessmentRequest::count());
    }

    /**
     * TEST: Completed or archived requests do not block new requests.
     */
    public function test_completed_request_does_not_block_new_request(): void
    {
        AssessmentRequest::create([
            'title'           => 'Old Completed TOEIC Request',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'completed',
        ]);

        $res = $this->actingAs($this->adminUser)->post(route('admin.assessment-requests.store'), [
            'title'           => 'New TOEIC Request After Completion',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
        ]);

        $res->assertRedirect(route('admin.assessment-requests.index'));
        $this->assertEquals(2, AssessmentRequest::count());
    }

    /**
     * TEST: Prefilled request form shows existing active request notice.
     */
    public function test_assessment_requests_index_displays_active_requirement_notice_when_prefilled(): void
    {
        $req = AssessmentRequest::create([
            'title'           => 'Active TOEIC In-Flight Request',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'pending',
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.assessment-requests.index', [
            'candidate_id'    => $this->candidate1->id,
            'test_type'       => 'toeic',
            'program_context' => 'iC.edu UAT University — Class 9A',
        ]));

        $res->assertStatus(200);
        $res->assertSee('Active Assessment Request Already Exists:');
        $res->assertSee('Active TOEIC In-Flight Request');
        $res->assertSee('Awaiting Repository Manager');
    }

    /**
     * TEST: Exact context match takes strict precedence over empty-context request.
     */
    public function test_exact_context_takes_precedence_over_empty_context(): void
    {
        $emptyContextReq = AssessmentRequest::create([
            'title'           => 'Legacy Empty Context Request',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => null,
            'requested_by'    => $this->adminUser->id,
            'status'          => 'pending',
        ]);

        $exactContextReq = AssessmentRequest::create([
            'title'           => 'Exact Class 9A Request',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'pending',
        ]);

        $resolved = AssessmentRequest::resolveActiveRequirement(
            $this->candidate1->id,
            'toeic',
            'iC.edu UAT University — Class 9A'
        );

        $this->assertNotNull($resolved);
        $this->assertEquals($exactContextReq->id, $resolved->id);
        $this->assertEquals('Exact Class 9A Request', $resolved->title);

        // Also test findMatchingInCollection directly
        $matched = AssessmentRequest::findMatchingInCollection(
            collect([$emptyContextReq, $exactContextReq]),
            $this->candidate1->id,
            'toeic',
            'iC.edu UAT University — Class 9A'
        );

        $this->assertNotNull($matched);
        $this->assertEquals($exactContextReq->id, $matched->id);
    }

    /**
     * TEST: Distinct explicit contexts are not conflated.
     */
    public function test_distinct_explicit_contexts_are_not_conflated(): void
    {
        AssessmentRequest::create([
            'title'           => 'Class 9A Request',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'pending',
        ]);

        // Querying for Class 9B should return null
        $resolved = AssessmentRequest::resolveActiveRequirement(
            $this->candidate1->id,
            'toeic',
            'iC.edu UAT University — Class 9B'
        );

        $this->assertNull($resolved);
    }

    /**
     * TEST: Empty context legacy request matches when no exact context request exists.
     */
    public function test_empty_context_matches_candidate_when_no_exact_match_exists(): void
    {
        $emptyContextReq = AssessmentRequest::create([
            'title'           => 'Legacy Empty Context Request',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => null,
            'requested_by'    => $this->adminUser->id,
            'status'          => 'pending',
        ]);

        $resolved = AssessmentRequest::resolveActiveRequirement(
            $this->candidate1->id,
            'toeic',
            'iC.edu UAT University — Class 9A'
        );

        $this->assertNotNull($resolved);
        $this->assertEquals($emptyContextReq->id, $resolved->id);
    }

    /**
     * TEST: Archived linked assessment is recognized as archived and not active.
     */
    public function test_archived_linked_assessment_is_recognized_as_archived_and_not_active(): void
    {
        $archivedTest = Test::create([
            'title'            => 'Archived TOEIC Test',
            'slug'             => 'archived-toeic-test',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 60,
            'pass_score'       => 70,
            'status'           => 'archived',
            'is_published'     => false,
            'created_by'       => $this->rmUser->id,
            'assigned_to'      => $this->teacherUser->id,
        ]);

        $req = AssessmentRequest::create([
            'title'           => 'TOEIC Request with Archived Test',
            'test_type'       => 'toeic',
            'candidate_id'    => $this->candidate1->id,
            'program_context' => 'iC.edu UAT University — Class 9A',
            'requested_by'    => $this->adminUser->id,
            'status'          => 'draft_created',
            'test_id'         => $archivedTest->id,
        ]);

        $this->assertFalse($req->isActive());
        $this->assertEquals('archived', $req->getWorkflowStage());
        $this->assertEquals('Archived', $req->getWorkflowStageLabel());

        // Because it's not active, resolveActiveRequirement returns null
        $activeReq = AssessmentRequest::resolveActiveRequirement(
            $this->candidate1->id,
            'toeic',
            'iC.edu UAT University — Class 9A'
        );
        $this->assertNull($activeReq);

        // Dashboard allows new mock test request
        $res = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
        $res->assertStatus(200);
        $res->assertSee('Request Mock Test');
    }

    /**
     * TEST: RA viewing unpublished assessment on admin.tests.show sees inspection details with locked assignment controls.
     */
    public function test_ra_viewing_unpublished_assessment_sees_inspection_details_with_locked_assignment(): void
    {
        $draftTest = Test::create([
            'title'            => 'TOEIC Draft Inspection Assessment',
            'slug'             => 'toeic-draft-inspection-assessment',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 75,
            'pass_score'       => 75,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->rmUser->id,
            'assigned_to'      => $this->teacherUser->id,
        ]);

        $res = $this->actingAs($this->adminUser)->get(route('admin.tests.show', $draftTest->id));

        $res->assertStatus(200);
        $res->assertSee('In Authoring');
        $res->assertSee('Assignment Controls Locked');
        $res->assertSee('TOEIC Draft Inspection Assessment');
        // Assignment form should not be rendered
        $res->assertDontSee('Assign Assessment to Candidate');
        $res->assertDontSee('action="' . route('admin.tests.assign-candidate', $draftTest->id) . '"', false);
    }

    /**
     * TEST: Backend rejects direct assignment to unpublished assessment.
     */
    public function test_backend_rejects_candidate_assignment_to_unpublished_test(): void
    {
        $draftTest = Test::create([
            'title'            => 'TOEIC Draft Guarded Assessment',
            'slug'             => 'toeic-draft-guarded-assessment',
            'test_type'        => 'toeic',
            'assessment_mode'  => AssessmentMode::RealTest,
            'duration_minutes' => 75,
            'pass_score'       => 75,
            'status'           => 'draft',
            'is_published'     => false,
            'created_by'       => $this->rmUser->id,
            'assigned_to'      => $this->teacherUser->id,
        ]);

        $res = $this->actingAs($this->adminUser)->post(route('admin.tests.assign-candidate', $draftTest->id), [
            'candidate_id' => $this->candidate1->id,
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('Assessment must be published before candidate assignment', session('error'));
        $this->assertEquals(0, CandidateTestAssignment::count());
    }

    /**
     * TEST: Multi-process concurrency test using an isolated SQLite database to verify duplicate prevention under racing requests.
     */
    public function test_concurrent_submissions_create_exactly_one_request_on_sqlite(): void
    {
        $tempDb = sys_get_temp_dir() . '/iap_concurrency_test_' . uniqid() . '.sqlite';
        touch($tempDb);

        try {
            $bootstrapScript = base_path('scratch_concurrency_runner.php');
            $runnerCode = <<<'PHP'
<?php
$tempDb = $argv[1];
$adminId = (int)$argv[2];
$title = $argv[3];
$testType = $argv[4];

putenv("APP_ENV=testing");
putenv("DB_CONNECTION=sqlite");
putenv("DB_DATABASE={$tempDb}");
putenv("CACHE_STORE=file");

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => $tempDb]);
config(['cache.default' => 'file']);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::reconnect('sqlite');

$admin = \App\Models\User::on('sqlite')->find($adminId);
if (!$admin) {
    echo "ERROR: Admin not found\n";
    exit(1);
}

\Illuminate\Support\Facades\Auth::login($admin);

$request = \Illuminate\Http\Request::create('/admin/assessment-requests', 'POST', [
    'title'     => $title,
    'test_type' => $testType,
]);
$request->setUserResolver(fn() => $admin);
$request->setLaravelSession($app['session']->driver());

$controller = $app->make(\App\Http\Controllers\Admin\AssessmentRequestController::class);

try {
    $response = $controller->store($request);
    echo "SUCCESS\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
PHP;
            file_put_contents($bootstrapScript, $runnerCode);

            // Run migrations on isolated temp DB
            config(['database.connections.temp_sqlite' => [
                'driver'   => 'sqlite',
                'database' => $tempDb,
                'prefix'   => '',
            ]]);
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'temp_sqlite',
                '--force'    => true,
            ]);

            // Seed admin user in temp db
            $tempDbConn = \Illuminate\Support\Facades\DB::connection('temp_sqlite');
            $adminId = $tempDbConn->table('users')->insertGetId([
                'name'       => 'Admin User',
                'email'      => 'admin_test_' . uniqid() . '@example.com',
                'password'   => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Launch 2 parallel processes simultaneously
            $cmd = sprintf(
                'php %s %s %d %s %s',
                escapeshellarg($bootstrapScript),
                escapeshellarg($tempDb),
                $adminId,
                escapeshellarg('Concurrent TOEIC Request'),
                escapeshellarg('toeic')
            );

            $p1 = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
            $p2 = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);

            $out1 = stream_get_contents($pipes1[1]);
            fclose($pipes1[1]);
            fclose($pipes1[2]);
            proc_close($p1);

            $out2 = stream_get_contents($pipes2[1]);
            fclose($pipes2[1]);
            fclose($pipes2[2]);
            proc_close($p2);

            // Assert exactly 1 record was created in the temp db
            $totalCount = $tempDbConn->table('assessment_requests')->count();
            $this->assertEquals(1, $totalCount, "Expected exactly 1 assessment request created concurrently, found {$totalCount}. Process outputs: [P1: {$out1}, P2: {$out2}]");

        } finally {
            if (file_exists($tempDb)) {
                @unlink($tempDb);
            }
            if (file_exists(base_path('scratch_concurrency_runner.php'))) {
                @unlink(base_path('scratch_concurrency_runner.php'));
            }
        }
    }
}
