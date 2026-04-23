<?php

namespace CorvMC\Finance\Listeners;

use CorvMC\Finance\Facades\Finance;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use Illuminate\Support\Facades\Log;

class CancelOrderOnReservationCancelled
{
    public function handle(ReservationCancelled $event): void
    {
        $reservation = $event->reservation;
        $activeOrder = Finance::findActiveOrder($reservation);

        if (! $activeOrder) {
            return;
        }

        try {
            Finance::cancel($activeOrder);
        } catch (\RuntimeException $e) {
            // Order may already be in a terminal state (e.g. cancelled by
            // another process). Log and continue — the reservation is
            // already cancelled, so this is informational.
            Log::info('Could not cancel order for cancelled reservation', [
                'reservation_id' => $reservation->id,
                'order_id' => $activeOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
