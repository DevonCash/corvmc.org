<?php

namespace CorvMC\Events\Concerns;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;

trait HasPublishing
{
    public function getPublicationDatetimeField(): string
    {
        return static::$publicationDatetimeField ?? 'published_at';
    }

    /**
     * Initialize the trait.
     */
    public function initializeHasPublishing(): void
    {
        $this->casts[$this->getPublicationDatetimeField()] = 'datetime';
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

    public function canPublish(): bool
    {
        return true;
    }

    /**
     * Publish the content immediately.
     */
    public function publish(): self
    {
        if (! $this->canPublish()) {
            throw new \Exception('This content cannot be published.');
        }

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

    /**
     * Get the start time field name for time-based published scopes.
     */
    protected function getStartTimeField(): string
    {
        return static::$startTimeField ?? 'start_datetime';
    }

    /**
     * Get statuses to exclude from published time scopes.
     */
    protected function getExcludedStatuses(): array
    {
        return static::$excludedStatuses ?? [];
    }

    /**
     * Scope to get published content that starts in the future (upcoming).
     */
    public function scopePublishedUpcoming(Builder $query): Builder
    {
        $startField = $this->getStartTimeField();
        $query = $query->published()
            ->where($startField, '>', now())
            ->orderBy($startField);

        $excludedStatuses = $this->getExcludedStatuses();
        if (! empty($excludedStatuses)) {
            $query->whereNotIn('status', $excludedStatuses);
        }

        return $query;
    }

    /**
     * Scope to get published content that already started (past).
     */
    public function scopePublishedPast(Builder $query): Builder
    {
        $startField = $this->getStartTimeField();
        $query = $query->published()
            ->where($startField, '<', now())
            ->orderBy($startField, 'desc');

        $excludedStatuses = $this->getExcludedStatuses();
        if (! empty($excludedStatuses)) {
            $query->whereNotIn('status', $excludedStatuses);
        }

        return $query;
    }

    /**
     * Scope to get published content happening today.
     */
    public function scopePublishedToday(Builder $query): Builder
    {
        $startField = $this->getStartTimeField();
        $query = $query->published()
            ->where($startField, '>=', now()->startOfDay())
            ->where($startField, '<=', now()->endOfDay())
            ->orderBy($startField);

        $excludedStatuses = $this->getExcludedStatuses();
        if (! empty($excludedStatuses)) {
            $query->whereNotIn('status', $excludedStatuses);
        }

        return $query;
    }

    public function getPublicationStatusAttribute(): PublicationStatus
    {
        $publishedAtField = $this[$this->getPublicationDatetimeField()];
        if (is_null($publishedAtField)) {
            return PublicationStatus::Draft;
        } elseif ($publishedAtField->isFuture()) {
            return PublicationStatus::Scheduled;
        } else {
            return PublicationStatus::Published;
        }
    }
}
