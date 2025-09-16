<?php

namespace App\States\Equipment;

class Loaned extends EquipmentState
{
    public static string $name = 'loaned';
    
    public function color(): string
    {
        return 'warning';
    }
    
    public function icon(): string
    {
        return 'heroicon-o-user-circle';
    }
    
    public function description(): string
    {
        return 'Currently loaned out to a member';
    }
}