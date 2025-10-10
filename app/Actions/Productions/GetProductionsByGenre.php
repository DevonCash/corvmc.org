<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductionsByGenre
{
    use AsAction;

    /**
     * Get productions by genre.
     */
    public function handle(string $genre): Collection
    {
        return Production::withAnyTags([$genre], 'genre')
            ->where('status', 'published')
            ->orderBy('start_time', 'asc')
            ->get();
    }
}
