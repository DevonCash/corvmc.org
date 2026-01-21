<?php

namespace CorvMC\Membership\Models;

use CorvMC\Moderation\Concerns\Revisionable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

enum StaffProfileType: string
{
    case Board = 'board';
    case Staff = 'staff';
}

/**
 * @property int $id
 * @property string $name
 * @property int $user_id
 * @property string|null $title
 * @property string|null $bio
 * @property \App\Models\StaffProfileType $type
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $email
 * @property array<array-key, mixed>|null $social_links
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $profile_image_thumb_url
 * @property-read string|null $profile_image_url
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $pendingRevisions
 * @property-read int|null $pending_revisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $revisions
 * @property-read int|null $revisions_count
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile board()
 * @method static \Database\Factories\StaffProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile staff()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereSocialLinks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffProfile whereUserId($value)
 *
 * @mixin \Eloquent
 */
class StaffProfile extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Revisionable;

    /**
     * Auto-approval mode for staff profiles - organizational content never auto-approves
     */
    protected string $autoApprove = 'never';

    protected $fillable = [
        'name',
        'title',
        'bio',
        'type',
        'sort_order',
        'is_active',
        'email',
        'social_links',
        'user_id',
        'type',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_active' => 'boolean',
        'type' => StaffProfileType::class,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10);

        $this->addMediaConversion('avatar')
            ->width(300)
            ->height(300)
            ->sharpen(10);
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('profile_image', 'avatar') ?: null;
    }

    public function getProfileImageThumbUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('profile_image', 'thumb') ?: null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeBoard($query)
    {
        return $query->where('type', 'board');
    }

    public function scopeStaff($query)
    {
        return $query->where('type', 'staff');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
