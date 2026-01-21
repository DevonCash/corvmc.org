<?php

namespace CorvMC\Events\Actions;

use App\Models\Band;
use CorvMC\Events\Models\Event;
use Lorisleiva\Actions\Concerns\AsAction;

class AddEventPerformer
{
    use AsAction;

    /**
     * Add a performer (band) to an event.
     *
     * @param  Event  $event  The event to add the performer to
     * @param  Band  $band  The band to add as a performer
     * @param  array  $options  Additional options (order, set_length)
     * @return bool True if performer was added, false if already exists
     */
    public function handle(Event $event, Band $band, array $options = []): bool
    {
        // Check if the band is already a performer
        if ($event->performers()->where('band_profile_id', $band->id)->exists()) {
            return false;
        }

        // Calculate order if not provided
        if (! isset($options['order'])) {
            $maxOrder = $event->performers()->max('event_bands.order');
            $options['order'] = $maxOrder ? $maxOrder + 1 : 1;
        }

        // Attach the performer with pivot data
        $event->performers()->attach($band->id, [
            'order' => $options['order'],
            'set_length' => $options['set_length'] ?? null,
        ]);

        // Future: Add notification logic here
        // - Notify band members that they were added to an event
        // - Notify event organizer

        return true;
    }
}
