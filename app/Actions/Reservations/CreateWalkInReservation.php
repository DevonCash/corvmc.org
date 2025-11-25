<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Enums\ReservationStatus;
use App\Models\RehearsalReservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateWalkInReservation
{
    use AsAction;

    /**
     * Create a walk-in reservation for immediate use (bypasses normal validation).
     *
     * Walk-in reservations:
     * - Can be created for same-day or past times
     * - Are immediately confirmed
     * - Still check for time slot conflicts
     * - Still apply credits/pricing
     *
     * @param  User  $user  The user the reservation is for
     * @param  Carbon  $startTime  Start time (can be in the past or today)
     * @param  Carbon  $endTime  End time
     * @param  array  $options  Additional options (notes, etc.)
     * @return RehearsalReservation
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $options = []): RehearsalReservation
    {
        // Validate time range is valid
        if ($endTime->lte($startTime)) {
            throw new \InvalidArgumentException('End time must be after start time.');
        }

        // Check business hours (9 AM - 10 PM)
        if ($startTime->hour < 9 || $endTime->hour > 22) {
            throw new \InvalidArgumentException('Reservations must be between 9 AM and 10 PM.');
        }

        // Check duration (1-8 hours)
        $hours = $startTime->floatDiffInHours($endTime);
        if ($hours < 1 || $hours > 8) {
            throw new \InvalidArgumentException('Reservation must be between 1 and 8 hours.');
        }

        // Check for conflicts
        $conflicts = GetAllConflicts::run($startTime, $endTime);
        if (! empty($conflicts)) {
            $conflictMessages = array_map(fn ($c) => $c['message'], $conflicts);
            throw new \InvalidArgumentException('Time slot conflicts: '.implode(' ', $conflictMessages));
        }

        $reservation = DB::transaction(function () use ($user, $startTime, $endTime, $options) {
            // Calculate cost
            $costCalculation = CalculateReservationCost::run($user, $startTime, $endTime);

            // Create reservation as confirmed (walk-ins are always confirmed)
            $reservation = RehearsalReservation::create([
                'user_id' => $user->id,
                'reservable_type' => User::class,
                'reservable_id' => $user->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'status' => ReservationStatus::Confirmed,
                'notes' => $options['notes'] ?? null,
            ]);

            // Deduct credits immediately since it's confirmed
            if ($costCalculation['free_hours'] > 0) {
                $user->deductFreeHoursCredit($costCalculation['free_hours']);
            }

            return $reservation;
        });

        // Sync to Google Calendar
        try {
            SyncReservationToGoogleCalendar::run($reservation, 'create');
        } catch (\Exception $e) {
            \Log::error('Failed to sync walk-in reservation to Google Calendar', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $reservation;
    }
}
