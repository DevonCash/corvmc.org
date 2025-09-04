<?php

namespace App\Models;

use App\Data\ContactData;
use App\Models\Scopes\MemberVisibilityScope;
use App\Settings\MemberDirectorySettings;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\Tags\HasTags;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\CropPosition;
use App\Traits\Reportable;

/**
 * Represents a member profile in the application.
 * It includes details about the user, their bio, links, and contact information.
 */
class MemberProfile extends Model implements HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia, LogsActivity, Reportable;
    
    // Report configuration
    protected static int $reportThreshold = 5;
    protected static bool $reportAutoHide = false;
    protected static string $reportableTypeName = 'Member Profile';

    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->user->name;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new MemberVisibilityScope);
    }

    protected $fillable = [
        'user_id',
        'bio',
        'hometown',
        'links',
        'contact',
        'embeds',
        'visibility',
    ];

    protected $casts = [
        'links' => 'array',
        'contact' => ContactData::class,
        'embeds' => 'array',
    ];

    public function getAvatarAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('avatar');

        return $url ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->user->name) . '&size=200';
    }

    public function getNameAttribute(): string
    {
        return $this->user->name;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'medium') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->user->name) . '&size=300';
    }

    public function getAvatarThumbUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->user->name) . '&size=100';
    }

    public function getAvatarLargeUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'large') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->user->name) . '&size=600';
    }

    public function getAvatarOptimizedUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'optimized') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->user->name) . '&size=1200';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        // Check if profile has key information filled out
        return !empty($this->bio) &&
            !empty($this->skills);
    }

    public function isVisible(?User $user = null): bool
    {
        if (! $user) {
            // Only public profiles are visible to guests
            return $this->visibility === 'public';
        }

        // User can always see their own profile
        if ($this->user_id === $user->id) {
            return true;
        }

        // Staff can see all profiles if they have permission
        if ($user->can('view private member profiles')) {
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

    public function getSkillsAttribute(): array
    {
        return $this->tagsWithType('skill')->pluck('name')->toArray();
    }

    public function getInfluencesAttribute(): array
    {
        return $this->tagsWithType('influence')->pluck('name')->toArray();
    }

    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre')->pluck('name')->toArray();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile()
            ->onlyKeepLatest(1);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Thumbnail for lists and small displays
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->crop(100, 100, CropPosition::Center)
            ->quality(90)
            ->sharpen(10);

        // Medium size for member cards and directory
        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->crop(300, 300, CropPosition::Center)
            ->quality(85);

        // Large size for profile pages
        $this->addMediaConversion('large')
            ->width(600)
            ->height(600)
            ->crop(600, 600, CropPosition::Center)
            ->quality(80);

        // Optimized original for high-res displays
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(1200)
            ->crop(1200, 1200, CropPosition::Center)
            ->quality(75);
    }

    public function getAvailableFlags(): array
    {
        $settings = app(MemberDirectorySettings::class);

        return $settings->getAvailableFlags();
    }

    public function getFlagLabel(string $flag): ?string
    {
        $settings = app(MemberDirectorySettings::class);

        return $settings->getFlagLabel($flag);
    }

    public function getActiveFlagsWithLabels(): array
    {
        $availableFlags = $this->getAvailableFlags();
        $activeFlags = [];

        foreach ($availableFlags as $flag => $label) {
            if ($this->hasFlag($flag)) {
                $activeFlags[$flag] = $label;
            }
        }

        return $activeFlags;
    }

    public function scopeWithFlag($query, string $flag)
    {
        return $query->whereHas('flags', function ($q) use ($flag) {
            $q->where('name', $flag);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['bio', 'hometown', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Member profile {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at']) // Don't log if only timestamps changed
            ->logExcept($this->visibility === 'private' ? ['bio', 'hometown'] : []); // Don't log profile content for private profiles
    }
}
