<?php

namespace App\Actions\Reservations;

use App\Models\RehearsalReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateReservation
{
    use AsAction;

    /**
     * Update an existing reservation.
     */
    public function handle(RehearsalReservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): RehearsalReservation
    {
        $errors = ValidateReservation::run($reservation->user, $startTime, $endTime, $reservation->id);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(' ', $errors));
        }

        $costCalculation = \App\Facades\ReservationService::calculateCost($reservation->user, $startTime, $endTime);

        return DB::transaction(function () use ($reservation, $startTime, $endTime, $costCalculation, $options) {
            $reservation->update([
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'notes' => $options['notes'] ?? $reservation->notes,
                'status' => $options['status'] ?? $reservation->status,
            ]);

            return $reservation;
        });
    }
}
