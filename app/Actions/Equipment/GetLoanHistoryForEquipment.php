<?php

namespace App\Actions\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLoanHistoryForEquipment
{
    use AsAction;

    /**
     * Get loan history for specific equipment.
     */
    public function handle(Equipment $equipment): Collection
    {
        return EquipmentLoan::forEquipment($equipment)
            ->with('borrower')
            ->orderByDesc('checked_out_at')
            ->get();
    }
}
