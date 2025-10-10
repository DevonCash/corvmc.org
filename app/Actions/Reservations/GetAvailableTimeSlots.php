<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use App\Models\Production;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAvailableTimeSlots
{
    use AsAction;

    /**
     * Get available time slots for a given date considering both reservations and productions.
     */
    public function handle(Carbon $date, int $durationHours = 1): array
    {
        $slots = [];
        $startHour = 9; // 9 AM
        $endHour = 22; // 10 PM

        // Get all reservations and productions for the day once to optimize queries
        $dayStart = $date->copy()->setTime(0, 0);
        $dayEnd = $date->copy()->setTime(23, 59);

        $existingReservations = Reservation::with('reservable')
            ->where('status', '!=', 'cancelled')
            ->where('reserved_until', '>', $dayStart)
            ->where('reserved_at', '<', $dayEnd)
            ->get();

        $existingProductions = Production::query()
            ->where('end_time', '>', $dayStart)
            ->where('start_time', '<', $dayEnd)
            ->get()
            ->filter(function (Production $production) {
                return $production->usesPracticeSpace();
            });

        for ($hour = $startHour; $hour <= $endHour - $durationHours; $hour++) {
            $slotStart = $date->copy()->setTime($hour, 0);
            $slotEnd = $slotStart->copy()->addHours($durationHours);
            $slotPeriod = \App\Facades\ReservationService::createPeriod($slotStart, $slotEnd);

            if (!$slotPeriod) {
                continue; // Skip invalid periods
            }

            $hasReservationConflict = $existingReservations->contains(function (Reservation $reservation) use ($slotPeriod) {
                return $reservation->overlapsWith($slotPeriod);
            });

            $hasProductionConflict = $existingProductions->contains(function (Production $production) use ($slotPeriod) {
                return $production->overlapsWith($slotPeriod);
            });

            if (!$hasReservationConflict && !$hasProductionConflict) {
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
