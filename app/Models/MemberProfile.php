<?php

namespace App\Models;

use App\Data\ContactData;
use App\Models\Scopes\MemberVisibilityScope;
use App\Settings\MemberDirectorySettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\Tags\HasTags;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a member profile in the application.
 * It includes details about the user, their bio, links, and contact information.
 */
class MemberProfile extends Model implements HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia, LogsActivity;

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
        'visibility',
    ];

    protected $casts = [
        'links' => 'array',
        'contact' => ContactData::class,
    ];

    public function getAvatarAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('avatar');

        return $url ?: null;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar') ?: 'https://fastly.picsum.photos/id/1012/100/100.jpg?hmac=vuow0o9zubuAYNA_nZKuHb055Vy6pf6df8dUXl-6F2Y';
    }

    public function getAvatarThumbUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: 'https://fastly.picsum.photos/id/1012/100/100.jpg?hmac=vuow0o9zubuAYNA_nZKuHb055Vy6pf6df8dUXl-6F2Y';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10);
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
