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

class OrganizationGroupMemberEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;
    protected User $adminUser;
    protected User $ownerUser;
    protected User $candidate1;
    protected User $candidate2;
    protected User $foreignCandidate;
    protected Organization $org;
    protected Organization $otherOrg;
    protected OrganizationMembership $membershipCoord;
    protected OrganizationMembership $membershipAdmin;
    protected OrganizationMembership $membershipOwner;
    protected OrganizationMembership $membershipCand1;
    protected OrganizationMembership $membershipCand2;
    protected OrganizationMembership $membershipForeignCand;
    protected OrganizationGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Primary Org
        $this->org = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'email'             => 'contact@icedu-uat.org',
            'status'            => OrganizationStatus::Active,
        ]);

        // Secondary Org for Tenant Isolation
        $this->otherOrg = Organization::create([
            'name'              => 'Other Academy',
            'slug'              => 'other-academy',
            'organization_type' => OrganizationType::School,
            'email'             => 'contact@other.org',
            'status'            => OrganizationStatus::Active,
        ]);

        // Coordinator
        $this->coordinator = User::create([
            'name'     => 'Indra Wahyudi',
            'email'    => 'ic.edu.bdg@gmail.com',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->coordinator->assignRole('organization-coordinator');

        $this->membershipCoord = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->coordinator->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
        ]);

        // Admin
        $this->adminUser = User::create([
            'name'     => 'Org Admin User',
            'email'    => 'admin@icedu-uat.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->membershipAdmin = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->adminUser->id,
            'role'            => MembershipRole::Admin,
            'status'          => MembershipStatus::Active,
        ]);

        // Owner
        $this->ownerUser = User::create([
            'name'     => 'Org Owner User',
            'email'    => 'owner@icedu-uat.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->membershipOwner = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->ownerUser->id,
            'role'            => MembershipRole::Owner,
            'status'          => MembershipStatus::Active,
        ]);

        // Candidates in Primary Org
        $this->candidate1 = User::create([
            'name'     => 'CA01',
            'email'    => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate1->assignRole('student');

        $this->membershipCand1 = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->candidate1->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
        ]);

        $this->candidate2 = User::create([
            'name'     => 'CA02',
            'email'    => 'ca02@uat.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->candidate2->assignRole('student');

        $this->membershipCand2 = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $this->candidate2->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
        ]);

        // Foreign Candidate in Other Org
        $this->foreignCandidate = User::create([
            'name'     => 'Foreign Candidate',
            'email'    => 'foreign@other.org',
            'password' => bcrypt('password'),
            'status'   => 'active',
        ]);
        $this->foreignCandidate->assignRole('student');

        $this->membershipForeignCand = OrganizationMembership::create([
            'organization_id' => $this->otherOrg->id,
            'user_id'         => $this->foreignCandidate->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
        ]);

        // Group Class 9A
        $this->group = OrganizationGroup::create([
            'organization_id' => $this->org->id,
            'name'            => 'Class 9A',
            'group_type'      => GroupType::ClassGroup->value,
            'is_active'       => true,
        ]);
    }

    /**
     * TEST GROUP-ELIG-01: Candidate Member appears in picker.
     */
    public function test_group_elig_01_candidate_member_appears_in_picker(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.groups.show', [$this->org->slug, $this->group->id]));

        $response->assertOk();
        $response->assertSee('CA01');
        $response->assertSee('ca01@uat.org');
        $response->assertSee('CA02');
        $response->assertSee('ca02@uat.org');

        $available = $response->viewData('availableMemberships');
        $this->assertTrue($available->contains('id', $this->membershipCand1->id));
        $this->assertTrue($available->contains('id', $this->membershipCand2->id));
    }

    /**
     * TEST GROUP-ELIG-02: Coordinator does not appear in picker.
     */
    public function test_group_elig_02_coordinator_does_not_appear_in_picker(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.groups.show', [$this->org->slug, $this->group->id]));

        $response->assertOk();
        $available = $response->viewData('availableMemberships');
        $this->assertFalse($available->contains('id', $this->membershipCoord->id));
    }

    /**
     * TEST GROUP-ELIG-03: Organization Admin does not appear in picker.
     */
    public function test_group_elig_03_org_admin_does_not_appear_in_picker(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.groups.show', [$this->org->slug, $this->group->id]));

        $response->assertOk();
        $available = $response->viewData('availableMemberships');
        $this->assertFalse($available->contains('id', $this->membershipAdmin->id));
    }

    /**
     * TEST GROUP-ELIG-04: Owner does not appear in picker.
     */
    public function test_group_elig_04_owner_does_not_appear_in_picker(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.groups.show', [$this->org->slug, $this->group->id]));

        $response->assertOk();
        $available = $response->viewData('availableMemberships');
        $this->assertFalse($available->contains('id', $this->membershipOwner->id));
    }

    /**
     * TEST GROUP-ELIG-05: Already assigned Candidate is excluded from picker.
     */
    public function test_group_elig_05_already_assigned_candidate_is_excluded_from_picker(): void
    {
        // Add CA01 to Class 9A
        $this->group->addMembership($this->membershipCand1);

        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.groups.show', [$this->org->slug, $this->group->id]));

        $response->assertOk();
        $available = $response->viewData('availableMemberships');
        $this->assertFalse($available->contains('id', $this->membershipCand1->id));
        $this->assertTrue($available->contains('id', $this->membershipCand2->id));
    }

    /**
     * TEST GROUP-SEC-01: Crafted request cannot add Coordinator as member.
     */
    public function test_group_sec_01_crafted_request_cannot_add_coordinator_as_member(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.groups.members.add', [$this->org->slug, $this->group->id]), [
                'membership_id' => $this->membershipCoord->id,
            ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertFalse($this->group->hasMembership($this->membershipCoord));
    }

    /**
     * TEST GROUP-SEC-02: Crafted request cannot add Admin or Owner.
     */
    public function test_group_sec_02_crafted_request_cannot_add_admin_or_owner(): void
    {
        // Try Admin
        $resAdmin = $this->actingAs($this->coordinator)
            ->post(route('organization.groups.members.add', [$this->org->slug, $this->group->id]), [
                'membership_id' => $this->membershipAdmin->id,
            ]);

        $resAdmin->assertSessionHasErrors(['error']);
        $this->assertFalse($this->group->hasMembership($this->membershipAdmin));

        // Try Owner
        $resOwner = $this->actingAs($this->coordinator)
            ->post(route('organization.groups.members.add', [$this->org->slug, $this->group->id]), [
                'membership_id' => $this->membershipOwner->id,
            ]);

        $resOwner->assertSessionHasErrors(['error']);
        $this->assertFalse($this->group->hasMembership($this->membershipOwner));
    }

    /**
     * TEST GROUP-SEC-03: Cross-Organization Candidate cannot be added.
     */
    public function test_group_sec_03_cross_organization_candidate_cannot_be_added(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.groups.members.add', [$this->org->slug, $this->group->id]), [
                'membership_id' => $this->membershipForeignCand->id,
            ]);

        $response->assertStatus(403);
        $this->assertFalse($this->group->hasMembership($this->membershipForeignCand));
    }

    /**
     * TEST GROUP-CA-01: After Candidate Member is assigned to group, Candidate institutional context displays group name.
     */
    public function test_group_ca_01_candidate_dashboard_displays_group_name_after_assignment(): void
    {
        // Before assignment: displays 'Not Assigned'
        $beforeRes = $this->actingAs($this->candidate1)->get(route('candidate.portal'));
        $beforeRes->assertOk();
        $beforeRes->assertSee('Not Assigned');
        $beforeRes->assertDontSee('Class 9A');

        // Add CA01 to Class 9A
        $addRes = $this->actingAs($this->coordinator)
            ->post(route('organization.groups.members.add', [$this->org->slug, $this->group->id]), [
                'membership_id' => $this->membershipCand1->id,
            ]);
        $addRes->assertSessionHasNoErrors();
        $this->assertTrue($this->group->hasMembership($this->membershipCand1));

        // After assignment: displays 'Class 9A'
        $afterRes = $this->actingAs($this->candidate1)->get(route('candidate.portal'));
        $afterRes->assertOk();
        $afterRes->assertSee('Class 9A');
        $afterRes->assertDontSee('Not Assigned');
    }
}
