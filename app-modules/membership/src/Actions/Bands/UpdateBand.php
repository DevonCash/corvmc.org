<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Membership\Models\Band;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBand
{
    use AsAction;

    /**
     * Update a band.
     */
    public function handle(Band $band, array $data): Band
    {
        return DB::transaction(function () use ($band, $data) {
            $band->update($data);

            // Update tags if provided
            if (isset($data['tags'])) {
                $band->syncTags($data['tags']);
            }

            return $band->fresh();
        });
    }
}
