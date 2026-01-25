<?php

namespace App\Policies;

use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): ?bool
    {
        // Users can view their own reservations, admins can view all
        if ($user->can('view reservations')) {
            return true;
        }

        return true; // Users can see their own reservations via view method
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Reservation $reservation): ?bool
    {
        if ($user->id === $reservation->getResponsibleUser()?->id || $user->can('view reservations')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        // Practice space managers can update reservations
        if ($user->can('manage practice space')) {
            return true;
        }

        // Users can update their own reservations (within time limits)
        return $user->id === $reservation->getResponsibleUser()?->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Reservation $reservation): ?bool
    {
        if ($user->id === $reservation->getResponsibleUser()?->id || $user->can('delete reservations')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Reservation $reservation): ?bool
    {
        if ($user->id === $reservation->getResponsibleUser()?->id || $user->can('restore reservations')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Reservation $reservation): bool
    {
        return false;
    }
}
