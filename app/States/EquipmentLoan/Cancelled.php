<?php

namespace App\States\EquipmentLoan;

class Cancelled extends EquipmentLoanState
{
    public static string $name = 'cancelled';
    
    public function color(): string
    {
        return 'gray';
    }
    
    public function icon(): string
    {
        return 'heroicon-o-x-circle';
    }
    
    public function description(): string
    {
        return 'Loan request cancelled';
    }
}