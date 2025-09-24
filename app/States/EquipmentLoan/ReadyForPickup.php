<?php

namespace App\States\EquipmentLoan;

class ReadyForPickup extends EquipmentLoanState
{
    public static string $name = 'ready_for_pickup';

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
        return 'Equipment ready for member pickup - awaiting handoff';
    }

    public function canBeCancelledByMember(): bool
    {
        return true;
    }

    public function requiresMemberAction(): bool
    {
        return true;
    }
}
