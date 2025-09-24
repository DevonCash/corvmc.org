<?php

namespace App\States\EquipmentLoan;

class Overdue extends EquipmentLoanState
{
    public static string $name = 'overdue';

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'tabler-calendar-exclamation';
    }

    public function description(): string
    {
        return 'Equipment loan is overdue - return required immediately';
    }

    public function requiresMemberAction(): bool
    {
        return true;
    }
}
