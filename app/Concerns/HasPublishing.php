<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasPublishing
{
    /**
     * Initialize the trait.
     */
    public function initializeHasPublishing(): void
    {
        $this->casts['published_at'] = 'datetime';
    }

    /**
     * Check if the content is published.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Check if the content is scheduled for future publishing.
     */
    public function isScheduledForPublishing(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /**
     * Check if the content is unpublished.
     */
    public function isUnpublished(): bool
    {
        return $this->published_at === null;
    }

    /**
     * Publish the content immediately.
     */
    public function publish(): self
    {
        $this->update(['published_at' => now()]);
        return $this;
    }

    /**
     * Schedule the content for publishing at a specific time.
     */
    public function schedulePublishing(\DateTimeInterface $publishAt): self
    {
        $this->update(['published_at' => $publishAt]);
        return $this;
    }

    /**
     * Unpublish the content.
     */
    public function unpublish(): self
    {
        $this->update(['published_at' => null]);
        return $this;
    }

    /**
     * Scope to get only published content.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope to get only unpublished content.
     */
    public function scopeUnpublished(Builder $query): Builder
    {
        return $query->whereNull('published_at');
    }

    /**
     * Scope to get content scheduled for future publishing.
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
                    ->where('published_at', '>', now());
    }
}