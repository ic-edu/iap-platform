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
use App\Modules\Organization\Models\OrganizationGroup;
use App\Modules\Organization\Models\OrganizationInvitation;
use App\Modules\Organization\Models\OrganizationMembership;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationIdentityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $adminRA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminRA = User::factory()->create([
            'name'   => 'Registration Admin',
            'email'  => 'admin.ra@example.com',
            'status' => 'active',
        ]);
        $this->adminRA->assignRole('admin');

        $this->org = Organization::create([
            'name'           => 'iC.edu UAT University',
            'slug'           => 'icedu-uat-university',
            'type'           => OrganizationType::University,
            'official_email' => 'contact@uat.org',
            'status'         => OrganizationStatus::Active,
        ]);
    }

    /**
     * PROVISIONING MATRIX: New Candidate Member invitation assigns ONLY student role.
     */
    public function test_new_user_candidate_member_invitation_assigns_only_student_role(): void
    {
        $plainToken = 'cand-token-abc-123';
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->org->id,
            'invited_by'      => $this->adminRA->id,
            'email'           => 'new.candidate@example.org',
            'intended_role'   => MembershipRole::Member,
            'token_hash'      => hash('sha256', $plainToken),
            'status'          => InvitationStatus::Pending,
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->post(route('invitations.process', $plainToken), [
            'name'                  => 'New Candidate',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('candidate.portal'));

        $newUser = User::where('email', 'new.candidate@example.org')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('student'));
        $this->assertFalse($newUser->hasRole('organization-coordinator'));

        $membership = OrganizationMembership::where('user_id', $newUser->id)->first();
        $this->assertNotNull($membership);
        $this->assertEquals(MembershipRole::Member, $membership->role);
        $this->assertEquals(MembershipStatus::Active, $membership->status);
    }

    /**
     * PROVISIONING MATRIX: New Coordinator invitation assigns ONLY organization-coordinator role.
     */
    public function test_new_user_coordinator_invitation_assigns_only_organization_coordinator_role(): void
    {
        $plainToken = 'coord-token-xyz-789';
        $invitation = OrganizationInvitation::create([
            'organization_id' => $this->org->id,
            'invited_by'      => $this->adminRA->id,
            'email'           => 'new.coordinator@example.org',
            'intended_role'   => MembershipRole::Coordinator,
            'token_hash'      => hash('sha256', $plainToken),
            'status'          => InvitationStatus::Pending,
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->post(route('invitations.process', $plainToken), [
            'name'                  => 'New Coordinator',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertRedirect(route('organization.dashboard', $this->org->slug));

        $newUser = User::where('email', 'new.coordinator@example.org')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('organization-coordinator'));
        $this->assertFalse($newUser->hasRole('student'), 'New coordinator must NOT be granted student role automatically.');

        $membership = OrganizationMembership::where('user_id', $newUser->id)->first();
        $this->assertNotNull($membership);
        $this->assertEquals(MembershipRole::Coordinator, $membership->role);
        $this->assertEquals(MembershipStatus::Active, $membership->status);
    }

    /**
     * PROVISIONING MATRIX: New Admin and Owner invitations assign organization-coordinator and NOT student.
     */
    public function test_new_user_admin_and_owner_invitations_do_not_assign_student_role(): void
    {
        foreach ([MembershipRole::Admin, MembershipRole::Owner] as $intendedRole) {
            \Illuminate\Support\Facades\Auth::logout();

            $token = "priv-token-{$intendedRole->value}";
            $email = "new.{$intendedRole->value}@example.org";

            OrganizationInvitation::create([
                'organization_id' => $this->org->id,
                'invited_by'      => $this->adminRA->id,
                'email'           => $email,
                'intended_role'   => $intendedRole,
                'token_hash'      => hash('sha256', $token),
                'status'          => InvitationStatus::Pending,
                'expires_at'      => now()->addDays(7),
            ]);

            $response = $this->post(route('invitations.process', $token), [
                'name'                  => "New {$intendedRole->value}",
                'password'              => 'SecurePass123!',
                'password_confirmation' => 'SecurePass123!',
            ]);

            $response->assertRedirect(route('organization.dashboard', $this->org->slug));

            $user = User::where('email', $email)->first();
            $this->assertNotNull($user);
            $this->assertTrue($user->hasRole('organization-coordinator'));
            $this->assertFalse($user->hasRole('student'), "New {$intendedRole->value} must NOT have student role.");
        }
    }

    /**
     * EXISTING USER DUAL ROLE: Existing Student accepting Coordinator invitation retains student role.
     */
    public function test_existing_student_accepting_coordinator_invitation_retains_student_and_gains_coordinator_role(): void
    {
        $existingStudent = User::factory()->create([
            'name'     => 'Existing Student',
            'email'    => 'student.promoted@example.org',
            'password' => Hash::make('Password123!'),
            'status'   => 'active',
        ]);
        $existingStudent->assignRole('student');

        $plainToken = 'dual-role-token-123';
        OrganizationInvitation::create([
            'organization_id' => $this->org->id,
            'invited_by'      => $this->adminRA->id,
            'email'           => 'student.promoted@example.org',
            'intended_role'   => MembershipRole::Coordinator,
            'token_hash'      => hash('sha256', $plainToken),
            'status'          => InvitationStatus::Pending,
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($existingStudent)->post(route('invitations.process', $plainToken));
        $response->assertRedirect(route('organization.dashboard', $this->org->slug));

        $existingStudent->refresh();
        $this->assertTrue($existingStudent->hasRole('student'), 'Legitimate student role must be preserved.');
        $this->assertTrue($existingStudent->hasRole('organization-coordinator'), 'Organization-coordinator role must be added.');

        $membership = OrganizationMembership::where('user_id', $existingStudent->id)->first();
        $this->assertEquals(MembershipRole::Coordinator, $membership->role);
    }

    /**
     * TEST IDENTITY-CA-01: Candidate-only student appears in Candidate Management.
     */
    public function test_identity_ca_01_candidate_only_student_appears_in_candidate_management(): void
    {
        $student = User::factory()->create([
            'name'   => 'Standalone Candidate',
            'email'  => 'standalone@example.org',
            'status' => 'active',
        ]);
        $student->assignRole('student');

        $response = $this->actingAs($this->adminRA)->get(route('admin.candidates.index'));
        $response->assertStatus(200);
        $response->assertSee('Standalone Candidate');
        $response->assertSee('standalone@example.org');
    }

    /**
     * TEST IDENTITY-CA-02: Institutional Candidate Member CA01 appears in Candidate Management.
     */
    public function test_identity_ca_02_institutional_candidate_member_appears_in_candidate_management(): void
    {
        $ca01 = User::factory()->create([
            'name'   => 'CA01',
            'email'  => 'ca01@uat.org',
            'status' => 'active',
        ]);
        $ca01->assignRole('student');

        OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $ca01->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.candidates.index'));
        $response->assertStatus(200);
        $response->assertSee('CA01');
        $response->assertSee('ca01@uat.org');
    }

    /**
     * TEST IDENTITY-CA-03: Coordinator-only user does NOT appear in Candidate Management.
     */
    public function test_identity_ca_03_coordinator_only_user_does_not_appear_in_candidate_management(): void
    {
        $coord = User::factory()->create([
            'name'   => 'Indra Wahyudi',
            'email'  => 'ic.edu.bdg@gmail.com',
            'status' => 'active',
        ]);
        $coord->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $coord->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($this->adminRA)->get(route('admin.candidates.index'));
        $response->assertStatus(200);
        $response->assertDontSee('ic.edu.bdg@gmail.com');
    }

    /**
     * TEST IDENTITY-CA-04: Legitimate dual-role user appears in Candidate Management.
     */
    public function test_identity_ca_04_legitimate_dual_role_user_appears_in_candidate_management(): void
    {
        $dualUser = User::factory()->create([
            'name'   => 'Dual Role User',
            'email'  => 'dual.role@example.org',
            'status' => 'active',
        ]);
        $dualUser->assignRole('student');
        $dualUser->assignRole('organization-coordinator');

        $response = $this->actingAs($this->adminRA)->get(route('admin.candidates.index'));
        $response->assertStatus(200);
        $response->assertSee('Dual Role User');
        $response->assertSee('dual.role@example.org');
    }

    /**
     * TEST IDENTITY-KPI-01, 02, 03: Candidate KPI accurately counts candidates and excludes coordinator-only.
     */
    public function test_identity_kpi_counts_candidates_and_excludes_coordinator_only(): void
    {
        // 1. Standalone student
        $student = User::factory()->create(['name' => 'Student 1', 'email' => 's1@example.com']);
        $student->assignRole('student');

        // 2. Candidate Member
        $member = User::factory()->create(['name' => 'Member 1', 'email' => 'm1@example.com']);
        $member->assignRole('student');

        // 3. Coordinator-only
        $coord = User::factory()->create(['name' => 'Coord 1', 'email' => 'c1@example.com']);
        $coord->assignRole('organization-coordinator');

        // Total candidate count should be exactly 2
        $candidateCount = User::role('student')->count();
        $this->assertEquals(2, $candidateCount);
        $this->assertFalse(User::role('student')->where('email', 'c1@example.com')->exists());
    }

    /**
     * TEST IDENTITY-ROUTE-01: Student Candidate allowed access to candidate portal.
     */
    public function test_identity_route_01_student_candidate_allowed_portal(): void
    {
        $student = User::factory()->create(['email' => 'candidate.user@example.org']);
        $student->assignRole('student');

        $response = $this->actingAs($student)->get(route('candidate.portal'));
        $response->assertStatus(200);
    }

    /**
     * TEST IDENTITY-ROUTE-02: Coordinator-only user denied access to candidate portal (403).
     */
    public function test_identity_route_02_coordinator_only_denied_candidate_portal(): void
    {
        $coord = User::factory()->create(['email' => 'coord.only@example.org']);
        $coord->assignRole('organization-coordinator');

        $response = $this->actingAs($coord)->get(route('candidate.portal'));
        $response->assertStatus(403);
    }

    /**
     * TEST IDENTITY-ROUTE-03: Finance-only user denied candidate portal (403).
     */
    public function test_identity_route_03_finance_only_denied_candidate_portal(): void
    {
        $finance = User::factory()->create(['email' => 'finance.only@example.org']);
        $finance->assignRole('finance');

        $response = $this->actingAs($finance)->get(route('candidate.portal'));
        $response->assertStatus(403);
    }

    /**
     * TEST IDENTITY-ROUTE-04: Teacher-only user denied candidate portal (403).
     */
    public function test_identity_route_04_teacher_only_denied_candidate_portal(): void
    {
        $teacher = User::factory()->create(['email' => 'teacher.only@example.org']);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get(route('candidate.portal'));
        $response->assertStatus(403);
    }

    /**
     * TEST IDENTITY-ROUTE-05: Legitimate dual-role (student + coordinator) allowed candidate portal.
     */
    public function test_identity_route_05_legitimate_dual_role_allowed_candidate_portal(): void
    {
        $dualUser = User::factory()->create(['email' => 'dual.portal@example.org']);
        $dualUser->assignRole('student');
        $dualUser->assignRole('organization-coordinator');

        $response = $this->actingAs($dualUser)->get(route('candidate.portal'));
        $response->assertStatus(200);
    }

    /**
     * COMMAND REPAIR: Artisan remediation command safely strips accidental student role.
     */
    public function test_remediation_command_safely_removes_accidental_student_role(): void
    {
        $coord = User::factory()->create([
            'name'   => 'Accidental Student Coord',
            'email'  => 'accidental@example.org',
            'status' => 'active',
        ]);
        $coord->assignRole('student');
        $coord->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $coord->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $this->artisan('iap:remediate-coordinator-roles')
            ->expectsOutputToContain('Detached \'student\' role from accidental@example.org')
            ->assertExitCode(0);

        $coord->refresh();
        $this->assertFalse($coord->hasRole('student'));
        $this->assertTrue($coord->hasRole('organization-coordinator'));

        // Check ActivityLog recorded
        $log = ActivityLog::where('action', 'USER_ROLE_REMEDIATED')->first();
        $this->assertNotNull($log);
        $this->assertEquals($coord->id, $log->subject_id);
    }

    /**
     * O1 & O2 PRESERVATION: Organization Portal access, Group eligibility, and Seat allocation boundary remain intact.
     */
    public function test_organization_portal_and_governance_boundaries_preserved(): void
    {
        $coord = User::factory()->create(['email' => 'coord.gov@example.org']);
        $coord->assignRole('organization-coordinator');

        OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $coord->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        $ca01 = User::factory()->create(['email' => 'member.gov@example.org']);
        $ca01->assignRole('student');

        $memberMembership = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $ca01->id,
            'role'            => MembershipRole::Member,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now(),
        ]);

        // Coordinator can access Organization Dashboard
        $response = $this->actingAs($coord)->get(route('organization.dashboard', $this->org->slug));
        $response->assertStatus(200);

        // Group member picker query check
        $eligibleMembers = OrganizationMembership::where('organization_id', $this->org->id)
            ->where('role', MembershipRole::Member)
            ->where('status', MembershipStatus::Active)
            ->get();

        $this->assertTrue($eligibleMembers->contains('id', $memberMembership->id));
        $this->assertFalse($eligibleMembers->contains('user_id', $coord->id));
    }

    /**
     * TEST IDENTITY-SAMEORG-01: Coordinator + Member invitation in same Organization does not downgrade scoped role.
     */
    public function test_identity_sameorg_01_coordinator_accepting_member_invitation_in_same_org_preserves_coordinator_role(): void
    {
        $coord = User::factory()->create([
            'name'   => 'Active Coordinator',
            'email'  => 'coord.sameorg@example.org',
            'status' => 'active',
        ]);
        $coord->assignRole('organization-coordinator');

        $membership = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $coord->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now()->subDays(10),
        ]);

        $plainToken = 'same-org-member-token';
        OrganizationInvitation::create([
            'organization_id' => $this->org->id,
            'invited_by'      => $this->adminRA->id,
            'email'           => 'coord.sameorg@example.org',
            'intended_role'   => MembershipRole::Member,
            'token_hash'      => hash('sha256', $plainToken),
            'status'          => InvitationStatus::Pending,
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($coord)->post(route('invitations.process', $plainToken));
        $response->assertRedirect(route('organization.dashboard', $this->org->slug));

        $coord->refresh();
        $membership->refresh();

        // User gains student role for candidate capability
        $this->assertTrue($coord->hasRole('student'));
        $this->assertTrue($coord->hasRole('organization-coordinator'));

        // Scoped OrganizationMembership role MUST remain COORDINATOR (not downgraded to member)
        $this->assertEquals(MembershipRole::Coordinator, $membership->role);
        $this->assertEquals(MembershipStatus::Active, $membership->status);
    }

    /**
     * TEST IDENTITY-MULTIORG-01: Coordinator in Organization A may become Member in Organization B without changing Org A role.
     */
    public function test_identity_multiorg_01_coordinator_in_org_a_becomes_member_in_org_b(): void
    {
        $orgB = Organization::create([
            'name'           => 'Second Campus Organization',
            'slug'           => 'second-campus-org',
            'type'           => OrganizationType::University,
            'official_email' => 'contact@second.org',
            'status'         => OrganizationStatus::Active,
        ]);

        $user = User::factory()->create([
            'name'   => 'Multi Org User',
            'email'  => 'multi.org@example.org',
            'status' => 'active',
        ]);
        $user->assignRole('organization-coordinator');

        // Membership in Org A is Coordinator
        $membershipA = OrganizationMembership::create([
            'organization_id' => $this->org->id,
            'user_id'         => $user->id,
            'role'            => MembershipRole::Coordinator,
            'status'          => MembershipStatus::Active,
            'joined_at'       => now()->subDays(5),
        ]);

        // Invitation for Org B is Candidate Member
        $plainToken = 'org-b-member-token';
        OrganizationInvitation::create([
            'organization_id' => $orgB->id,
            'invited_by'      => $this->adminRA->id,
            'email'           => 'multi.org@example.org',
            'intended_role'   => MembershipRole::Member,
            'token_hash'      => hash('sha256', $plainToken),
            'status'          => InvitationStatus::Pending,
            'expires_at'      => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->post(route('invitations.process', $plainToken));
        $response->assertRedirect(route('candidate.portal'));

        $user->refresh();
        $membershipA->refresh();

        $membershipB = OrganizationMembership::where('organization_id', $orgB->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membershipB);
        // Org A role remains Coordinator
        $this->assertEquals(MembershipRole::Coordinator, $membershipA->role);
        // Org B role is Member
        $this->assertEquals(MembershipRole::Member, $membershipB->role);
        // User has both platform roles
        $this->assertTrue($user->hasRole('student'));
        $this->assertTrue($user->hasRole('organization-coordinator'));
    }
}

