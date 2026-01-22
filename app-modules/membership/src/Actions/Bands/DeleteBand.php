<?php

namespace CorvMC\Membership\Actions\Bands;

use App\Models\Band;
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
        return DB::transaction(function () use ($band) {
            // Remove all members
            $band->memberships()->delete();

            $band->tags()->detach();

            return $band->delete();
        });
    }
}
