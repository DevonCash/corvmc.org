<?php

namespace App\Actions\Productions;

use App\Models\Band;
use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class HasPerformer
{
    use AsAction;

    /**
     * Check if a production has a specific performer.
     */
    public function handle(Production $production, Band $band): bool
    {
        return $production->performers()->where('band_profile_id', $band->id)->exists();
    }
}
