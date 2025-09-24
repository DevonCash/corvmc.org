<?php

namespace App\States\EquipmentLoan;

class StaffPreparing extends EquipmentLoanState
{
    public static string $name = 'staff_preparing';

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'tabler-wrecking-ball';
    }

    public function description(): string
    {
        return 'Staff is preparing equipment - checking condition and taking photos';
    }

    public function canBeCancelledByMember(): bool
    {
        return true;
    }

    public function requiresStaffAction(): bool
    {
        return true;
    }
}
