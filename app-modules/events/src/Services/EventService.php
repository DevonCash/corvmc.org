<?php

namespace CorvMC\Events\Services;

use Carbon\Carbon;
use CorvMC\Events\Data\EventFormData;
use CorvMC\Events\Events\EventCancelled;
use CorvMC\Events\Events\EventScheduling;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use CorvMC\Events\Notifications\EventCreatedNotification;
use CorvMC\Events\Notifications\EventCancelledNotification;
use CorvMC\Events\Notifications\EventRescheduledNotification;
use CorvMC\Events\Notifications\EventPublishedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing events and productions.
 * 
 * This service handles event lifecycle management including creation,
 * updates, scheduling, cancellation, and performer management.
 */
class EventService
{
    /**
     * Create a new event.
     *
     * @param array $data Event data to create from
     * @return Event The created event
     * @throws \InvalidArgumentException if event start time is not provided
     */
    public function create(array $data): Event
    {
        $formData = EventFormData::from($data);

        $event = DB::transaction(function () use ($formData, $data) {
            // Get resolved model attributes from DTO
            $attributes = $formData->toModelAttributes();

            // Set defaults
            $attributes['status'] ??= 'scheduled';
            $attributes['visibility'] ??= 'public';

            // Validate required fields
            $startDatetime = $formData->getStartDatetime();
            if (! $startDatetime) {
                throw new \InvalidArgumentException('Event start time is required but was not provided.');
            }

            // Fire scheduling hook - listeners can throw SchedulingConflictException
            EventScheduling::dispatch(
                $attributes,
                $startDatetime,
                $formData->getEndDatetime(),
                $attributes['venue_id'] ?? null
            );

            $event = Event::create($attributes);

            // Set flags if provided
            $notaflof = $formData->getNotaflof();
            if ($notaflof !== null) {
                $event->setNotaflof($notaflof);
            }

            // Attach tags if provided
            $tags = $formData->getTags();
            if (! empty($tags)) {
                $event->attachTags($tags);
            }

            return $event;
        });

        // Notify organizer outside transaction - don't let email failures affect event creation
        if ($event->organizer) {
            try {
                $event->organizer->notify(new EventCreatedNotification($event));
            } catch (\Exception $e) {
                Log::error('Failed to send event creation notification', [
                    'event_id' => $event->id,
                    'organizer_id' => $event->organizer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $event;
    }

    /**
     * Update an existing event.
     *
     * @param Event $event The event to update
     * @param array $data Updated event data
     * @return Event The updated event
     */
    public function update(Event $event, array $data): Event
    {
        $formData = EventFormData::from($data);

        DB::transaction(function () use ($event, $formData, $data) {
            // Get resolved model attributes from DTO
            $attributes = $formData->toModelAttributes();

            // Fire scheduling hook if date/time or venue changed
            $startDatetime = $formData->getStartDatetime();
            $endDatetime = $formData->getEndDatetime();
            $venueId = $attributes['venue_id'] ?? null;

            if ($startDatetime && (
                $startDatetime->ne($event->start_datetime) ||
                ($endDatetime && $endDatetime->ne($event->end_datetime)) ||
                $venueId !== $event->venue_id
            )) {
                EventScheduling::dispatch(
                    $attributes,
                    $startDatetime,
                    $endDatetime,
                    $venueId,
                    $event
                );
            }

            $event->update($attributes);

            // Update flags if provided
            $notaflof = $formData->getNotaflof();
            if ($notaflof !== null) {
                $event->setNotaflof($notaflof);
            }

            // Update tags if provided
            $tags = $formData->getTags();
            if (! empty($tags)) {
                $event->syncTags($tags);
            }
        });

        return $event->fresh();
    }

    /**
     * Delete an event.
     *
     * @param Event $event The event to delete
     */
    public function delete(Event $event): void
    {
        $event->delete();
    }

    /**
     * Cancel an event.
     *
     * @param Event $event The event to cancel
     * @return Event The cancelled event
     */
    public function cancel(Event $event): Event
    {
        DB::transaction(function () use ($event) {
            $event->update(['status' => 'cancelled']);
        });

        // Notify attendees and organizer
        if ($event->organizer) {
            $event->organizer->notify(new EventCancelledNotification($event));
        }

        EventCancelled::dispatch($event);

        return $event->fresh();
    }

    /**
     * Publish an event.
     *
     * @param Event $event The event to publish
     */
    public function publish(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $event->update([
                'published_at' => now(),
                'visibility' => 'public',
            ]);

            // Notify interested users
            if ($event->organizer) {
                $event->organizer->notify(new EventPublishedNotification($event));
            }
        });
    }

    /**
     * Update event status.
     *
     * @param Event $event The event to update
     * @param string $status The new status
     */
    public function updateStatus(Event $event, string $status): void
    {
        $event->update(['status' => $status]);
    }

    /**
     * Reschedule an event to a new date/time.
     *
     * @param Event $event The event to reschedule
     * @param Carbon $newStartDatetime The new start date/time
     * @param Carbon|null $newEndDatetime The new end date/time
     * @param int|null $newVenueId Optional new venue ID
     * @return Event The rescheduled event
     */
    public function reschedule(
        Event $event,
        ?Carbon $newStartDatetime = null,
        ?Carbon $newEndDatetime = null,
        ?int $newVenueId = null
    ): Event {
        // If no new start datetime, this is a "postpone to TBA" — just mark as postponed
        if ($newStartDatetime === null) {
            $event->update([
                'status' => 'postponed',
            ]);

            return $event;
        }

        $oldStartDatetime = $event->start_datetime;

        $newEvent = null;

        DB::transaction(function () use ($event, $newStartDatetime, $newEndDatetime, $newVenueId, &$newEvent) {
            // Fire scheduling hook to check for conflicts
            EventScheduling::dispatch(
                $event->toArray(),
                $newStartDatetime,
                $newEndDatetime ?? $event->end_datetime,
                $newVenueId ?? $event->venue_id,
                $event
            );

            $updateData = [
                'title' => $event->title,
                'description' => $event->description,
                'start_datetime' => $newStartDatetime,
                'venue_id' => $newVenueId ?? $event->venue_id,
                'organizer_id' => $event->organizer_id,
            ];

            if ($newEndDatetime) {
                $updateData['end_datetime'] = $newEndDatetime;
            } elseif ($event->end_datetime) {
                // Calculate new end time based on original duration
                $duration = $event->start_datetime->diffInMinutes($event->end_datetime);
                $updateData['end_datetime'] = $newStartDatetime->copy()->addMinutes($duration);
            }

            $newEvent = $this->create($updateData);

            // Copy performers from original event to new event
            foreach ($event->performers as $performer) {
                $this->addPerformer(
                    $newEvent,
                    $performer->id,
                    $performer->pivot->order,
                    $performer->pivot->set_length
                );
            }

            // Copy tags from original event to new event
            if ($event->tags()->count() > 0) {
                $newEvent->attachTags($event->tags->pluck('name')->toArray());
            }

            // Mark original event as postponed with reference to new event
            $event->update([
                'status' => 'postponed',
                'rescheduled_to_id' => $newEvent->id,
            ]);
        });

        // Notify attendees and organizer
        if ($event->organizer) {
            $event->organizer->notify(new EventRescheduledNotification($event, $oldStartDatetime));
        }

        return $newEvent ?? $event->fresh();
    }

    /**
     * Duplicate an event.
     *
     * @param Event $event The event to duplicate
     * @param array $overrides Optional attributes to override
     * @return Event The duplicated event
     */
    public function duplicate(Event $event, array $overrides = []): Event
    {
        $data = array_merge(
            $event->only([
                'title', 'description', 'venue_id', 'organizer_id',
                'max_attendees', 'ticket_price', 'visibility',
            ]),
            $overrides
        );

        // Add suffix to title if not overridden
        if (! isset($overrides['title'])) {
            $data['title'] = $event->title . ' (Copy)';
        }

        return $this->create($data);
    }

    /**
     * Search events.
     *
     * @param string|null $query Search query
     * @param array $filters Additional filters
     * @return Collection
     */
    public function search(?string $query = null, array $filters = []): Collection
    {
        $queryBuilder = Event::query();

        if ($query) {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            });
        }

        // Apply filters
        if (isset($filters['status'])) {
            $queryBuilder->where('status', $filters['status']);
        }

        if (isset($filters['venue_id'])) {
            $queryBuilder->where('venue_id', $filters['venue_id']);
        }

        if (isset($filters['organizer_id'])) {
            $queryBuilder->where('organizer_id', $filters['organizer_id']);
        }

        if (isset($filters['start_date'])) {
            $queryBuilder->whereDate('start_datetime', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $queryBuilder->whereDate('start_datetime', '<=', $filters['end_date']);
        }

        return $queryBuilder->orderBy('start_datetime')->get();
    }

    /**
     * Get users interested in an event.
     *
     * @param Event $event The event to check
     * @return Collection Users interested in the event
     */
    public function getInterestedUsers(Event $event): Collection
    {
        // This would typically involve a pivot table or similar relationship
        // For now, returning an empty collection as placeholder
        return new Collection();
    }

    /**
     * Add a performer to an event.
     *
     * @param Event $event The event to add performer to
     * @param int $performerId The performer ID (band)
     * @param int|null $order Optional performance order
     * @param int|null $setLength Optional set length in minutes
     * @return bool True if performer was added, false if already on event
     */
    public function addPerformer(Event $event, int $performerId, ?int $order = null, ?int $setLength = null): bool
    {
        if ($event->performers()->where('band_profile_id', $performerId)->exists()) {
            return false;
        }

        $event->performers()->attach($performerId, [
            'order' => $order ?? $event->performers()->count() + 1,
            'set_length' => $setLength,
        ]);

        return true;
    }

    /**
     * Remove a performer from an event.
     *
     * @param Event $event The event to remove performer from
     * @param int $performerId The performer ID to remove
     * @return bool True if performer was removed
     */
    public function removePerformer(Event $event, int $performerId): bool
    {
        return $event->performers()->detach($performerId) > 0;
    }

    /**
     * Update performer order for an event.
     *
     * @param Event $event The event to update
     * @param int $performerId The performer ID
     * @param int $order The new order
     * @return bool True if performer was found and updated
     */
    public function updatePerformerOrder(Event $event, int $performerId, int $order): bool
    {
        if (! $event->performers()->where('band_profile_id', $performerId)->exists()) {
            return false;
        }

        $event->performers()->updateExistingPivot($performerId, ['order' => $order]);

        return true;
    }

    /**
     * Update performer set length for an event.
     *
     * @param Event $event The event to update
     * @param int $performerId The performer ID
     * @param int $setLength The new set length in minutes
     * @return bool True if performer was found and updated
     */
    public function updatePerformerSetLength(Event $event, int $performerId, int $setLength): bool
    {
        if (! $event->performers()->where('band_profile_id', $performerId)->exists()) {
            return false;
        }

        $event->performers()->updateExistingPivot($performerId, ['set_length' => $setLength]);

        return true;
    }

    /**
     * Duplicate an event with optional new start/end datetimes.
     *
     * @param Event $event The event to duplicate
     * @param Carbon|null $newStartDatetime Optional new start date/time
     * @param Carbon|null $newEndDatetime Optional new end date/time
     * @param array $overrides Optional additional attributes to override
     * @return Event The duplicated event
     */
    public function duplicateEvent(
        Event $event,
        ?Carbon $newStartDatetime = null,
        ?Carbon $newEndDatetime = null,
        array $overrides = []
    ): Event {
        $overrides = array_merge([
            'title' => $event->title,
        ], $overrides);

        if ($newStartDatetime) {
            $overrides['start_datetime'] = $newStartDatetime;
        }

        if ($newEndDatetime) {
            $overrides['end_datetime'] = $newEndDatetime;
        }

        $duplicated = $this->duplicate($event, $overrides);

        // Copy performers from original event to duplicated event
        foreach ($event->performers as $performer) {
            $this->addPerformer(
                $duplicated,
                $performer->id,
                $performer->pivot->order,
                $performer->pivot->set_length
            );
        }

        // Copy tags from original event to duplicated event
        if ($event->tags()->count() > 0) {
            $duplicated->attachTags($event->tags->pluck('name')->toArray());
        }

        return $duplicated;
    }
}