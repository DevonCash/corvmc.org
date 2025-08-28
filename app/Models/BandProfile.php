<?php

namespace App\Models;

use App\Data\ContactData;
use App\Models\Scopes\OwnedBandsScope;
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

/**
 * Represents a band profile in the application.
 * It includes details about the band's name, bio, links, and contact information.
 * The band can have multiple members and exactly one owner.
 */
class BandProfile extends Model implements HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'name',
        'bio',
        'links',
        'contact',
        'embeds',
        'hometown',
        'owner_id',
        'visibility',
    ];

    protected $casts = [
        'links' => 'array',
        'contact' => ContactData::class,
        'embeds' => 'array',
    ];

    /**
     * The "booted" method of the model.
     * Apply global scope to filter out touring bands by default.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new OwnedBandsScope);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'band_profile_members')
            ->withPivot('role', 'position', 'name', 'status', 'invited_at')
            ->withTimestamps();
    }

    public function allMembers()
    {
        return $this->hasMany(\App\Models\BandProfileMember::class);
    }

    public function activeMembers()
    {
        return $this->hasMany(BandProfileMember::class)->where('status', 'active');
    }

    public function pendingInvitations()
    {
        return $this->belongsToMany(User::class, 'band_profile_members')
            ->withPivot('role', 'position', 'name', 'status', 'invited_at')
            ->wherePivot('status', 'invited')
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre');
    }

    public function getInfluencesAttribute()
    {
        return $this->tagsWithType('influence');
    }

    public function getAvatarUrlAttribute()
    {
        return $this->getFirstMediaUrl('avatar', 'medium') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=400';
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
            ->width(150)
            ->height(150)
            ->crop(150, 150, CropPosition::Center)
            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('avatar');

        // Medium size for band cards and directory
        $this->addMediaConversion('medium')
            ->width(400)
            ->height(400)
            ->crop(400, 400, CropPosition::Center)
            ->quality(85)
            ->performOnCollections('avatar');

        // Large size for band profile pages
        $this->addMediaConversion('large')
            ->width(800)
            ->height(800)
            ->crop(800, 800, CropPosition::Center)
            ->quality(80)
            ->performOnCollections('avatar');

        // Optimized original for high-res displays
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(1200)
            ->crop(1200, 1200, CropPosition::Center)
            ->quality(75)
            ->performOnCollections('avatar');
    }

    public function getAvatarThumbUrlAttribute()
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=150';
    }

    public function getAvatarLargeUrlAttribute()
    {
        return $this->getFirstMediaUrl('avatar', 'large') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=800';
    }

    public function getAvatarOptimizedUrlAttribute()
    {
        return $this->getFirstMediaUrl('avatar', 'optimized') ?: 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&size=1200';
    }

    /**
     * Check if a user is the owner of this band.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Check if a user is a member of this band.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->wherePivot('user_id', $user->id)->exists();
    }

    /**
     * Check if a user is an admin of this band.
     */
    public function hasAdmin(User $user): bool
    {
        return $this->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Get a user's role in this band.
     */
    public function getUserRole(User $user): ?string
    {
        if ($this->owner_id === $user->id) {
            return 'owner';
        }

        $membership = $this->members()
            ->wherePivot('user_id', $user->id)
            ->first();

        return $membership?->pivot->role;
    }

    /**
     * Get a user's position in this band.
     */
    public function getUserPosition(User $user): ?string
    {
        $membership = $this->members()
            ->wherePivot('user_id', $user->id)
            ->first();

        return $membership?->pivot->position;
    }

    /**
     * Add a member to the band with optional role and position.
     *
     * @deprecated Use BandService::addMember() instead
     */
    public function addMember(User $user, string $role = 'member', ?string $position = null): void
    {
        app(\App\Services\BandService::class)->addMember($this, $user, $role, $position);
    }

    /**
     * Invite a user to join the band.
     *
     * @deprecated Use BandService::inviteMember() instead
     */
    public function inviteMember(User $user, string $role = 'member', ?string $position = null): void
    {
        app(\App\Services\BandService::class)->inviteMember($this, $user, $role, $position);
    }

    /**
     * Check if a user has been invited to this band.
     *
     * @deprecated Use BandService::hasInvitedUser() instead
     */
    public function hasInvitedUser(User $user): bool
    {
        return app(\App\Services\BandService::class)->hasInvitedUser($this, $user);
    }

    /**
     * Accept an invitation to join the band.
     *
     * @deprecated Use BandService::acceptInvitation() instead
     */
    public function acceptInvitation(User $user): void
    {
        app(\App\Services\BandService::class)->acceptInvitation($this, $user);
    }

    /**
     * Decline an invitation to join the band.
     *
     * @deprecated Use BandService::declineInvitation() instead
     */
    public function declineInvitation(User $user): void
    {
        app(\App\Services\BandService::class)->declineInvitation($this, $user);
    }

    /**
     * Remove a member from the band.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    /**
     * Update a member's role in the band.
     */
    public function updateMemberRole(User $user, string $role): void
    {
        $this->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Update a member's position in the band.
     */
    public function updateMemberPosition(User $user, ?string $position): void
    {
        $this->members()->updateExistingPivot($user->id, ['position' => $position]);
    }

    /**
     * Get all bands including touring bands.
     */
    public static function withTouringBands()
    {
        return static::withoutGlobalScope(OwnedBandsScope::class);
    }

    /**
     * Get the primary link for this band profile.
     * Returns public profile if visible, otherwise first external link.
     */
    public function primaryLink(): ?array
    {
        // Priority 1: Public profile
        if ($this->visibility === 'public') {
            return [
                'url' => route('bands.show', $this),
                'text' => 'View Profile',
                'icon' => 'tabler:user',
                'external' => false,
            ];
        }

        // Priority 2: First external link
        if (!empty($this->links) && is_array($this->links)) {
            $firstLink = reset($this->links);
            if ($firstLink) {
                return [
                    'url' => $firstLink,
                    'text' => 'Visit Website',
                    'icon' => 'tabler:external-link',
                    'external' => true,
                ];
            }
        }

        return null;
    }

    /**
     * Check if the band profile is visible to the given user.
     */
    public function isVisible(?User $user = null): bool
    {
        if (! $user) {
            // Only public profiles are visible to guests
            return $this->visibility === 'public';
        }

        // Band members and owner can always see the profile
        if ($this->owner_id === $user->id || $this->members->contains($user)) {
            return true;
        }

        // Staff can see all profiles if they have permission
        if ($user->can('view private band profiles') || $user->can('view private member profiles')) {
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'bio', 'hometown', 'visibility'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Band profile {$eventName}")
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->logExcept($this->visibility === 'private' ? ['bio', 'hometown'] : []); // Don't log content for private bands
    }
}
