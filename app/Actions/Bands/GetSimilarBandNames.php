<?php

namespace App\Actions\Bands;

use App\Models\Band;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSimilarBandNames
{
    use AsAction;

    /**
     * Get similar band names for suggestion purposes.
     */
    public function handle(string $bandName, int $limit = 5): Collection
    {
        return Band::where('name', 'ilike', "%{$bandName}%")
            ->whereNull('owner_id')
            ->limit($limit)
            ->pluck('name', 'id');
    }
}
