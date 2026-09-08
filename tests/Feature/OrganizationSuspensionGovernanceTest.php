<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Models\OrganizationSuspensionRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationSuspensionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminRA;
    protected User $coordinatorUser;
    protected Organization $testOrg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name'  => 'SA Governance Chief',
            'email' => 'sa-gov-' . uniqid() . '@assessment.gov',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->adminRA = User::factory()->create([
            'name'  => 'Operational RA Officer',
            'email' => 'ra-ops-' . uniqid() . '@assessment.ops',
        ]);
        $this->adminRA->assignRole('admin');

        $this->coordinatorUser = User::factory()->create([
            'name'  => 'Coord Lead',
            'email' => 'coord-lead-' . uniqid() . '@campus.edu',
        ]);
        $this->coordinatorUser->assignRole('organization-coordinator');

        $this->testOrg = Organization::create([
            'name'              => 'Institute of Technology ' . uniqid(),
            'slug'              => 'it-inst-' . uniqid(),
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now(),
        ]);

        OrganizationMembership::create([
            'organization_id' => $this->testOrg->id,
            'user_id'         => $this->coordinatorUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'is_active'       => true,
            'joined_at'       => now(),
        ]);
    }

    /**
     * ORG-GOV-01: RA index view does NOT render Portal ↗ link for organizations.
     */
    public function test_org_gov_01_ra_view_does_not_render_portal_link(): void
    {
        $response = $this->actingAs($this->adminRA)->get(route('admin.organizations.index'));
        $response->assertOk();
        $response->assertSee($this->testOrg->name);
        $response->assertDontSee('Portal ↗');
    }

    /**
     * ORG-GOV-02: Direct suspension by RA is blocked and preserves Active status.
     */
    public function test_org_gov_02_ra_direct_suspend_is_blocked(): void
    {
        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.suspend', $this->testOrg->id));
        $response->assertSessionHasErrors('error');

        $this->testOrg->refresh();
        $this->assertEquals(OrganizationStatus::Active, $this->testOrg->status);
        $this->assertTrue($this->testOrg->isActive());
    }

    /**
     * ORG-GOV-03: RA can submit a valid suspension request; org remains Active while pending.
     */
    public function test_org_gov_03_ra_can_submit_suspension_request(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.request-suspension', $this->testOrg->id), [
            'reason' => 'Annual compliance audit failure and overdue licensing fees',
            'notes'  => 'Contacted registrar on Sept 1st without reply',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->testOrg->refresh();
        $this->assertEquals(OrganizationStatus::Active, $this->testOrg->status);
        $this->assertTrue($this->testOrg->hasPendingSuspension());

        $suspRequest = $this->testOrg->pendingSuspensionRequest;
        $this->assertNotNull($suspRequest);
        $this->assertEquals('pending', $suspRequest->status);
        $this->assertEquals($this->adminRA->id, $suspRequest->requested_by);
        $this->assertEquals('Annual compliance audit failure and overdue licensing fees', $suspRequest->reason);
        $this->assertEquals('Contacted registrar on Sept 1st without reply', $suspRequest->notes);
    }

    /**
     * ORG-GOV-04: Suspension request validation requires non-empty reason.
     */
    public function test_org_gov_04_suspension_request_requires_valid_reason(): void
    {
        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.request-suspension', $this->testOrg->id), [
            'reason' => '',
            'notes'  => 'Just some notes without reason',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertFalse($this->testOrg->fresh()->hasPendingSuspension());
    }

    /**
     * ORG-GOV-05: Duplicate suspension request is rejected when one is already pending.
     */
    public function test_org_gov_05_duplicate_suspension_request_is_prevented(): void
    {
        OrganizationSuspensionRequest::create([
            'organization_id' => $this->testOrg->id,
            'requested_by'    => $this->adminRA->id,
            'reason'          => 'First pending request',
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.request-suspension', $this->testOrg->id), [
            'reason' => 'Duplicate second attempt',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertEquals(1, $this->testOrg->suspensionRequests()->count());
    }

    /**
     * ORG-GOV-06: Super Admin governance view displays pending suspension requests.
     */
    public function test_org_gov_06_super_admin_governance_view_displays_pending_suspension_requests(): void
    {
        $suspRequest = OrganizationSuspensionRequest::create([
            'organization_id' => $this->testOrg->id,
            'requested_by'    => $this->adminRA->id,
            'reason'          => 'Unresolved security breach report #883',
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.approvals.organizations'));
        $response->assertOk();
        $response->assertSee('Pending Suspension Requests');
        $response->assertSee('Unresolved security breach report #883');
        $response->assertSee($this->testOrg->name);
        $response->assertSee('Approve Suspension');
        $response->assertSee('Portal ↗'); // Super Admin retains portal link in Active table
    }

    /**
     * ORG-GOV-07: Super Admin can approve suspension request; transitions org to Suspended.
     */
    public function test_org_gov_07_super_admin_can_approve_suspension(): void
    {
        Notification::fake();

        $suspRequest = OrganizationSuspensionRequest::create([
            'organization_id' => $this->testOrg->id,
            'requested_by'    => $this->adminRA->id,
            'reason'          => 'Contract termination protocol',
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.approvals.organizations.suspensions.approve', $suspRequest->id),
            ['decision_notes' => 'Suspension confirmed by Executive Board.']
        );

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->testOrg->refresh();
        $suspRequest->refresh();

        $this->assertEquals(OrganizationStatus::Suspended, $this->testOrg->status);
        $this->assertTrue($this->testOrg->isSuspended());
        $this->assertEquals('approved', $suspRequest->status);
        $this->assertEquals($this->superAdmin->id, $suspRequest->reviewed_by);
        $this->assertNotNull($suspRequest->reviewed_at);
        $this->assertFalse($this->testOrg->hasPendingSuspension());
    }

    /**
     * ORG-GOV-08: Super Admin can reject suspension request; org remains Active.
     */
    public function test_org_gov_08_super_admin_can_reject_suspension(): void
    {
        Notification::fake();

        $suspRequest = OrganizationSuspensionRequest::create([
            'organization_id' => $this->testOrg->id,
            'requested_by'    => $this->adminRA->id,
            'reason'          => 'Suspected fraudulent registration',
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.approvals.organizations.suspensions.reject', $suspRequest->id),
            ['rejection_reason' => 'Official government accreditation documents verified valid.']
        );

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->testOrg->refresh();
        $suspRequest->refresh();

        $this->assertEquals(OrganizationStatus::Active, $this->testOrg->status);
        $this->assertTrue($this->testOrg->isActive());
        $this->assertEquals('rejected', $suspRequest->status);
        $this->assertEquals('Official government accreditation documents verified valid.', $suspRequest->decision_notes);
        $this->assertEquals($this->superAdmin->id, $suspRequest->reviewed_by);
        $this->assertNotNull($suspRequest->reviewed_at);
        $this->assertFalse($this->testOrg->hasPendingSuspension());
    }

    /**
     * ORG-GOV-09: Coordinator Portal access control.
     * Coordinator can access own portal; Super Admin can access; RA is denied (403).
     */
    public function test_org_gov_09_portal_access_boundaries(): void
    {
        // 1. Coordinator accessing own organization portal
        $coordResp = $this->actingAs($this->coordinatorUser)->get(route('organization.dashboard', $this->testOrg->slug));
        $coordResp->assertOk();

        // 2. Super Admin oversight accessing organization portal
        $saResp = $this->actingAs($this->superAdmin)->get(route('organization.dashboard', $this->testOrg->slug));
        $saResp->assertOk();

        // 3. Operational RA attempting direct portal entry is forbidden
        $raResp = $this->actingAs($this->adminRA)->get(route('organization.dashboard', $this->testOrg->slug));
        $raResp->assertForbidden();
    }

    /**
     * ORG-GOV-10: RA table renders '⏳ Suspension Pending' badge when request is pending.
     */
    public function test_org_gov_10_ra_table_renders_suspension_pending_badge(): void
    {
        OrganizationSuspensionRequest::create([
            'organization_id' => $this->testOrg->id,
            'requested_by'    => $this->adminRA->id,
            'reason'          => 'Audit in progress',
            'status'          => 'pending',
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.organizations.index'));
        $response->assertOk();
        $response->assertSee('Suspension Pending');
        $response->assertDontSee('Request Suspension');
    }
}
