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
use Spatie\Tags\HasTags;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\Reportable;
use App\Traits\Revisionable;

/**
 * Community Event Model
 * 
 * Represents member-submitted public performances and events
 * for the community calendar platform.
 * 
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property string $venue_name
 * @property string $venue_address
 * @property string $event_type
 * @property string $status
 * @property string $visibility
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $organizer_id
 * @property int $trust_points
 * @property bool $auto_approved
 * @property float|null $distance_from_corvallis
 * @property string|null $ticket_url
 * @property string|null $ticket_price
 */
class CommunityEvent extends Model implements Eventable, HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia, LogsActivity, Reportable, Revisionable, SoftDeletes;
    
    // Report configuration
    protected static int $reportThreshold = 3;
    protected static bool $reportAutoHide = true;
    protected static string $reportableTypeName = 'Community Event';
    
    /**
     * Auto-approval mode for community events - public content requires trust
     */
    protected string $autoApprove = 'trusted';

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'venue_name',
        'venue_address',
        'event_type',
        'status',
        'visibility',
        'published_at',
        'organizer_id',
        'trust_points',
        'auto_approved',
        'distance_from_corvallis',
        'ticket_url',
        'ticket_price',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'published_at' => 'datetime',
        'auto_approved' => 'boolean',
        'distance_from_corvallis' => 'float',
        'ticket_price' => 'decimal:2',
    ];

    /**
     * Event status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Visibility level constants
     */
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_MEMBERS_ONLY = 'members_only';

    /**
     * Event type constants
     */
    const TYPE_PERFORMANCE = 'performance';
    const TYPE_WORKSHOP = 'workshop';
    const TYPE_OPEN_MIC = 'open_mic';
    const TYPE_COLLABORATIVE_SHOW = 'collaborative_show';
    const TYPE_ALBUM_RELEASE = 'album_release';

    /**
     * Trust level thresholds
     */
    const TRUST_TRUSTED = 5;
    const TRUST_VERIFIED = 15;
    const TRUST_AUTO_APPROVED = 30;

    /**
     * Get the organizer (user) of this event.
     */
    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get event tags by type.
     */
    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre');
    }

    /**
     * Media collections configuration.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('poster')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->singleFile()
            ->onlyKeepLatest(1);
    }

    /**
     * Media conversions configuration.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Thumbnail for lists and cards
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(258)
            ->crop('crop-center')
            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('poster');
        
        // Medium size for event listings
        $this->addMediaConversion('medium')
            ->width(400)
            ->height(517)
            ->crop('crop-center')
            ->quality(85)
            ->performOnCollections('poster');
    }

    /**
     * Get poster URL attribute.
     */
    public function getPosterUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'medium') ?: 'https://picsum.photos/400/517?random=' . $this->id;
    }

    /**
     * Get poster thumbnail URL attribute.
     */
    public function getPosterThumbUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'thumb') ?: 'https://picsum.photos/200/258?random=' . $this->id;
    }

    /**
     * Check if a user is the organizer of this event.
     */
    public function isOrganizedBy(User $user): bool
    {
        return $this->organizer_id === $user->id;
    }

    /**
     * Get formatted date range for the event.
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
     * Check if event is approved and published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_APPROVED && 
               $this->published_at !== null && 
               $this->published_at->isPast();
    }

    /**
     * Check if event is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->start_time && $this->start_time->isFuture();
    }

    /**
     * Check if event is public.
     */
    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    /**
     * Scope to get approved upcoming events.
     */
    public function scopeApprovedUpcoming($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where('start_time', '>', now())
            ->orderBy('start_time');
    }

    /**
     * Scope to get published public events.
     */
    public function scopePublishedPublic($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('visibility', self::VISIBILITY_PUBLIC)
            ->orderBy('start_time');
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeEventType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to filter by organizer.
     */
    public function scopeByOrganizer($query, $organizerId)
    {
        return $query->where('organizer_id', $organizerId);
    }

    /**
     * Scope to get local events (within distance limit).
     */
    public function scopeLocal($query, $maxDistance = 60)
    {
        return $query->where('distance_from_corvallis', '<=', $maxDistance)
            ->orWhereNull('distance_from_corvallis');
    }

    /**
     * Get the trust level for the organizer.
     */
    public function getOrganizerTrustLevel(): string
    {
        return app(\App\Services\CommunityEventTrustService::class)->getTrustLevel($this->organizer);
    }

    /**
     * Get trust badge information for the organizer.
     */
    public function getOrganizerTrustBadge(): ?array
    {
        return app(\App\Services\CommunityEventTrustService::class)->getTrustBadge($this->organizer);
    }

    /**
     * Check if tickets are available for this event.
     */
    public function hasTickets(): bool
    {
        return !empty($this->ticket_url);
    }

    /**
     * Get formatted ticket price display.
     */
    public function getTicketPriceDisplayAttribute(): string
    {
        if (!$this->hasTickets() || $this->ticket_price == 0) {
            return 'Free';
        }

        return $this->ticket_price ? '$' . number_format($this->ticket_price, 2) : 'Ticketed';
    }

    /**
     * Check if this is a free event.
     */
    public function isFree(): bool
    {
        return !$this->hasTickets() || ($this->ticket_price === null || $this->ticket_price == 0);
    }

    /**
     * Get the duration of the event in hours.
     */
    public function getDurationAttribute(): float
    {
        if (!$this->start_time || !$this->end_time) {
            return 0;
        }

        return $this->start_time->diffInMinutes($this->end_time) / 60;
    }

    /**
     * Convert event to calendar event.
     */
    public function toCalendarEvent(): CalendarEvent
    {
        return \App\Facades\CalendarService::communityEventToCalendarEvent($this);
    }

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'start_time', 'end_time', 'venue_name', 'venue_address', 'status', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Community Event {$eventName}");
    }

    /**
     * Set default values when creating.
     */
    protected static function booted(): void
    {
        static::creating(function (CommunityEvent $event) {
            if ($event->status === null) {
                $event->status = self::STATUS_PENDING;
            }
            
            if ($event->visibility === null) {
                $event->visibility = self::VISIBILITY_PUBLIC;
            }
        });
    }
}