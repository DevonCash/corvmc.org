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
        // Managers can always confirm
        if ($this->manage($user)) {
            return true;
        }
        
        // Owners can confirm their own reservations within the time window
        if ($reservation->isOwnedBy($user)) {
            // Business rule: Can't confirm more than 5 days in advance
            // This could also use ReservationService::checkConfirmationReadiness()
            // but we'll keep it simple here
            $daysUntilReservation = now()->diffInDays($reservation->reserved_at, false);
            return $daysUntilReservation <= 5;
        }
        
        return false;
    }

    public function cancel(User $user, RehearsalReservation $reservation): bool
    {
        return $this->manage($user) || $reservation->isOwnedBy($user);
    }

    /**
     * Can this user schedule recurring rehearsal reservations?
     * Called by RecurringSeriesPolicy::create() via delegation.
     *
     * @param User $user The authenticated user
     * @param User|null $forUser The user the series is being created for (null = self)
     */
    public function scheduleRecurring(User $user, ?User $forUser = null): bool
    {
        // Practice space managers can create for anyone
        if ($user->hasRole('practice space manager')) {
            return true;
        }

        // Sustaining members can only create for themselves
        if ($user->hasRole('sustaining member')) {
            return $forUser === null || $forUser->is($user);
        }

        return false;
    }
}
