<?php

namespace App\States\EquipmentLoan;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EquipmentLoanState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Requested::class)
            ->allowTransition(Requested::class, StaffPreparing::class)
            ->allowTransition(StaffPreparing::class, ReadyForPickup::class)
            ->allowTransition(ReadyForPickup::class, CheckedOut::class)
            ->allowTransition(CheckedOut::class, Overdue::class)
            ->allowTransition(CheckedOut::class, DropoffScheduled::class)
            ->allowTransition(CheckedOut::class, Returned::class) // Direct return from checked out
            ->allowTransition(Overdue::class, DropoffScheduled::class)
            ->allowTransition(Overdue::class, Returned::class) // Direct return from overdue
            ->allowTransition(DropoffScheduled::class, CheckedOut::class) // Member can reschedule
            ->allowTransition(DropoffScheduled::class, StaffProcessingReturn::class)
            ->allowTransition(StaffProcessingReturn::class, Returned::class)
            ->allowTransition(StaffProcessingReturn::class, DamageReported::class)
            ->allowTransition(DamageReported::class, Returned::class)

            // Allow direct return from various states
            ->allowTransition(Requested::class, Returned::class) // Direct return from requested

            // Allow cancellation from most states
            ->allowTransition(Requested::class, Cancelled::class)
            ->allowTransition(StaffPreparing::class, Cancelled::class)
            ->allowTransition(ReadyForPickup::class, Cancelled::class);
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'tabler-help';
    }

    public function description(): string
    {
        return 'Unknown loan status';
    }

    public function canBeCancelledByMember(): bool
    {
        return false;
    }

    public function requiresStaffAction(): bool
    {
        return false;
    }

    public function requiresMemberAction(): bool
    {
        return false;
    }
}
