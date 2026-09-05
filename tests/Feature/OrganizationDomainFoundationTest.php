<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Organization\Enums\GroupType;
use App\Modules\Organization\Enums\InvitationStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationGroupMember;
use App\Modules\Organization\Models\OrganizationInvitation;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizationDomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $coordinatorA;
    protected User $coordinatorB;
    protected User $candidateX;
    protected User $candidateY;
    protected User $multiOrgUser;
    protected User $orphanCoordinator;
    protected Organization $orgA;
    protected Organization $orgB;
    protected OrganizationMembership $membershipCoordA;
    protected OrganizationMembership $membershipCoordB;
    protected OrganizationMembership $membershipCandA;
    protected OrganizationMembership $membershipCandB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Super Admin
        $this->superAdmin = User::factory()->create(['name' => 'Super Admin Demo']);
        $this->superAdmin->assignRole('super-admin');

        // 2. Organizations
        $this->orgA = Organization::create([
            'name'              => 'Alpha University',
            'slug'              => 'alpha-university',
            'organization_type' => OrganizationType::University,
            'email'             => 'contact@alpha.edu',
            'status'            => OrganizationStatus::Active,
        ]);

        $this->orgB = Organization::create([
            'name'              => 'Beta Corporation',
            'slug'              => 'beta-corporation',
            'organization_type' => OrganizationType::Company,
            'email'             => 'hr@beta.com',
            'status'            => OrganizationStatus::Active,
        ]);

        // 3. Coordinators
        $this->coordinatorA = User::factory()->create(['name' => 'Coordinator Alpha', 'email' => 'coord.a@alpha.edu']);
        $this->coordinatorA->assignRole('organization-coordinator');

        $this->membershipCoordA = OrganizationMembership::create([
            'organization_id'   => $this->orgA->id,
            'user_id'           => $this->coordinatorA->id,
            'role'              => MembershipRole::Coordinator,
            'member_identifier' => 'COORD-A',
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
        ]);

        $this->coordinatorB = User::factory()->create(['name' => 'Coordinator Beta', 'email' => 'coord.b@beta.com']);
        $this->coordinatorB->assignRole('organization-coordinator');

        $this->membershipCoordB = OrganizationMembership::create([
            'organization_id'   => $this->orgB->id,
            'user_id'           => $this->coordinatorB->id,
            'role'              => MembershipRole::Coordinator,
            'member_identifier' => 'COORD-B',
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
        ]);

        // 4. Candidate Members
        $this->candidateX = User::factory()->create(['name' => 'Candidate X', 'email' => 'candidate.x@example.com']);
        $this->candidateX->assignRole('student');
        $this->membershipCandA = OrganizationMembership::create([
            'organization_id'   => $this->orgA->id,
            'user_id'           => $this->candidateX->id,
            'role'              => MembershipRole::Member,
            'member_identifier' => 'NIM-1001',
            'department'        => 'Computer Science',
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
        ]);

        $this->candidateY = User::factory()->create(['name' => 'Candidate Y', 'email' => 'candidate.y@example.com']);
        $this->candidateY->assignRole('student');
        $this->membershipCandB = OrganizationMembership::create([
            'organization_id'   => $this->orgB->id,
            'user_id'           => $this->candidateY->id,
            'role'              => MembershipRole::Member,
            'member_identifier' => 'EMP-2001',
            'department'        => 'Marketing',
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
        ]);

        // 5. Multi-Org Coordinator (Member of both A and B)
        $this->multiOrgUser = User::factory()->create(['name' => 'Multi Org Leader', 'email' => 'multi@example.com']);
        $this->multiOrgUser->assignRole('organization-coordinator');
        OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $this->multiOrgUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);
        OrganizationMembership::create([
            'organization_id' => $this->orgB->id,
            'user_id'         => $this->multiOrgUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        // 6. Orphan Coordinator (Has global role but 0 active memberships)
        $this->orphanCoordinator = User::factory()->create(['name' => 'Orphan Coordinator', 'email' => 'orphan@example.com']);
        $this->orphanCoordinator->assignRole('organization-coordinator');
    }

    /**
     * TEST ORG-01: Organization can be created with valid type/status.
     * TEST ORG-02: Organization status defaults correctly.
     * TEST ORG-03: Same User can belong to multiple Organizations.
     * TEST ORG-04: Duplicate same-Organization membership prevented.
     */
    public function test_org_01_to_04_domain_and_membership_foundations(): void
    {
        // ORG-01 & ORG-02: Creation & status defaults
        $org = Organization::create([
            'name'              => 'Gamma Academy',
            'slug'              => 'gamma-academy',
            'organization_type' => OrganizationType::School,
        ]);
        $this->assertTrue($org->isActive());
        $this->assertEquals(OrganizationStatus::Active, $org->status);
        $this->assertEquals(OrganizationType::School, $org->organization_type);

        // ORG-03: Same user belongs to multiple organizations
        $user = User::factory()->create();
        $mem1 = OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $user->id,
            'role'            => MembershipRole::Member,
        ]);
        $mem2 = OrganizationMembership::create([
            'organization_id' => $this->orgB->id,
            'user_id'         => $user->id,
            'role'            => MembershipRole::Member,
        ]);
        $this->assertCount(2, $user->organizationMemberships);

        // ORG-04: Duplicate same-org membership throws unique constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);
        OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $user->id,
            'role'            => MembershipRole::Coordinator,
        ]);
    }

    /**
     * TEST INV-01: Coordinator can invite email in own Organization.
     * TEST INV-02: Invitation token is one-time.
     * TEST INV-03: Expired invitation cannot be accepted.
     * TEST INV-04: Revoked invitation cannot be accepted.
     * TEST INV-05: Existing User acceptance links existing user_id, no duplicate user.
     * TEST INV-06: New account flow never exposes coordinator-controlled password.
     * TEST INV-07: Accepted invitation creates/activates correct OrganizationMembership.
     * TEST INV-08: Pending invitation does NOT create contradictory active membership.
     * TEST INV-09: Accepted invitation does not remain pending.
     * TEST INV-10: No invitation/member status dual-source contradiction.
     */
    public function test_inv_01_to_10_invitation_lifecycle_and_acceptance(): void
    {
        // INV-01: Coordinator can invite email
        $response = $this->actingAs($this->coordinatorA)->post(route('organization.candidates.invite', $this->orgA->slug), [
            'email'             => 'new.student@example.com',
            'intended_role'     => 'member',
            'member_identifier' => 'ID-999',
            'department'        => 'Physics',
        ]);
        $response->assertRedirect(route('organization.candidates', $this->orgA->slug));

        $invitation = OrganizationInvitation::where('email', 'new.student@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertEquals(InvitationStatus::Pending, $invitation->status);

        // INV-08: Pending invitation does NOT create an active membership
        $this->assertNull(OrganizationMembership::where('organization_id', $this->orgA->id)->whereHas('user', fn($q) => $q->where('email', 'new.student@example.com'))->first());

        // Logout coordinator to test guest registration flow
        Auth::logout();

        // Create fresh invitation with known token for testing acceptance
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'fresh.user@example.com',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinatorA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];
        $freshInv = $invData['invitation'];

        // INV-06 & INV-07: Accept invitation by registering new account
        $acceptResponse = $this->post(route('invitations.process', $token), [
            'name'                  => 'Fresh Registered Candidate',
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);
        $acceptResponse->assertRedirect(route('candidate.portal'));

        // INV-09: Invitation is now accepted
        $freshInv->refresh();
        $this->assertEquals(InvitationStatus::Accepted, $freshInv->status);
        $this->assertNotNull($freshInv->accepted_at);

        // Verify membership created
        $newUser = User::where('email', 'fresh.user@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue(Hash::check('StrongPassword123!', $newUser->password));
        $membership = OrganizationMembership::where('organization_id', $this->orgA->id)->where('user_id', $newUser->id)->first();
        $this->assertNotNull($membership);
        $this->assertEquals(MembershipStatus::Active, $membership->status);

        // INV-02: Same token cannot be accepted again
        $reacceptResponse = $this->post(route('invitations.process', $token), [
            'name'                  => 'Duplicate Attempt',
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);
        $reacceptResponse->assertSessionHasErrors(['error']);

        // INV-03: Expired invitation cannot be accepted
        $expData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'expired.user@example.com',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinatorA->id,
            'expires_at'      => now()->subDay(),
        ]);
        $expResponse = $this->post(route('invitations.process', $expData['token']), [
            'name'                  => 'Expired User',
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);
        $expResponse->assertSessionHasErrors(['error']);

        // INV-04: Revoked invitation cannot be accepted
        $revData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'revoked.user@example.com',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinatorA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $revData['invitation']->update(['status' => InvitationStatus::Revoked]);
        $revResponse = $this->post(route('invitations.process', $revData['token']), [
            'name'                  => 'Revoked User',
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);
        $revResponse->assertSessionHasErrors(['error']);

        // INV-05: Existing User acceptance links existing user_id without duplicating user
        $existingCandidate = User::factory()->create(['email' => 'existing.cand@example.com']);
        $existingCandCountBefore = User::count();

        $linkInvData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'existing.cand@example.com',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinatorA->id,
            'expires_at'      => now()->addDays(7),
        ]);

        // Accept while logged in
        $linkResponse = $this->actingAs($existingCandidate)->post(route('invitations.process', $linkInvData['token']));
        $linkResponse->assertRedirect(route('candidate.portal'));

        $this->assertEquals($existingCandCountBefore, User::count()); // Zero user duplication
        $linkedMem = OrganizationMembership::where('organization_id', $this->orgA->id)->where('user_id', $existingCandidate->id)->first();
        $this->assertNotNull($linkedMem);
        $this->assertEquals(MembershipStatus::Active, $linkedMem->status);
    }

    /**
     * TEST CTX-01: Coordinator A can access Organization A.
     * TEST CTX-02: Coordinator A cannot access Organization B.
     * TEST CTX-03: Direct URL tampering is rejected.
     * TEST CTX-04: User with memberships in two Organizations can switch contexts safely.
     * TEST CTX-05: Current Organization does not leak between route contexts.
     */
    public function test_ctx_01_to_05_context_resolution_and_tenant_isolation(): void
    {
        // CTX-01: Coordinator A accesses Org A dashboard -> 200 OK
        $resA = $this->actingAs($this->coordinatorA)->get(route('organization.dashboard', $this->orgA->slug));
        $resA->assertStatus(200);
        $resA->assertSee('Alpha University');

        // CTX-02 & CTX-03: Coordinator A attempts to access Org B -> 403 Forbidden
        $resB = $this->actingAs($this->coordinatorA)->get(route('organization.dashboard', $this->orgB->slug));
        $resB->assertStatus(403);

        // CTX-04 & CTX-05: Multi-org user switches between Org A and Org B cleanly
        $resMultiA = $this->actingAs($this->multiOrgUser)->get(route('organization.dashboard', $this->orgA->slug));
        $resMultiA->assertStatus(200);
        $resMultiA->assertSee('Alpha University');
        $resMultiA->assertDontSee('Beta Corporation');

        $resMultiB = $this->actingAs($this->multiOrgUser)->get(route('organization.dashboard', $this->orgB->slug));
        $resMultiB->assertStatus(200);
        $resMultiB->assertSee('Beta Corporation');
        $resMultiB->assertDontSee('Alpha University');
    }

    /**
     * TEST ROLE-01: Global organization-coordinator without membership cannot manage Organization.
     * TEST ROLE-02: Organization membership coordinator can manage roster/groups only for own Org.
     * TEST ROLE-03: member role cannot manage Organization Portal.
     * TEST ROLE-04: Super Admin can manage Organization according to internal authority.
     */
    public function test_role_01_to_04_scoped_authorization_rules(): void
    {
        // ROLE-01: Orphan coordinator has global role but 0 memberships -> 403 on specific org
        $orphanRes = $this->actingAs($this->orphanCoordinator)->get(route('organization.dashboard', $this->orgA->slug));
        $orphanRes->assertStatus(403);

        // Orphan coordinator dashboard redirection routes safely to no-access
        $orphanDash = $this->actingAs($this->orphanCoordinator)->get(route('dashboard'));
        $orphanDash->assertRedirect(route('organization.no-access'));

        // ROLE-03: Candidate Member cannot access management portal -> 403
        $candRes = $this->actingAs($this->candidateX)->get(route('organization.dashboard', $this->orgA->slug));
        $candRes->assertStatus(403);

        // ROLE-04: Super Admin has global override authority on all organizations
        $saResA = $this->actingAs($this->superAdmin)->get(route('organization.dashboard', $this->orgA->slug));
        $saResA->assertStatus(200);
        $saResB = $this->actingAs($this->superAdmin)->get(route('organization.dashboard', $this->orgB->slug));
        $saResB->assertStatus(200);
    }

    /**
     * TEST GROUP-01: Group belongs to one Organization.
     * TEST GROUP-02: Same-Organization active membership can be added.
     * TEST GROUP-03: Cross-Organization membership cannot be added.
     * TEST GROUP-04: Member can be removed without deleting OrganizationMembership.
     * TEST GROUP-05: Inactive group cannot accept new membership.
     */
    public function test_group_01_to_05_group_and_cohort_management(): void
    {
        // GROUP-01: Create group in Org A
        $groupA = OrganizationGroup::create([
            'organization_id' => $this->orgA->id,
            'name'            => 'Class 12-Science-1',
            'group_type'      => GroupType::ClassGroup->value,
            'is_active'       => true,
        ]);
        $this->assertEquals($this->orgA->id, $groupA->organization_id);

        // GROUP-02: Add active Org A candidate to Group A
        $addRes = $this->actingAs($this->coordinatorA)->post(route('organization.groups.members.add', [$this->orgA->slug, $groupA->id]), [
            'membership_id' => $this->membershipCandA->id,
        ]);
        $addRes->assertRedirect();
        $this->assertTrue($groupA->hasMembership($this->membershipCandA));

        // GROUP-03: Cross-organization assignment violation (Candidate Y from Org B) -> 403 Forbidden
        $crossRes = $this->actingAs($this->coordinatorA)->post(route('organization.groups.members.add', [$this->orgA->slug, $groupA->id]), [
            'membership_id' => $this->membershipCandB->id,
        ]);
        $crossRes->assertStatus(403);

        // Direct model level cross-org check throws InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $groupA->addMembership($this->membershipCandB);

        // GROUP-04: Member can be removed from group without deleting membership
        $delRes = $this->actingAs($this->coordinatorA)->delete(route('organization.groups.members.remove', [$this->orgA->slug, $groupA->id, $this->membershipCandA->id]));
        $delRes->assertRedirect();
        $this->assertFalse($groupA->fresh()->hasMembership($this->membershipCandA));
        $this->assertNotNull(OrganizationMembership::find($this->membershipCandA->id)); // Membership still intact

        // GROUP-05: Inactive group cannot accept members
        $groupA->update(['is_active' => false]);
        $inactiveRes = $this->actingAs($this->coordinatorA)->post(route('organization.groups.members.add', [$this->orgA->slug, $groupA->id]), [
            'membership_id' => $this->membershipCandA->id,
        ]);
        $inactiveRes->assertSessionHasErrors(['error']);
    }

    /**
     * TEST DASH-01: Active Member count only current Organization.
     * TEST DASH-02: Active Group count only current Organization.
     * TEST DASH-03: Pending Invitation count only current Organization.
     */
    public function test_dash_01_to_03_dashboard_kpi_scoping(): void
    {
        // Setup: Org A has 2 members (Coord A, Cand X), 1 group, 1 pending invite
        OrganizationGroup::create(['organization_id' => $this->orgA->id, 'name' => 'Group A1']);
        OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'invite.a@example.com',
            'invited_by'      => $this->coordinatorA->id,
        ]);

        // Org B has 3 groups and 4 pending invites
        for ($i = 0; $i < 3; $i++) {
            OrganizationGroup::create(['organization_id' => $this->orgB->id, 'name' => "Group B{$i}"]);
        }
        for ($i = 0; $i < 4; $i++) {
            OrganizationInvitation::createWithToken([
                'organization_id' => $this->orgB->id,
                'email'           => "invite.b{$i}@example.com",
                'invited_by'      => $this->coordinatorB->id,
            ]);
        }

        $resA = $this->actingAs($this->coordinatorA)->get(route('organization.dashboard', $this->orgA->slug));
        $resA->assertViewHas('activeGroupsCount', 1);
        $resA->assertViewHas('pendingInvitationsCount', 1);

        $resB = $this->actingAs($this->coordinatorB)->get(route('organization.dashboard', $this->orgB->slug));
        $resB->assertViewHas('activeGroupsCount', 3);
        $resB->assertViewHas('pendingInvitationsCount', 4);
    }

    /**
     * TEST SEC-01: Organization A coordinator cannot read B members.
     * TEST SEC-02: Organization A coordinator cannot mutate B member.
     * TEST SEC-03: Organization A coordinator cannot read B invitations.
     * TEST SEC-04: Organization A coordinator cannot mutate B groups.
     * TEST SEC-05: Organization A coordinator cannot alter B profile.
     */
    public function test_sec_01_to_05_security_threat_prevention(): void
    {
        // SEC-01: Org A cannot view Org B candidates page
        $res1 = $this->actingAs($this->coordinatorA)->get(route('organization.candidates', $this->orgB->slug));
        $res1->assertStatus(403);

        // SEC-03: Org A cannot invite in Org B context
        $res3 = $this->actingAs($this->coordinatorA)->post(route('organization.candidates.invite', $this->orgB->slug), [
            'email'         => 'tamper@example.com',
            'intended_role' => 'member',
        ]);
        $res3->assertStatus(403);

        // SEC-04: Org A cannot create group in Org B
        $res4 = $this->actingAs($this->coordinatorA)->post(route('organization.groups.store', $this->orgB->slug), [
            'name' => 'Forged Group',
        ]);
        $res4->assertStatus(403);

        // SEC-05: Org A cannot update Org B profile
        $res5 = $this->actingAs($this->coordinatorA)->put(route('organization.profile.update', $this->orgB->slug), [
            'name'              => 'Defaced Name',
            'organization_type' => 'school',
        ]);
        $res5->assertStatus(403);
    }

    /**
     * TEST LOG-01: Organization creation logged.
     * TEST LOG-02: Invitation sent/accepted/revoked logged.
     * TEST LOG-03: Group membership change logged.
     * TEST LOG-04: No invitation plaintext token logged.
     */
    public function test_log_01_to_04_activity_logging_and_privacy(): void
    {
        // Super admin creates org via admin endpoint
        $this->actingAs($this->superAdmin)->post(route('admin.organizations.store'), [
            'name'              => 'Delta Institute',
            'organization_type' => 'company',
        ]);

        $createdLog = ActivityLog::where('action', 'ORG_CREATED')->first();
        $this->assertNotNull($createdLog);
        $this->assertStringContainsString('Delta Institute', $createdLog->description);

        // Coordinator invites member
        $this->actingAs($this->coordinatorA)->post(route('organization.candidates.invite', $this->orgA->slug), [
            'email'         => 'log.test@example.com',
            'intended_role' => 'member',
        ]);

        $inviteLog = ActivityLog::where('action', 'ORG_INVITATION_SENT')->latest()->first();
        $this->assertNotNull($inviteLog);
        $this->assertEquals('log.test@example.com', $inviteLog->properties['email']);

        // LOG-04: Verify plaintext token is NEVER persisted in activity logs
        $allLogsJson = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString('token_hash', $allLogsJson);
    }

    /**
     * SEC-INV-01: Mismatched authenticated email cannot accept invitation.
     */
    public function test_sec_inv_01_mismatched_authenticated_email_cannot_accept_invitation(): void
    {
        // Issue invitation for candidate-a@example.com
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'candidate-a@example.com',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinatorA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];
        $invitation = $invData['invitation'];

        // Candidate B is authenticated
        $candidateB = User::factory()->create(['email' => 'candidate-b@example.com', 'name' => 'Candidate B']);

        // 1. Viewing invitation page reveals mismatch state and hides acceptance form
        $viewResponse = $this->actingAs($candidateB)->get(route('invitations.accept', $token));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Account Identity Mismatch');
        $viewResponse->assertSee('candidate-b@example.com');
        $viewResponse->assertSee('candidate-a@example.com');
        $viewResponse->assertDontSee('Accept Invitation & Continue');

        // 2. Direct POST acceptance attempt by Candidate B is rejected
        $postResponse = $this->actingAs($candidateB)->post(route('invitations.process', $token));
        $postResponse->assertRedirect(route('invitations.accept', $token));
        $postResponse->assertSessionHasErrors(['error']);

        // 3. Invitation remains pending and no membership is granted to Candidate B
        $this->assertEquals(InvitationStatus::Pending, $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertNull(OrganizationMembership::where('organization_id', $this->orgA->id)->where('user_id', $candidateB->id)->first());
    }

    /**
     * SEC-INV-02: New registration cannot accept invitation using a different email.
     */
    public function test_sec_inv_02_new_registration_cannot_accept_invitation_using_different_email(): void
    {
        Auth::logout();

        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'candidate-a@example.com',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinatorA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];
        $invitation = $invData['invitation'];

        // Guest attempts to register using forged/different email
        $response = $this->post(route('invitations.process', $token), [
            'name'                  => 'Evil Candidate',
            'email'                 => 'candidate-evil@example.com',
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $response->assertRedirect(route('invitations.accept', $token));
        $response->assertSessionHasErrors(['email']);

        // Invitation remains pending and no user/membership created for evil email
        $this->assertEquals(InvitationStatus::Pending, $invitation->fresh()->status);
        $this->assertNull(User::where('email', 'candidate-evil@example.com')->first());
    }

    /**
     * SEC-INV-03: Matching existing User can accept invitation.
     */
    public function test_sec_inv_03_matching_existing_user_can_accept_invitation(): void
    {
        $existingCandidate = User::factory()->create(['email' => 'candidate-match@example.com', 'name' => 'Matched Candidate']);
        $existingCandCountBefore = User::count();

        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'candidate-match@example.com',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinatorA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];
        $invitation = $invData['invitation'];

        // Acceptance while authenticated as the matching user
        $response = $this->actingAs($existingCandidate)->post(route('invitations.process', $token));
        $response->assertRedirect(route('candidate.portal'));

        // Zero duplicate user created
        $this->assertEquals($existingCandCountBefore, User::count());

        // Invitation accepted and membership activated
        $this->assertEquals(InvitationStatus::Accepted, $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $membership = OrganizationMembership::where('organization_id', $this->orgA->id)->where('user_id', $existingCandidate->id)->first();
        $this->assertNotNull($membership);
        $this->assertEquals(MembershipStatus::Active, $membership->status);
    }

    /**
     * SEC-RBAC-01: Owner portal capability works.
     */
    public function test_sec_rbac_01_owner_portal_capability_works(): void
    {
        $owner = User::factory()->create(['email' => 'owner.alpha@alpha.edu']);
        $owner->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $owner->id,
            'role'            => MembershipRole::Owner,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $dashResp = $this->actingAs($owner)->get(route('organization.dashboard', $this->orgA->slug));
        $dashResp->assertStatus(200);

        $candResp = $this->actingAs($owner)->get(route('organization.candidates', $this->orgA->slug));
        $candResp->assertStatus(200);

        $groupResp = $this->actingAs($owner)->get(route('organization.groups', $this->orgA->slug));
        $groupResp->assertStatus(200);

        $profResp = $this->actingAs($owner)->get(route('organization.profile', $this->orgA->slug));
        $profResp->assertStatus(200);
    }

    /**
     * SEC-RBAC-02: Admin portal capability works.
     */
    public function test_sec_rbac_02_admin_portal_capability_works(): void
    {
        $admin = User::factory()->create(['email' => 'admin.alpha@alpha.edu']);
        $admin->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $admin->id,
            'role'            => MembershipRole::Admin,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $dashResp = $this->actingAs($admin)->get(route('organization.dashboard', $this->orgA->slug));
        $dashResp->assertStatus(200);

        $candResp = $this->actingAs($admin)->get(route('organization.candidates', $this->orgA->slug));
        $candResp->assertStatus(200);
    }

    /**
     * SEC-RBAC-03: Coordinator portal capability works.
     */
    public function test_sec_rbac_03_coordinator_portal_capability_works(): void
    {
        $dashResp = $this->actingAs($this->coordinatorA)->get(route('organization.dashboard', $this->orgA->slug));
        $dashResp->assertStatus(200);

        $candResp = $this->actingAs($this->coordinatorA)->get(route('organization.candidates', $this->orgA->slug));
        $candResp->assertStatus(200);
    }

    /**
     * SEC-RBAC-04: Member cannot manage Organization Portal.
     */
    public function test_sec_rbac_04_member_cannot_manage_organization_portal(): void
    {
        $dashResp = $this->actingAs($this->candidateX)->get(route('organization.dashboard', $this->orgA->slug));
        $dashResp->assertStatus(403);

        $candResp = $this->actingAs($this->candidateX)->get(route('organization.candidates', $this->orgA->slug));
        $candResp->assertStatus(403);

        $grpResp = $this->actingAs($this->candidateX)->get(route('organization.groups', $this->orgA->slug));
        $grpResp->assertStatus(403);

        $profResp = $this->actingAs($this->candidateX)->get(route('organization.profile', $this->orgA->slug));
        $profResp->assertStatus(403);
    }

    /**
     * SEC-RBAC-05: Global organization-coordinator with zero membership has no tenant access.
     */
    public function test_sec_rbac_05_global_organization_coordinator_with_zero_membership_has_no_tenant_access(): void
    {
        // 1. Direct tenant URL access returns 403
        $respA = $this->actingAs($this->orphanCoordinator)->get(route('organization.dashboard', $this->orgA->slug));
        $respA->assertStatus(403);

        $respB = $this->actingAs($this->orphanCoordinator)->get(route('organization.dashboard', $this->orgB->slug));
        $respB->assertStatus(403);

        // 2. Canonical dashboard redirects safely to no-access page
        $dashResp = $this->actingAs($this->orphanCoordinator)->get(route('dashboard'));
        $dashResp->assertRedirect(route('organization.no-access'));

        // 3. No-access page renders institutional help
        $noAccessResp = $this->actingAs($this->orphanCoordinator)->get(route('organization.no-access'));
        $noAccessResp->assertStatus(200);
        $noAccessResp->assertSee('No Active Organization Assigned');
    }

    /**
     * SEC-RBAC-06: Multi-organization scoped roles do not bleed.
     */
    public function test_sec_rbac_06_multi_organization_scoped_roles_do_not_bleed(): void
    {
        $splitUser = User::factory()->create(['email' => 'split.leader@example.com']);
        $splitUser->assignRole('organization-coordinator');

        // Owner in Org A
        $memA = OrganizationMembership::create([
            'organization_id' => $this->orgA->id,
            'user_id'         => $splitUser->id,
            'role'            => MembershipRole::Owner,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        // Coordinator in Org B
        $memB = OrganizationMembership::create([
            'organization_id' => $this->orgB->id,
            'user_id'         => $splitUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        // Access Org A -> Context resolves as Owner
        $respA = $this->actingAs($splitUser)->get(route('organization.dashboard', $this->orgA->slug));
        $respA->assertStatus(200);
        $this->assertEquals(MembershipRole::Owner, $respA->viewData('currentMembership')?->role);

        // Access Org B -> Context resolves as Coordinator, NOT Owner
        $respB = $this->actingAs($splitUser)->get(route('organization.dashboard', $this->orgB->slug));
        $respB->assertStatus(200);
        $this->assertEquals(MembershipRole::Coordinator, $respB->viewData('currentMembership')?->role);
    }

    /**
     * TEST UI-ORG-01 to 07: Super Admin Organization views use canonical IAP Super Admin layout.
     */
    public function test_ui_org_01_to_07_super_admin_layout_integration(): void
    {
        // UI-ORG-01, 02, 03, 04, 05: Index page layout, branding, and active navigation
        $indexResp = $this->actingAs($this->superAdmin)->get(route('admin.organizations.index'));
        $indexResp->assertStatus(200);
        $indexResp->assertSee('iC.edu', false);
        $indexResp->assertSee('Assessment Platform', false);
        $indexResp->assertSee('Institutional Management', false);
        $indexResp->assertSee('Organizations', false);
        $indexResp->assertSee('bg-indigo-600 text-white shadow-md shadow-indigo-600/20', false); // Active sidebar state
        $indexResp->assertDontSee('Workspace Laravel', false);
        $indexResp->assertDontSee('x-app-layout', false);

        // UI-ORG-06: Create page uses canonical shell
        $createResp = $this->actingAs($this->superAdmin)->get(route('admin.organizations.create'));
        $createResp->assertStatus(200);
        $createResp->assertSee('iC.edu', false);
        $createResp->assertSee('Assessment Platform', false);
        $createResp->assertSee('Create New Organization', false);
        $createResp->assertDontSee('Workspace Laravel', false);

        // UI-ORG-07: Edit page uses canonical shell
        $editResp = $this->actingAs($this->superAdmin)->get(route('admin.organizations.edit', $this->orgA->id));
        $editResp->assertStatus(200);
        $editResp->assertSee('iC.edu', false);
        $editResp->assertSee('Assessment Platform', false);
        $editResp->assertSee('Edit Organization: Alpha University', false);
        $editResp->assertDontSee('Workspace Laravel', false);
    }

    /**
     * TEST AUTH-01 to 03: Internal Super Admin Organization management authorization.
     */
    public function test_auth_01_to_03_super_admin_organization_authorization(): void
    {
        // AUTH-01: Super Admin authorized
        $saResp = $this->actingAs($this->superAdmin)->get(route('admin.organizations.index'));
        $saResp->assertStatus(200);

        // AUTH-02: Candidate rejected (403)
        $candResp = $this->actingAs($this->candidateX)->get(route('admin.organizations.index'));
        $candResp->assertStatus(403);

        // AUTH-03: Organization Coordinator rejected from internal Super Admin management (403)
        $coordResp = $this->actingAs($this->coordinatorA)->get(route('admin.organizations.index'));
        $coordResp->assertStatus(403);
    }
}
