<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Moderation\Models\Report;

class ReportPolicy
{
    /**
     * Determine if the user can manage reports (moderator or admin).
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(['admin', 'moderator']);
    }

    /**
     * Determine if the user can view any reports.
     * Users can see the reports list (filtered in queries to show their own reports).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the report.
     * Moderators can view any report, users can view their own reports.
     */
    public function view(User $user, Report $report): bool
    {
        return $this->manage($user) || $report->isReportedBy($user);
    }

    /**
     * Determine if the user can create reports.
     * Any authenticated user can create reports.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the report.
     * Only moderators can resolve/update reports.
     */
    public function update(User $user, Report $report): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can delete the report.
     * Only moderators can delete reports.
     */
    public function delete(User $user, Report $report): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can restore the report.
     */
    public function restore(User $user, Report $report): bool
    {
        return false;
    }

    /**
     * Determine if the user can permanently delete the report.
     */
    public function forceDelete(User $user, Report $report): bool
    {
        return false;
    }

    /**
     * Determine if the user can resolve the report.
     */
    public function resolve(User $user, Report $report): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine if the user can escalate the report.
     */
    public function escalate(User $user, Report $report): bool
    {
        return $this->manage($user);
    }
}
