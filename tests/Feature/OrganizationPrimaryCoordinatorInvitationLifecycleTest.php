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

class OrganizationPrimaryCoordinatorInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminRA;
    protected User $superAdmin;
    protected Organization $activeOrg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminRA = User::factory()->create([
            'email'  => 'admin.ra@example.com',
            'status' => 'active',
        ]);
        $this->adminRA->assignRole('admin');

        $this->superAdmin = User::factory()->create([
            'email'  => 'super.admin@example.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super-admin');

        $this->activeOrg = Organization::create([
            'name'              => 'Alpha Innovation University',
            'slug'              => 'alpha-innovation-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now(),
        ]);
    }

    /**
     * TEST COORD-ROLE-01 & 02: Invite Primary Coordinator creates invitation with intended_role = coordinator and never owner.
     */
    public function test_coord_role_01_and_02_invite_primary_coordinator_sets_coordinator_role(): void
    {
        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.invite-coordinator', $this->activeOrg->id), [
            'coordinator_email' => 'primary.coord@example.edu',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $invitation = OrganizationInvitation::where('email', 'primary.coord@example.edu')->first();
        $this->assertNotNull($invitation);
        $this->assertEquals(MembershipRole::Coordinator, $invitation->intended_role);
        $this->assertNotEquals(MembershipRole::Owner, $invitation->intended_role);
        $this->assertEquals(InvitationStatus::Pending, $invitation->status);
    }

    /**
     * TEST COORD-ROLE-03: Crafted request cannot elevate intended role to owner.
     */
    public function test_coord_role_03_crafted_request_cannot_elevate_role_to_owner(): void
    {
        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.invite-coordinator', $this->activeOrg->id), [
            'coordinator_email' => 'exploit.attempt@example.edu',
            'intended_role'     => 'owner',
            'role'              => 'owner',
            'is_owner'          => true,
        ]);

        $response->assertRedirect();

        $invitation = OrganizationInvitation::where('email', 'exploit.attempt@example.edu')->first();
        $this->assertNotNull($invitation);
        $this->assertEquals(MembershipRole::Coordinator, $invitation->intended_role);
        $this->assertNotEquals(MembershipRole::Owner, $invitation->intended_role);
    }

    /**
     * TEST COORD-ROLE-04: Pending invitation displays as COORDINATOR in RA directory and modal.
     */
    public function test_coord_role_04_invitation_displays_as_coordinator_in_directory(): void
    {
        OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'dean.alpha@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.organizations.index'));
        $response->assertStatus(200);
        $response->assertSee('Alpha Innovation University');
        $response->assertSee('dean.alpha@example.edu');
        $response->assertSee('Pending');
        $response->assertSee('Coord Pending');
        $response->assertSee('Primary Coordinator Governance');
        $response->assertSee('ROLE: COORDINATOR');
        $response->assertDontSee('ROLE: OWNER');
        $response->assertSee('Resend / Extend (7 Days)');
        $response->assertSee('Revoke Invitation');
    }

    /**
     * TEST INV-LIFE-02: Active Coordinator membership visibility on directory.
     */
    public function test_inv_life_02_active_coordinator_visibility(): void
    {
        $coordUser = User::factory()->create([
            'name'   => 'Dr. Jane Coordinator',
            'email'  => 'jane.coord@example.edu',
            'status' => 'active',
        ]);
        $coordUser->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->activeOrg->id,
            'user_id'         => $coordUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.organizations.index'));
        $response->assertStatus(200);
        $response->assertSee('Dr. Jane Coordinator');
        $response->assertSee('jane.coord@example.edu');
        $response->assertSee('Active');
        $response->assertSee('Coord Active');
        $response->assertSee('ROLE: COORDINATOR');
    }

    /**
     * TEST INV-LIFE-03: Expired Coordinator invitation shows expired status and resend.
     */
    public function test_inv_life_03_expired_invitation_visibility(): void
    {
        OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'expired.coord@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->subDay(),
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.organizations.index'));
        $response->assertStatus(200);
        $response->assertSee('expired.coord@example.edu');
        $response->assertSee('Expired');
        $response->assertSee('Coord Expired');
        $response->assertSee('Resend Invitation (7 Days)');
    }

    /**
     * TEST INV-LIFE-04: Revoked Coordinator invitation shows revoked status.
     */
    public function test_inv_life_04_revoked_invitation_visibility(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'revoked.coord@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $invData['invitation']->update(['status' => InvitationStatus::Revoked]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.organizations.index'));
        $response->assertStatus(200);
        $response->assertSee('revoked.coord@example.edu');
        $response->assertSee('Revoked');
        $response->assertSee('Coord Revoked');
        $response->assertSee('Re-invite revoked.coord@example.edu');
    }

    /**
     * TEST INV-IDEMP-01: Duplicate invitation blocked if a pending invitation exists.
     */
    public function test_inv_idemp_01_duplicate_invitation_blocked_when_pending(): void
    {
        OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'leader1@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.invite-coordinator', $this->activeOrg->id), [
            'coordinator_email' => 'leader2@example.edu',
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(1, OrganizationInvitation::where('organization_id', $this->activeOrg->id)->count());
    }

    /**
     * TEST INV-IDEMP-02: Invitation blocked if an active coordinator membership already exists.
     */
    public function test_inv_idemp_02_invitation_blocked_when_coordinator_active(): void
    {
        $existingCoord = User::factory()->create(['email' => 'current.owner@example.edu']);
        OrganizationMembership::create([
            'organization_id' => $this->activeOrg->id,
            'user_id'         => $existingCoord->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.invite-coordinator', $this->activeOrg->id), [
            'coordinator_email' => 'new.owner@example.edu',
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(0, OrganizationInvitation::where('organization_id', $this->activeOrg->id)->count());
    }

    /**
     * TEST COORD-LIFE-01: Resend preserves intended_role = coordinator.
     */
    public function test_coord_life_01_resend_preserves_coordinator_role(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'coordinator.resend@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDay(),
        ]);
        $invitation = $invData['invitation'];
        $oldToken = $invData['token'];
        $oldHash = $invitation->token_hash;

        $resendResp = $this->actingAs($this->adminRA)->post(route('admin.organizations.invitations.resend', [
            'organization' => $this->activeOrg->id,
            'invitation'   => $invitation->id,
        ]));

        $resendResp->assertRedirect();
        $resendResp->assertSessionHas('status');
        $resendResp->assertSessionHas('invitation_url');

        $invitation->refresh();
        $this->assertEquals(MembershipRole::Coordinator, $invitation->intended_role);
        $this->assertNotEquals($oldHash, $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->gt(now()->addDays(6)));

        // Old token fails
        $oldAcceptResp = $this->get(route('invitations.accept', $oldToken));
        $oldAcceptResp->assertSee('invalid or has already been used');

        // Cannot resend accepted invitation
        $invitation->update(['status' => InvitationStatus::Accepted]);
        $failResend = $this->actingAs($this->adminRA)->post(route('admin.organizations.invitations.resend', [
            'organization' => $this->activeOrg->id,
            'invitation'   => $invitation->id,
        ]));
        $failResend->assertSessionHasErrors(['error']);
    }

    /**
     * TEST COORD-LIFE-02: Revoke marks status revoked and blocks acceptance.
     */
    public function test_coord_life_02_revoke_lifecycle_and_invariants(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'coordinator.revoke@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $invitation = $invData['invitation'];
        $token = $invData['token'];

        $revokeResp = $this->actingAs($this->adminRA)->post(route('admin.organizations.invitations.revoke', [
            'organization' => $this->activeOrg->id,
            'invitation'   => $invitation->id,
        ]));

        $revokeResp->assertRedirect();
        $revokeResp->assertSessionHas('status');

        $invitation->refresh();
        $this->assertEquals(InvitationStatus::Revoked, $invitation->status);

        $acceptResp = $this->get(route('invitations.accept', $token));
        $acceptResp->assertSee('revoked');

        $processResp = $this->post(route('invitations.process', $token), [
            'name'                  => 'Revoked User',
            'password'              => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
        ]);
        $processResp->assertSessionHasErrors(['error']);
    }

    /**
     * TEST COORD-ACC-01 to 04: Primary Coordinator acceptance creates coordinator membership, increments active members, grants portal role.
     */
    public function test_coord_acc_01_to_04_acceptance_creates_coordinator_membership(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'new.coordinator@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];

        // Before acceptance: active members = 0
        $this->assertEquals(0, $this->activeOrg->activeMemberships()->count());

        // Process acceptance (new user self-sets password)
        Auth::logout();
        $acceptResp = $this->post(route('invitations.process', $token), [
            'name'                  => 'Coordinator Alex',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $acceptResp->assertRedirect(route('organization.dashboard', $this->activeOrg->slug));

        // After acceptance: active members = 1
        $this->assertEquals(1, $this->activeOrg->activeMemberships()->count());

        $createdUser = User::where('email', 'new.coordinator@example.edu')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('SecurePass123!', $createdUser->password));
        $this->assertTrue($createdUser->hasRole('organization-coordinator'));

        $membership = OrganizationMembership::where('organization_id', $this->activeOrg->id)
            ->where('user_id', $createdUser->id)
            ->first();
        $this->assertNotNull($membership);
        $this->assertEquals(MembershipRole::Coordinator, $membership->role);
        $this->assertNotEquals(MembershipRole::Owner, $membership->role);
        $this->assertEquals(MembershipStatus::Active, $membership->status);
    }

    /**
     * TEST COORD-PERM-01 to 05: Coordinator authority vs RA, SA, and cross-tenant boundaries.
     */
    public function test_coord_perm_boundaries_and_isolation(): void
    {
        $coordUser = User::factory()->create([
            'email'  => 'operational.coord@example.edu',
            'status' => 'active',
        ]);
        $coordUser->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->activeOrg->id,
            'user_id'         => $coordUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $orgOther = Organization::create([
            'name'              => 'Other University',
            'slug'              => 'other-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
        ]);

        // COORD-PERM-01: Coordinator can access Portal Dashboard & Roster in own org
        $dashResp = $this->actingAs($coordUser)->get(route('organization.dashboard', $this->activeOrg->slug));
        $dashResp->assertStatus(200);

        $candResp = $this->actingAs($coordUser)->get(route('organization.candidates', $this->activeOrg->slug));
        $candResp->assertStatus(200);

        // COORD-PERM-02: Coordinator cannot access other Organization portal (cross-tenant 403)
        $otherResp = $this->actingAs($coordUser)->get(route('organization.dashboard', $orgOther->slug));
        $otherResp->assertStatus(403);

        // COORD-PERM-03: Coordinator cannot access RA directory
        $raResp = $this->actingAs($coordUser)->get(route('admin.organizations.index'));
        $raResp->assertStatus(403);

        // COORD-PERM-04: Coordinator cannot access SA approvals
        $saResp = $this->actingAs($coordUser)->get(route('admin.approvals.organizations'));
        $saResp->assertStatus(403);
    }

    /**
     * TEST COORD-MULTI-01: Multi-organization role context isolation.
     */
    public function test_coord_multi_01_multi_org_role_isolation(): void
    {
        $multiUser = User::factory()->create(['email' => 'multi.user@example.edu']);
        $multiUser->assignRole('organization-coordinator');

        $orgB = Organization::create([
            'name'              => 'Beta Tech Institute',
            'slug'              => 'beta-tech-institute',
            'organization_type' => OrganizationType::Company,
            'status'            => OrganizationStatus::Active,
        ]);

        $orgC = Organization::create([
            'name'              => 'Gamma Academy',
            'slug'              => 'gamma-academy',
            'organization_type' => OrganizationType::School,
            'status'            => OrganizationStatus::Active,
        ]);

        // User is Coordinator in activeOrg, and Owner in orgB
        $memA = OrganizationMembership::create([
            'organization_id' => $this->activeOrg->id,
            'user_id'         => $multiUser->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $memB = OrganizationMembership::create([
            'organization_id' => $orgB->id,
            'user_id'         => $multiUser->id,
            'role'            => MembershipRole::Owner,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        // In activeOrg: can access as Coordinator
        $respA = $this->actingAs($multiUser)->get(route('organization.dashboard', $this->activeOrg->slug));
        $respA->assertStatus(200);
        $this->assertEquals(MembershipRole::Coordinator, $memA->role);

        // In orgB: can access as Owner
        $respB = $this->actingAs($multiUser)->get(route('organization.dashboard', $orgB->slug));
        $respB->assertStatus(200);
        $this->assertEquals(MembershipRole::Owner, $memB->role);

        // In orgC (no membership): forbidden 403
        $respC = $this->actingAs($multiUser)->get(route('organization.dashboard', $orgC->slug));
        $respC->assertStatus(403);
    }

    /**
     * TEST COORD-SEC-01: Identity binding prevents cross-account invitation acceptance.
     */
    public function test_coord_sec_01_identity_binding_enforcement(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'designated.coord@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];

        $intruder = User::factory()->create(['email' => 'intruder@example.com']);

        $badAcceptResp = $this->actingAs($intruder)->post(route('invitations.process', $token));
        $badAcceptResp->assertSessionHasErrors(['error']);

        $this->assertEquals(0, $this->activeOrg->activeMemberships()->count());
    }
}
