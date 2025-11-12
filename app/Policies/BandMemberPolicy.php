<?php

namespace App\Policies;

use App\Models\BandMember;
use App\Models\User;

/**
 * Simple policy for BandMember model creation/management.
 * Most band member operations are now handled in BandPolicy using scoped permissions.
 */
class BandMemberPolicy
{
    /**
     * Determine whether the user can view any band members.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BandMember $bandMember): bool
    {
        // Viewing individual band member records is not generally allowed
        return false;
    }

    /**
     * Determine whether the user can create band memberships (invite people).
     */
    public function create(User $user): bool
    {
        // This is used as a fallback in BandPolicy::invite()
        // Global permission to invite members to any band
        return $user->can('invite band members');
    }

    /**
     * Determine whether the user can update a band member record.
     */
    public function update(User $user, BandMember $bandMember): bool
    {
        // Delegate to BandPolicy::manageMembers for proper scoped permission check
        return $user->can('manage band members', $bandMember->band);
    }

    /**
     * Determine whether the user can delete a band member record.
     */
    public function delete(User $user, BandMember $bandMember): bool
    {
        // Delegate to BandPolicy::removeMember for proper scoped permission check
        return $bandMember->status !== 'invited' && $user->can('remove band members', $bandMember->band);
    }

    public function cancel(User $user, BandMember $bandMember): bool
    {
        return $bandMember->status === 'invited' && $user->can('update', $bandMember->band);
    }

    public function accept(User $user, BandMember $bandMember): bool
    {
        return $bandMember->status === 'invited' && $user->is($bandMember->user);
    }

    public function decline(User $user, BandMember $bandMember): bool
    {
        return $bandMember->status === 'invited' && $user->is($bandMember->user);
    }
}
