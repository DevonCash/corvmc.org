<?php

namespace CorvMC\Sponsorship\Policies;

use App\Models\User;
use CorvMC\Sponsorship\Models\Sponsor;

class SponsorPolicy
{
    /**
     * Determine whether the user can view any sponsors.
     */
    public function viewAny(?User $user): bool
    {
        return $user && $user->can('view sponsors');
    }

    /**
     * Determine whether the user can view the sponsor.
     */
    public function view(User $user, Sponsor $sponsor): bool
    {
        return $user->can('view sponsors');
    }

    /**
     * Determine whether the user can create sponsors.
     */
    public function create(User $user): bool
    {
        return $user->can('create sponsors');
    }

    /**
     * Determine whether the user can update the sponsor.
     */
    public function update(User $user, Sponsor $sponsor): bool
    {
        return $user->can('update sponsors');
    }

    /**
     * Determine whether the user can delete the sponsor.
     */
    public function delete(User $user, Sponsor $sponsor): bool
    {
        return $user->can('delete sponsors');
    }

    /**
     * Determine whether the user can restore the sponsor.
     */
    public function restore(User $user, Sponsor $sponsor): bool
    {
        return $user->can('restore sponsors');
    }

    /**
     * Determine whether the user can permanently delete the sponsor.
     */
    public function forceDelete(User $user, Sponsor $sponsor): bool
    {
        return $user->can('force delete sponsors');
    }

    /**
     * Determine whether the user can attach a sponsored member to the sponsor.
     */
    public function attachUser(User $user, Sponsor $sponsor): bool
    {
        return $user->can('update sponsors');
    }

    /**
     * Determine whether the user can detach a sponsored member from the sponsor.
     */
    public function detachUser(User $user, Sponsor $sponsor): bool
    {
        return $user->can('update sponsors');
    }
}
