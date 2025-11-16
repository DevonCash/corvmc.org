<?php

namespace App\Models;

use App\Concerns\HasVisibility;
use App\Concerns\Reportable;
use App\Concerns\Revisionable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\Tags\HasTags;

/**
 * Abstract base class for user-generated content models.
 *
 * Provides common functionality for models that represent content created by users,
 * including media handling, activity logging, reporting, and tagging capabilities.
 */
abstract class ContentModel extends Model implements HasMedia
{
    use HasFactory, HasFlags, HasTags, HasVisibility, InteractsWithMedia, LogsActivity, Reportable, Revisionable;

    // Default report configuration - can be overridden in subclasses
    protected static int $reportThreshold = 4;

    protected static bool $reportAutoHide = false;

    /**
     * Standard media collections for content models.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile()
            ->onlyKeepLatest(1);
    }

    /**
     * Standard media conversions for content models.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Thumbnail for lists and small displays
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->quality(80)
            ->performOnCollections('avatar');

        // Medium size for profile views
        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->quality(85)
            ->performOnCollections('avatar');
    }

    /**
     * The fields that should be logged for this content type.
     */
    protected static array $loggedFields = [];

    /**
     * The content title for activity log descriptions.
     */
    protected static string $logTitle = 'Content';

    /**
     * Standard activity logging configuration for content models.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $fields = $this->getLoggedFields();

        return LogOptions::defaults()
            ->logOnly($fields)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "{$this->getLogTitle()} {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->logExcept($this->isPrivateContent() ? $this->getPrivateContentFields() : []);
    }

    /**
     * Get the fields that should be logged for this content type.
     */
    public function getLoggedFields(): array
    {
        return static::$loggedFields;
    }

    /**
     * Get the content title for activity log descriptions.
     */
    public function getLogTitle(): string
    {
        return static::$logTitle;
    }

    /**
     * Check if this content is private and should have restricted logging.
     */
    protected function isPrivateContent(): bool
    {
        return isset($this->visibility) && $this->visibility === 'private';
    }

    /**
     * Get fields that should not be logged when content is private.
     * Override in subclasses to customize privacy handling.
     */
    protected function getPrivateContentFields(): array
    {
        return ['bio', 'description', 'content'];
    }
}
