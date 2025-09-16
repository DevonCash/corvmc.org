<?php

namespace App\States\EquipmentLoan;

class CheckedOut extends EquipmentLoanState
{
    public static string $name = 'checked_out';
    
    public function color(): string
    {
        return 'primary';
    }
    
    public function icon(): string
    {
        return 'heroicon-o-arrow-right-circle';
    }
    
    public function description(): string
    {
        return 'Equipment checked out to member - in active use';
    }
    
    public function requiresMemberAction(): bool
    {
        return true;
    }
}