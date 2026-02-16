<?php

namespace CorvMC\Membership\Models;

use App\Models\User;
use App\Settings\MemberDirectorySettings;
use CorvMC\Membership\Data\ContactData;
use CorvMC\Moderation\Enums\Visibility;
use CorvMC\Moderation\Models\ContentModel;
use CorvMC\Moderation\Models\Scopes\MemberVisibilityScope;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Represents a member profile in the application.
 *
 * It includes details about the user, their bio, links, and contact information.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property int $user_id
 * @property string|null $hometown
 * @property string|null $bio
 * @property array<array-key, mixed>|null $links
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData|null $contact
 * @property Visibility $visibility
 * @property array<array-key, mixed>|null $embeds
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\ModelFlags\Models\Flag> $flags
 * @property-read int|null $flags_count
 * @property-read string|null $avatar
 * @property-read string $avatar_large_url
 * @property-read string $avatar_optimized_url
 * @property-read string $avatar_thumb_url
 * @property-read string $avatar_url
 * @property-read mixed $genres
 * @property-read array $influences
 * @property-read string $name
 * @property-read array $skills
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \CorvMC\Moderation\Models\Report> $pendingReports
 * @property-read int|null $pending_reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \CorvMC\Moderation\Models\Revision> $pendingRevisions
 * @property-read int|null $pending_revisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \CorvMC\Moderation\Models\Report> $reports
 * @property-read int|null $reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \CorvMC\Moderation\Models\Revision> $revisions
 * @property-read int|null $revisions_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \Spatie\Tags\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \CorvMC\Moderation\Models\Report> $upheldReports
 * @property-read int|null $upheld_reports_count
 * @property-read User $user
 *
 * @method static \Database\Factories\MemberProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile flagged(\BackedEnum|string $name)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile notFlagged(\BackedEnum|string $name)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile visibleTo(?User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile visibleToMembers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereEmbeds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereHometown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereLinks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile whereVisibility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile withAnyTagsOfType(array|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile withFlag(string $flag)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MemberProfile withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 *
 * @mixin \Eloquent
 */
class MemberProfile extends ContentModel
{
    // Report configuration
    protected static int $reportThreshold = 5;

    protected static bool $reportAutoHide = false;

    protected static string $reportableTypeName = 'Member Profile';

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

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\MemberProfileFactory
    {
        return \Database\Factories\MemberProfileFactory::new();
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
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
        'visibility' => Visibility::class,
    ];

    public function getSanitizedBioAttribute(): ?string
    {
        if (empty($this->bio)) {
            return null;
        }

        return clean($this->bio);
    }

    public function getAvatarAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('avatar');

        return $url ?: 'https://ui-avatars.com/api/?name='.urlencode($this->user->name).'&size=200';
    }

    public function getNameAttribute(): string
    {
        return $this->user->name;
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'medium') ?: 'https://ui-avatars.com/api/?name='.urlencode($this->user->name).'&size=300';
    }

    public function getAvatarThumbUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: 'https://ui-avatars.com/api/?name='.urlencode($this->user->name).'&size=100';
    }

    public function getAvatarLargeUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'large') ?: 'https://ui-avatars.com/api/?name='.urlencode($this->user->name).'&size=600';
    }

    public function getAvatarOptimizedUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar', 'optimized') ?: 'https://ui-avatars.com/api/?name='.urlencode($this->user->name).'&size=1200';
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->user_id === $user->id;
    }

    public function isComplete(): bool
    {
        // Check if profile has key information filled out
        return ! empty($this->bio) &&
            ! empty($this->skills);
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
