<?php

namespace App\Models;

use App\Data\ContactData;
use App\Enums\Visibility;
use App\Models\Scopes\OwnedBandsScope;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Represents a band in the application.
 *
 * It includes details about the band's name, bio, links, and contact information.
 * The band can have multiple members and exactly one owner.
 */
class Band extends ContentModel
{
    use HasSlug;

    // Report configuration
    protected static int $reportThreshold = 4;

    protected static bool $reportAutoHide = false;

    protected static string $reportableTypeName = 'Band Profile';

    // Activity logging configuration
    protected static array $loggedFields = ['name', 'bio', 'hometown', 'visibility'];

    protected static string $logTitle = 'Band profile';

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
        'visibility' => Visibility::class,
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

    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
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
        if (! $user) {
            return null;
        }

        return $this->memberships()->for($user)->first();
    }


    public function activeMembers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->memberships()->active();
    }

    public function pendingInvitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->memberships()->invited();
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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

    /**
     * Register additional media conversions specific to bands.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        parent::registerMediaConversions($media);

        // Large size for band profile pages
        $this->addMediaConversion('large')
            ->height(800)
            ->width(800)
            ->quality(80)
            ->performOnCollections('avatar');

        // Optimized original for high-res displays
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(1200)
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
        if (! empty($this->links) && is_array($this->links)) {
            $links = $this->links;
            $firstLink = reset($links);
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
     * Override trait method to include band member logic.
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
        return match ((string) $this->visibility) {
            'public' => true,
            'members' => true, // All logged-in users are considered members
            'private' => false,
            default => false,
        };
    }

    /**
     * Override trait method to define band ownership logic.
     */
    protected function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id || $this->members->contains($user);
    }

    /**
     * Override trait method for band-specific permission.
     */
    protected function getViewPrivatePermission(): string
    {
        return 'view private band profiles';
    }

    public function getUserRole(User $user): ?string
    {
        $membership = $this->memberships()->for($user)->first();

        return $membership ? $membership->role : null;
    }
}
