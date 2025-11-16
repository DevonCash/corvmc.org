<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasVisibility
{
    /**
     * The available visibility options.
     */
    protected static array $visibilityOptions = ['public', 'members', 'private'];

    /**
     * Get the available visibility options.
     */
    public static function getVisibilityOptions(): array
    {
        return static::$visibilityOptions;
    }

    /**
     * Check if the content is visible to the given user.
     */
    public function isVisible(?User $user = null): bool
    {
        if (! $user) {
            // Only public content is visible to guests
            return $this->visibility === 'public';
        }

        // Check if user is the owner/creator
        if ($this->isOwnedBy($user)) {
            return true;
        }

        // Staff can see all content if they have permission
        if ($user->can($this->getViewPrivatePermission())) {
            return true;
        }

        // Check visibility settings
        return match ($this->visibility) {
            'public' => true,
            'members' => true, // All logged-in users are considered members
            'private' => false,
            default => false,
        };
    }

    /**
     * Check if the content is owned by the given user.
     * Override this method in models to define ownership logic.
     */
    protected function isOwnedBy(User $user): bool
    {
        // Default ownership check - override in models
        if (isset($this->user_id)) {
            return $this->user_id === $user->id;
        }

        if (isset($this->owner_id)) {
            return $this->owner_id === $user->id;
        }

        return false;
    }

    /**
     * Get the permission name for viewing private content.
     * Override this method in models to define specific permissions.
     */
    protected function getViewPrivatePermission(): string
    {
        $modelName = strtolower(class_basename($this));

        return "view private {$modelName} profiles";
    }

    /**
     * Set the visibility level.
     */
    public function setVisibility(string $visibility): self
    {
        if (! in_array($visibility, static::$visibilityOptions)) {
            throw new \InvalidArgumentException("Invalid visibility option: {$visibility}");
        }

        $this->update(['visibility' => $visibility]);

        return $this;
    }

    /**
     * Make the content public.
     */
    public function makePublic(): self
    {
        return $this->setVisibility('public');
    }

    /**
     * Make the content members-only.
     */
    public function makeMembersOnly(): self
    {
        return $this->setVisibility('members');
    }

    /**
     * Make the content private.
     */
    public function makePrivate(): self
    {
        return $this->setVisibility('private');
    }

    /**
     * Check if the content is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * Check if the content is members-only.
     */
    public function isMembersOnly(): bool
    {
        return $this->visibility === 'members';
    }

    /**
     * Check if the content is private.
     */
    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    /**
     * Scope to get only public content.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope to get content visible to members.
     */
    public function scopeVisibleToMembers(Builder $query): Builder
    {
        return $query->whereIn('visibility', ['public', 'members']);
    }

    /**
     * Scope to get content visible to a specific user.
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        if (! $user) {
            return $query->public();
        }

        // If user can view private content, show all
        if ($user->can($this->getViewPrivatePermission())) {
            return $query;
        }

        // Show public and members content, plus user's own content
        return $query->where(function ($q) use ($user) {
            $q->whereIn('visibility', ['public', 'members']);

            // Add user's own content
            if (isset($this->user_id)) {
                $q->orWhere('user_id', $user->id);
            } elseif (isset($this->owner_id)) {
                $q->orWhere('owner_id', $user->id);
            }
        });
    }
}
