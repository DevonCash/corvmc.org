<?php

namespace App\Actions\Bands;

use App\Models\Band;
use Lorisleiva\Actions\Concerns\AsAction;

class FindClaimableBand
{
    use AsAction;

    /**
     * Find a claimable band by name (touring band without owner).
     */
    public function handle(string $name): ?Band
    {
        return Band::where('name', $name)
            ->where('is_touring_band', true)
            ->whereNull('owner_id')
            ->first();
    }
}
