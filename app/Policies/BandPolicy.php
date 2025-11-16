<?php

namespace App\Policies;

use App\Data\ContactData;
use App\Models\Band;
use App\Models\BandMember;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BandPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Band $band): ?bool
    {
        if ($user->can('view private bands')) {
            return true;
        }

        // Public bands are viewable by anyone
        if ($band->visibility === 'public') {
            return true;
        }

        // Members-only bands are viewable by authenticated users with permission
        if ($band->visibility === 'members' && $user) {
            return true;
        }

        // Private bands are viewable by members
        if ($band->visibility === 'private' && $band->membership($user)) {
            return true;
        }

        return null;
    }

    public function create(User $user): bool
    {
        // All authenticated users can create bands
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Band $band): ?bool
    {
        // Owner can always update their band
        if ($user->is($band->owner)) {
            return true;
        }

        // Context check: admin members can update
        if ($band->membership($user)?->role === 'admin') {
            return true;
        }

        // Cross-cutting permission to update any band
        if ($user->can('update bands')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Band $band): ?bool
    {
        // Context check: only owner can delete their band
        if ($user->is($band->owner)) {
            return true;
        }

        // Cross-cutting permission to delete any band
        if ($user->can('delete bands')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Band $band): ?bool
    {
        // Context check: only owner can restore their band
        if ($user->is($band->owner)) {
            return true;
        }

        // Cross-cutting permission to restore bands
        if ($user->can('restore bands')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Band $band): ?bool
    {
        // Only global permission for force delete (admins only)
        if ($user->can('force delete bands')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can invite members to the band.
     */
    public function invite(User $user, Band $band): ?bool
    {
        // Context check: owner or admin member can invite
        if ($band->membership($user)->role === 'admin') {
            return true;
        }

        return Gate::allows('create', BandMember::class);
    }

    /**
     * Determine whether the user can transfer ownership of the band.
     */
    public function transfer(User $user, Band $band): ?bool
    {
        // Must be owner AND have transfer permission (AND logic)
        if ($user->is($band->owner)) {
            return true;
        }

        return null;
    }

    public function contact(?User $user, Band $band): ?bool
    {
        /**
         * @var ContactData $contact
         */
        $contact = $band->contact;
        // Public bands: anyone can view contact info
        if ($contact->visibility === 'public') {
            return true;
        }

        // Members-only bands: users with permission can view
        if ($contact->visibility === 'members' && $user) {
            return true;
        }

        // Private bands: band members can view contact info
        if ($contact->visibility === 'private' && $band->membership($user)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can join the band.
     */
    public function join(User $user, Band $band): ?bool
    {
        // Check for existing invitation through pivot table
        if ($band->membership($user)->status === 'invited') {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can leave the band.
     */
    public function leave(User $user, Band $band): ?bool
    {
        // Owner cannot leave (must transfer ownership first)
        if ($user->is($band->owner)) {
            return false;
        }

        // Can leave if they have a band membership
        if ($band->membership($user)) {
            return true;
        }

        return false;
    }
}
