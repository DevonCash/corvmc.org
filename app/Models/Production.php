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

class Production extends Model implements Eventable, HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia, LogsActivity, SoftDeletes;

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
        return $this->belongsToMany(BandProfile::class, 'production_bands')
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
            ->singleFile(); // This is key for single poster uploads
    }
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(200)
            ->sharpen(10)
            ->performOnCollections('poster');
    }

    public function getPosterUrlAttribute()
    {
        if ($this->hasMedia('poster')) {
            return $this->getFirstMediaUrl('poster');
        }

        return 'https://picsum.photos/200/258?random=' . $this->id;
    }

    public function getPosterThumbUrlAttribute()
    {
        if ($this->hasMedia('poster')) {
            return $this->getFirstMediaUrl('poster', 'thumb');
        }

        return $this->poster_url;
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
        // Only show productions that use the practice space
        if (! $this->usesPracticeSpace()) {
            return CalendarEvent::make($this)
                ->title('')
                ->start($this->start_time->toISOString())
                ->end($this->end_time->toISOString())
                ->display('none');
        }

        $title = $this->title;

        if (! $this->isPublished()) {
            $title .= ' (Draft)';
        }

        $color = match ($this->status) {
            'pre-production' => '#8b5cf6', // purple
            'production' => '#3b82f6',     // blue
            'completed' => '#10b981',      // green
            'cancelled' => '#ef4444',      // red
            default => '#6b7280',          // gray
        };

        return CalendarEvent::make($this)
            ->title($title)
            ->start($this->start_time)
            ->end($this->end_time)
            ->backgroundColor($color)
            ->textColor('#fff')
            ->extendedProps([
                'type' => 'production',
                'manager_name' => $this->manager->name ?? '',
                'status' => $this->status,
                'venue_name' => $this->venue_name,
                'is_published' => $this->isPublished(),
                'ticket_url' => $this->ticket_url,
            ]);
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
