<?php

namespace App\Actions\Equipment;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPopularEquipment
{
    use AsAction;

    /**
     * Get popular equipment (most borrowed).
     */
    public function handle(int $limit = 10): Collection
    {
        return Equipment::withCount('loans')
            ->orderByDesc('loans_count')
            ->limit($limit)
            ->get();
    }
}
