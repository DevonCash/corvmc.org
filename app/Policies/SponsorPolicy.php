<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Sponsorship\Models\Sponsor;

class SponsorPolicy
{
    /**
     * Determine if the user can manage sponsors (admin only).
     */
    public function manage(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can view any sponsors.
     * Sponsors page is public.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the sponsor.
     * Individual sponsors are publicly viewable.
     */
    public function view(?User $user, Sponsor $sponsor): bool
    {
        return true;
    }

    /**
     * Determine if the user can create sponsors.
     */
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can update the sponsor.
     */
    public function update(User $user, Sponsor $sponsor): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can delete the sponsor.
     */
    public function delete(User $user, Sponsor $sponsor): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can restore the sponsor.
     */
    public function restore(User $user, Sponsor $sponsor): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can permanently delete the sponsor.
     * Force deletion is never allowed.
     */
    public function forceDelete(User $user, Sponsor $sponsor): bool
    {
        return false;
    }

    /**
     * Determine if the user can attach a user to the sponsor.
     */
    public function attachUser(User $user, Sponsor $sponsor): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can detach a user from the sponsor.
     */
    public function detachUser(User $user, Sponsor $sponsor): bool
    {
        return $this->manage($user);
    }
}
