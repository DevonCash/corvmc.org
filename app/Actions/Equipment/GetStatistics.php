<?php

namespace App\Actions\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentLoan;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStatistics
{
    use AsAction;

    /**
     * Get equipment statistics.
     */
    public function handle(): array
    {
        return [
            'total_equipment' => Equipment::count(),
            'available_equipment' => Equipment::available()->count(),
            'checked_out_equipment' => Equipment::where('status', 'checked_out')->count(),
            'maintenance_equipment' => Equipment::where('status', 'maintenance')->count(),
            'active_loans' => EquipmentLoan::active()->count(),
            'overdue_loans' => EquipmentLoan::overdue()->count(),
            'donated_equipment' => Equipment::donated()->count(),
            'loaned_to_cmc' => Equipment::onLoanToCmc()->count(),
        ];
    }
}
