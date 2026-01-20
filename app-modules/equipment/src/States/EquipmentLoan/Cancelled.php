<?php

namespace CorvMC\Equipment\States\EquipmentLoan;

class Cancelled extends EquipmentLoanState
{
    public static string $name = 'cancelled';

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'tabler-circle-x';
    }

    public function description(): string
    {
        return 'Loan request cancelled';
    }
}
