<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Models\Reservation;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class GetAvailableTimeSlots
{
    use AsAction;

    /**
     * Get available time slots for a given date considering all reservations.
     * Note: Events automatically create reservations, so we only need to check reservations.
     */
    public function handle(Carbon $date, int $durationHours = 1): array
    {
        $slots = [];
        $startHour = 9; // 9 AM
        $endHour = 22; // 10 PM

        // Get all reservations for the day once to optimize queries
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $existingReservations = Reservation::with('reservable')
            ->where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $dayStart)
            ->where('reserved_at', '<', $dayEnd)
            ->get();

        for ($hour = $startHour; $hour <= $endHour - $durationHours; $hour++) {
            $slotStart = $date->copy()->setTime($hour, 0);
            $slotEnd = $slotStart->copy()->addHours($durationHours);
            $slotPeriod = Period::make($slotStart, $slotEnd, Precision::MINUTE());

            $hasReservationConflict = $existingReservations->contains(function (Reservation $reservation) use ($slotPeriod) {
                return $reservation->overlapsWith($slotPeriod);
            });

            if (! $hasReservationConflict) {
                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'duration' => $durationHours,
                    'period' => $slotPeriod,
                ];
            }
        }

        return $slots;
    }
}
