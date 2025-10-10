<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentLoan;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkOverdue
{
    use AsAction;

    /**
     * Mark equipment loan as overdue.
     */
    public function handle(EquipmentLoan $loan): void
    {
        $loan->markOverdue();
    }
}
