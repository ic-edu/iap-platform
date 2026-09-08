<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Modules\Assessment\Models\Test;
use App\Modules\Assessment\Models\TestSection;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use App\Modules\Commerce\Domain\Enums\OrderStatus;
use App\Modules\Commerce\Domain\Models\Order;
use App\Modules\Commerce\Domain\Models\OrderItem;
use App\Modules\Commerce\Domain\Models\Product;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\SeatAllocationStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationEntitlement;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSeatAllocation;
use App\Notifications\EnterpriseSystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GovernedMockTestRequestRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rm;
    protected User $teacher;
    protected User $coordinator;
    protected User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'repository-manager']);
        Role::firstOrCreate(['name' => 'teacher']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'coordinator']);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'status' => 'active']);
        $this->admin->assignRole('admin');

        $this->rm = User::factory()->create(['name' => 'RM User', 'status' => 'active']);
        $this->rm->assignRole('repository-manager');

        $this->teacher = User::factory()->create(['name' => 'Teacher User', 'status' => 'active']);
        $this->teacher->assignRole('teacher');

        $this->coordinator = User::factory()->create(['name' => 'Coordinator User', 'status' => 'active']);
        $this->coordinator->assignRole('coordinator');

        $this->student = User::factory()->create(['name' => 'Student User', 'status' => 'active']);
        $this->student->assignRole('student');

        $this->createInstitutionalSeatFor($this->student);
    }

    protected function createInstitutionalSeatFor(User $student): OrganizationSeatAllocation
    {
        $org = Organization::create(['name' => 'UAT University', 'slug' => 'uat-university-' . $student->id, 'status' => 'active']);
        $membership = OrganizationMembership::create([
            'organization_id'   => $org->id,
            'user_id'           => $student->id,
            'role'              => MembershipRole::Member,
            'status'            => MembershipStatus::Active,
            'member_identifier' => 'NIM-' . $student->id,
        ]);

        $product = Product::create([
            'title'             => 'TOEIC Mock Test Package',
            'slug'              => 'toeic-mock-test-package-' . $student->id,
            'product_type'      => 'assessment',
            'assessment_family' => 'toeic',
            'price'             => 150000,
            'is_active'         => true,
        ]);

        $order = Order::create([
            'order_number'    => 'ORD-MOCK-' . $student->id,
            'user_id'         => $this->coordinator->id,
            'organization_id' => $org->id,
            'status'          => OrderStatus::Completed,
            'subtotal'        => 150000,
            'tax_amount'      => 0,
            'grand_total'     => 150000,
            'currency'        => 'IDR',
        ]);

        $orderItem = OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 3,
            'price'      => 50000,
            'total'      => 150000,
        ]);

        $entitlement = OrganizationEntitlement::create([
            'organization_id' => $org->id,
            'order_item_id'   => $orderItem->id,
            'product_id'      => $product->id,
            'total_seats'     => 3,
            'allocated_seats' => 1,
            'available_seats' => 2,
            'status'          => 'active',
        ]);

        return OrganizationSeatAllocation::create([
            'organization_entitlement_id' => $entitlement->id,
            'organization_membership_id'  => $membership->id,
            'status'                      => SeatAllocationStatus::Active,
            'allocated_at'                => now(),
        ]);
    }

    /** REQ-01: Approved but unpublished real_test is NOT visible in RA Published Mock Tests catalog */
    public function test_approved_but_unpublished_real_test_is_not_visible_in_ra_published_mock_tests(): void
    {
        $approvedTest = Test::create([
            'title'            => 'Approved Secret Mock Test',
            'slug'             => 'approved-secret-mock-test',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'created_by'       => $this->rm->id,
            'status'           => 'approved',
            'is_published'     => false,
        ]);
        TestSection::create(['test_id' => $approvedTest->id, 'title' => 'Section 1', 'order' => 1]);

        $response = $this->actingAs($this->admin)->get(route('admin.tests.index', ['tab' => 'mock_tests']));
        $response->assertOk();
        $response->assertDontSee('Approved Secret Mock Test');
        $response->assertSee('No Published Mock Tests in Catalog');
    }

    /** REQ-02: Published real_test IS visible in RA Published Mock Tests catalog */
    public function test_published_real_test_is_visible_in_ra_published_mock_tests(): void
    {
        $publishedTest = Test::create([
            'title'            => 'Published Live Mock Test',
            'slug'             => 'published-live-mock-test',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'created_by'       => $this->rm->id,
            'status'           => 'published',
            'is_published'     => true,
        ]);
        TestSection::create(['test_id' => $publishedTest->id, 'title' => 'Section 1', 'order' => 1]);

        $response = $this->actingAs($this->admin)->get(route('admin.tests.index', ['tab' => 'mock_tests']));
        $response->assertOk();
        $response->assertSee('Published Live Mock Test');
    }

    /** REQ-03: Simulator test is excluded from Published Mock Tests and appears in Practice Simulators */
    public function test_simulator_test_is_excluded_from_published_mock_tests(): void
    {
        $simulator = Test::create([
            'title'            => 'Open Simulator Test',
            'slug'             => 'open-simulator-test',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'simulator',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'created_by'       => $this->teacher->id,
            'status'           => 'published',
            'is_published'     => true,
        ]);
        TestSection::create(['test_id' => $simulator->id, 'title' => 'Section 1', 'order' => 1]);

        // Excluded from Mock Tests
        $respMock = $this->actingAs($this->admin)->get(route('admin.tests.index', ['tab' => 'mock_tests']));
        $respMock->assertDontSee('Open Simulator Test');

        // Included in Simulators
        $respSim = $this->actingAs($this->admin)->get(route('admin.tests.index', ['tab' => 'simulators']));
        $respSim->assertSee('Open Simulator Test');
    }

    /** REQ-04 & REQ-05: Request completion on publication and RA notification */
    public function test_publishing_requested_test_closes_request_and_notifies_ra(): void
    {
        Notification::fake();

        $request = AssessmentRequest::create([
            'title'        => 'Custom TOEIC for Class 9A',
            'test_type'    => 'toeic',
            'requested_by' => $this->admin->id,
            'status'       => 'draft_created',
        ]);

        $test = Test::create([
            'title'                 => 'Custom TOEIC for Class 9A Live',
            'slug'                  => 'custom-toeic-for-class-9a-live',
            'test_type'             => 'toeic',
            'assessment_mode'       => 'real_test',
            'duration_minutes'      => 120,
            'pass_score'            => 700,
            'created_by'            => $this->rm->id,
            'assigned_to'           => $this->teacher->id,
            'assessment_request_id' => $request->id,
            'status'                => 'approved',
            'is_published'          => false,
        ]);
        TestSection::create(['test_id' => $test->id, 'title' => 'Section 1', 'order' => 1]);

        $request->update(['test_id' => $test->id]);

        $response = $this->actingAs($this->rm)->post(route('admin.publications.assessments.publish', $test->id));
        $response->assertRedirect();

        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue($test->is_published);

        $request->refresh();
        $this->assertEquals('completed', $request->status);

        Notification::assertSentTo(
            $this->admin,
            EnterpriseSystemNotification::class,
            function (EnterpriseSystemNotification $notif) use ($test) {
                return $notif->title === 'Requested Mock Test Published'
                    && $notif->type === 'ASSESSMENT_REQUEST_COMPLETED'
                    && $notif->priority === 'HIGH'
                    && str_contains($notif->targetUrl, 'tab=mock_tests');
            }
        );
    }

    /** REQ-06: Publishing normal test without request has no request closure side effect */
    public function test_publishing_normal_test_without_request_publishes_normally(): void
    {
        $test = Test::create([
            'title'            => 'Standard Catalog Test',
            'slug'             => 'standard-catalog-test',
            'test_type'        => 'toeic',
            'assessment_mode'  => 'real_test',
            'duration_minutes' => 120,
            'pass_score'       => 700,
            'created_by'       => $this->rm->id,
            'status'           => 'approved',
            'is_published'     => false,
        ]);
        TestSection::create(['test_id' => $test->id, 'title' => 'Section 1', 'order' => 1]);

        $response = $this->actingAs($this->rm)->post(route('admin.publications.assessments.publish', $test->id));
        $response->assertRedirect();

        $test->refresh();
        $this->assertEquals('published', $test->status);
        $this->assertTrue($test->is_published);
        $this->assertEquals(0, AssessmentRequest::count());
    }

    /** REQ-07: Contextual prefill on Assessment Requests page */
    public function test_assessment_requests_index_accepts_contextual_query_params(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.assessment-requests.index', [
            'candidate_id'    => $this->student->id,
            'test_type'       => 'toeic',
            'program_context' => 'iC.edu UAT University — Class 9A',
            'title'           => 'TOEIC Mock Test',
        ]));

        $response->assertOk();
        $response->assertSee('value="TOEIC Mock Test"', false);
        $response->assertSee('value="iC.edu UAT University — Class 9A"', false);
        $response->assertSee('openCreateRequestModal()', false);
    }

    /** REQ-08: Institutional candidate with active seat allocation is eligible for request */
    public function test_institutional_candidate_with_active_seat_is_eligible_for_request(): void
    {
        $newStudent = User::factory()->create(['status' => 'active']);
        $newStudent->assignRole('student');

        $this->createInstitutionalSeatFor($newStudent);

        $response = $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
            'title'              => 'Institutional TOEIC Requirement',
            'test_type'          => 'toeic',
            'candidate_id'       => $newStudent->id,
            'program_context'    => 'UAT University',
            'notes'              => 'Required for Class 9A',
        ]);

        $response->assertRedirect(route('admin.assessment-requests.index'));
        $this->assertDatabaseHas('assessment_requests', [
            'title'        => 'Institutional TOEIC Requirement',
            'candidate_id' => $newStudent->id,
            'status'       => 'pending',
        ]);
    }

    /** REQ-09: Candidate without B2C payment and without active seat is rejected */
    public function test_candidate_without_b2c_payment_or_seat_is_rejected(): void
    {
        $unpaidCandidate = User::factory()->create(['status' => 'active']);
        $unpaidCandidate->assignRole('student');

        $response = $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
            'title'        => 'Invalid Candidate Request',
            'test_type'    => 'toeic',
            'candidate_id' => $unpaidCandidate->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('assessment_requests', [
            'title'        => 'Invalid Candidate Request',
            'candidate_id' => $unpaidCandidate->id,
        ]);
    }

    /** REQ-10: Duplicate active request guard */
    public function test_duplicate_active_request_guard_blocks_pending_and_draft_created(): void
    {
        AssessmentRequest::create([
            'title'        => 'Duplicate Test Title',
            'test_type'    => 'toeic',
            'candidate_id' => $this->student->id,
            'requested_by' => $this->admin->id,
            'status'       => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.assessment-requests.store'), [
            'title'        => 'Duplicate Test Title',
            'test_type'    => 'toeic',
            'candidate_id' => $this->student->id,
        ]);

        $response->assertRedirect(route('admin.assessment-requests.index'));
        $response->assertSessionHas('error');
    }

    /** REQ-11: Role boundary checks on Assessment Request creation */
    public function test_non_ra_roles_cannot_submit_assessment_requests(): void
    {
        // Teacher
        $respTeacher = $this->actingAs($this->teacher)->post(route('admin.assessment-requests.store'), [
            'title'     => 'Teacher Request',
            'test_type' => 'toeic',
        ]);
        $respTeacher->assertForbidden();

        // Coordinator
        $respCoord = $this->actingAs($this->coordinator)->post(route('admin.assessment-requests.store'), [
            'title'     => 'Coordinator Request',
            'test_type' => 'toeic',
        ]);
        $respCoord->assertForbidden();

        // Candidate
        $respStudent = $this->actingAs($this->student)->post(route('admin.assessment-requests.store'), [
            'title'     => 'Student Request',
            'test_type' => 'toeic',
        ]);
        $respStudent->assertForbidden();
    }
}
