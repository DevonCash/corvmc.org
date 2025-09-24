<?php

namespace App\States\EquipmentLoan;

class StaffProcessingReturn extends EquipmentLoanState
{
    public static string $name = 'staff_processing_return';

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'tabler-progress-check';
    }

    public function description(): string
    {
        return 'Staff processing equipment return - inspecting condition';
    }

    public function requiresStaffAction(): bool
    {
        return true;
    }
}
