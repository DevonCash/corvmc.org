<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Equipment\Models\Equipment;

class EquipmentPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('equipment manager');
    }

    public function viewAny(?User $user): bool
    {
        return true; // Equipment library is public
    }

    public function view(?User $user, Equipment $equipment): bool
    {
        return true; // All equipment is publicly viewable
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $this->manage($user);
    }

    public function restore(User $user, Equipment $equipment): bool
    {
        return $this->manage($user);
    }

    public function forceDelete(User $user, Equipment $equipment): bool
    {
        return false; // Never allowed
    }

    // Domain-specific methods
    public function checkout(User $user, Equipment $equipment): bool
    {
        return $this->manage($user);
    }
}
