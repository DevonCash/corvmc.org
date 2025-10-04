<?php

namespace App\Models;

use App\Data\LocationData;
use App\Models\ContentModel;
use App\Concerns\HasTimePeriod;
use App\Concerns\HasPublishing;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\CropPosition;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Period\Period;

class Production extends ContentModel implements Eventable
{
    use SoftDeletes, HasTimePeriod, HasPublishing;

    // Report configuration
    protected static int $reportThreshold = 3;
    protected static bool $reportAutoHide = false;
    protected static string $reportableTypeName = 'Production';

    // Activity logging configuration
    protected static array $loggedFields = ['title', 'description', 'start_time', 'end_time', 'status', 'visibility'];
    protected static string $logTitle = 'Production';

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

    /**
     * Space reservation for this production (if using practice space).
     */
    public function spaceReservation()
    {
        return $this->morphOne(ProductionReservation::class, 'reservable');
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
            ->crop(200, 258, CropPosition::Center)

            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('poster');

        // Medium size for event listings (8.5:11 aspect ratio)
        $this->addMediaConversion('medium')
            ->crop(400, 517, CropPosition::Center)
            ->quality(85)
            ->performOnCollections('poster');

        // Large size for event detail pages (8.5:11 aspect ratio)
        $this->addMediaConversion('large')
            ->crop(600, 776, CropPosition::Center)
            ->quality(80)
            ->performOnCollections('poster');

        // Optimized original for high-res displays (8.5:11 aspect ratio)
        $this->addMediaConversion('optimized')
            ->crop(850, 1100, CropPosition::Center)
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
     * Get fields that should not trigger revision workflow.
     * Status and published_at changes are workflow states, not content changes.
     */
    protected function getRevisionExemptFields(): array
    {
        return ['status', 'published_at'];
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
     * Get the production as a Period object.
     * 
     * @deprecated Use createPeriod() instead
     */
    public function getPeriod(): ?Period
    {
        return $this->createPeriod();
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

        static::saved(function (Production $production) {
            // Sync space reservation if using practice space
            if ($production->usesPracticeSpace()) {
                $production->syncSpaceReservation();
            }
        });
    }

    /**
     * Create or update the space reservation for this production.
     */
    protected function syncSpaceReservation(): void
    {
        // Default: 2 hours setup before event, 1 hour breakdown after
        $reservedAt = $this->start_time->copy()->subHours(2);
        $reservedUntil = $this->end_time?->copy()->addHour() ?? $this->start_time->copy()->addHours(3);

        $this->spaceReservation()->updateOrCreate(
            [],
            [
                'type' => ProductionReservation::class,
                'reserved_at' => $reservedAt,
                'reserved_until' => $reservedUntil,
                'status' => $this->status ?? 'confirmed',
                'notes' => "Setup/breakdown for production: {$this->title}",
            ]
        );
    }

    /**
     * Convert production to calendar event.
     */
    public function toCalendarEvent(): CalendarEvent
    {
        return \App\Facades\CalendarService::productionToCalendarEvent($this);
    }
}
