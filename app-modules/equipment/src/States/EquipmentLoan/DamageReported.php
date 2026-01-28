<?php

namespace CorvMC\Equipment\States\EquipmentLoan;

class DamageReported extends EquipmentLoanState
{
    public static string $name = 'damage_reported';

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'tabler-alert-circle';
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
