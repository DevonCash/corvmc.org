<?php

namespace CorvMC\Equipment\States\Equipment;

class Available extends EquipmentState
{
    public static string $name = 'available';

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'tabler-circle-check';
    }

    public function description(): string
    {
        return 'Available for checkout';
    }
}
