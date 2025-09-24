<?php

namespace App\States\EquipmentLoan;

class Requested extends EquipmentLoanState
{
    public static string $name = 'requested';

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'tabler-hand-stop';
    }

    public function description(): string
    {
        return 'Member has requested loan - awaiting staff preparation';
    }

    public function canBeCancelledByMember(): bool
    {
        return true;
    }

    public function requiresStaffAction(): bool
    {
        return true;
    }
}
