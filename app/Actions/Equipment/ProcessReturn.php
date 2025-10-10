<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentLoan;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessReturn
{
    use AsAction;

    /**
     * Process return of equipment.
     */
    public function handle(
        EquipmentLoan $loan,
        string $conditionIn,
        ?string $damageNotes = null
    ): EquipmentLoan {
        $loan->processReturn($conditionIn, $damageNotes);

        return $loan->fresh();
    }
}
