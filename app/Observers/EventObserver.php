<?php

namespace App\Observers;

use App\Actions\Events\SyncEventSpaceReservation;
use Carbon\Carbon;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use Illuminate\Support\Facades\Cache;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $this->clearEventCaches($event);
        $this->createSpaceReservationIfNeeded($event);
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        $this->clearEventCaches($event);

        // Handle status changes that affect space reservation
        if ($event->wasChanged('status')) {
            $this->syncReservationStatusWithEvent($event);

            // If times also changed during reactivation, recalculate the reservation
            if ($event->status->isActive() && ($event->wasChanged('start_datetime') || $event->wasChanged('end_datetime'))) {
                SyncEventSpaceReservation::run($event, null, null, force: true);
            }
        } else {
            $this->shiftSpaceReservationIfNeeded($event);
        }

        // If event date changed, clear both old and new date caches
        if ($event->isDirty('start_datetime')) {
            $originalDate = $event->getOriginal('start_datetime');
            if ($originalDate) {
                Cache::forget('events.conflicts.'.date('Y-m-d', strtotime($originalDate)));
            }
        }
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        $this->clearEventCaches($event);

        // Delete the space reservation if it exists
        $event->spaceReservation?->delete();
    }

    /**
     * Handle the Event "restored" event (after soft delete restoration).
     */
    public function restored(Event $event): void
    {
        $this->clearEventCaches($event);

        // Recreate the space reservation if this is an active CMC event
        // (reservations don't use soft deletes, so the original was hard-deleted)
        if ($event->usesPracticeSpace() && $event->status->isActive()) {
            SyncEventSpaceReservation::run($event, null, null, force: true);
        }
    }

    /**
     * Clear all caches related to an event.
     */
    private function clearEventCaches(Event $event): void
    {
        // Clear conflict detection cache for the event date
        if ($event->start_datetime) {
            $start_datetime = Carbon::parse($event->start_datetime);
            $date = $start_datetime->format('Y-m-d');
            Cache::forget("events.conflicts.{$date}");
        }

        // Clear organizer's user stats if they have an organizer
        if ($event->organizer_id) {
            Cache::forget("user_stats.{$event->organizer_id}");
        }
    }

    /**
     * Create space reservation for new events at CMC venue.
     * Uses default setup/teardown times from settings.
     * Forces through conflicts since there's no UI to show warnings.
     */
    private function createSpaceReservationIfNeeded(Event $event): void
    {
        if ($event->usesPracticeSpace()) {
            // Force through since observer has no UI context for conflict warnings
            SyncEventSpaceReservation::run($event, null, null, force: true);
        }
    }

    /**
     * Sync reservation status when event status changes.
     *
     * - Cancelled/Postponed → Cancel reservation
     * - Scheduled/AtCapacity (from inactive) → Restore or create reservation
     */
    private function syncReservationStatusWithEvent(Event $event): void
    {
        if (! $event->usesPracticeSpace()) {
            return;
        }

        $reservation = $event->spaceReservation;

        // Get the original status value (handles both enum instances and strings)
        $originalStatus = $event->getOriginal('status');
        $originalStatusValue = $originalStatus instanceof EventStatus
            ? $originalStatus->value
            : $originalStatus;

        $wasInactive = in_array($originalStatusValue, ['cancelled', 'postponed']);
        $isNowInactive = in_array($event->status->value, ['cancelled', 'postponed']);

        if ($isNowInactive) {
            // Event became inactive - cancel reservation
            if ($reservation) {
                $reason = $event->status === EventStatus::Cancelled
                    ? 'Event was cancelled'
                    : 'Event was postponed';

                $reservation->update([
                    'status' => ReservationStatus::Cancelled,
                    'cancellation_reason' => $reason,
                ]);
            }
        } elseif ($wasInactive && $event->status->isActive()) {
            // Event was reactivated - restore or create reservation
            if ($reservation && $reservation->status === ReservationStatus::Cancelled) {
                $reservation->update([
                    'status' => ReservationStatus::Confirmed,
                    'cancellation_reason' => null,
                ]);
            } elseif (! $reservation) {
                SyncEventSpaceReservation::run($event, null, null, force: true);
            }
        }
    }

    /**
     * Shift existing space reservation when event times change.
     * Preserves custom setup/teardown times by applying the same delta.
     */
    private function shiftSpaceReservationIfNeeded(Event $event): void
    {
        if (! $event->usesPracticeSpace()) {
            return;
        }

        $reservation = $event->spaceReservation;

        // If no reservation exists, create one with defaults
        if (! $reservation) {
            SyncEventSpaceReservation::run($event);

            return;
        }

        // Check if event times changed
        $startChanged = $event->isDirty('start_datetime');
        $endChanged = $event->isDirty('end_datetime');

        if (! $startChanged && ! $endChanged) {
            return;
        }

        // Calculate deltas and shift reservation times
        if ($startChanged) {
            $oldStart = Carbon::parse($event->getOriginal('start_datetime'));
            $newStart = $event->start_datetime;
            $startDelta = $oldStart->diffInMinutes($newStart, false);
            $reservation->reserved_at = $reservation->reserved_at->addMinutes($startDelta);
        }

        if ($endChanged) {
            $oldEnd = $event->getOriginal('end_datetime');
            $newEnd = $event->end_datetime;

            // If either old or new end is null, recalculate via SyncEventSpaceReservation
            // instead of trying to compute a delta
            if (! $oldEnd || ! $newEnd) {
                SyncEventSpaceReservation::run($event, null, null, force: true);

                return;
            }

            $endDelta = Carbon::parse($oldEnd)->diffInMinutes($newEnd, false);
            $reservation->reserved_until = $reservation->reserved_until->addMinutes($endDelta);
        }

        $reservation->save();
    }
}
