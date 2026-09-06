<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateInstitutionalMembershipContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_institutional_candidate_sees_organization_context_card_with_not_assigned_group(): void
    {
        $candidate = User::create([
            'name' => 'CA01',
            'email' => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org = Organization::create([
            'name' => 'iC.edu UAT University',
            'slug' => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('Institutional Membership');
        $response->assertSee('iC.edu UAT University');
        $response->assertSee('University / College');
        $response->assertSee('Candidate Member');
        $response->assertSee('Active');
        $response->assertSee('Not Assigned');
    }

    public function test_institutional_candidate_with_assigned_group_displays_group_name(): void
    {
        $candidate = User::create([
            'name' => 'CA01',
            'email' => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org = Organization::create([
            'name' => 'iC.edu UAT University',
            'slug' => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
        ]);

        $membership = OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        $group = OrganizationGroup::create([
            'organization_id' => $org->id,
            'name' => 'Class 12A',
            'is_active' => true,
        ]);

        $group->addMembership($membership);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('iC.edu UAT University');
        $response->assertSee('Class 12A');
        $response->assertDontSee('Not Assigned');
    }

    public function test_institutional_candidate_with_multiple_groups_displays_all_active_groups(): void
    {
        $candidate = User::create([
            'name' => 'CA01',
            'email' => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org = Organization::create([
            'name' => 'iC.edu UAT University',
            'slug' => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
        ]);

        $membership = OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        $group1 = OrganizationGroup::create([
            'organization_id' => $org->id,
            'name' => 'Class 12A',
            'is_active' => true,
        ]);
        $group2 = OrganizationGroup::create([
            'organization_id' => $org->id,
            'name' => 'Batch 2026',
            'is_active' => true,
        ]);
        $inactiveGroup = OrganizationGroup::create([
            'organization_id' => $org->id,
            'name' => 'Archived Group',
            'is_active' => false,
        ]);

        $group1->addMembership($membership);
        $group2->addMembership($membership);
        $inactiveGroup->addMembership($membership);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('Class 12A, Batch 2026');
        $response->assertDontSee('Archived Group');
    }

    public function test_individual_candidate_does_not_see_institutional_membership_card(): void
    {
        $candidate = User::create([
            'name' => 'Standalone Student',
            'email' => 'standalone@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertDontSee('Institutional Membership');
        $response->assertDontSee('Group / Cohort');
    }

    public function test_inactive_or_suspended_organization_is_not_displayed(): void
    {
        $candidate = User::create([
            'name' => 'CA01',
            'email' => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org = Organization::create([
            'name' => 'Suspended Academy',
            'slug' => 'suspended-academy',
            'organization_type' => OrganizationType::School,
            'status' => OrganizationStatus::Suspended,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertDontSee('Suspended Academy');
        $response->assertDontSee('Institutional Membership');
    }

    public function test_inactive_or_revoked_membership_is_not_displayed(): void
    {
        $candidate = User::create([
            'name' => 'CA01',
            'email' => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org = Organization::create([
            'name' => 'Active Academy',
            'slug' => 'active-academy',
            'organization_type' => OrganizationType::School,
            'status' => OrganizationStatus::Active,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Suspended,
        ]);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertDontSee('Active Academy');
        $response->assertDontSee('Institutional Membership');
    }

    public function test_multi_institutional_candidate_sees_all_active_organizations(): void
    {
        $candidate = User::create([
            'name' => 'Multi Org Candidate',
            'email' => 'multiorg@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org1 = Organization::create([
            'name' => 'Alpha University',
            'slug' => 'alpha-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
        ]);
        $org2 = Organization::create([
            'name' => 'Beta Training Center',
            'slug' => 'beta-training-center',
            'organization_type' => OrganizationType::TrainingInstitution,
            'status' => OrganizationStatus::Active,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org1->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);
        OrganizationMembership::create([
            'organization_id' => $org2->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('Alpha University');
        $response->assertSee('University / College');
        $response->assertSee('Beta Training Center');
        $response->assertSee('Training Institution');
    }

    public function test_candidate_portal_does_not_render_organization_management_controls(): void
    {
        $candidate = User::create([
            'name' => 'CA01',
            'email' => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org = Organization::create([
            'name' => 'iC.edu UAT University',
            'slug' => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        // Ensure no coordinator/management links or buttons exist
        $response->assertDontSee(route('organization.dashboard', $org));
        $response->assertDontSee('Manage Organization');
        $response->assertDontSee('Invite Member');
        $response->assertDontSee('Organization Settings');
    }

    public function test_institutional_membership_card_includes_theme_responsive_classes(): void
    {
        $candidate = User::create([
            'name' => 'CA01',
            'email' => 'ca01@uat.org',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $candidate->assignRole('student');

        $org = Organization::create([
            'name' => 'iC.edu UAT University',
            'slug' => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status' => OrganizationStatus::Active,
        ]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $candidate->id,
            'role' => MembershipRole::Member,
            'status' => MembershipStatus::Active,
        ]);

        $response = $this->actingAs($candidate)->get(route('candidate.portal'));

        $response->assertOk();
        $response->assertSee('dark:bg-slate-900');
        $response->assertSee('dark:border-slate-800');
        $response->assertSee('dark:text-white');
    }
}
