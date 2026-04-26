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
        return $user->can('manageMembers', $bandMember->band);
    }
}
