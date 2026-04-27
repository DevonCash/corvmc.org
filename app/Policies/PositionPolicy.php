<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Volunteering\Models\Position;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('volunteer.position.manage');
    }

    public function view(User $user, Position $position): bool
    {
        return $user->hasPermissionTo('volunteer.position.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('volunteer.position.manage');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasPermissionTo('volunteer.position.manage');
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasPermissionTo('volunteer.position.manage');
    }

    public function restore(User $user, Position $position): bool
    {
        return $user->hasPermissionTo('volunteer.position.manage');
    }

    public function forceDelete(User $user, Position $position): bool
    {
        return false;
    }
}
