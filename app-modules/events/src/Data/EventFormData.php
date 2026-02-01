<?php

namespace CorvMC\Events\Data;

use Carbon\Carbon;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Moderation\Enums\Visibility;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO for event form submissions.
 *
 * Handles the conversion of virtual UI fields (event_date, start_time, etc.)
 * into proper datetime fields for the Event model.
 */
class EventFormData extends Data
{
    public function __construct(
        // Core fields
        public string|Optional $title = new Optional,
        public string|Optional|null $description = new Optional,
        public int|Optional|null $venue_id = new Optional,
        public int|Optional|null $organizer_id = new Optional,
        public EventStatus|string|Optional|null $status = new Optional,
        public Visibility|string|Optional|null $visibility = new Optional,

        // Virtual datetime fields from UI forms
        #[Nullable]
        public string|Optional|null $event_date = new Optional,
        #[Nullable]
        public string|Optional|null $start_time = new Optional,
        #[Nullable]
        public string|Optional|null $end_time = new Optional,
        #[Nullable]
        public string|Optional|null $doors_time = new Optional,

        // Direct datetime fields (alternative to virtual fields)
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP'])]
        public Carbon|string|Optional|null $start_datetime = new Optional,
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP'])]
        public Carbon|string|Optional|null $end_datetime = new Optional,
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP'])]
        public Carbon|string|Optional|null $doors_datetime = new Optional,

        // Additional event fields
        public string|Optional|null $event_link = new Optional,
        public string|Optional|null $ticket_url = new Optional,
        public int|Optional|null $ticket_price = new Optional,
        public string|Optional|null $event_type = new Optional,
        public int|Optional|null $distance_from_corvallis = new Optional,

        // Native ticketing fields
        public bool|Optional|null $ticketing_enabled = new Optional,
        public int|Optional|null $ticket_quantity = new Optional,
        public int|Optional|null $ticket_price_override = new Optional,
        public int|Optional|null $tickets_sold = new Optional,

        // Relationship fields (handled separately by actions)
        public array|Optional $tags = new Optional,
        public bool|Optional|null $notaflof = new Optional,

        // Recurring series
        public int|Optional|null $recurring_series_id = new Optional,
    ) {}

    /**
     * Get the resolved start datetime.
     */
    public function getStartDatetime(): ?Carbon
    {
        // Direct datetime takes precedence
        if (! $this->start_datetime instanceof Optional) {
            if ($this->start_datetime instanceof Carbon) {
                return $this->start_datetime;
            }
            if ($this->start_datetime !== null) {
                return Carbon::parse($this->start_datetime, config('app.timezone'));
            }
        }

        // Virtual fields: event_date + start_time
        if (! $this->event_date instanceof Optional && ! $this->start_time instanceof Optional) {
            if ($this->event_date !== null && $this->start_time !== null) {
                return Carbon::parse(
                    "{$this->event_date} {$this->start_time}",
                    config('app.timezone')
                );
            }
        }

        return null;
    }

    /**
     * Get the resolved end datetime.
     */
    public function getEndDatetime(): ?Carbon
    {
        // Direct datetime takes precedence
        if (! $this->end_datetime instanceof Optional) {
            if ($this->end_datetime instanceof Carbon) {
                return $this->end_datetime;
            }
            if ($this->end_datetime !== null) {
                return Carbon::parse($this->end_datetime, config('app.timezone'));
            }
        }

        // Virtual field: end_time combined with event date
        if (! $this->end_time instanceof Optional && $this->end_time !== null) {
            $baseDate = $this->getEventDate();
            if ($baseDate) {
                return Carbon::parse(
                    "{$baseDate} {$this->end_time}",
                    config('app.timezone')
                );
            }
        }

        return null;
    }

    /**
     * Get the resolved doors datetime.
     */
    public function getDoorsDatetime(): ?Carbon
    {
        // Direct datetime takes precedence
        if (! $this->doors_datetime instanceof Optional) {
            if ($this->doors_datetime instanceof Carbon) {
                return $this->doors_datetime;
            }
            if ($this->doors_datetime !== null) {
                return Carbon::parse($this->doors_datetime, config('app.timezone'));
            }
        }

        // Virtual field: event_date + doors_time (or derive from start_datetime)
        if (! $this->doors_time instanceof Optional && $this->doors_time !== null) {
            $baseDate = $this->getEventDate();
            if ($baseDate) {
                return Carbon::parse(
                    "{$baseDate} {$this->doors_time}",
                    config('app.timezone')
                );
            }
        }

        return null;
    }

    /**
     * Get the event date (from virtual field or derived from start_datetime).
     */
    protected function getEventDate(): ?string
    {
        if (! $this->event_date instanceof Optional && $this->event_date !== null) {
            return $this->event_date;
        }

        $startDatetime = $this->getStartDatetime();
        if ($startDatetime) {
            return $startDatetime->format('Y-m-d');
        }

        return null;
    }

    /**
     * Convert to model attributes, resolving virtual fields.
     *
     * @return array<string, mixed>
     */
    public function toModelAttributes(): array
    {
        $attributes = [];

        // Resolve datetime fields
        $startDatetime = $this->getStartDatetime();
        $endDatetime = $this->getEndDatetime();
        $doorsDatetime = $this->getDoorsDatetime();

        if ($startDatetime !== null) {
            $attributes['start_datetime'] = $startDatetime;
        }
        if ($endDatetime !== null) {
            $attributes['end_datetime'] = $endDatetime;
        }
        if ($doorsDatetime !== null) {
            $attributes['doors_datetime'] = $doorsDatetime;
        }

        // Add non-virtual fields that were provided
        $directFields = [
            'title',
            'description',
            'venue_id',
            'organizer_id',
            'status',
            'visibility',
            'event_link',
            'ticket_url',
            'ticket_price',
            'event_type',
            'distance_from_corvallis',
            'recurring_series_id',
            'ticketing_enabled',
            'ticket_quantity',
            'ticket_price_override',
            'tickets_sold',
        ];

        foreach ($directFields as $field) {
            if (! $this->{$field} instanceof Optional) {
                $attributes[$field] = $this->{$field};
            }
        }

        return $attributes;
    }

    /**
     * Check if this data has scheduling information.
     */
    public function hasSchedulingData(): bool
    {
        return $this->getStartDatetime() !== null;
    }

    /**
     * Get tags if provided (for separate handling by actions).
     *
     * @return array|null
     */
    public function getTags(): ?array
    {
        if ($this->tags instanceof Optional) {
            return null;
        }

        return $this->tags;
    }

    /**
     * Get notaflof flag if provided (for separate handling by actions).
     */
    public function getNotaflof(): ?bool
    {
        if ($this->notaflof instanceof Optional) {
            return null;
        }

        return $this->notaflof;
    }
}
