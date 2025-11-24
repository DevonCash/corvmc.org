<?php

namespace App\Models;

use App\Concerns\HasPublishing;
use App\Concerns\HasTimePeriod;
use App\Data\LocationData;
use App\Enums\EventStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReservationStatus;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Period\Period;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Image\Enums\Fit;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property \Illuminate\Support\Carbon|null $doors_time
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData|null $location
 * @property string|null $event_link
 * @property string|null $ticket_url
 * @property string|null $ticket_price
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int|null $organizer_id
 * @property EventStatus $status
 * @property int|null $rescheduled_to_id
 * @property string|null $reschedule_reason
 * @property ModerationStatus $moderation_status
 * @property \Illuminate\Support\Carbon|null $moderation_reviewed_at
 * @property int|null $moderation_reviewed_by
 * @property Visibility $visibility
 * @property string|null $event_type
 * @property float|null $distance_from_corvallis
 * @property int $trust_points
 * @property bool $auto_approved
 * @property int|null $recurring_series_id
 * @property string|null $instance_date
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
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\User|null $moderationReviewer
 * @property-read \App\Models\User|null $organizer
 * @property-read \App\Models\Event|null $rescheduledFrom
 * @property-read \App\Models\Event|null $rescheduledTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $pendingReports
 * @property-read int|null $pending_reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $pendingRevisions
 * @property-read int|null $pending_revisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Band> $performers
 * @property-read int|null $performers_count
 * @property-read \App\Models\RecurringSeries|null $recurringSeries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $reports
 * @property-read int|null $reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $revisions
 * @property-read int|null $revisions_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \Spatie\Tags\Tag> $tags
 * @property-read \App\Models\EventReservation|null $spaceReservation
 * @property-read int|null $tags_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $upheldReports
 * @property-read int|null $upheld_reports_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event byGenre($genreName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event dateRange($range)
 * @method static \Database\Factories\EventFactory factory($count = null, $state = [])
 * @method static Builder<static>|Event flagged(\BackedEnum|string $name)
 * @method static Builder<static>|Event forBand($bandId)
 * @method static Builder<static>|Event newModelQuery()
 * @method static Builder<static>|Event newQuery()
 * @method static Builder<static>|Event notFlagged(\BackedEnum|string $name)
 * @method static Builder<static>|Event onlyTrashed()
 * @method static Builder<static>|Event organizedBy($userId)
 * @method static Builder<static>|Event public()
 * @method static Builder<static>|Event published()
 * @method static Builder<static>|Event publishedPast()
 * @method static Builder<static>|Event publishedToday()
 * @method static Builder<static>|Event publishedUpcoming()
 * @method static Builder<static>|Event query()
 * @method static Builder<static>|Event scheduled()
 * @method static Builder<static>|Event unpublished()
 * @method static Builder<static>|Event venue($venueType)
 * @method static Builder<static>|Event visibleTo(?\App\Models\User $user = null)
 * @method static Builder<static>|Event visibleToMembers()
 * @method static Builder<static>|Event whereAutoApproved($value)
 * @method static Builder<static>|Event whereCreatedAt($value)
 * @method static Builder<static>|Event whereDeletedAt($value)
 * @method static Builder<static>|Event whereDescription($value)
 * @method static Builder<static>|Event whereDistanceFromCorvallis($value)
 * @method static Builder<static>|Event whereDoorsTime($value)
 * @method static Builder<static>|Event whereEndTime($value)
 * @method static Builder<static>|Event whereEventLink($value)
 * @method static Builder<static>|Event whereEventType($value)
 * @method static Builder<static>|Event whereId($value)
 * @method static Builder<static>|Event whereInstanceDate($value)
 * @method static Builder<static>|Event whereLocation($value)
 * @method static Builder<static>|Event whereOrganizerId($value)
 * @method static Builder<static>|Event wherePublishedAt($value)
 * @method static Builder<static>|Event whereRecurringSeriesId($value)
 * @method static Builder<static>|Event whereStartTime($value)
 * @method static Builder<static>|Event whereStatus($value)
 * @method static Builder<static>|Event whereTicketPrice($value)
 * @method static Builder<static>|Event whereTicketUrl($value)
 * @method static Builder<static>|Event whereTitle($value)
 * @method static Builder<static>|Event whereTrustPoints($value)
 * @method static Builder<static>|Event whereUpdatedAt($value)
 * @method static Builder<static>|Event whereVisibility($value)
 * @method static Builder<static>|Event withAllTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Event withAllTagsOfAnyType($tags)
 * @method static Builder<static>|Event withAnyTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Event withAnyTagsOfAnyType($tags)
 * @method static Builder<static>|Event withAnyTagsOfType(array|string $type)
 * @method static Builder<static>|Event withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Event withoutTags(\ArrayAccess|\Spatie\Tags\Tag|array|string $tags, ?string $type = null)
 * @method static Builder<static>|Event withoutTrashed()
 * @mixin \Eloquent
 */
class Event extends ContentModel
{
    use HasPublishing, HasTimePeriod, SoftDeletes;

    // Report configuration
    protected static int $reportThreshold = 3;

    protected static bool $reportAutoHide = false;

    protected static string $reportableTypeName = 'Event';

    protected static string $creatorForeignKey = 'organizer_id';

    // Activity logging configuration
    protected static array $loggedFields = ['title', 'description', 'start_time', 'end_time', 'status', 'visibility'];

    protected static string $logTitle = 'Event';

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'doors_time',
        'location',
        'event_link',
        'ticket_url',
        'ticket_price',
        'published_at',
        'approved_at',
        'organizer_id',
        'status',
        'rescheduled_to_id',
        'reschedule_reason',
        'moderation_status',
        'moderation_reviewed_at',
        'moderation_reviewed_by',
        'visibility',
        'event_type',
        'distance_from_corvallis',
        'trust_points',
        'auto_approved',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'doors_time' => 'datetime',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
            'moderation_reviewed_at' => 'datetime',
            'location' => LocationData::class,
            'status' => EventStatus::class,
            'moderation_status' => ModerationStatus::class,
            'visibility' => Visibility::class,
            'auto_approved' => 'boolean',
            'distance_from_corvallis' => 'float',
        ];
    }

    /**
     * Get the organizer (user) of this event.
     */
    public function organizer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get the user who reviewed this event for moderation.
     */
    public function moderationReviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'moderation_reviewed_by');
    }

    /**
     * Get the event this was rescheduled to (if this event was rescheduled).
     */
    public function rescheduledTo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Event::class, 'rescheduled_to_id');
    }

    /**
     * Get the event(s) that were rescheduled to this event.
     */
    public function rescheduledFrom(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Event::class, 'rescheduled_to_id');
    }

    /**
     * Relationship to the recurring series this event belongs to.
     */
    public function recurringSeries()
    {
        return $this->belongsTo(RecurringSeries::class, 'recurring_series_id');
    }

    /**
     * Check if this event is part of a recurring series.
     */
    public function isRecurring(): bool
    {
        return $this->recurring_series_id !== null;
    }

    /**
     * Get the performers/bands for this event.
     */
    public function performers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Band::class, 'event_bands', 'event_id', 'band_profile_id')
            ->withPivot('order', 'set_length')
            ->orderBy('event_bands.order')
            ->withTimestamps();
    }

    /**
     * Space reservation for this event (if using practice space).
     */
    public function spaceReservation()
    {
        return $this->morphOne(EventReservation::class, 'reservable');
    }

    /**
     * Get genre tags.
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
            ->onlyKeepLatest(1)
            ->useFallbackUrl('/images/default-poster.png');
    }

    /**
     * Media conversions configuration.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 200, 258)
            ->quality(90)
            ->sharpen(10)
            ->performOnCollections('poster');

        $this->addMediaConversion('medium')
            ->fit(Fit::Contain, 400, 517)
            ->quality(85)
            ->performOnCollections('poster');

        $this->addMediaConversion('large')
            ->fit(Fit::Contain, 600, 776)
            ->quality(80)
            ->performOnCollections('poster');

        $this->addMediaConversion('optimized')
            ->fit(Fit::Contain, 850, 1100)
            ->quality(75)
            ->performOnCollections('poster');
    }

    /**
     * Get poster URL attributes.
     */
    public function getPosterUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'medium') ?: 'https://picsum.photos/400/517?random='.$this->id;
    }

    public function getPosterThumbUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'thumb') ?: 'https://picsum.photos/200/258?random='.$this->id;
    }

    public function getPosterLargeUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'large') ?: 'https://picsum.photos/600/776?random='.$this->id;
    }

    public function getPosterOptimizedUrlAttribute()
    {
        return $this->getFirstMediaUrl('poster', 'optimized') ?: 'https://picsum.photos/850/1100?random='.$this->id;
    }

    /**
     * Get fields that should not trigger revision workflow.
     */
    protected function getRevisionExemptFields(): array
    {
        return ['status', 'published_at'];
    }

    /**
     * Check if a user is the organizer of this event.
     */
    public function isOrganizedBy(User $user): bool
    {
        return $this->organizer_id === $user->id;
    }

    /**
     * Check if this is a staff event (no organizer).
     */
    public function isStaffEvent(): bool
    {
        return $this->organizer_id === null;
    }

    /**
     * Check if a user can manage this event (is organizer or has permission).
     */
    public function canBeManaged(User $user): bool
    {
        return $this->isOrganizedBy($user) || $user->can('manage events');
    }

    /**
     * Check if this event has a specific performer.
     */
    public function hasPerformer(Band $band): bool
    {
        return $this->performers()->where('band_profile_id', $band->id)->exists();
    }

    /**
     * Add a performer (band) to this event.
     */
    public function addPerformer(Band $band, array $options = []): bool
    {
        if ($this->hasPerformer($band)) {
            return false;
        }

        if (! isset($options['order'])) {
            $options['order'] = $this->performers()->max('event_bands.order') + 1 ?? 1;
        }

        $this->performers()->attach($band->id, [
            'order' => $options['order'],
            'set_length' => $options['set_length'] ?? null,
        ]);

        return true;
    }

    /**
     * Remove a performer from this event.
     */
    public function removePerformer(Band $band): bool
    {
        if (! $this->hasPerformer($band)) {
            return false;
        }

        $this->performers()->detach($band->id);

        return true;
    }

    /**
     * Update a performer's order in the lineup.
     */
    public function updatePerformerOrder(Band $band, int $order): bool
    {
        if (! $this->hasPerformer($band)) {
            return false;
        }

        $this->performers()->updateExistingPivot($band->id, ['order' => $order]);

        return true;
    }

    /**
     * Update a performer's set length.
     */
    public function updatePerformerSetLength(Band $band, int $setLength): bool
    {
        if (! $this->hasPerformer($band)) {
            return false;
        }

        $this->performers()->updateExistingPivot($band->id, ['set_length' => $setLength]);

        return true;
    }

    /**
     * Publish this event.
     */
    public function publish(): self
    {
        if (empty($this->title)) {
            throw new \InvalidArgumentException('Event title is required to publish');
        }

        $this->update([
            'published_at' => $this->published_at ?? now(),
        ]);

        return $this;
    }

    /**
     * Unpublish this event.
     */
    public function unpublish(): self
    {
        $this->update([
            'published_at' => null,
        ]);

        return $this;
    }

    /**
     * Cancel this event.
     */
    public function cancel(?string $reason = null): self
    {
        $this->update([
            'status' => EventStatus::Cancelled,
        ]);

        return $this;
    }

    /**
     * Reschedule this event to a new event listing.
     *
     * @param Event|int $newEvent The new event (or its ID) this is rescheduled to
     * @param string|null $reason Optional reason for the reschedule
     */
    public function reschedule(Event|int $newEvent, ?string $reason = null): self
    {
        $newEventId = $newEvent instanceof Event ? $newEvent->id : $newEvent;

        $this->update([
            'status' => EventStatus::Postponed,
            'rescheduled_to_id' => $newEventId,
            'reschedule_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Postpone this event (without setting a new date).
     */
    public function postpone(?string $reason = null): self
    {
        $this->update([
            'status' => EventStatus::Postponed,
            'reschedule_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Mark this event as at capacity (sold out/full).
     */
    public function markAtCapacity(): self
    {
        $this->update([
            'status' => EventStatus::AtCapacity,
        ]);

        return $this;
    }

    /**
     * Mark this event as available again (reset from at capacity).
     */
    public function markAvailable(): self
    {
        $this->update([
            'status' => EventStatus::Scheduled,
        ]);

        return $this;
    }

    /**
     * Check if this event has been rescheduled to a new event.
     */
    public function isRescheduled(): bool
    {
        return $this->rescheduled_to_id !== null;
    }

    /**
     * Get formatted date range.
     */
    public function getDateRangeAttribute(): string
    {
        if ($this->start_time && $this->end_time) {
            if ($this->start_time->isSameDay($this->end_time)) {
                return $this->start_time->format('M j, Y g:i A').' - '.$this->end_time->format('g:i A');
            }

            return $this->start_time->format('M j, Y g:i A').' - '.$this->end_time->format('M j, Y g:i A');
        }

        return $this->start_time ? $this->start_time->format('M j, Y g:i A') : 'TBD';
    }

    /**
     * Check if event is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->start_time && $this->start_time->isFuture();
    }

    /**
     * Scope to get published upcoming events.
     */
    public function scopePublishedUpcoming($query)
    {
        return $query->where('published_at', '<=', now())
            ->whereNotNull('published_at')
            ->where('start_time', '>', now())
            ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Postponed])
            ->orderBy('start_time');
    }

    /**
     * Scope to get published past events.
     */
    public function scopePublishedPast($query)
    {
        return $query->where('published_at', '<=', now())
            ->whereNotNull('published_at')
            ->where('start_time', '<', now())
            ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Postponed])
            ->orderBy('start_time', 'desc');
    }

    /**
     * Scope to get published events happening today.
     */
    public function scopePublishedToday($query)
    {
        return $query->where('published_at', '<=', now())
            ->whereNotNull('published_at')
            ->where('start_time', '>=', now()->startOfDay())
            ->where('start_time', '<=', now()->endOfDay())
            ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Postponed])
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
     * Scope to get events for a specific band.
     */
    public function scopeForBand($query, $bandId)
    {
        return $query->whereHas('performers', function ($q) use ($bandId) {
            $q->where('band_profile_id', $bandId);
        });
    }

    /**
     * Scope to get events by genre.
     */
    public function scopeByGenre($query, $genreName)
    {
        return $query->withAnyTags([$genreName], 'genre');
    }

    /**
     * Scope to get events organized by a specific user.
     */
    public function scopeOrganizedBy($query, $userId)
    {
        return $query->where('organizer_id', $userId);
    }

    /**
     * Get the total estimated duration of the event.
     */
    public function getEstimatedDurationAttribute(): int
    {
        return $this->performers()->sum('event_bands.set_length') ?: 0;
    }

    /**
     * Check if this event is at an external venue.
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
     * Check if tickets are available for this event.
     */
    public function hasTickets(): bool
    {
        return ! empty($this->ticket_url) || ! empty($this->event_link);
    }

    /**
     * Get the primary ticket/event URL.
     */
    public function getTicketUrlAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return 'https://'.$value;
        }

        return $value;
    }

    /**
     * Get the event link URL.
     */
    public function getEventLinkAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return 'https://'.$value;
        }

        return $value;
    }

    /**
     * Check if this event is NOTAFLOF.
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
        if ($this->isFree()) {
            return 'Free';
        }

        $price = $this->ticket_price ? '$'.number_format($this->ticket_price, 2) : 'Ticketed';

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
        return $this->ticket_price === null || $this->ticket_price == 0;
    }

    /**
     * Get the event as a Period object.
     *
     * @deprecated Use createPeriod() instead
     */
    public function getPeriod(): ?Period
    {
        return $this->createPeriod();
    }

    /**
     * Check if this event overlaps with another period.
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
     * Check if this event touches another period (adjacent periods).
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
     * Get the duration of the event in hours.
     */
    public function getDurationAttribute(): float
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        return $this->start_time->diffInMinutes($this->end_time) / 60;
    }

    /**
     * Check if this event uses the CMC practice space (not external venue).
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
        static::creating(function (Event $event) {
            if (! $event->location) {
                $event->location = LocationData::cmc();
            }
        });

        static::saved(function (Event $event) {
            if ($event->usesPracticeSpace()) {
                $event->syncSpaceReservation();
            }
        });
    }

    /**
     * Create or update the space reservation for this event.
     */
    protected function syncSpaceReservation(): void
    {
        $reservedAt = $this->start_time->copy()->subHours(2);
        $reservedUntil = $this->end_time?->copy()->addHour() ?? $this->start_time->copy()->addHours(3);

        $this->spaceReservation()->updateOrCreate(
            [],
            [
                'type' => EventReservation::class,
                'reserved_at' => $reservedAt,
                'reserved_until' => $reservedUntil,
                'status' => ReservationStatus::Confirmed,
                'notes' => "Setup/breakdown for event: {$this->title}",
            ]
        );
    }
}
