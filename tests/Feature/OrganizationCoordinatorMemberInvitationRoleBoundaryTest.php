<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Organization\Enums\InvitationStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationInvitation;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationCoordinatorMemberInvitationRoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;
    protected User $otherCoordinator;
    protected User $adminRA;
    protected User $superAdmin;
    protected Organization $orgA;
    protected Organization $orgB;
    protected OrganizationMembership $membershipA;
    protected OrganizationMembership $membershipB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->coordinator = User::factory()->create([
            'name'             => 'Indra Wahyudi',
            'email'            => 'ic.edu.bdg@gmail.com',
            'status'           => 'active',
            'theme_preference' => 'dark',
        ]);
        $this->coordinator->assignRole('admin');

        $this->otherCoordinator = User::factory()->create([
            'name'             => 'Other Coordinator',
            'email'            => 'other.coordinator@example.org',
            'status'           => 'active',
            'theme_preference' => 'dark',
        ]);
        $this->otherCoordinator->assignRole('admin');

        $this->adminRA = User::factory()->create([
            'name'   => 'Registration Admin',
            'email'  => 'admin.ra@example.com',
            'status' => 'active',
        ]);
        $this->adminRA->assignRole('admin');

        $this->superAdmin = User::factory()->create([
            'name'   => 'Super Admin',
            'email'  => 'super.admin@example.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        // Organization A (Target UAT Organization)
        $this->orgA = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'submitted_at'      => now()->subDays(2),
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now()->subDay(),
        ]);

        $this->membershipA = OrganizationMembership::create([
            'organization_id'   => $this->orgA->id,
            'user_id'           => $this->coordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now()->subDay(),
            'member_identifier' => 'COORD-001',
        ]);

        // Organization B (Other Tenant)
        $this->orgB = Organization::create([
            'name'              => 'Delta Enterprise Institute',
            'slug'              => 'delta-enterprise-institute',
            'organization_type' => OrganizationType::Company,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'submitted_at'      => now()->subDays(3),
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now()->subDays(2),
        ]);

        $this->membershipB = OrganizationMembership::create([
            'organization_id'   => $this->orgB->id,
            'user_id'           => $this->otherCoordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now()->subDays(2),
            'member_identifier' => 'COORD-002',
        ]);
    }

    /**
     * TEST MEMBER-ROLE-UI-01: Authenticated Coordinator opens Invite Member. Candidate Member is displayed.
     */
    public function test_member_role_ui_01_shows_candidate_member_role_only(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.candidates', $this->orgA->slug));

        $response->assertStatus(200);
        $response->assertSee('Candidate Member');
        $response->assertSee('Invite New Member');
    }

    /**
     * TEST MEMBER-ROLE-UI-02: Coordinator role option is not rendered in select dropdown.
     */
    public function test_member_role_ui_02_coordinator_option_not_rendered(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.candidates', $this->orgA->slug));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('<option value="coordinator">', $content);
    }

    /**
     * TEST MEMBER-ROLE-UI-03: Organization Admin option is not rendered in select dropdown.
     */
    public function test_member_role_ui_03_admin_option_not_rendered(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.candidates', $this->orgA->slug));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('<option value="admin">', $content);
    }

    /**
     * TEST MEMBER-ROLE-UI-04: Owner option is not rendered in select dropdown.
     */
    public function test_member_role_ui_04_owner_option_not_rendered(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('organization.candidates', $this->orgA->slug));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('<option value="owner">', $content);
    }

    /**
     * TEST MEMBER-ROLE-SEC-01: Coordinator can invite Candidate Member.
     */
    public function test_member_role_sec_01_coordinator_can_invite_candidate_member(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.candidates.invite', $this->orgA->slug), [
                'email'             => 'candidate.test@example.org',
                'member_identifier' => 'STU-1001',
                'department'        => 'Computer Science',
            ]);

        $response->assertRedirect(route('organization.candidates', $this->orgA->slug));
        $response->assertSessionHas('status');

        $invitation = OrganizationInvitation::where('organization_id', $this->orgA->id)
            ->where('email', 'candidate.test@example.org')
            ->first();

        $this->assertNotNull($invitation);
        $this->assertEquals(MembershipRole::Member, $invitation->intended_role);
        $this->assertEquals(InvitationStatus::Pending, $invitation->status);
        $this->assertEquals('STU-1001', $invitation->member_identifier);
        $this->assertEquals('Computer Science', $invitation->department);
    }

    /**
     * TEST MEMBER-ROLE-SEC-02: Coordinator cannot create Coordinator invitation via crafted request.
     */
    public function test_member_role_sec_02_coordinator_cannot_create_coordinator_invitation(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.candidates.invite', $this->orgA->slug), [
                'email'         => 'hacked.coord@example.org',
                'intended_role' => 'coordinator',
            ]);

        $response->assertSessionHasErrors(['intended_role']);

        $invitation = OrganizationInvitation::where('email', 'hacked.coord@example.org')->first();
        $this->assertNull($invitation);
    }

    /**
     * TEST MEMBER-ROLE-SEC-03: Coordinator cannot create Organization Admin invitation via crafted request.
     */
    public function test_member_role_sec_03_coordinator_cannot_create_admin_invitation(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.candidates.invite', $this->orgA->slug), [
                'email'         => 'hacked.admin@example.org',
                'intended_role' => 'admin',
            ]);

        $response->assertSessionHasErrors(['intended_role']);

        $invitation = OrganizationInvitation::where('email', 'hacked.admin@example.org')->first();
        $this->assertNull($invitation);
    }

    /**
     * TEST MEMBER-ROLE-SEC-04: Coordinator cannot create Owner invitation via crafted request.
     */
    public function test_member_role_sec_04_coordinator_cannot_create_owner_invitation(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.candidates.invite', $this->orgA->slug), [
                'email'         => 'hacked.owner@example.org',
                'intended_role' => 'owner',
            ]);

        $response->assertSessionHasErrors(['intended_role']);

        $invitation = OrganizationInvitation::where('email', 'hacked.owner@example.org')->first();
        $this->assertNull($invitation);
    }

    /**
     * TEST MEMBER-ROLE-SEC-05: Crafted role alias payload creates no invitation.
     */
    public function test_member_role_sec_05_crafted_role_alias_payload_creates_no_invitation(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.candidates.invite', $this->orgA->slug), [
                'email' => 'tampered.alias@example.org',
                'role'  => 'coordinator',
            ]);

        $response->assertSessionHasErrors(['role']);

        $invitation = OrganizationInvitation::where('email', 'tampered.alias@example.org')->first();
        $this->assertNull($invitation);
    }

    /**
     * TEST MEMBER-ACC-01 & 02 & 03: Candidate Member invitation acceptance creates ACTIVE membership with Member role only.
     */
    public function test_member_acc_01_candidate_invitation_acceptance_creates_active_member_role(): void
    {
        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id'   => $this->orgA->id,
            'email'             => 'new.candidate@example.org',
            'intended_role'     => MembershipRole::Member,
            'member_identifier' => 'CAND-001',
            'department'        => 'Engineering',
            'invited_by'        => $this->coordinator->id,
            'expires_at'        => now()->addDays(7),
        ]);
        $plainToken = $invitationData['token'];

        // Register and accept invitation
        $response = $this->post(route('invitations.process', $plainToken), [
            'name'                  => 'New Candidate Student',
            'email'                 => 'new.candidate@example.org',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('candidate.portal'));

        $user = User::where('email', 'new.candidate@example.org')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('student'));
        $this->assertFalse($user->hasRole('organization-coordinator'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('super-admin'));

        $membership = OrganizationMembership::where('organization_id', $this->orgA->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals(MembershipRole::Member, $membership->role);
        $this->assertEquals(MembershipStatus::Active, $membership->status);
        $this->assertEquals('CAND-001', $membership->member_identifier);
        $this->assertEquals('Engineering', $membership->department);
    }

    /**
     * TEST MEMBER-ACC-04 & 05: Active members count semantics: pending does not increment, accepted increments by 1.
     */
    public function test_member_acc_04_active_members_kpi_counts_correctly(): void
    {
        // Initial active members: 1 (coordinator)
        $this->assertEquals(1, $this->orgA->activeMemberships()->count());

        // Step 1: Create pending invitation
        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'kpi.candidate@example.org',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinator->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $plainToken = $invitationData['token'];

        // Pending invitation does NOT increment active members
        $this->assertEquals(1, $this->orgA->fresh()->activeMemberships()->count());

        // Step 2: Accept invitation
        $this->post(route('invitations.process', $plainToken), [
            'name'                  => 'KPI Candidate',
            'email'                 => 'kpi.candidate@example.org',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // After acceptance, active members increments to 2
        $this->assertEquals(2, $this->orgA->fresh()->activeMemberships()->count());
    }

    /**
     * TEST MEMBER-USER-01: Existing candidate user is reused when accepting invitation.
     */
    public function test_member_user_01_existing_candidate_user_is_reused(): void
    {
        $existingCandidate = User::factory()->create([
            'name'     => 'Existing Candidate',
            'email'    => 'existing.student@example.org',
            'password' => Hash::make('Secret123!'),
            'status'   => 'active',
        ]);
        $existingCandidate->assignRole('student');

        $userCountBefore = User::count();

        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'existing.student@example.org',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinator->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $plainToken = $invitationData['token'];

        // Existing user logs in to accept
        $response = $this->post(route('invitations.process', $plainToken), [
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('candidate.portal'));

        // No new user created
        $this->assertEquals($userCountBefore, User::count());

        // Membership created for existing user
        $membership = OrganizationMembership::where('organization_id', $this->orgA->id)
            ->where('user_id', $existingCandidate->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertEquals(MembershipRole::Member, $membership->role);
    }

    /**
     * TEST MEMBER-TENANT-01: Coordinator cannot invite member into another Organization.
     */
    public function test_member_tenant_01_coordinator_cannot_invite_into_other_organization(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.candidates.invite', $this->orgB->slug), [
                'email' => 'cross.tenant@example.org',
            ]);

        // org.context rejects cross-tenant requests
        $this->assertTrue(in_array($response->status(), [302, 403, 404]));

        $invitation = OrganizationInvitation::where('organization_id', $this->orgB->id)
            ->where('email', 'cross.tenant@example.org')
            ->first();

        $this->assertNull($invitation);
    }

    /**
     * TEST MEMBER-REG-01: RA Primary Coordinator onboarding workflow still creates COORDINATOR role.
     */
    public function test_member_reg_01_ra_primary_coordinator_invitation_creates_coordinator_role(): void
    {
        $newOrg = Organization::create([
            'name'              => 'Epsilon Tech Institute',
            'slug'              => 'epsilon-tech-institute',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now(),
        ]);

        $response = $this->actingAs($this->adminRA)
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.invite-coordinator', $newOrg->id), [
                'coordinator_email' => 'primary.coord@epsilon.edu',
            ]);

        $response->assertRedirect(route('admin.organizations.index'));

        $invitation = OrganizationInvitation::where('organization_id', $newOrg->id)
            ->where('email', 'primary.coord@epsilon.edu')
            ->first();

        $this->assertNotNull($invitation);
        $this->assertEquals(MembershipRole::Coordinator, $invitation->intended_role);
    }
}
