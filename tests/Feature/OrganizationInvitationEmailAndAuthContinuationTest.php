<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Organization\Enums\InvitationStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Enums\OrganizationStatus;
use App\Modules\Organization\Enums\OrganizationType;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationInvitation;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Modules\Organization\Notifications\OrganizationInvitationNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationInvitationEmailAndAuthContinuationTest extends TestCase
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
            'name'              => 'Test Polytechnic Institute',
            'slug'              => 'test-polytechnic-institute',
            'organization_type' => OrganizationType::University,
            'status'            => OrganizationStatus::Active,
            'created_by'        => $this->adminRA->id,
            'submitted_by'      => $this->adminRA->id,
            'reviewed_by'       => $this->superAdmin->id,
            'reviewed_at'       => now(),
        ]);
    }

    /**
     * TEST MAIL-INV-01 to 06: Generating Coordinator invitation dispatches email notification with valid payload.
     */
    public function test_mail_inv_01_to_06_generates_and_dispatches_email_notification(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.invite-coordinator', $this->activeOrg->id), [
            'coordinator_email' => 'dean.poly@example.edu',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $response->assertSessionHas('invitation_url');

        $invitation = OrganizationInvitation::where('email', 'dean.poly@example.edu')->first();
        $this->assertNotNull($invitation);

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (OrganizationInvitationNotification $notification, array $channels, $notifiable) use ($invitation) {
                // TEST MAIL-INV-02: Recipient equals invited email
                $this->assertContains('mail', $channels);
                $this->assertEquals('dean.poly@example.edu', $notifiable->routes['mail']);

                $mailData = $notification->toMail($notifiable);

                // TEST MAIL-INV-03: Email identifies correct Organization
                $this->assertStringContainsString('Test Polytechnic Institute', $mailData->subject);
                $this->assertStringContainsString('Test Polytechnic Institute', implode(' ', $mailData->introLines));

                // TEST MAIL-INV-04: Email identifies COORDINATOR role
                $this->assertStringContainsString('Organization Coordinator', implode(' ', $mailData->introLines));

                // TEST MAIL-INV-05: Email contains canonical acceptance URL
                $this->assertStringContainsString('/invitations/', $mailData->actionUrl);
                $this->assertTrue(str_starts_with($mailData->actionUrl, config('app.url')));

                // TEST MAIL-INV-06: No password is sent
                $allText = $mailData->subject . ' ' . implode(' ', $mailData->introLines) . ' ' . implode(' ', $mailData->outroLines);
                $this->assertStringNotContainsString('password', strtolower($allText));

                return true;
            }
        );
    }

    /**
     * TEST MAIL-Q-01 & 02: Notification implements ShouldQueue and serializes valid invitation context.
     */
    public function test_mail_q_01_and_02_notification_is_queueable(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'queue.check@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);

        $notification = new OrganizationInvitationNotification($invData['invitation'], 'http://localhost/invitations/accept/' . $invData['token']);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
        $this->assertEquals($invData['invitation']->id, $notification->invitation->id);
    }

    /**
     * TEST MAIL-Q-03: Resend dispatches updated email notification.
     */
    public function test_mail_q_03_resend_dispatches_email_notification(): void
    {
        Notification::fake();

        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'resend.target@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDay(),
        ]);

        $response = $this->actingAs($this->adminRA)->post(route('admin.organizations.invitations.resend', [
            'organization' => $this->activeOrg->id,
            'invitation'   => $invData['invitation']->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (OrganizationInvitationNotification $notification, array $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'resend.target@example.edu';
            }
        );
    }

    /**
     * TEST AUTH-RETURN-01 & 02: Switch Account preserves invitation URL and returns upon login.
     */
    public function test_auth_return_01_and_02_switch_account_preserves_invitation_url(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'invited.user@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];
        $invUrl = route('invitations.accept', $token);

        $existingInvitedUser = User::factory()->create([
            'name'     => 'Dr. Invited',
            'email'    => 'invited.user@example.edu',
            'password' => Hash::make('Password123!'),
            'status'   => 'active',
        ]);

        // 1. Wrong user is logged in
        $wrongUser = User::factory()->create([
            'email'  => 'wrong.user@example.edu',
            'status' => 'active',
        ]);
        $wrongUser->assignRole('admin');

        $viewResp = $this->actingAs($wrongUser)->get($invUrl);
        $viewResp->assertStatus(200);
        $viewResp->assertSee('Account Identity Mismatch');
        $viewResp->assertSee('Log Out &amp; Switch Account', false);

        // 2. Click Log Out & Switch Account
        $logoutResp = $this->post(route('logout'), [
            'return_url' => $invUrl,
        ]);
        $this->assertGuest();
        $logoutResp->assertRedirect(route('login', ['redirect' => $invUrl]));

        // 3. Login as the invited user
        $loginResp = $this->post(route('login'), [
            'email'    => 'invited.user@example.edu',
            'password' => 'Password123!',
            'redirect' => $invUrl,
        ]);

        // TEST AUTH-RETURN-02: Returns to the same invitation URL
        $loginResp->assertRedirect($invUrl);

        // 4. View invitation as matching logged-in user
        $matchingViewResp = $this->actingAs($existingInvitedUser)->get($invUrl);
        $matchingViewResp->assertStatus(200);
        $matchingViewResp->assertDontSee('Account Identity Mismatch');
        $matchingViewResp->assertSee('You are logged in as');
        $matchingViewResp->assertSee('Accept Invitation & Continue', false);

        // TEST AUTH-NEW-05: Invitation remains pending until explicit accept
        $invData['invitation']->refresh();
        $this->assertEquals(InvitationStatus::Pending, $invData['invitation']->status);
        $this->assertEquals(0, $this->activeOrg->activeMemberships()->count());
    }

    /**
     * TEST AUTH-RETURN-03: Wrong user remains blocked by Account Identity Mismatch.
     */
    public function test_auth_return_03_wrong_user_cannot_accept(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'target.coordinator@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];

        $intruder = User::factory()->create([
            'email'  => 'unauthorized@example.edu',
            'status' => 'active',
        ]);

        $response = $this->actingAs($intruder)->post(route('invitations.process', $token));
        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(0, $this->activeOrg->activeMemberships()->count());
    }

    /**
     * TEST AUTH-NEW-01 to 04: Non-existing user invitation-scoped registration.
     */
    public function test_auth_new_01_to_04_new_user_registration_through_invitation(): void
    {
        $invData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'brand.new.user@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $token = $invData['token'];

        // TEST AUTH-NEW-01: View invitation as guest shows registration fields
        $guestView = $this->get(route('invitations.accept', $token));
        $guestView->assertStatus(200);
        $guestView->assertSee('brand.new.user@example.edu');
        $guestView->assertSee('Your Full Name *');
        $guestView->assertSee('Create Password *');

        // TEST AUTH-NEW-02: Attempting to submit a different email is rejected
        $badEmailResp = $this->post(route('invitations.process', $token), [
            'name'                  => 'Attacker',
            'email'                 => 'different.email@example.edu',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);
        $badEmailResp->assertSessionHasErrors(['email']);
        $this->assertNull(User::where('email', 'different.email@example.edu')->first());

        // TEST AUTH-NEW-03 & 04: Valid registration creates user, hashes password, sets coordinator role, links membership
        $validResp = $this->post(route('invitations.process', $token), [
            'name'                  => 'New Coordinator Alex',
            'password'              => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
        ]);

        $validResp->assertRedirect(route('organization.dashboard', $this->activeOrg->slug));

        $newUser = User::where('email', 'brand.new.user@example.edu')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue(Hash::check('SuperSecret123!', $newUser->password));
        $this->assertTrue($newUser->hasRole('organization-coordinator'));

        $this->assertEquals(1, $this->activeOrg->activeMemberships()->count());
        $invData['invitation']->refresh();
        $this->assertEquals(InvitationStatus::Accepted, $invData['invitation']->status);
    }

    /**
     * TEST AUTH-LIFE-01 & 02: Expired or Revoked invitations cannot be accepted.
     */
    public function test_auth_life_01_and_02_expired_and_revoked_invitations_blocked(): void
    {
        // Expired
        $expiredData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'expired.auth@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->subMinute(),
        ]);
        $expView = $this->get(route('invitations.accept', $expiredData['token']));
        $expView->assertSee('expired');

        $expProcess = $this->post(route('invitations.process', $expiredData['token']), [
            'name'                  => 'Expired User',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $expProcess->assertSessionHasErrors(['error']);

        // Revoked
        $revokedData = OrganizationInvitation::createWithToken([
            'organization_id' => $this->activeOrg->id,
            'email'           => 'revoked.auth@example.edu',
            'intended_role'   => MembershipRole::Coordinator,
            'invited_by'      => $this->adminRA->id,
            'expires_at'      => now()->addDays(7),
        ]);
        $revokedData['invitation']->update(['status' => InvitationStatus::Revoked]);

        $revView = $this->get(route('invitations.accept', $revokedData['token']));
        $revView->assertSee('revoked');

        $revProcess = $this->post(route('invitations.process', $revokedData['token']), [
            'name'                  => 'Revoked User',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $revProcess->assertSessionHasErrors(['error']);
    }
}
