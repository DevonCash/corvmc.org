<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Equipment\Models\EquipmentLoan;

class EquipmentLoanPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('equipment manager');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EquipmentLoan $loan): bool
    {
        // Manager or the borrower can view
        return $this->manage($user) || $loan->isBorrower($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, EquipmentLoan $loan): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, EquipmentLoan $loan): bool
    {
        return $this->manage($user);
    }

    public function restore(User $user, EquipmentLoan $loan): bool
    {
        return $this->manage($user);
    }

    public function forceDelete(User $user, EquipmentLoan $loan): bool
    {
        return false; // Never allowed
    }

    // Domain-specific methods
    public function cancel(User $user, EquipmentLoan $loan): bool
    {
        // Manager can always cancel
        if ($this->manage($user)) {
            return true;
        }
        // Borrower can cancel if not yet checked out
        return $loan->isBorrower($user) && !$loan->checked_out_at;
    }

    public function return(User $user, EquipmentLoan $loan): bool
    {
        return $this->manage($user);
    }

    public function reportDamage(User $user, EquipmentLoan $loan): bool
    {
        // Manager or borrower can report damage
        return $this->manage($user) || $loan->isBorrower($user);
    }
}
