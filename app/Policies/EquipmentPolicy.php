<?php

namespace App\Policies;

use App\Models\User;

class EquipmentPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAll(User $user): bool
    {
        return true;
    }
}
