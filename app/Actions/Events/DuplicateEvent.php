<?php

namespace App\Actions\Events;

use App\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class DuplicateEvent
{
    use AsAction;

    /**
     * Duplicate an event with new date/time.
     */
    public function handle(
        Event $originalEvent,
        \DateTime $newStartTime,
        ?\DateTime $newEndTime = null,
        ?\DateTime $newDoorsTime = null
    ): Event {
        $newEvent = $originalEvent->replicate();
        $newEvent->start_time = $newStartTime;
        $newEvent->end_time = $newEndTime;
        $newEvent->doors_time = $newDoorsTime;
        $newEvent->status = 'approved';
        $newEvent->published_at = null;
        $newEvent->save();

        // Copy performers
        foreach ($originalEvent->performers as $performer) {
            $pivot = $performer->pivot;
            $newEvent->performers()->attach($performer->id, [
                'order' => $pivot?->order,
                'set_length' => $pivot?->set_length,
            ]);
        }

        // Copy tags
        foreach ($originalEvent->tags as $tag) {
            $newEvent->attachTag($tag->name ?? '', $tag->type ?? null);
        }

        return $newEvent;
    }
}
