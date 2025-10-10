<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductionStats
{
    use AsAction;

    /**
     * Get production statistics.
     */
    public function handle(): array
    {
        return [
            'total' => Production::count(),
            'published' => Production::where('status', 'published')->count(),
            'upcoming' => Production::where('status', 'published')
                ->where('start_time', '>', now())
                ->count(),
            'completed' => Production::where('status', 'completed')->count(),
            'in_production' => Production::where('status', 'in-production')->count(),
            'cancelled' => Production::where('status', 'cancelled')->count(),
        ];
    }
}
