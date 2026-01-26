<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Moderation\Models\Revision;

class RevisionPolicy
{
    /**
     * Determine if the user can manage revisions (moderator or admin).
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(['admin', 'moderator']);
    }

    /**
     * Determine if the user can view any revisions.
     * Users can see the revisions list (filtered in queries to show relevant revisions).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the revision.
     * Moderators can view any revision, users can view their own revisions.
     */
    public function view(User $user, Revision $revision): bool
    {
        return $this->manage($user) || $revision->isSubmittedBy($user);
    }

    /**
     * Determine if the user can create revisions.
     * Any authenticated user can submit revisions.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the revision.
     * Only pending revisions can be updated by submitter or moderator.
     */
    public function update(User $user, Revision $revision): bool
    {
        if (!$revision->isPending()) {
            return false;
        }

        return $this->manage($user) || $revision->isSubmittedBy($user);
    }

    /**
     * Determine if the user can delete the revision.
     * Only pending revisions can be deleted by submitter or moderator.
     */
    public function delete(User $user, Revision $revision): bool
    {
        if (!$revision->isPending()) {
            return false;
        }

        return $this->manage($user) || $revision->isSubmittedBy($user);
    }

    /**
     * Determine if the user can restore the revision.
     */
    public function restore(User $user, Revision $revision): bool
    {
        return false;
    }

    /**
     * Determine if the user can permanently delete the revision.
     */
    public function forceDelete(User $user, Revision $revision): bool
    {
        return false;
    }

    /**
     * Determine if the user can approve the revision.
     * Cannot approve own revisions, must be pending, must be moderator.
     */
    public function approve(User $user, Revision $revision): bool
    {
        if ($revision->isSubmittedBy($user) || !$revision->isPending()) {
            return false;
        }

        return $this->manage($user);
    }

    /**
     * Determine if the user can reject the revision.
     * Same rules as approve.
     */
    public function reject(User $user, Revision $revision): bool
    {
        return $this->approve($user, $revision);
    }
}
