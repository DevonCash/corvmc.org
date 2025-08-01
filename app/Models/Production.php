<?php

namespace App\Models;

use App\Data\LocationData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\Tags\HasTags;

class Production extends Model implements HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia, SoftDeletes;

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

    public function reservation()
    {
        return $this->hasOne(Reservation::class);
    }

    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre');
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
        return null;
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
        return !empty($this->ticket_url);
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
        if (!str_starts_with($value, 'http://') && !str_starts_with($value, 'https://')) {
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
        if (!$this->hasTickets()) {
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
        return !$this->hasTickets() || ($this->ticket_price === null || $this->ticket_price == 0);
    }

    /**
     * Set a default location if none exists.
     */
    protected static function booted(): void
    {
        static::creating(function (Production $production) {
            if (!$production->location) {
                $production->location = LocationData::cmc();
            }
        });
    }
}
