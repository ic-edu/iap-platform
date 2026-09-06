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
     * TEST INV-LIFE-01: Pending Coordinator invitation is visible on RA directory.
     */
    public function test_inv_life_01_pending_coordinator_invitation_visibility(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'dean.alpha@example.edu',
            'intended_role'   => MembershipRole::Owner,
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
            'role'            => MembershipRole::Owner,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.organizations.index'));
        $response->assertStatus(200);
        $response->assertSee('Dr. Jane Coordinator');
        $response->assertSee('jane.coord@example.edu');
        $response->assertSee('Active');
        $response->assertSee('Coord Active');
    }

    /**
     * TEST INV-LIFE-03: Expired Coordinator invitation shows expired status and resend.
     */
    public function test_inv_life_03_expired_invitation_visibility(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'expired.coord@example.edu',
            'intended_role'   => MembershipRole::Owner,
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
            'intended_role'   => MembershipRole::Owner,
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
            'intended_role'   => MembershipRole::Owner,
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
            'role'            => MembershipRole::Owner,
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
     * TEST INV-RESEND-01 to 03: RA resend rotates token, refreshes expiry, cannot resend accepted.
     */
    public function test_inv_resend_lifecycle_and_invariants(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'coordinator.resend@example.edu',
            'intended_role'   => MembershipRole::Owner,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDay(),
        ]);
        $invitation = $invData['invitation'];
        $oldToken = $invData['token'];
        $oldHash = $invitation->token_hash;

        // Resend
        $resendResp = $this->actingAs($this->adminRA)->post(route('admin.organizations.invitations.resend', [
            'organization' => $this->activeOrg->id,
            'invitation'   => $invitation->id,
        ]));

        $resendResp->assertRedirect();
        $resendResp->assertSessionHas('status');
        $resendResp->assertSessionHas('invitation_url');

        $invitation->refresh();
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
     * TEST INV-REVOKE-01 to 03: RA revoke marks status revoked and blocks acceptance.
     */
    public function test_inv_revoke_lifecycle_and_invariants(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'coordinator.revoke@example.edu',
            'intended_role'   => MembershipRole::Owner,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $invitation = $invData['invitation'];
        $token = $invData['token'];

        // Revoke
        $revokeResp = $this->actingAs($this->adminRA)->post(route('admin.organizations.invitations.revoke', [
            'organization' => $this->activeOrg->id,
            'invitation'   => $invitation->id,
        ]));

        $revokeResp->assertRedirect();
        $revokeResp->assertSessionHas('status');

        $invitation->refresh();
        $this->assertEquals(InvitationStatus::Revoked, $invitation->status);

        // Attempting to accept revoked invitation fails
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
     * TEST INV-ACC-01 to 02: Active members remain 0 while pending, increments to 1 upon acceptance.
     */
    public function test_inv_acc_active_member_count_and_role_grant(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'new.coordinator@example.edu',
            'intended_role'   => MembershipRole::Owner,
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
        $this->assertEquals(MembershipRole::Owner, $membership->role);
        $this->assertEquals(MembershipStatus::Active, $membership->status);
    }
}
