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
        return 'heroicon-o-check-circle';
    }
    
    public function description(): string
    {
        return 'Equipment successfully returned and processed';
    }
}