<?php

namespace App\Actions\Productions;

use App\Models\Band;
use App\Models\Production;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAvailableBands
{
    use AsAction;

    /**
     * Get available bands for a production (bands that aren't already performing).
     */
    public function handle(Production $production, string $search = ''): Collection
    {
        return Band::withTouringBands()
            ->where('name', 'like', "%{$search}%")
            ->whereNotIn('id', $production->performers->pluck('id'))
            ->limit(50)
            ->get();
    }
}
