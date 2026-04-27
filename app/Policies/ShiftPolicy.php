<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Volunteering\Models\Shift;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('volunteer.shift.manage');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->hasPermissionTo('volunteer.shift.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('volunteer.shift.manage');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->hasPermissionTo('volunteer.shift.manage');
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->hasPermissionTo('volunteer.shift.manage');
    }
}
