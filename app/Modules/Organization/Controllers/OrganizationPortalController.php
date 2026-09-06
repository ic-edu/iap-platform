<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
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
use App\Modules\Organization\Services\OrganizationContext;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationPortalController extends Controller
{
    public function __construct(
        protected OrganizationContext $context
    ) {}

    /**
     * Display Organization Selector when user has multiple organizations.
     */
    public function selectOrganization(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasRole('super-admin')) {
            $organizations = Organization::where('status', OrganizationStatus::Active)->get();
        } else {
            $memberships = $user->organizationMemberships()
                ->where('status', MembershipStatus::Active)
                ->with('organization')
                ->get();
            $organizations = $memberships->map(fn($m) => $m->organization)->filter(fn($o) => $o && $o->isActive());
        }

        if ($organizations->count() === 1) {
            return redirect()->route('organization.dashboard', $organizations->first()->slug);
        }

        if ($organizations->isEmpty()) {
            return redirect()->route('organization.no-access');
        }

        return view('organization::select', compact('organizations'));
    }

    /**
     * Display Zero-Membership Safe Landing.
     */
    public function noAccess(): View
    {
        return view('organization::no_access');
    }

    /**
     * Display Organization Dashboard.
     */
    public function dashboard(Organization $organization): View
    {
        $activeMembersCount = $organization->memberships()->where('status', MembershipStatus::Active)->count();
        $activeGroupsCount = $organization->groups()->where('is_active', true)->count();
        $pendingInvitationsCount = $organization->invitations()
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->count();
        $organizationStatus = $organization->status;

        $recentMembers = $organization->memberships()
            ->where('status', MembershipStatus::Active)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        $recentGroups = $organization->groups()
            ->withCount('groupMembers')
            ->latest()
            ->limit(5)
            ->get();

        return view('organization::dashboard', compact(
            'organization',
            'activeMembersCount',
            'activeGroupsCount',
            'pendingInvitationsCount',
            'organizationStatus',
            'recentMembers',
            'recentGroups'
        ));
    }

    /**
     * Display Member Roster and Invitations.
     */
    public function candidates(Request $request, Organization $organization): View
    {
        $search = $request->query('search');
        $roleFilter = $request->query('role');
        $statusFilter = $request->query('status', 'active');

        $membershipsQuery = $organization->memberships()
            ->with(['user', 'groups'])
            ->when($statusFilter && $statusFilter !== 'all', fn($q) => $q->where('status', $statusFilter))
            ->when($roleFilter && $roleFilter !== 'all', fn($q) => $q->where('role', $roleFilter))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('member_identifier', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        $memberships = $membershipsQuery->latest()->paginate(15)->withQueryString();

        $invitations = $organization->invitations()
            ->with('inviter')
            ->latest()
            ->limit(20)
            ->get();

        return view('organization::candidates.index', compact(
            'organization',
            'memberships',
            'invitations',
            'search',
            'roleFilter',
            'statusFilter'
        ));
    }

    /**
     * Create single member invitation.
     */
    public function invite(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'email'             => ['required', 'email', 'max:255'],
            'intended_role'     => ['required', Rule::in(MembershipRole::values())],
            'member_identifier' => ['nullable', 'string', 'max:100'],
            'department'        => ['nullable', 'string', 'max:100'],
        ]);

        // Check if user already has an active membership in this organization
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser && $organization->hasUser($existingUser)) {
            $membership = $organization->getMembership($existingUser);
            if ($membership && $membership->isActive()) {
                return back()->withErrors(['email' => "User {$validated['email']} is already an active member of this organization."]);
            }
        }

        // Revoke any existing pending invitations for this email in this org
        $organization->invitations()
            ->where('email', $validated['email'])
            ->where('status', InvitationStatus::Pending)
            ->update(['status' => InvitationStatus::Revoked]);

        $invitationData = OrganizationInvitation::createWithToken([
            'organization_id'   => $organization->id,
            'email'             => $validated['email'],
            'intended_role'     => $validated['intended_role'],
            'member_identifier' => $validated['member_identifier'] ?? null,
            'department'        => $validated['department'] ?? null,
            'invited_by'        => Auth::id(),
            'expires_at'        => now()->addDays(7),
        ]);

        $invitation = $invitationData['invitation'];
        $plainToken = $invitationData['token'];

        ActivityLogger::log(
            action: 'ORG_INVITATION_SENT',
            description: "Sent organization invitation to {$invitation->email} as {$invitation->intended_role->value}",
            subject: $invitation,
            properties: [
                'organization_id' => $organization->id,
                'email'           => $invitation->email,
                'intended_role'   => $invitation->intended_role->value,
                'invited_by'      => Auth::id(),
            ]
        );

        $acceptUrl = route('invitations.accept', ['token' => $plainToken]);

        return redirect()->route('organization.candidates', $organization->slug)
            ->with('status', "Invitation sent to {$invitation->email} successfully.")
            ->with('invitation_url', $acceptUrl);
    }

    /**
     * Resend an existing invitation.
     */
    public function resendInvitation(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        if ((string) $invitation->organization_id !== (string) $organization->id) {
            abort(403, 'Invitation does not belong to this organization.');
        }

        if ($invitation->status === InvitationStatus::Accepted) {
            return back()->withErrors(['error' => 'Cannot resend an already accepted invitation.']);
        }

        $plainToken = Str::random(40);
        $invitation->update([
            'token_hash' => hash('sha256', $plainToken),
            'status'     => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);

        ActivityLogger::log(
            action: 'ORG_INVITATION_RESENT',
            description: "Resent organization invitation to {$invitation->email}",
            subject: $invitation,
            properties: [
                'organization_id' => $organization->id,
                'email'           => $invitation->email,
            ]
        );

        $acceptUrl = route('invitations.accept', ['token' => $plainToken]);

        return back()
            ->with('status', "Invitation resent to {$invitation->email}.")
            ->with('invitation_url', $acceptUrl);
    }

    /**
     * Revoke a pending invitation.
     */
    public function revokeInvitation(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        if ((string) $invitation->organization_id !== (string) $organization->id) {
            abort(403, 'Invitation does not belong to this organization.');
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            return back()->withErrors(['error' => 'Only pending invitations can be revoked.']);
        }

        $invitation->update(['status' => InvitationStatus::Revoked]);

        ActivityLogger::log(
            action: 'ORG_INVITATION_REVOKED',
            description: "Revoked organization invitation for {$invitation->email}",
            subject: $invitation,
            properties: [
                'organization_id' => $organization->id,
                'email'           => $invitation->email,
            ]
        );

        return back()->with('status', "Invitation for {$invitation->email} has been revoked.");
    }

    /**
     * Display Organization Groups List.
     */
    public function groups(Organization $organization): View
    {
        $groups = $organization->groups()
            ->withCount('groupMembers')
            ->latest()
            ->paginate(12);

        $groupTypes = GroupType::cases();

        return view('organization::groups.index', compact('organization', 'groups', 'groupTypes'));
    }

    /**
     * Store new Organization Group.
     */
    public function storeGroup(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'group_type'  => ['nullable', Rule::in(GroupType::values())],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $group = OrganizationGroup::create([
            'organization_id' => $organization->id,
            'name'            => $validated['name'],
            'group_type'      => $validated['group_type'] ?? GroupType::ClassGroup->value,
            'description'     => $validated['description'] ?? null,
            'is_active'       => true,
            'created_by'      => Auth::id(),
        ]);

        ActivityLogger::log(
            action: 'ORG_GROUP_CREATED',
            description: "Created group '{$group->name}' in {$organization->name}",
            subject: $group,
            properties: [
                'organization_id' => $organization->id,
                'group_id'        => $group->id,
                'name'            => $group->name,
            ]
        );

        return redirect()->route('organization.groups.show', [$organization->slug, $group->id])
            ->with('status', "Group '{$group->name}' created successfully.");
    }

    /**
     * Show Organization Group details and member list.
     */
    public function showGroup(Organization $organization, OrganizationGroup $group): View
    {
        if ((string) $group->organization_id !== (string) $organization->id) {
            abort(403, 'Group does not belong to this organization.');
        }

        $group->loadCount('groupMembers');

        $members = $group->groupMembers()
            ->with(['membership.user'])
            ->paginate(20);

        // Active organization members who are not yet in this group
        $assignedMembershipIds = $group->groupMembers()->pluck('membership_id');
        $availableMemberships = $organization->memberships()
            ->where('status', MembershipStatus::Active)
            ->whereNotIn('id', $assignedMembershipIds)
            ->with('user')
            ->orderBy('department')
            ->get();

        $groupTypes = GroupType::cases();

        return view('organization::groups.show', compact('organization', 'group', 'members', 'availableMemberships', 'groupTypes'));
    }

    /**
     * Update Organization Group metadata.
     */
    public function updateGroup(Request $request, Organization $organization, OrganizationGroup $group): RedirectResponse
    {
        if ((string) $group->organization_id !== (string) $organization->id) {
            abort(403, 'Group does not belong to this organization.');
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'group_type'  => ['nullable', Rule::in(GroupType::values())],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $group->update([
            'name'        => $validated['name'],
            'group_type'  => $validated['group_type'] ?? $group->group_type,
            'description' => $validated['description'] ?? null,
            'is_active'   => $request->has('is_active') ? (bool) $request->input('is_active') : $group->is_active,
        ]);

        ActivityLogger::log(
            action: 'ORG_GROUP_UPDATED',
            description: "Updated group '{$group->name}'",
            subject: $group,
            properties: [
                'organization_id' => $organization->id,
                'group_id'        => $group->id,
                'name'            => $group->name,
            ]
        );

        return back()->with('status', "Group '{$group->name}' updated successfully.");
    }

    /**
     * Toggle group active/inactive status.
     */
    public function toggleGroupStatus(Organization $organization, OrganizationGroup $group): RedirectResponse
    {
        if ((string) $group->organization_id !== (string) $organization->id) {
            abort(403, 'Group does not belong to this organization.');
        }

        $group->update(['is_active' => !$group->is_active]);

        $label = $group->is_active ? 'activated' : 'deactivated';

        ActivityLogger::log(
            action: 'ORG_GROUP_UPDATED',
            description: "Group '{$group->name}' was {$label}",
            subject: $group,
            properties: [
                'organization_id' => $organization->id,
                'group_id'        => $group->id,
                'is_active'       => $group->is_active,
            ]
        );

        return back()->with('status', "Group '{$group->name}' {$label}.");
    }

    /**
     * Add existing Organization member to Group.
     */
    public function addGroupMember(Request $request, Organization $organization, OrganizationGroup $group): RedirectResponse
    {
        if ((string) $group->organization_id !== (string) $organization->id) {
            abort(403, 'Group does not belong to this organization.');
        }

        if (!$group->is_active) {
            return back()->withErrors(['error' => 'Cannot add members to an inactive group.']);
        }

        $validated = $request->validate([
            'membership_id' => ['required', 'exists:organization_memberships,id'],
        ]);

        $membership = OrganizationMembership::findOrFail($validated['membership_id']);

        if ((string) $membership->organization_id !== (string) $organization->id) {
            abort(403, 'Cross-organization assignment violation: Candidate does not belong to this organization.');
        }

        if ($membership->status !== MembershipStatus::Active) {
            return back()->withErrors(['error' => 'Only active members can be assigned to groups.']);
        }

        $group->addMembership($membership);

        ActivityLogger::log(
            action: 'ORG_GROUP_MEMBER_ADDED',
            description: "Added member {$membership->user?->name} to group '{$group->name}'",
            subject: $group,
            properties: [
                'organization_id' => $organization->id,
                'group_id'        => $group->id,
                'membership_id'   => $membership->id,
                'user_id'         => $membership->user_id,
            ]
        );

        return back()->with('status', "Member {$membership->user?->name} added to group.");
    }

    /**
     * Remove member from Group.
     */
    public function removeGroupMember(
        Organization $organization,
        OrganizationGroup $group,
        OrganizationMembership $membership
    ): RedirectResponse {
        if ((string) $group->organization_id !== (string) $organization->id) {
            abort(403, 'Group does not belong to this organization.');
        }

        if ((string) $membership->organization_id !== (string) $organization->id) {
            abort(403, 'Membership does not belong to this organization.');
        }

        $group->removeMembership($membership);

        ActivityLogger::log(
            action: 'ORG_GROUP_MEMBER_REMOVED',
            description: "Removed member {$membership->user?->name} from group '{$group->name}'",
            subject: $group,
            properties: [
                'organization_id' => $organization->id,
                'group_id'        => $group->id,
                'membership_id'   => $membership->id,
                'user_id'         => $membership->user_id,
            ]
        );

        return back()->with('status', "Member removed from group.");
    }

    /**
     * Display Organization Profile edit form.
     */
    public function profile(Organization $organization): View
    {
        $organizationTypes = OrganizationType::cases();

        return view('organization::profile', compact('organization', 'organizationTypes'));
    }

    /**
     * Update Organization Profile (Operational contact fields only).
     * Governance-sensitive identity fields (name, organization_type, status, slug) are protected.
     */
    public function updateProfile(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'email'       => ['nullable', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'website'     => ['nullable', 'url', 'max:255'],
            'address'     => ['nullable', 'string', 'max:500'],
            'city'        => ['nullable', 'string', 'max:100'],
            'province'    => ['nullable', 'string', 'max:100'],
            'country'     => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        $organization->update([
            'email'       => $validated['email'] ?? null,
            'phone'       => $validated['phone'] ?? null,
            'website'     => $validated['website'] ?? null,
            'address'     => $validated['address'] ?? null,
            'city'        => $validated['city'] ?? null,
            'province'    => $validated['province'] ?? null,
            'country'     => $validated['country'] ?? 'Indonesia',
            'postal_code' => $validated['postal_code'] ?? null,
        ]);

        ActivityLogger::log(
            action: 'ORG_PROFILE_UPDATED',
            description: "Coordinator updated contact & operational profile for '{$organization->name}'",
            subject: $organization,
            properties: [
                'organization_id' => $organization->id,
                'updated_fields'  => array_keys($validated),
            ]
        );

        return back()->with('status', 'Organization profile updated successfully.');
    }
}
