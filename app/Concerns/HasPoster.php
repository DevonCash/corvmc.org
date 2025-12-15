<?php

namespace App\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait for models that have a poster image.
 *
 * This trait provides poster media collection configuration,
 * multiple size conversions, and accessor methods for URLs.
 *
 * Models using this trait should use Spatie's HasMedia/InteractsWithMedia.
 */
trait HasPoster
{
    /**
     * Register the poster media collection.
     * Models can override this method to customize the collection.
     */
    public function registerPosterMediaCollection(): void
    {
        $this->addMediaCollection('poster')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile()
            ->onlyKeepLatest(1)
            ->useFallbackUrl($this->getPosterFallbackUrl());
    }

    /**
     * Register poster media conversions.
     * Models can override this method to customize conversions.
     */
    public function registerPosterMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 200, 258)
            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('poster');

        $this->addMediaConversion('medium')
            ->fit(Fit::Contain, 400, 517)
            ->quality(85)
            ->performOnCollections('poster');

        $this->addMediaConversion('large')
            ->fit(Fit::Contain, 600, 776)
            ->quality(80)
            ->performOnCollections('poster');

        $this->addMediaConversion('optimized')
            ->fit(Fit::Contain, 850, 1100)
            ->quality(75)
            ->performOnCollections('poster');
    }

    /**
     * Get the fallback URL for the poster when no image is uploaded.
     * Models can override this method to provide custom fallback logic.
     */
    protected function getPosterFallbackUrl(): string
    {
        return '/images/default-poster.png';
    }

    /**
     * Get the placeholder URL for the poster when no image is uploaded.
     * Models can override this method to provide custom placeholder logic.
     */
    protected function getPosterPlaceholderUrl(int $width, int $height): string
    {
        return "https://picsum.photos/{$width}/{$height}?random=".$this->id;
    }

    /**
     * Get poster URL (medium size).
     */
    public function getPosterUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('poster', 'medium') ?: $this->getPosterPlaceholderUrl(400, 517);
    }

    /**
     * Get poster thumbnail URL.
     */
    public function getPosterThumbUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('poster', 'thumb') ?: $this->getPosterPlaceholderUrl(200, 258);
    }

    /**
     * Get poster large URL.
     */
    public function getPosterLargeUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('poster', 'large') ?: $this->getPosterPlaceholderUrl(600, 776);
    }

    /**
     * Get poster optimized URL.
     */
    public function getPosterOptimizedUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('poster', 'optimized') ?: $this->getPosterPlaceholderUrl(850, 1100);
    }
}
