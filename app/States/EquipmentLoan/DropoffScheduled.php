<?php

namespace App\States\EquipmentLoan;

class DropoffScheduled extends EquipmentLoanState
{
    public static string $name = 'dropoff_scheduled';

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'tabler-calendar-down';
    }

    public function description(): string
    {
        return 'Member has scheduled equipment dropoff';
    }

    public function requiresMemberAction(): bool
    {
        return true;
    }
}
