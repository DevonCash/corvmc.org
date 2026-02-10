<?php

namespace CorvMC\Membership\Actions\Bands;

use CorvMC\Bands\Events\BandDeleted as BandDeletedEvent;
use CorvMC\Bands\Models\Band;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteBand
{
    use AsAction;

    /**
     * Delete a band.
     */
    public function handle(Band $band): bool
    {
        $result = DB::transaction(function () use ($band) {
            // Remove all members
            $band->memberships()->delete();

            $band->tags()->detach();

            return $band->delete();
        });

        if ($result) {
            BandDeletedEvent::dispatch($band);
        }

        return $result;
    }
}
