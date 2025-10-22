<?php

namespace App\Actions\Reservations;

use App\Enums\CreditType;
use App\Models\Reservation;
use App\Models\RehearsalReservation;
use App\Notifications\ReservationConfirmedNotification;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmReservation
{
    use AsAction;

    /**
     * Confirm a pending reservation.
     *
     * This recalculates the cost with current credit balance and deducts credits.
     * Should be called when user confirms a reservation within the confirmation window.
     */
    public function handle(RehearsalReservation $reservation): RehearsalReservation
    {
        if ($reservation->status !== 'pending') {
            return $reservation;
        }

        return DB::transaction(function () use ($reservation) {
            $user = $reservation->getResponsibleUser();

            // Recalculate cost with current credit balance
            $costCalculation = CalculateReservationCost::run(
                $user,
                $reservation->reserved_at,
                $reservation->reserved_until
            );

            // Deduct credits if any free hours are available
            $freeBlocks = Reservation::hoursToBlocks($costCalculation['free_hours']);
            if ($freeBlocks > 0) {
                $user->deductCredit(
                    $freeBlocks,
                    CreditType::FreeHours,
                    'reservation_usage',
                    $reservation->id,
                    "Deducted {$freeBlocks} blocks for reservation confirmation"
                );
            }

            // Update reservation with calculated values
            $reservation->update([
                'status' => 'confirmed',
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
            ]);

            // Auto-confirm if cost is zero
            if ($reservation->cost->isZero()) {
                $reservation->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            // Send confirmation notification
            $user->notify(new ReservationConfirmedNotification($reservation));

            return $reservation->fresh();
        });
    }
}
