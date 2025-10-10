<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductionsInDateRange
{
    use AsAction;

    /**
     * Get published productions within a date range.
     */
    public function handle(\DateTime $startDate, \DateTime $endDate): Collection
    {
        return Production::where('status', 'published')
            ->whereBetween('start_time', [$startDate, $endDate])
            ->orderBy('start_time', 'asc')
            ->get();
    }
}
