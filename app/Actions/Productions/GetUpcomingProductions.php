<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUpcomingProductions
{
    use AsAction;

    /**
     * Get upcoming productions.
     */
    public function handle(): Collection
    {
        return Production::where('status', 'published')
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->get();
    }
}
