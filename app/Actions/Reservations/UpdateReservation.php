<?php

namespace App\Actions\Reservations;

use App\Enums\CreditType;
use App\Models\Reservation;
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
    public function handle(Reservation $reservation, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
    {
        // Only validate and recalculate costs for rehearsal reservations
        if ($reservation instanceof RehearsalReservation) {
            $errors = ValidateReservation::run($reservation->user, $startTime, $endTime, $reservation->id);

            if (!empty($errors)) {
                throw new \InvalidArgumentException('Validation failed: ' . implode(' ', $errors));
            }

            $costCalculation = CalculateReservationCost::run($reservation->user, $startTime, $endTime);
        } else {
            // For production reservations, just set basic fields
            $costCalculation = [
                'cost' => $reservation->cost,
                'total_hours' => $startTime->diffInHours($endTime),
                'free_hours' => 0,
            ];
        }

        return DB::transaction(function () use ($reservation, $startTime, $endTime, $costCalculation, $options) {
            $updateData = [
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'notes' => $options['notes'] ?? $reservation->notes,
                'status' => $options['status'] ?? $reservation->status,
            ];

            // Only update cost-related fields for rehearsal reservations
            if ($reservation instanceof RehearsalReservation) {
                $oldFreeHoursUsed = $reservation->free_hours_used;
                $newFreeHours = $costCalculation['free_hours'];

                $updateData['cost'] = $costCalculation['cost'];
                $updateData['hours_used'] = $costCalculation['total_hours'];
                $updateData['free_hours_used'] = $newFreeHours;

                if (isset($options['payment_status'])) {
                    $updateData['payment_status'] = $options['payment_status'];
                }

                // Adjust credits if free hours changed
                $user = $reservation->getResponsibleUser();
                if ($user && $newFreeHours != $oldFreeHoursUsed) {
                    $oldBlocks = Reservation::hoursToBlocks($oldFreeHoursUsed);
                    $newBlocks = Reservation::hoursToBlocks($newFreeHours);
                    $blocksDifference = $newBlocks - $oldBlocks;

                    if ($blocksDifference > 0) {
                        // Need to deduct more credits
                        $user->deductCredit(
                            $blocksDifference,
                            CreditType::FreeHours,
                            'reservation_update',
                            $reservation->id
                        );
                    } elseif ($blocksDifference < 0) {
                        // Refund credits
                        $user->addCredit(
                            abs($blocksDifference),
                            CreditType::FreeHours,
                            'reservation_update',
                            $reservation->id,
                            "Refund from reservation update"
                        );
                    }
                }
            }

            $reservation->update($updateData);

            return $reservation;
        });
    }
}
