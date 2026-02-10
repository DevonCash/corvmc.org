<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Bands\Events\BandUpdated as BandUpdatedEvent;
use CorvMC\Bands\Models\Band;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBand
{
    use AsAction;

    /**
     * Update a band.
     */
    private const LOGGED_FIELDS = ['name', 'bio', 'hometown', 'visibility'];

    public function handle(Band $band, array $data): Band
    {
        $originalData = $band->toArray();

        $band = DB::transaction(function () use ($band, $data) {
            $band->update($data);

            // Update tags if provided
            if (isset($data['tags'])) {
                $band->syncTags($data['tags']);
            }

            return $band->fresh();
        });

        $changedFields = array_keys(array_filter(
            array_intersect_key($band->toArray(), array_flip(self::LOGGED_FIELDS)),
            fn ($value, $key) => ($originalData[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if (! empty($changedFields)) {
            $oldValues = array_intersect_key($originalData, array_flip($changedFields));
            BandUpdatedEvent::dispatch($band, $changedFields, $oldValues);
        }

        return $band;
    }
}
