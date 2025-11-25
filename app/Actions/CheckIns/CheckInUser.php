<?php

namespace App\Actions\CheckIns;

use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckInUser
{
    use AsAction;

    /**
     * Check in a user for a specific reservation, shift, or event
     *
     * @param  User  $user  The user being checked in
     * @param  Model  $checkable  The entity being checked in for (Reservation, VolunteerShift, etc.)
     * @param  string|null  $notes  Optional notes about the check-in
     * @return CheckIn
     */
    public function handle(User $user, Model $checkable, ?string $notes = null): CheckIn
    {
        // Check if user already has an open check-in
        $existingCheckIn = $user->checkIns()
            ->currentlyCheckedIn()
            ->first();

        if ($existingCheckIn) {
            // Warn but allow - return the existing check-in
            logger()->warning("User {$user->id} already has an open check-in (ID: {$existingCheckIn->id}). Creating new check-in anyway.");
        }

        return CheckIn::create([
            'user_id' => $user->id,
            'checkable_type' => $checkable->getMorphClass(),
            'checkable_id' => $checkable->getKey(),
            'checked_in_at' => now(),
            'notes' => $notes,
        ]);
    }
}
