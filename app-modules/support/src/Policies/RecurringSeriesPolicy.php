<?php

namespace CorvMC\Support\Policies;

use CorvMC\Support\Models\RecurringSeries;
use App\Models\User;

class RecurringSeriesPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Users can view their own series, admins can view all
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RecurringSeries $recurringSeries): bool
    {
        // Users can view their own series, or staff can view all
        if ($user->id === $recurringSeries->user_id || $user->can('view reservations')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only sustaining members can create recurring series
        return $user->hasRole('sustaining member');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RecurringSeries $recurringSeries): bool
    {
        // Practice space managers can update any series
        if ($user->can('manage practice space')) {
            return true;
        }

        // Users can update their own series
        return $user->id === $recurringSeries->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RecurringSeries $recurringSeries): bool
    {
        // Users can delete (cancel) their own series, or staff can delete any
        if ($user->id === $recurringSeries->user_id || $user->can('delete reservations')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RecurringSeries $recurringSeries): bool
    {
        // Users can restore their own series, or staff can restore any
        if ($user->id === $recurringSeries->user_id || $user->can('restore reservations')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RecurringSeries $recurringSeries): bool
    {
        return false;
    }
}
