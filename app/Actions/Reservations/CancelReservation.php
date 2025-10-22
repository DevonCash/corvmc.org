<?php

namespace App\Actions\Reservations;

use App\Enums\CreditType;
use App\Models\CreditTransaction;
use App\Models\Reservation;
use App\Models\RehearsalReservation;
use App\Notifications\ReservationCancelledNotification;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelReservation
{
    use AsAction;

    /**
     * Cancel a reservation.
     */
    public function handle(Reservation $reservation, ?string $reason = null): Reservation
    {
        $reservation->update([
            'status' => 'cancelled',
            'notes' => $reservation->notes . ($reason ? "\nCancellation reason: " . $reason : ''),
        ]);

        // Refund credits if this was a rehearsal reservation with free hours used
        if ($reservation instanceof RehearsalReservation && $reservation->free_hours_used > 0) {
            $user = $reservation->getResponsibleUser();

            if ($user) {
                // Find the original deduction transaction
                $deductionTransaction = CreditTransaction::where('user_id', $user->id)
                    ->where('credit_type', CreditType::FreeHours->value)
                    ->where('source', 'reservation_usage')
                    ->where('source_id', $reservation->id)
                    ->where('amount', '<', 0)
                    ->first();

                if ($deductionTransaction) {
                    // Refund the credits (add back the absolute value)
                    $blocksToRefund = abs($deductionTransaction->amount);
                    $user->addCredit(
                        $blocksToRefund,
                        CreditType::FreeHours,
                        'reservation_cancellation',
                        $reservation->id,
                        "Refund for cancelled reservation #{$reservation->id}"
                    );
                }
            }
        }

        // Send cancellation notification to responsible user
        $user = $reservation->getResponsibleUser();
        if ($user) {
            $user->notify(new ReservationCancelledNotification($reservation));
        }

        return $reservation;
    }
}
