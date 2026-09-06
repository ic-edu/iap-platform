<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Enums\GroupType;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationO1TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinatorA;
    protected User $coordinatorB;
    protected User $candidateA;
    protected User $candidateB;
    protected Organization $orgA;
    protected Organization $orgB;
    protected OrganizationMembership $membershipCoordA;
    protected OrganizationMembership $membershipCoordB;
    protected OrganizationMembership $membershipCandA;
    protected OrganizationMembership $membershipCandB;
    protected OrganizationGroup $groupA;
    protected OrganizationGroup $groupB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Organization A (iC.edu UAT University)
        $this->orgA = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'email'             => 'contact@icedu-uat.org',
            'phone'             => '+62 22 1234567',
            'website'           => 'https://uat.icedu.org',
            'address'           => 'Jl. UAT No. 1',
            'city'              => 'Bandung',
            'province'          => 'Jawa Barat',
            'country'           => 'Indonesia',
            'postal_code'       => '40132',
            'status'            => OrganizationStatus::Active,
        ]);

        // 2. Organization B (IAP Tenant Isolation UAT B)
        $this->orgB = Organization::create([
            'name'              => 'IAP Tenant Isolation UAT B',
            'slug'              => 'iap-tenant-isolation-uat-b',
            'organization_type' => OrganizationType::School,
            'email'             => 'secret.contact@tenant-b.org',
            'phone'             => '+62 21 9876543',
            'website'           => 'https://secret.tenant-b.org',
            'address'           => 'Jl. Rahasia No. 99',
            'city'              => 'Jakarta',
            'province'          => 'DKI Jakarta',
            'country'           => 'Indonesia',
            'postal_code'       => '10110',
            'status'            => OrganizationStatus::Active,
        ]);

        // 3. Coordinator A (Indra Wahyudi) - member of Org A only
        $this->coordinatorA = User::create([
            'name'     => 'Indra Wahyudi',
            'email'    => 'ic.edu.bdg@gmail.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->coordinatorA->assignRole('organization-coordinator');

        $this->membershipCoordA = OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $this->coordinatorA->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        // 4. Coordinator B - member of Org B only
        $this->coordinatorB = User::create([
            'name'     => 'Coordinator B',
            'email'    => 'coord.b@tenant-b.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->coordinatorB->assignRole('organization-coordinator');

        $this->membershipCoordB = OrganizationMembership::create([
            'organization_id' => $this->orgB->id,
            'user_id'         => $this->coordinatorB->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        // 5. Candidate CA01 - member of Org A only
        $this->candidateA = User::create([
            'name'     => 'CA01',
            'email'    => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidateA->assignRole('student');

        $this->membershipCandA = OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $this->candidateA->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
        ]);

        // 6. Candidate B - member of Org B only
        $this->candidateB = User::create([
            'name'     => 'Candidate B',
            'email'    => 'candidate.b@tenant-b.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidateB->assignRole('student');

        $this->membershipCandB = OrganizationMembership::create([
            'organization_id' => $this->orgB->id,
            'user_id'         => $this->candidateB->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
        ]);

        // 7. Groups
        $this->groupA = OrganizationGroup::create([
            'organization_id' => $this->orgA->id,
            'name'            => 'Class 9A',
            'group_type'      => GroupType::ClassGroup->value,
            'is_active'       => true,
        ]);
        $this->groupA->addMembership($this->membershipCandA);

        $this->groupB = OrganizationGroup::create([
            'organization_id' => $this->orgB->id,
            'name'            => 'Secret Cohort B',
            'group_type'      => GroupType::ClassGroup->value,
            'is_active'       => true,
        ]);
        $this->groupB->addMembership($this->membershipCandB);
    }

    /**
     * TEST TENANT-01: Coordinator A can access Organization A dashboard.
     */
    public function test_tenant_01_coordinator_a_can_access_organization_a_dashboard(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.dashboard', $this->orgA->slug));

        $response->assertOk();
        $response->assertSee('iC.edu UAT University');
        $response->assertSee('Class 9A');
    }

    /**
     * TEST TENANT-02: Coordinator A cannot access Organization B dashboard.
     */
    public function test_tenant_02_coordinator_a_cannot_access_organization_b_dashboard(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.dashboard', $this->orgB->slug));

        $response->assertStatus(403);
        $response->assertDontSee('IAP Tenant Isolation UAT B');
        $response->assertDontSee('Secret Cohort B');
    }

    /**
     * TEST TENANT-03: Coordinator A cannot access Organization B members.
     */
    public function test_tenant_03_coordinator_a_cannot_access_organization_b_members(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.candidates', $this->orgB->slug));

        $response->assertStatus(403);
        $response->assertDontSee('candidate.b@tenant-b.org');
        $response->assertDontSee('Candidate B');
    }

    /**
     * TEST TENANT-04: Coordinator A cannot access Organization B groups.
     */
    public function test_tenant_04_coordinator_a_cannot_access_organization_b_groups(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.groups', $this->orgB->slug));

        $response->assertStatus(403);
        $response->assertDontSee('Secret Cohort B');
    }

    /**
     * TEST TENANT-05: Coordinator A cannot access Organization B profile.
     */
    public function test_tenant_05_coordinator_a_cannot_access_organization_b_profile(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.profile', $this->orgB->slug));

        $response->assertStatus(403);
        $response->assertDontSee('secret.contact@tenant-b.org');
        $response->assertDontSee('Jl. Rahasia No. 99');
    }

    /**
     * TEST TENANT-MUT-01: Coordinator A cannot update Organization B profile.
     */
    public function test_tenant_mut_01_coordinator_a_cannot_update_organization_b_profile(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->put(route('organization.profile.update', $this->orgB->slug), [
                'email'   => 'hacked@tenant-b.org',
                'phone'   => '+123456789',
                'address' => 'Hacked Address',
            ]);

        $response->assertStatus(403);
        $this->assertEquals('secret.contact@tenant-b.org', $this->orgB->fresh()->email);
    }

    /**
     * TEST TENANT-MUT-02: Coordinator A cannot invite member into Organization B.
     */
    public function test_tenant_mut_02_coordinator_a_cannot_invite_member_into_organization_b(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->post(route('organization.candidates.invite', $this->orgB->slug), [
                'email'         => 'forged.candidate@tenant-b.org',
                'intended_role' => MembershipRole::Member->value,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('organization_invitations', [
            'organization_id' => $this->orgB->id,
            'email'           => 'forged.candidate@tenant-b.org',
        ]);
    }

    /**
     * TEST TENANT-MUT-03: Coordinator A cannot create Group in Organization B.
     */
    public function test_tenant_mut_03_coordinator_a_cannot_create_group_in_organization_b(): void
    {
        $response = $this->actingAs($this->coordinatorA)
            ->post(route('organization.groups.store', $this->orgB->slug), [
                'name'       => 'Forged Org B Group',
                'group_type' => GroupType::ClassGroup->value,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('organization_groups', [
            'organization_id' => $this->orgB->id,
            'name'            => 'Forged Org B Group',
        ]);
    }

    /**
     * TEST TENANT-MUT-04: Coordinator A cannot add member to Organization B Group.
     */
    public function test_tenant_mut_04_coordinator_a_cannot_add_member_to_organization_b_group(): void
    {
        // Try to add CA01 (from Org A) to Org B Group
        $resA = $this->actingAs($this->coordinatorA)
            ->post(route('organization.groups.members.add', [$this->orgB->slug, $this->groupB->id]), [
                'membership_id' => $this->membershipCandA->id,
            ]);

        $resA->assertStatus(403);
        $this->assertFalse($this->groupB->hasMembership($this->membershipCandA));

        // Try to add Candidate B (from Org B) to Org B Group as Coordinator A
        $resB = $this->actingAs($this->coordinatorA)
            ->post(route('organization.groups.members.add', [$this->orgB->slug, $this->groupB->id]), [
                'membership_id' => $this->membershipCandB->id,
            ]);

        $resB->assertStatus(403);
    }

    /**
     * TEST TENANT-SWITCH-01: Organization switcher lists only Organizations where current User has valid membership.
     */
    public function test_tenant_switch_01_organization_switcher_lists_only_authorized_organizations(): void
    {
        // For Coordinator A with only 1 membership, select route redirects straight to Org A dashboard
        $response = $this->actingAs($this->coordinatorA)
            ->get(route('organization.select'));

        $response->assertRedirect(route('organization.dashboard', $this->orgA->slug));

        // Create a user belonging to both Org A and a 3rd Org C
        $multiUser = User::create([
            'name'     => 'Multi Org Coordinator',
            'email'    => 'multi@example.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $multiUser->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $multiUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        $orgC = Organization::create([
            'name'              => 'Organization C',
            'slug'              => 'organization-c',
            'organization_type' => OrganizationType::Company,
            'status'            => OrganizationStatus::Active,
        ]);

        OrganizationMembership::create([
            'organization_id' => $orgC->id,
            'user_id'         => $multiUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        $multiRes = $this->actingAs($multiUser)->get(route('organization.select'));
        $multiRes->assertOk();
        $multiRes->assertSee('iC.edu UAT University');
        $multiRes->assertSee('Organization C');
        // Must NOT see Organization B
        $multiRes->assertDontSee('IAP Tenant Isolation UAT B');
    }

    /**
     * TEST TENANT-CA-01: Candidate CA01 sees Organization A institutional membership.
     */
    public function test_tenant_ca_01_candidate_ca01_sees_organization_a_membership(): void
    {
        $response = $this->actingAs($this->candidateA)
            ->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('iC.edu UAT University');
        $response->assertSee('Class 9A');
    }

    /**
     * TEST TENANT-CA-02: Candidate CA01 cannot see Organization B context.
     */
    public function test_tenant_ca_02_candidate_ca01_cannot_see_organization_b_context(): void
    {
        $response = $this->actingAs($this->candidateA)
            ->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertDontSee('IAP Tenant Isolation UAT B');
        $response->assertDontSee('Secret Cohort B');
    }

    /**
     * TEST TENANT-GLOBAL-01: Global organization-coordinator capability does NOT grant global tenant access.
     */
    public function test_tenant_global_01_global_coordinator_capability_does_not_grant_unassociated_access(): void
    {
        // Unassociated coordinator with organization-coordinator Spatie role but NO OrganizationMembership
        $unassociatedCoord = User::create([
            'name'     => 'Unassociated Coordinator',
            'email'    => 'unassociated@iap.test',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $unassociatedCoord->assignRole('organization-coordinator');

        $resA = $this->actingAs($unassociatedCoord)->get(route('organization.dashboard', $this->orgA->slug));
        $resA->assertStatus(403);

        $resB = $this->actingAs($unassociatedCoord)->get(route('organization.dashboard', $this->orgB->slug));
        $resB->assertStatus(403);
    }

    /**
     * TEST TENANT-NESTED-01: Coordinator A cannot access or mutate Org B group details or nested member remove routes.
     */
    public function test_tenant_nested_01_coordinator_a_cannot_access_or_mutate_org_b_nested_resources(): void
    {
        // Attempt to view Org B group details
        $resShow = $this->actingAs($this->coordinatorA)
            ->get(route('organization.groups.show', [$this->orgB->slug, $this->groupB->id]));
        $resShow->assertStatus(403);

        // Attempt to remove member from Org B group
        $resRemove = $this->actingAs($this->coordinatorA)
            ->delete(route('organization.groups.members.remove', [$this->orgB->slug, $this->groupB->id, $this->membershipCandB->id]));
        $resRemove->assertStatus(403);
        $this->assertTrue($this->groupB->fresh()->hasMembership($this->membershipCandB));
    }

    /**
     * TEST TENANT-ID-SLUG-01: Tampering with route parameters using Org B ULID or slug returns 403.
     */
    public function test_tenant_id_slug_01_tampering_with_route_parameters_returns_403(): void
    {
        // Using slug
        $resSlug = $this->actingAs($this->coordinatorA)
            ->get("/organization/{$this->orgB->slug}/dashboard");
        $resSlug->assertStatus(403);

        // Using ULID
        $resUlid = $this->actingAs($this->coordinatorA)
            ->get("/organization/{$this->orgB->id}/dashboard");
        $resUlid->assertStatus(403);
    }
}
