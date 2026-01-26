<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Bands\Models\BandMember;

class BandMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BandMember $bandMember): bool
    {
        return false; // Not needed
    }

    public function create(User $user): bool
    {
        return true; // Actual check delegated to BandPolicy::invite
    }

    public function update(User $user, BandMember $bandMember): bool
    {
        return $user->can('manageMembers', $bandMember->band);
    }

    public function delete(User $user, BandMember $bandMember): bool
    {
        // Can only delete (remove) active members, not invitations
        if ($bandMember->status === 'invited') {
            return false;
        }

        return $user->can('manageMembers', $bandMember->band);
    }

    public function cancel(User $user, BandMember $bandMember): bool
    {
        // Cancel pending invitation
        if ($bandMember->status !== 'invited') {
            return false;
        }

        return $user->can('update', $bandMember->band);
    }

    public function accept(User $user, BandMember $bandMember): bool
    {
        return $bandMember->status === 'invited'
            && $user->is($bandMember->user);
    }

    public function decline(User $user, BandMember $bandMember): bool
    {
        return $this->accept($user, $bandMember);
    }
}
