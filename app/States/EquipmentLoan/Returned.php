<?php

namespace App\States\EquipmentLoan;

class Returned extends EquipmentLoanState
{
    public static string $name = 'returned';

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'tabler-home-check';
    }

    public function description(): string
    {
        return 'Equipment successfully returned and processed';
    }
}
