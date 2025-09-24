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
        return 'tabler-user-circle';
    }

    public function description(): string
    {
        return 'Currently loaned out to a member';
    }
}
