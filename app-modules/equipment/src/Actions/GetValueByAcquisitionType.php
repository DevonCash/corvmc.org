<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Models\Equipment;
use Lorisleiva\Actions\Concerns\AsAction;

class GetValueByAcquisitionType
{
    use AsAction;

    /**
     * Calculate total value of equipment by acquisition type.
     */
    public function handle(): array
    {
        return Equipment::selectRaw('acquisition_type, SUM(estimated_value) as total_value')
            ->whereNotNull('estimated_value')
            ->groupBy('acquisition_type')
            ->pluck('total_value', 'acquisition_type')
            ->toArray();
    }
}
