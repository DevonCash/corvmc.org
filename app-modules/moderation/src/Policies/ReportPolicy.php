<?php

namespace CorvMC\Moderation\Policies;

use CorvMC\Moderation\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): ?bool
    {
        // Allow admins to view reports for moderation (or all users for testing)
        if (Report::where('reported_by_id', $user->id)->exists()) {
            return true;
        } // Allow all users for now

        return null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Report $report): bool
    {
        // Allow admins to view any report, or users to view their own reports
        return $user->hasRole('admin') || $report->reported_by_id === $user->id; // Allow all for testing
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create reports
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Report $report): bool
    {
        // Only admins can resolve/update reports (or all for testing)
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Report $report): bool
    {
        // Only admins can delete reports
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Report $report): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Report $report): bool
    {
        return false;
    }
}
