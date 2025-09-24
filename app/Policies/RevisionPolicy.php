<?php

namespace App\Policies;

use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RevisionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any revisions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view revisions');
    }

    /**
     * Determine whether the user can view the revision.
     */
    public function view(User $user, Revision $revision): bool
    {
        // Users can view their own revisions
        if ($revision->submitted_by_id === $user->id) {
            return true;
        }

        // Users who can approve revisions can view all revisions
        if ($user->can('approve revisions')) {
            return true;
        }

        // Users who can view revisions for this specific content type
        $contentType = $this->getContentTypeName($revision->revisionable_type);
        return $user->can("view {$contentType} revisions");
    }

    /**
     * Determine whether the user can create revisions.
     */
    public function create(User $user): bool
    {
        return $user->can('submit revisions');
    }

    /**
     * Determine whether the user can submit a revision for specific content.
     */
    public function submitFor(User $user, $content): bool
    {
        // Users cannot submit revisions for their own content unless explicitly allowed
        if ($this->isContentOwner($user, $content) && !$user->can('submit revisions for own content')) {
            return false;
        }

        // Check if user can submit revisions for this content type
        $contentType = $this->getContentTypeName(get_class($content));
        
        if ($user->can("submit {$contentType} revisions")) {
            return true;
        }

        // General revision submission permission
        return $user->can('submit revisions');
    }

    /**
     * Determine whether the user can update the revision.
     */
    public function update(User $user, Revision $revision): bool
    {
        // Only pending revisions can be updated
        if ($revision->status !== 'pending') {
            return false;
        }

        // Users can update their own pending revisions
        if ($revision->submitted_by_id === $user->id) {
            return true;
        }

        // Admins can update any revision
        return $user->can('manage revisions');
    }

    /**
     * Determine whether the user can delete the revision.
     */
    public function delete(User $user, Revision $revision): bool
    {
        // Only pending revisions can be deleted
        if ($revision->status !== 'pending') {
            return false;
        }

        // Users can delete their own pending revisions
        if ($revision->submitted_by_id === $user->id) {
            return true;
        }

        // Admins can delete any revision
        return $user->can('manage revisions');
    }

    /**
     * Determine whether the user can approve revisions.
     */
    public function approve(User $user, Revision $revision): bool
    {
        // Cannot approve your own revisions
        if ($revision->submitted_by_id === $user->id) {
            return false;
        }

        // Must be pending to approve
        if ($revision->status !== 'pending') {
            return false;
        }

        // Check content-specific approval permissions
        $contentType = $this->getContentTypeName($revision->revisionable_type);
        
        if ($user->can("approve {$contentType} revisions")) {
            return true;
        }

        // General approval permission
        return $user->can('approve revisions');
    }

    /**
     * Determine whether the user can reject revisions.
     */
    public function reject(User $user, Revision $revision): bool
    {
        // Cannot reject your own revisions
        if ($revision->submitted_by_id === $user->id) {
            return false;
        }

        // Must be pending to reject
        if ($revision->status !== 'pending') {
            return false;
        }

        // Check content-specific rejection permissions
        $contentType = $this->getContentTypeName($revision->revisionable_type);
        
        if ($user->can("reject {$contentType} revisions")) {
            return true;
        }

        // General approval permission includes rejection
        return $user->can('approve revisions');
    }

    /**
     * Determine whether the user can manage revisions (bulk actions, settings).
     */
    public function manage(User $user): bool
    {
        return $user->can('manage revisions');
    }

    /**
     * Check if user is the owner of the content being revised.
     */
    private function isContentOwner(User $user, $content): bool
    {
        // Check common ownership patterns
        if (isset($content->user_id)) {
            return $content->user_id === $user->id;
        }

        if (isset($content->owner_id)) {
            return $content->owner_id === $user->id;
        }

        if (isset($content->organizer_id)) {
            return $content->organizer_id === $user->id;
        }

        if (isset($content->manager_id)) {
            return $content->manager_id === $user->id;
        }

        // Check if content has an ownership method
        if (method_exists($content, 'isOwnedBy')) {
            return $content->isOwnedBy($user);
        }

        return false;
    }

    /**
     * Get human-readable content type name from class name.
     */
    private function getContentTypeName(string $className): string
    {
        $baseName = class_basename($className);
        
        return match ($baseName) {
            'MemberProfile' => 'member profile',
            'Band' => 'band',
            'Production' => 'production',
            'CommunityEvent' => 'community event',
            'StaffProfile' => 'staff profile',
            'Equipment' => 'equipment',
            default => strtolower($baseName),
        };
    }
}