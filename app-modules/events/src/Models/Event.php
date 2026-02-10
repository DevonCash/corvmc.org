<?php

namespace CorvMC\Events\Models;

use CorvMC\Bands\Models\Band;
use App\Models\EventReservation;
use App\Models\User;
use Brick\Money\Money;
use Carbon\Carbon;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Concerns\HasPoster;
use CorvMC\Events\Concerns\HasPublishing;
use CorvMC\Events\Data\LocationData;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Moderation\Enums\Visibility;
use CorvMC\Moderation\Models\ContentModel;
use CorvMC\Support\Concerns\HasRecurringSeries;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use CorvMC\Support\Concerns\HasTimePeriod;
use CorvMC\Support\Contracts\Recurrable;
use CorvMC\Support\Models\RecurringSeries;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $start_datetime
 * @property \Illuminate\Support\Carbon|null $end_datetime
 * @property \Illuminate\Support\Carbon|null $doors_datetime
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData|null $location
 * @property string|null $event_link
 * @property string|null $ticket_url
 * @property string|null $ticket_price
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int|null $organizer_id
 * @property EventStatus $status
 * @property int|null $rescheduled_to_id
 * @property string|null $reschedule_reason
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
 * @property-read \App\Models\User|null $organizer
 * @property-read \CorvMC\Events\Models\Event|null $rescheduledFrom
 * @property-read \CorvMC\Events\Models\Event|null $rescheduledTo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $pendingReports
 * @property-read int|null $pending_reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $pendingRevisions
 * @property-read int|null $pending_revisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \CorvMC\Bands\Models\Band> $performers
 * @property-read int|null $performers_count
 * @property-read \App\Models\RecurringSeries|null $recurringSeries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $reports
 * @property-read int|null $reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Revision> $revisions
 * @property-read int|null $revisions_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \Spatie\Tags\Tag> $tags
 * @property-read \CorvMC\Events\Models\EventReservation|null $spaceReservation
 * @property-read int|null $tags_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Report> $upheldReports
 * @property-read int|null $upheld_reports_count
 *
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
 * @method static Builder<static>|Event whereDoorsDatetime($value)
 * @method static Builder<static>|Event whereEndDatetime($value)
 * @method static Builder<static>|Event whereEventLink($value)
 * @method static Builder<static>|Event whereEventType($value)
 * @method static Builder<static>|Event whereId($value)
 * @method static Builder<static>|Event whereInstanceDate($value)
 * @method static Builder<static>|Event whereLocation($value)
 * @method static Builder<static>|Event whereOrganizerId($value)
 * @method static Builder<static>|Event wherePublishedAt($value)
 * @method static Builder<static>|Event whereRecurringSeriesId($value)
 * @method static Builder<static>|Event whereStartDatetime($value)
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
 *
 * @mixin \Eloquent
 */
class Event extends ContentModel implements Recurrable
{
    use HasFactory, HasPoster, HasPublishing, HasRecurringSeries, HasTimePeriod, LogsActivity, SoftDeletes;

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ticketing_enabled' => false,
        'tickets_sold' => 0,
    ];

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    // HasPublishing configuration
    protected static string $startTimeField = 'start_datetime';

    protected static array $excludedStatuses = [
        EventStatus::Cancelled,
        EventStatus::Postponed,
    ];

    // Report configuration
    protected static int $reportThreshold = 3;

    protected static bool $reportAutoHide = false;

    protected static string $reportableTypeName = 'Event';

    protected static string $creatorForeignKey = 'organizer_id';

    // TODO: Replace LogsActivity trait with domain event-based logging (requires Event domain events + LogEventActivity listener)
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([])
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'doors_datetime',
        'location',
        'venue_id',
        'event_link',
        'ticket_url',
        'ticket_price',
        'ticketing_enabled',
        'ticket_quantity',
        'ticket_price_override',
        'tickets_sold',
        'published_at',
        'organizer_id',
        'status',
        'rescheduled_to_id',
        'reschedule_reason',
        'cancellation_reason',
        'visibility',
        'event_type',
        'distance_from_corvallis',
        'trust_points',
        'auto_approved',
    ];

    /**
     * Virtual attributes that should be appended to array/JSON output.
     * This makes them available to Filament forms during hydration.
     */
    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'doors_datetime' => 'datetime',
            'published_at' => 'datetime',
            'location' => LocationData::class,
            'status' => EventStatus::class,
            'visibility' => Visibility::class,
            'auto_approved' => 'boolean',
            'distance_from_corvallis' => 'float',
            'ticketing_enabled' => 'boolean',
            'ticket_quantity' => 'integer',
            'ticket_price_override' => 'integer',
            'tickets_sold' => 'integer',
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
     * Get the venue for this event.
     */
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Get the ticket orders for this event.
     */
    public function ticketOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TicketOrder::class);
    }

    /**
     * Get all tickets for this event (through orders).
     */
    public function tickets(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, TicketOrder::class);
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
        $this->registerPosterMediaCollection();
    }

    /**
     * Media conversions configuration.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerPosterMediaConversions($media);
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
     * Check if this event has a specific performer.
     */
    public function hasPerformer(Band $band): bool
    {
        return $this->performers()->where('band_profile_id', $band->id)->exists();
    }

    public function canPublish(): bool
    {
        return ! empty($this->title);
    }

    /**
     * Reschedule this event to a new event listing.
     *
     * @param  Event|int  $newEvent  The new event (or its ID) this is rescheduled to
     * @param  string|null  $reason  Optional reason for the reschedule
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
        if ($this->start_datetime && $this->end_datetime) {
            if ($this->start_datetime->isSameDay($this->end_datetime)) {
                return $this->start_datetime->format('M j, Y g:i A').' - '.$this->end_datetime->format('g:i A');
            }

            return $this->start_datetime->format('M j, Y g:i A').' - '.$this->end_datetime->format('M j, Y g:i A');
        }

        return $this->start_datetime ? $this->start_datetime->format('M j, Y g:i A') : 'TBD';
    }

    /**
     * Check if event is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->start_datetime && $this->start_datetime->isFuture();
    }

    /**
     * Scope to filter by venue type.
     */
    public function scopeVenue($query, $venueType)
    {
        switch ($venueType) {
            case 'cmc':
                return $query->whereHas('venue', function ($q) {
                    $q->where('is_cmc', true);
                });
            case 'external':
                return $query->whereHas('venue', function ($q) {
                    $q->where('is_cmc', false);
                });
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
        // Use venue relationship if available, fallback to location JSON for backward compatibility
        if ($this->venue) {
            return ! $this->venue->is_cmc;
        }

        return $this->location?->isExternal() ?? false;
    }

    /**
     * Get the venue display name.
     */
    public function getVenueNameAttribute(): string
    {
        // Use venue relationship if available, fallback to location JSON for backward compatibility
        if ($this->venue) {
            return $this->venue->name;
        }

        return $this->location?->getVenueName() ?? 'Corvallis Music Collective';
    }

    /**
     * Get the full venue details for display.
     */
    public function getVenueDetailsAttribute(): string
    {
        // Use venue relationship if available, fallback to location JSON for backward compatibility
        if ($this->venue) {
            return $this->venue->formatted_address;
        }

        return $this->location?->getVenueDetails() ?? 'Corvallis Music Collective';
    }

    /**
     * Check if tickets are available for this event (native or external).
     */
    public function hasTickets(): bool
    {
        return $this->ticketing_enabled
            || ! empty($this->attributes['ticket_url'] ?? null)
            || ! empty($this->event_link);
    }

    /**
     * Get the ticket URL - returns native ticketing route if enabled, otherwise external URL.
     */
    public function getTicketUrlAttribute(): ?string
    {
        // If there's an external ticket URL set, use it (with URL normalization)
        $externalUrl = $this->attributes['ticket_url'] ?? null;
        if (! empty($externalUrl)) {
            if (! str_starts_with($externalUrl, 'http://') && ! str_starts_with($externalUrl, 'https://')) {
                return 'https://'.$externalUrl;
            }

            return $externalUrl;
        }

        // If native ticketing is enabled, return the ticket purchase route
        if ($this->ticketing_enabled) {
            return route('events.tickets', $this);
        }

        return null;
    }

    /**
     * Check if CMC native ticketing is enabled for this event.
     */
    public function hasNativeTicketing(): bool
    {
        return (bool) $this->ticketing_enabled;
    }

    /**
     * Get the ticket price for this event.
     *
     * @param  User|null  $user  User to calculate price for (for member discount)
     * @return Money Price in USD
     */
    public function getTicketPriceForUser(?User $user = null): Money
    {
        $basePrice = $this->ticket_price_override ?? config('ticketing.default_price', 1000);

        // Apply sustaining member discount
        if ($user && $user->isSustainingMember()) {
            $discountPercent = config('ticketing.sustaining_member_discount', 50);
            $basePrice = (int) round($basePrice * (1 - $discountPercent / 100));
        }

        return Money::ofMinor($basePrice, 'USD');
    }

    /**
     * Get the base ticket price (without any discounts).
     */
    public function getBaseTicketPrice(): Money
    {
        $price = $this->ticket_price_override ?? config('ticketing.default_price', 1000);

        return Money::ofMinor($price, 'USD');
    }

    /**
     * Get the number of tickets remaining.
     *
     * @return int|null Remaining tickets, or null if unlimited
     */
    public function getTicketsRemaining(): ?int
    {
        if ($this->ticket_quantity === null) {
            return null; // Unlimited
        }

        return max(0, $this->ticket_quantity - $this->tickets_sold);
    }

    /**
     * Check if tickets are sold out.
     */
    public function isSoldOut(): bool
    {
        $remaining = $this->getTicketsRemaining();

        return $remaining !== null && $remaining <= 0;
    }

    /**
     * Check if a given quantity of tickets is available.
     */
    public function hasTicketsAvailable(int $quantity = 1): bool
    {
        $remaining = $this->getTicketsRemaining();

        return $remaining === null || $remaining >= $quantity;
    }

    /**
     * Increment the tickets sold count.
     */
    public function incrementTicketsSold(int $quantity = 1): self
    {
        $this->increment('tickets_sold', $quantity);

        return $this;
    }

    /**
     * Decrement the tickets sold count.
     */
    public function decrementTicketsSold(int $quantity = 1): self
    {
        $this->decrement('tickets_sold', $quantity);

        return $this;
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
     * Check if this event is NOTAFLOF (No One Turned Away For Lack of Funds).
     *
     * All non-free CMC events using native ticketing are automatically NOTAFLOF.
     * External ticketed events can opt-in via the notaflof flag.
     */
    public function isNotaflof(): bool
    {
        // Native ticketing = CMC event = always NOTAFLOF
        if ($this->ticketing_enabled) {
            return true;
        }

        // External events can opt-in via flag
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

        // For native ticketing, show the base ticket price
        if ($this->ticketing_enabled) {
            $price = '$'.number_format($this->getBaseTicketPrice()->getAmount()->toFloat(), 2);
        } else {
            $price = $this->ticket_price ? '$'.number_format($this->ticket_price, 2) : 'Ticketed';
        }

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
        // Native ticketing events are never free (have default or override price)
        if ($this->ticketing_enabled) {
            return false;
        }

        return $this->ticket_price === null || $this->ticket_price == 0;
    }

    /**
     * Check if this event uses the CMC practice space (not external venue).
     */
    public function usesPracticeSpace(): bool
    {
        return ! $this->isExternalVenue();
    }

    /**
     * Set a default venue if none exists.
     */
    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            // Set default venue to CMC if not specified
            if (! $event->venue_id) {
                $cmcVenue = Venue::cmc()->first();
                if ($cmcVenue) {
                    $event->venue_id = $cmcVenue->id;
                }
            }

            // Backward compatibility: Set location JSON if not set
            if (! $event->location) {
                $event->location = LocationData::cmc();
            }
        });
    }

    /**
     * Override HasTimePeriod trait to use correct field names.
     */
    protected function getStartTimeField(): string
    {
        return 'start_datetime';
    }

    /**
     * Override HasTimePeriod trait to use correct field names.
     */
    protected function getEndTimeField(): string
    {
        return 'end_datetime';
    }

    // =========================================================================
    // Recurrable Interface Implementation
    // =========================================================================

    /**
     * Create an event instance from a recurring series.
     *
     * @throws \InvalidArgumentException If the event cannot be created (e.g., conflict)
     */
    public static function createFromRecurringSeries(RecurringSeries $series, Carbon $date): static
    {
        $startDateTime = $date->copy()->setTimeFromTimeString($series->start_time->format('H:i:s'));
        $endDateTime = $date->copy()->setTimeFromTimeString($series->end_time->format('H:i:s'));

        /** @var static */
        return CreateEvent::run([
            'organizer_id' => $series->user_id,
            'recurring_series_id' => $series->id,
            'instance_date' => $date->toDateString(),
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'status' => EventStatus::Scheduled,
            'published_at' => now(),
        ]);
    }

    /**
     * Create a cancelled placeholder to track a skipped instance.
     *
     * Events don't use cancelled placeholders the same way reservations do.
     * If an event can't be created due to a conflict, we simply skip it.
     */
    public static function createCancelledPlaceholder(RecurringSeries $series, Carbon $date): void
    {
        // Events don't use cancelled placeholders - simply skip conflicting dates
    }

    /**
     * Check if an instance already exists for this date in the series.
     */
    public static function instanceExistsForDate(RecurringSeries $series, Carbon $date): bool
    {
        return static::where('recurring_series_id', $series->id)
            ->whereDate('instance_date', $date->toDateString())
            ->exists();
    }

    /**
     * Cancel all future instances for a series.
     */
    public static function cancelFutureInstances(RecurringSeries $series, ?string $reason = null): int
    {
        $futureInstances = static::where('recurring_series_id', $series->id)
            ->where('start_datetime', '>', now())
            ->where('status', EventStatus::Scheduled)
            ->get();

        foreach ($futureInstances as $event) {
            $event->update([
                'status' => EventStatus::Cancelled,
            ]);
        }

        return $futureInstances->count();
    }
}
