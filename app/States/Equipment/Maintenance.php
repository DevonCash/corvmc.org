<?php

namespace App\States\Equipment;

class Maintenance extends EquipmentState
{
    public static string $name = 'maintenance';

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'tabler-tool';
    }

    public function description(): string
    {
        return 'Under maintenance or repair';
    }
}
