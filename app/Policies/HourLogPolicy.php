<?php

namespace App\Policies;

use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\Confirmed;

class HourLogPolicy
{
    /**
     * Sign up for a shift.
     */
    public function signUp(User $user): bool
    {
        return $user->hasPermissionTo('volunteer.signup');
    }

    /**
     * Confirm or release a volunteer.
     * Allowed for volunteer.manage permission holders or the event's organizer.
     */
    public function manage(User $user, HourLog $hourLog): bool
    {
        if ($user->hasPermissionTo('volunteer.manage')) {
            return true;
        }

        // Event organizers can manage volunteers for their events
        return $this->isEventOrganizer($user, $hourLog);
    }

    /**
     * Alias for manage — used by confirm action.
     */
    public function confirm(User $user, HourLog $hourLog): bool
    {
        return $this->manage($user, $hourLog);
    }

    /**
     * Alias for manage — used by release action.
     */
    public function release(User $user, HourLog $hourLog): bool
    {
        return $this->manage($user, $hourLog);
    }

    /**
     * Check in a volunteer.
     * Self-check-in: allowed if it's the user's own HourLog in Confirmed status.
     * Staff check-in: allowed with volunteer.checkin permission.
     */
    public function checkIn(User $user, HourLog $hourLog): bool
    {
        // Self-check-in
        if ($hourLog->user_id === $user->id && $hourLog->status instanceof Confirmed) {
            return true;
        }

        return $user->hasPermissionTo('volunteer.checkin');
    }

    /**
     * Check out a volunteer.
     * Self-check-out: allowed if it's the user's own HourLog in CheckedIn status.
     * Staff check-out: allowed with volunteer.checkin permission.
     */
    public function checkOut(User $user, HourLog $hourLog): bool
    {
        // Self-check-out
        if ($hourLog->user_id === $user->id && $hourLog->status instanceof CheckedIn) {
            return true;
        }

        return $user->hasPermissionTo('volunteer.checkin');
    }

    /**
     * Submit self-reported hours.
     */
    public function submitHours(User $user): bool
    {
        return $user->hasPermissionTo('volunteer.hours.submit');
    }

    /**
     * Approve self-reported hours.
     */
    public function approve(User $user, HourLog $hourLog): bool
    {
        return $user->hasPermissionTo('volunteer.hours.approve');
    }

    /**
     * Reject self-reported hours.
     */
    public function reject(User $user, HourLog $hourLog): bool
    {
        return $user->hasPermissionTo('volunteer.hours.approve');
    }

    /**
     * View volunteer reports.
     */
    public function viewReport(User $user): bool
    {
        return $user->hasPermissionTo('volunteer.hours.report');
    }

    /**
     * Check if the user is the organizer of the event linked to this hour log's shift.
     */
    private function isEventOrganizer(User $user, HourLog $hourLog): bool
    {
        $shift = $hourLog->shift;
        if (! $shift || ! $shift->event_id) {
            return false;
        }

        $event = $shift->event;
        if (! $event) {
            return false;
        }

        return $event->isOrganizedBy($user);
    }
}
