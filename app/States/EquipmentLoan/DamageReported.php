<?php

namespace App\States\EquipmentLoan;

class DamageReported extends EquipmentLoanState
{
    public static string $name = 'damage_reported';
    
    public function color(): string
    {
        return 'danger';
    }
    
    public function icon(): string
    {
        return 'heroicon-o-exclamation-circle';
    }
    
    public function description(): string
    {
        return 'Damage reported during return - requires assessment';
    }
    
    public function requiresStaffAction(): bool
    {
        return true;
    }
}