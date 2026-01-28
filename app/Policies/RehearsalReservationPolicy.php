<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\SpaceManagement\Models\RehearsalReservation;

class RehearsalReservationPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('manage practice space');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RehearsalReservation $reservation): bool
    {
        return $this->manage($user) || $reservation->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function confirm(User $user, RehearsalReservation $reservation): bool
    {
        return $this->manage($user) || $reservation->isOwnedBy($user);
    }

    public function cancel(User $user, RehearsalReservation $reservation): bool
    {
        return $this->manage($user) || $reservation->isOwnedBy($user);
    }

    /**
     * Can this user schedule recurring rehearsal reservations?
     * Called by RecurringSeriesPolicy::create() via delegation.
     */
    public function scheduleRecurring(User $user): bool
    {
        return $user->hasRole('sustaining member');
    }
}
