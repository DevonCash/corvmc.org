<?php

namespace App\Observers;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $this->clearEventCaches($event);
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        $this->clearEventCaches($event);

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
    }

    /**
     * Clear all caches related to an event.
     */
    private function clearEventCaches(Event $event): void
    {
        // Clear upcoming events caches
        Cache::forget('upcoming_events');
        // Clear all user-specific upcoming events caches (wildcard not supported, so we'll clear on next load)

        // Clear conflict detection cache for the event date
        if ($event->start_datetime) {
            $start_datetime = \Illuminate\Support\Carbon::parse($event->start_datetime);
            $date = $start_datetime->format('Y-m-d');
            Cache::forget("events.conflicts.{$date}");
        }

        // Clear organizer's user stats if they have an organizer
        if ($event->organizer_id) {
            Cache::forget("user_stats.{$event->organizer_id}");
        }
    }
}
