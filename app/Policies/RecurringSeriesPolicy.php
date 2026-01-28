<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Support\Models\RecurringSeries;

class RecurringSeriesPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole('practice space manager');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RecurringSeries $series): bool
    {
        return $this->manage($user) || $series->isOwnedBy($user);
    }

    /**
     * Delegates to the recurrable type's policy.
     * E.g., for RehearsalReservation, checks RehearsalReservationPolicy::scheduleRecurring()
     */
    public function create(User $user, ?string $recurrableType = null): bool
    {
        if (!$recurrableType) {
            return false;
        }

        return $user->can('scheduleRecurring', $recurrableType);
    }

    public function cancel(User $user, RecurringSeries $series): bool
    {
        return $this->manage($user) || $series->isOwnedBy($user);
    }
}
