<?php

namespace App\Models;

use App\Data\ContactData;
use App\Models\ContentModel;
use App\Models\Scopes\MemberVisibilityScope;
use App\Settings\MemberDirectorySettings;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Represents a member profile in the application.
 * 
 * It includes details about the user, their bio, links, and contact information.
 */
class MemberProfile extends ContentModel
{
    // Report configuration
    protected static int $reportThreshold = 5;
    protected static bool $reportAutoHide = false;
    protected static string $reportableTypeName = 'Member Profile';

    // Activity logging configuration
    protected static array $loggedFields = ['bio', 'hometown', 'visibility', 'pronouns'];
    protected static string $logTitle = 'Member profile';

    /**
     * Auto-approval mode for member profiles - personal content
     */
    protected string $autoApprove = 'personal';

    // Revision configuration - override trait method
    protected function getRevisionExemptFields(): array
    {
        return [
            'user_id', // Don't allow changing ownership through revisions
        ];
    }

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

    /**
     * Override trait method for member profile-specific permission.
     */
    protected function getViewPrivatePermission(): string
    {
        return 'view private member profiles';
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

    /**
     * Register media conversions for member profiles.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        parent::registerMediaConversions($media);

        // Large size for profile pages
        $this->addMediaConversion('large')
            ->width(600)
            ->height(600)
            ->quality(80)
            ->performOnCollections('avatar');

        // Optimized original for high-res displays
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(1200)
            ->quality(75)
            ->performOnCollections('avatar');
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
}
