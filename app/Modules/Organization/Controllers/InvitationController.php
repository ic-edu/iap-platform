<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organization\Enums\InvitationStatus;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Models\OrganizationInvitation;
use App\Modules\Organization\Models\OrganizationMembership;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Display Invitation Acceptance Screen.
     */
    public function showAccept(string $token): View
    {
        $invitation = OrganizationInvitation::findByPlainToken($token);

        if (!$invitation) {
            return view('organization::invitations.accept', [
                'invitation' => null,
                'error'      => 'This invitation link is invalid or has already been used.',
                'token'      => $token,
            ]);
        }

        if ($invitation->isExpired()) {
            return view('organization::invitations.accept', [
                'invitation' => $invitation,
                'error'      => 'This invitation has expired. Please contact your Organization Coordinator to request a new invitation.',
                'token'      => $token,
            ]);
        }

        if ($invitation->isRevoked()) {
            return view('organization::invitations.accept', [
                'invitation' => $invitation,
                'error'      => 'This invitation has been revoked by the Organization Administrator.',
                'token'      => $token,
            ]);
        }

        if ($invitation->isAccepted()) {
            return view('organization::invitations.accept', [
                'invitation' => $invitation,
                'error'      => 'This invitation has already been accepted.',
                'token'      => $token,
            ]);
        }

        $existingUser = User::where('email', $invitation->email)->first();

        return view('organization::invitations.accept', [
            'invitation'   => $invitation,
            'organization' => $invitation->organization,
            'existingUser' => $existingUser,
            'token'        => $token,
            'error'        => null,
        ]);
    }

    /**
     * Process Invitation Acceptance (Linking existing user or creating new user).
     */
    public function processAccept(Request $request, string $token): RedirectResponse
    {
        $invitation = OrganizationInvitation::findByPlainToken($token);

        if (!$invitation || !$invitation->canBeAccepted()) {
            return redirect()->route('invitations.accept', $token)
                ->withErrors(['error' => 'Invitation is no longer valid or has expired.']);
        }

        $organization = $invitation->organization;
        $user = Auth::user();

        return DB::transaction(function () use ($request, $invitation, $organization, &$user) {
            // Case 1: User is already logged in
            if ($user) {
                // Link logged in user to organization membership
            } else {
                // Check if user exists by email
                $existingUser = User::where('email', $invitation->email)->first();

                if ($existingUser) {
                    // Prompt to authenticate first
                    $request->validate([
                        'password' => ['required', 'string'],
                    ]);

                    if (!Auth::attempt(['email' => $invitation->email, 'password' => $request->input('password')])) {
                        return back()->withErrors(['password' => 'The provided password does not match our records for this account.']);
                    }

                    $user = Auth::user();
                } else {
                    // Register new user with their chosen password
                    $validated = $request->validate([
                        'name'     => ['required', 'string', 'max:255'],
                        'password' => ['required', 'confirmed', Password::defaults()],
                    ]);

                    $user = User::create([
                        'name'              => $validated['name'],
                        'email'             => $invitation->email,
                        'password'          => Hash::make($validated['password']),
                        'status'            => 'active',
                        'email_verified_at' => now(),
                    ]);

                    // Assign student role as default candidate role
                    $user->assignRole('student');

                    Auth::login($user);
                }
            }

            // If intended role is coordinator/admin/owner, grant global organization-coordinator role
            if (in_array($invitation->intended_role, [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Coordinator], true)) {
                if (!$user->hasRole('organization-coordinator') && !$user->hasRole('super-admin')) {
                    $user->assignRole('organization-coordinator');
                }
            }

            // Create or update OrganizationMembership
            $membership = OrganizationMembership::where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership) {
                $membership->update([
                    'role'              => $invitation->intended_role,
                    'member_identifier' => $invitation->member_identifier ?? $membership->member_identifier,
                    'department'        => $invitation->department ?? $membership->department,
                    'status'            => MembershipStatus::Active,
                    'joined_at'         => $membership->joined_at ?? now(),
                ]);
            } else {
                $membership = OrganizationMembership::create([
                    'organization_id'   => $organization->id,
                    'user_id'           => $user->id,
                    'role'              => $invitation->intended_role,
                    'member_identifier' => $invitation->member_identifier,
                    'department'        => $invitation->department,
                    'status'            => MembershipStatus::Active,
                    'joined_at'         => now(),
                ]);
            }

            // Mark invitation as accepted
            $invitation->update([
                'status'      => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            ActivityLogger::log(
                action: 'ORG_INVITATION_ACCEPTED',
                description: "User {$user->name} ({$user->email}) accepted invitation for {$organization->name} as {$invitation->intended_role->value}",
                subject: $membership,
                properties: [
                    'organization_id' => $organization->id,
                    'user_id'         => $user->id,
                    'membership_id'   => $membership->id,
                    'role'            => $invitation->intended_role->value,
                ],
                userId: $user->id
            );

            if ($membership->hasManagementAuthority()) {
                return redirect()->route('organization.dashboard', $organization->slug)
                    ->with('status', "Welcome to {$organization->name}! Your {$invitation->intended_role->label()} access is now active.");
            }

            return redirect()->route('candidate.portal')
                ->with('status', "You have successfully joined {$organization->name} as a Candidate Member.");
        });
    }
}
