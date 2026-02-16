<?php

namespace CorvMC\Moderation\Concerns;

use CorvMC\Moderation\Enums\Visibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasVisibility
{
    /**
     * Get the available visibility options.
     */
    public static function getVisibilityOptions(): array
    {
        return Visibility::values();
    }

    /**
     * Check if the content is visible to the given user.
     */
    public function isVisible(?User $user = null): bool
    {
        if (! $user) {
            // Only public content is visible to guests
            return $this->visibility?->isVisibleToGuests() ?? false;
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
            Visibility::Public => true,
            Visibility::Members => true, // All logged-in users are considered members
            Visibility::Private => false,
            default => false,
        };
    }

    /**
     * Check if the content is owned by the given user.
     * Override this method in models to define ownership logic.
     */
    protected function isOwnedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

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
    public function setVisibility(Visibility $visibility): self
    {
        $this->update(['visibility' => $visibility]);

        return $this;
    }

    /**
     * Make the content public.
     */
    public function makePublic(): self
    {
        return $this->setVisibility(Visibility::Public);
    }

    /**
     * Make the content members-only.
     */
    public function makeMembersOnly(): self
    {
        return $this->setVisibility(Visibility::Members);
    }

    /**
     * Make the content private.
     */
    public function makePrivate(): self
    {
        return $this->setVisibility(Visibility::Private);
    }

    /**
     * Check if the content is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === Visibility::Public;
    }

    /**
     * Check if the content is members-only.
     */
    public function isMembersOnly(): bool
    {
        return $this->visibility === Visibility::Members;
    }

    /**
     * Check if the content is private.
     */
    public function isPrivate(): bool
    {
        return $this->visibility === Visibility::Private;
    }

    /**
     * Scope to get only public content.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', Visibility::Public);
    }

    /**
     * Scope to get content visible to members.
     */
    public function scopeVisibleToMembers(Builder $query): Builder
    {
        return $query->whereIn('visibility', Visibility::visibleToMembers());
    }

    /**
     * Scope to get content visible to a specific user.
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        if (! $user) {
            /** @phpstan-ignore method.notFound */
            return $query->public();
        }

        // If user can view private content, show all
        if ($user->can($this->getViewPrivatePermission())) {
            return $query;
        }

        // Show public and members content, plus user's own content
        return $query->where(function ($q) use ($user) {
            $q->whereIn('visibility', Visibility::visibleToMembers());

            // Add user's own content
            if (isset($this->user_id)) {
                $q->orWhere('user_id', $user->id);
            } elseif (isset($this->owner_id)) {
                $q->orWhere('owner_id', $user->id);
            }
        });
    }
}
