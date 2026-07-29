<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('super-admin') || $actor->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $actor, User $target): bool
    {
        return $actor->hasRole('super-admin') || $actor->hasRole('admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): bool
    {
        return $actor->hasRole('super-admin') || $actor->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model according to Hierarchical Role Protection.
     */
    public function update(User $actor, User $target): bool
    {
        if ($actor->hasRole('super-admin')) {
            return true;
        }

        if ($actor->hasRole('admin')) {
            return !$target->hasRole('super-admin');
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model according to Hierarchical Role Protection.
     */
    public function delete(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($actor->hasRole('super-admin')) {
            return true;
        }

        if ($actor->hasRole('admin')) {
            return !$target->hasRole('super-admin');
        }

        return false;
    }

    /**
     * Determine whether the user can reset password for target user.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    /**
     * Determine whether the user can toggle status for target user.
     */
    public function toggleStatus(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        return $this->update($actor, $target);
    }
}
