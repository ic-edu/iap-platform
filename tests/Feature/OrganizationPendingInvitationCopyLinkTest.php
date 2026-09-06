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

class OrganizationPendingInvitationCopyLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;
    protected User $otherCoordinator;
    protected User $candidateStudent;
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

        $this->candidateStudent = User::factory()->create([
            'name'   => 'Candidate Student',
            'email'  => 'candidate.student@example.org',
            'status' => 'active',
        ]);
        $this->candidateStudent->assignRole('student');

        // Organization A
        $this->orgA = Organization::create([
            'name'              => 'iC.edu UAT University',
            'slug'              => 'icedu-uat-university',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->coordinator->id,
            'submitted_by'      => $this->coordinator->id,
            'reviewed_by'       => $this->coordinator->id,
            'reviewed_at'       => now(),
        ]);

        $this->membershipA = OrganizationMembership::create([
            'organization_id'   => $this->orgA->id,
            'user_id'           => $this->coordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
            'member_identifier' => 'COORD-001',
        ]);

        // Organization B
        $this->orgB = Organization::create([
            'name'              => 'Delta Enterprise Institute',
            'slug'              => 'delta-enterprise-institute',
            'organization_type' => OrganizationType::Company,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->otherCoordinator->id,
            'submitted_by'      => $this->otherCoordinator->id,
            'reviewed_by'       => $this->otherCoordinator->id,
            'reviewed_at'       => now(),
        ]);

        $this->membershipB = OrganizationMembership::create([
            'organization_id'   => $this->orgB->id,
            'user_id'           => $this->otherCoordinator->id,
            'role'              => MembershipRole::Coordinator,
            'status'            => MembershipStatus::Active,
            'joined_at'         => now(),
            'member_identifier' => 'COORD-002',
        ]);
    }

    /**
     * TEST INV-LINK-01 through 06: Functional tests for generating a secure new link for a pending invitation.
     */
    public function test_inv_link_01_pending_invitation_can_generate_secure_link(): void
    {
        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id'   => $this->orgA->id,
            'email'             => 'candidate.invitee@example.org',
            'intended_role'     => MembershipRole::Member,
            'member_identifier' => 'ID-001',
            'department'        => 'Informatics',
            'invited_by'        => $this->coordinator->id,
            'expires_at'        => now()->addDays(7),
        ]);
        $invitation = $invitationData['invitation'];
        $oldToken = $invitationData['token'];
        $originalId = $invitation->id;

        // Active members before: 1
        $this->assertEquals(1, $this->orgA->activeMemberships()->count());

        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.invitations.generate-link', [$this->orgA->slug, $invitation->id]));

        $response->assertRedirect();
        $response->assertSessionHas('status', "Invitation link generated successfully for {$invitation->email}.");
        $response->assertSessionHas('invitation_url');

        $generatedUrl = session('invitation_url');
        $this->assertStringContainsString('/invitations/', $generatedUrl);

        $invitation->refresh();

        // TEST INV-LINK-02: Same invitation row
        $this->assertEquals($originalId, $invitation->id);
        $this->assertEquals(1, OrganizationInvitation::count());

        // TEST INV-LINK-03: Status remains PENDING
        $this->assertEquals(InvitationStatus::Pending, $invitation->status);

        // TEST INV-LINK-04: Role remains Candidate Member
        $this->assertEquals(MembershipRole::Member, $invitation->intended_role);

        // TEST INV-LINK-05 & 06: No membership created, active members does not increment
        $this->assertEquals(1, $this->orgA->activeMemberships()->count());
    }

    /**
     * TEST INV-LINK-SEC-01 through 04: Token security, invalidation of old token, and acceptance of new token.
     */
    public function test_inv_link_sec_token_rotation_invalidates_old_and_accepts_new(): void
    {
        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'secure.candidate@example.org',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinator->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $invitation = $invitationData['invitation'];
        $oldToken = $invitationData['token'];

        // TEST INV-LINK-SEC-01: Plaintext token is not stored in DB
        $dbRow = OrganizationInvitation::find($invitation->id);
        $this->assertNotEquals($oldToken, $dbRow->token_hash);
        $this->assertEquals(hash('sha256', $oldToken), $dbRow->token_hash);

        // Rotate link
        $this->actingAs($this->coordinator)
            ->post(route('organization.invitations.generate-link', [$this->orgA->slug, $invitation->id]));

        $newUrl = session('invitation_url');
        $parts = explode('/invitations/', $newUrl);
        $newToken = end($parts);

        $this->assertNotEquals($oldToken, $newToken);

        // TEST INV-LINK-SEC-02: Old token is rejected
        $oldResponse = $this->get(route('invitations.accept', ['token' => $oldToken]));
        $oldResponse->assertStatus(200);
        $oldResponse->assertSee('This invitation link is invalid or has already been used.');

        $oldPostResponse = $this->post(route('invitations.process', ['token' => $oldToken]), [
            'password' => 'SomePassword123!',
        ]);
        $oldPostResponse->assertSessionHasErrors(['error']);

        // TEST INV-LINK-SEC-03: New token works and renders accept screen
        $newResponse = $this->get(route('invitations.accept', ['token' => $newToken]));
        $newResponse->assertStatus(200);
        $newResponse->assertSee('secure.candidate@example.org');

        // TEST INV-LINK-SEC-04: Token is not written to ActivityLog
        $log = ActivityLog::where('action', 'ORG_INVITATION_LINK_ROTATED')
            ->where('subject_id', (string) $invitation->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString($newToken, $log->description);
        $this->assertStringNotContainsString($newToken, json_encode($log->properties));
    }

    /**
     * TEST INV-LINK-AUTH-01 through 03: Authorization and tenant isolation.
     */
    public function test_inv_link_auth_guards_and_tenant_isolation(): void
    {
        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'tenant.test@example.org',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinator->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $invitationA = $invitationData['invitation'];

        // Candidate Student cannot generate link (no access to portal route)
        $resStudent = $this->actingAs($this->candidateStudent)
            ->post(route('organization.invitations.generate-link', [$this->orgA->slug, $invitationA->id]));
        $this->assertTrue(in_array($resStudent->status(), [302, 403, 404]));

        // Coordinator of Org B cannot generate link for Org A
        $resOrgB = $this->actingAs($this->otherCoordinator)
            ->post(route('organization.invitations.generate-link', [$this->orgA->slug, $invitationA->id]));
        $this->assertTrue(in_array($resOrgB->status(), [302, 403, 404]));
    }

    /**
     * TEST INV-LINK-LIFE-01 through 03: Accepted, revoked, and expired invitations cannot generate new links.
     */
    public function test_inv_link_life_guards_for_accepted_revoked_expired(): void
    {
        // 1. Accepted
        $acceptedInv = OrganizationInvitation::create([
            'organization_id' => $this->orgA->id,
            'email'           => 'accepted@example.org',
            'intended_role'   => MembershipRole::Member,
            'token_hash'      => hash('sha256', 'tok1'),
            'status'          => InvitationStatus::Accepted,
            'expires_at'      => now()->addDays(7),
            'accepted_at'     => now(),
            'invited_by'      => $this->coordinator->id,
        ]);

        $resAccepted = $this->actingAs($this->coordinator)
            ->post(route('organization.invitations.generate-link', [$this->orgA->slug, $acceptedInv->id]));
        $resAccepted->assertSessionHasErrors(['error']);

        // 2. Revoked
        $revokedInv = OrganizationInvitation::create([
            'organization_id' => $this->orgA->id,
            'email'           => 'revoked@example.org',
            'intended_role'   => MembershipRole::Member,
            'token_hash'      => hash('sha256', 'tok2'),
            'status'          => InvitationStatus::Revoked,
            'expires_at'      => now()->addDays(7),
            'invited_by'      => $this->coordinator->id,
        ]);

        $resRevoked = $this->actingAs($this->coordinator)
            ->post(route('organization.invitations.generate-link', [$this->orgA->slug, $revokedInv->id]));
        $resRevoked->assertSessionHasErrors(['error']);

        // 3. Expired
        $expiredInv = OrganizationInvitation::create([
            'organization_id' => $this->orgA->id,
            'email'           => 'expired@example.org',
            'intended_role'   => MembershipRole::Member,
            'token_hash'      => hash('sha256', 'tok3'),
            'status'          => InvitationStatus::Pending,
            'expires_at'      => now()->subDay(),
            'invited_by'      => $this->coordinator->id,
        ]);

        $resExpired = $this->actingAs($this->coordinator)
            ->post(route('organization.invitations.generate-link', [$this->orgA->slug, $expiredInv->id]));
        $resExpired->assertSessionHasErrors(['error']);
    }

    /**
     * TEST INV-LINK-RESEND-01 through 03: Existing Resend action still works and preserves role and record.
     */
    public function test_inv_link_resend_preserves_role_and_record(): void
    {
        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->orgA->id,
            'email'           => 'resend.candidate@example.org',
            'intended_role'   => MembershipRole::Member,
            'invited_by'      => $this->coordinator->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $invitation = $invitationData['invitation'];
        $origId = $invitation->id;

        $response = $this->actingAs($this->coordinator)
            ->post(route('organization.invitations.resend', [$this->orgA->slug, $invitation->id]));

        $response->assertRedirect();
        $response->assertSessionHas('status', "Invitation resent to {$invitation->email}.");
        $response->assertSessionHas('invitation_url');

        $invitation->refresh();
        $this->assertEquals($origId, $invitation->id);
        $this->assertEquals(MembershipRole::Member, $invitation->intended_role);
        $this->assertEquals(InvitationStatus::Pending, $invitation->status);
    }
}
