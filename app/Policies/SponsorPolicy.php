<?php

namespace App\Policies;

use App\Models\User;

class SponsorPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAny(?User $user): bool
    {
        return $user && $user->can('view sponsors');
    }
}
