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
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use App\Traits\Reportable;
use App\Traits\Revisionable;
use Illuminate\Database\Eloquent\Attributes\Scope;

/**
 * Represents a band in the application.
 * 
 * It includes details about the band's name, bio, links, and contact information.
 * The band can have multiple members and exactly one owner.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $hometown
 * @property int|null $owner_id
 * @property string $name
 * @property string|null $bio
 * @property array<array-key, mixed>|null $links
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData|null $contact
 * @property string $visibility
 * @property array<array-key, mixed>|null $embeds
 * @property string|null $slug
 * @property string $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\ModelFlags\Models\Flag> $flags
 * @property-read int|null $flags_count
 * @property-read mixed $avatar_large_url
 * @property-read mixed $avatar_optimized_url
 * @property-read mixed $avatar_thumb_url
 * @property-read mixed $avatar_url
 * @property-read mixed $genres
 * @property-read mixed $influences
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BandMember> $memberships
 * @property-read int|null $memberships_count
 * @property-read \App\Models\User|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $pendingRevisions
 * @property-read int|null $pending_revisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $reports
 * @property-read int|null $reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $revisions
 * @property-read int|null $revisions_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \Spatie\Tags\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Database\Factories\BandFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band flagged(\BackedEnum|string $name)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band notFlagged(\BackedEnum|string $name)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereEmbeds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereHometown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereLinks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band withAnyTagsOfType(array|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Band withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @mixin \Eloquent
 */
class Band extends Model implements HasMedia
{
    use HasFactory, HasFlags, HasSlug, HasTags, InteractsWithMedia, LogsActivity, Reportable, Revisionable;

    // Report configuration
    protected static int $reportThreshold = 4;
    protected static bool $reportAutoHide = false;
    protected static string $reportableTypeName = 'Band Profile';

    protected $table = 'band_profiles';

    protected $fillable = [
        'name',
        'slug',
        'bio',
        'links',
        'contact',
        'embeds',
        'hometown',
        'owner_id',
        'visibility',
        'status',
    ];

    protected $casts = [
        'links' => 'array',
        'contact' => ContactData::class,
        'embeds' => 'array',
    ];
    
    /**
     * Auto-approval mode for bands - personal content
     */
    protected string $autoApprove = 'personal';
    
    /**
     * Get revision exempt fields for bands.
     */
    protected function getRevisionExemptFields(): array
    {
        return [
            'owner_id', // Don't allow changing ownership through revisions
            'slug', // Slug changes are system-managed
        ];
    }

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
        return $this->belongsToMany(User::class, 'band_profile_members', 'band_profile_id', 'user_id')
            ->withPivot('role', 'position', 'name', 'status', 'invited_at')
            ->withTimestamps();
    }

    public function memberships()
    {
        return $this->hasMany(BandMember::class, 'band_profile_id');
    }

    public function membership(?User $user): ?BandMember
    {
        if (!$user) return null;
        return $this->memberships()->for($user)->first();
    }

    public function activeMembers()
    {
        return $this->memberships()->active();
    }

    public function pendingInvitations()
    {
        return $this->memberships()->invited();
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
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Remove a member from the band.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
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

    public function getUserRole(User $user): ?string
    {
        $membership = $this->memberships()->for($user)->first();
        return $membership ? $membership->role : null;
    }
}
