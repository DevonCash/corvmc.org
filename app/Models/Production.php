<?php

namespace App\Models;

use App\Data\LocationData;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use Spatie\Tags\HasTags;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Reportable;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property \Illuminate\Support\Carbon|null $doors_time
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData|null $location
 * @property string|null $ticket_url
 * @property string|null $ticket_price
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $manager_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\ModelFlags\Models\Flag> $flags
 * @property-read int|null $flags_count
 * @property-read string $date_range
 * @property-read float $duration
 * @property-read int $estimated_duration
 * @property-read mixed $genres
 * @property-read mixed $poster_large_url
 * @property-read mixed $poster_optimized_url
 * @property-read mixed $poster_thumb_url
 * @property-read mixed $poster_url
 * @property-read string $ticket_price_display
 * @property-read string $venue_details
 * @property-read string $venue_name
 * @property-read \App\Models\User $manager
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Band> $performers
 * @property-read int|null $performers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $reports
 * @property-read int|null $reports_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \Spatie\Tags\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production dateRange($range)
 * @method static \Database\Factories\ProductionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production flagged(\BackedEnum|string $name)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production notFlagged(\BackedEnum|string $name)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production publishedPast()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production publishedToday()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production publishedUpcoming()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production venue($venueType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereDoorsTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereManagerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereTicketPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereTicketUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withAllTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withAnyTagsOfAnyType($tags)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withAnyTagsOfType(array|string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Production withoutTrashed()
 * @mixin \Eloquent
 */
class Production extends Model implements Eventable, HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia, LogsActivity, Reportable, SoftDeletes;
    
    // Report configuration
    protected static int $reportThreshold = 3;
    protected static bool $reportAutoHide = false;
    protected static string $reportableTypeName = 'Production';

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'doors_time',
        'location',
        'ticket_url',
        'ticket_price',
        'status',
        'published_at',
        'manager_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'doors_time' => 'datetime',
        'published_at' => 'datetime',
        'location' => LocationData::class,
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function performers()
    {
        return $this->belongsToMany(Band::class, 'production_bands', 'production_id', 'band_profile_id')
            ->withPivot('order', 'set_length')
            ->orderBy('production_bands.order')
            ->withTimestamps();
    }

    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('poster')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile()
            ->onlyKeepLatest(1)
            ->useFallbackUrl('/images/default-poster.png');
    }
    public function registerMediaConversions(?Media $media = null): void
    {
        // Thumbnail for lists and cards (8.5:11 aspect ratio)
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(258) // 8.5:11 ratio (200 * 1.294)
            ->crop('crop-center')
            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('poster');
        
        // Medium size for event listings (8.5:11 aspect ratio)
        $this->addMediaConversion('medium')
            ->width(400)
            ->height(517) // 8.5:11 ratio (400 * 1.294)
            ->crop('crop-center')
            ->quality(85)
            ->performOnCollections('poster');
        
        // Large size for event detail pages (8.5:11 aspect ratio)
        $this->addMediaConversion('large')
            ->width(600)
            ->height(776) // 8.5:11 ratio (600 * 1.294)
            ->crop('crop-center')
            ->quality(80)
            ->performOnCollections('poster');
        
        // Optimized original for high-res displays (8.5:11 aspect ratio)
        $this->addMediaConversion('optimized')
            ->width(850)
            ->height(1100) // Exact 8.5:11 ratio
            ->crop('crop-center')
            ->quality(75)
            ->performOnCollections('poster');
    }

    public function getPosterUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'medium') ?: 'https://picsum.photos/400/517?random=' . $this->id;
    }

    public function getPosterThumbUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'thumb') ?: 'https://picsum.photos/200/258?random=' . $this->id;
    }

    public function getPosterLargeUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'large') ?: 'https://picsum.photos/600/776?random=' . $this->id;
    }

    public function getPosterOptimizedUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'optimized') ?: 'https://picsum.photos/850/1100?random=' . $this->id;
    }

    /**
     * Check if a user is the manager of this production.
     */
    public function isManageredBy(User $user): bool
    {
        return $this->manager_id === $user->id;
    }

    /**
     * Get formatted date range for the production.
     */
    public function getDateRangeAttribute(): string
    {
        if ($this->start_time && $this->end_time) {
            if ($this->start_time->isSameDay($this->end_time)) {
                return $this->start_time->format('M j, Y g:i A') . ' - ' . $this->end_time->format('g:i A');
            }

            return $this->start_time->format('M j, Y g:i A') . ' - ' . $this->end_time->format('M j, Y g:i A');
        }

        return $this->start_time ? $this->start_time->format('M j, Y g:i A') : 'TBD';
    }

    /**
     * Check if production is published.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Check if production is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->start_time && $this->start_time->isFuture();
    }

    /**
     * Scope to get published upcoming productions.
     */
    public function scopePublishedUpcoming($query)
    {
        return $query->where('published_at', '<=', now())
            ->whereNotNull('published_at')
            ->where('start_time', '>', now())
            ->orderBy('start_time');
    }

    /**
     * Scope to get published past productions.
     */
    public function scopePublishedPast($query)
    {
        return $query->where('published_at', '<=', now())
            ->whereNotNull('published_at')
            ->where('start_time', '<', now())
            ->orderBy('start_time', 'desc');
    }

    /**
     * Scope to get published productions happening today.
     */
    public function scopePublishedToday($query)
    {
        return $query->where('published_at', '<=', now())
            ->whereNotNull('published_at')
            ->where('start_time', '>=', now()->startOfDay())
            ->where('start_time', '<=', now()->endOfDay())
            ->orderBy('start_time');
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $range)
    {
        switch ($range) {
            case 'this_week':
                return $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
            case 'this_month':
                return $query->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()]);
            case 'next_month':
                return $query->whereBetween('start_time', [now()->addMonth()->startOfMonth(), now()->addMonth()->endOfMonth()]);
            default:
                return $query;
        }
    }

    /**
     * Scope to filter by venue type.
     */
    public function scopeVenue($query, $venueType)
    {
        switch ($venueType) {
            case 'cmc':
                return $query->where(function ($q) {
                    $q->whereNull('location->is_external')->orWhere('location->is_external', false);
                });
            case 'external':
                return $query->where('location->is_external', true);
            default:
                return $query;
        }
    }

    /**
     * Get the total estimated duration of the production.
     */
    public function getEstimatedDurationAttribute(): int
    {
        return $this->performers()->sum('production_bands.set_length') ?: 0;
    }

    /**
     * Check if this production is at an external venue.
     */
    public function isExternalVenue(): bool
    {
        return $this->location?->isExternal() ?? false;
    }

    /**
     * Get the venue display name.
     */
    public function getVenueNameAttribute(): string
    {
        return $this->location?->getVenueName() ?? 'Corvallis Music Collective';
    }

    /**
     * Get the full venue details for display.
     */
    public function getVenueDetailsAttribute(): string
    {
        return $this->location?->getVenueDetails() ?? 'Corvallis Music Collective';
    }

    /**
     * Check if tickets are available for this production.
     */
    public function hasTickets(): bool
    {
        return ! empty($this->ticket_url);
    }

    /**
     * Get the ticket URL with validation.
     */
    public function getTicketUrlAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Ensure URL has a protocol
        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return 'https://' . $value;
        }

        return $value;
    }

    /**
     * Check if this production is NOTAFLOF (No One Turned Away For Lack of Funds).
     */
    public function isNotaflof(): bool
    {
        return $this->hasFlag('notaflof');
    }

    /**
     * Set NOTAFLOF flag.
     */
    public function setNotaflof(bool $notaflof = true): self
    {
        if ($notaflof) {
            $this->flag('notaflof');
        } else {
            $this->unflag('notaflof');
        }

        return $this;
    }

    /**
     * Get formatted ticket price display.
     */
    public function getTicketPriceDisplayAttribute(): string
    {
        if (! $this->hasTickets()) {
            return 'Free';
        }

        $price = $this->ticket_price ? '$' . number_format($this->ticket_price, 2) : 'Ticketed';

        if ($this->isNotaflof()) {
            $price .= ' (NOTAFLOF)';
        }

        return $price;
    }

    /**
     * Check if this is a free event.
     */
    public function isFree(): bool
    {
        return ! $this->hasTickets() || ($this->ticket_price === null || $this->ticket_price == 0);
    }

    /**
     * Get the production time as a Period object.
     */
    public function getPeriod(): ?Period
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        return Period::make(
            $this->start_time,
            $this->end_time,
            Precision::MINUTE()
        );
    }

    /**
     * Check if this production overlaps with another period.
     */
    public function overlapsWith(Period $period): bool
    {
        $thisPeriod = $this->getPeriod();

        if (! $thisPeriod) {
            return false;
        }

        return $thisPeriod->overlapsWith($period);
    }

    /**
     * Check if this production touches another period (adjacent periods).
     */
    public function touchesWith(Period $period): bool
    {
        $thisPeriod = $this->getPeriod();

        if (! $thisPeriod) {
            return false;
        }

        return $thisPeriod->touchesWith($period);
    }

    /**
     * Get the duration of the production in hours.
     */
    public function getDurationAttribute(): float
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        return $this->start_time->diffInMinutes($this->end_time) / 60;
    }

    /**
     * Check if this production uses the CMC practice space (not external venue).
     */
    public function usesPracticeSpace(): bool
    {
        return ! $this->isExternalVenue();
    }

    /**
     * Set a default location if none exists.
     */
    protected static function booted(): void
    {
        static::creating(function (Production $production) {
            if (! $production->location) {
                $production->location = LocationData::cmc();
            }
        });
    }

    /**
     * Convert production to calendar event.
     */
    public function toCalendarEvent(): CalendarEvent
    {
        return \App\Facades\CalendarService::productionToCalendarEvent($this);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'start_time', 'end_time', 'location', 'status', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Production {$eventName}");
    }
}
