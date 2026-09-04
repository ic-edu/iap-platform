<?php

namespace App\Modules\Organization\Policies;

use App\Models\User;
use App\Modules\Organization\Enums\MembershipRole;
use App\Modules\Organization\Enums\MembershipStatus;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMembership;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view the organization portal.
     */
    public function view(User $user, Organization $organization): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $membership = $this->getMembership($user, $organization);
        return $membership && $membership->isActive() && $membership->hasManagementAuthority();
    }

    /**
     * Determine whether the user can manage members and candidates.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $membership = $this->getMembership($user, $organization);
        return $membership && $membership->isActive() && $membership->hasManagementAuthority();
    }

    /**
     * Determine whether the user can manage organization groups.
     */
    public function manageGroups(User $user, Organization $organization): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $membership = $this->getMembership($user, $organization);
        return $membership && $membership->isActive() && $membership->hasManagementAuthority();
    }

    /**
     * Determine whether the user can manage invitations.
     */
    public function manageInvitations(User $user, Organization $organization): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $membership = $this->getMembership($user, $organization);
        return $membership && $membership->isActive() && $membership->hasManagementAuthority();
    }

    /**
     * Determine whether the user can manage organization profile.
     */
    public function manageProfile(User $user, Organization $organization): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $membership = $this->getMembership($user, $organization);
        return $membership && $membership->isActive() && in_array($membership->role, [MembershipRole::Owner, MembershipRole::Admin], true);
    }

    protected function getMembership(User $user, Organization $organization): ?OrganizationMembership
    {
        return OrganizationMembership::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
