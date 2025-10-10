<?php

namespace App\Actions\Productions;

use App\Models\Band;
use App\Models\Production;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductionsForBand
{
    use AsAction;

    /**
     * Get productions featuring a specific band.
     */
    public function handle(Band $band): Collection
    {
        return Production::whereHas('performers', function ($query) use ($band) {
            $query->where('band_profile_id', $band->id);
        })->orderBy('start_time', 'desc')->get();
    }
}
