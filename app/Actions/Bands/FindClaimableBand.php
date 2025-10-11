<?php

namespace App\Actions\Bands;

use App\Models\Band;
use Lorisleiva\Actions\Concerns\AsAction;

class FindClaimableBand
{
    use AsAction;

    /**
     * Find a claimable band by name (band without owner).
     */
    public function handle(string $name): ?Band
    {
        return Band::where('name', $name)
            ->whereNull('owner_id')
            ->first();
    }
}
