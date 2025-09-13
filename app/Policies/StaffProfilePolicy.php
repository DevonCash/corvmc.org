<?php

namespace App\Policies;

use App\Models\User;

class StaffProfilePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function updateField(User $user, $staffProfile, $field): ?bool
    {
        // Define which fields are restricted
        $restrictedFields = ['title', 'profile_type', 'display_order'];

        // If the field is restricted, only allow if the user has 'admin' role
        if (in_array($field, $restrictedFields)) {
            return null;
        }

        // Otherwise, allow all users to update non-restricted fields
        return true;
    }
}
