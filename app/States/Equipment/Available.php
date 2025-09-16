<?php

namespace App\States\Equipment;

class Available extends EquipmentState
{
    public static string $name = 'available';
    
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
        return 'Available for checkout';
    }
}