<?php

namespace CorvMC\Finance\Listeners;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Pending;
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
            if ($activeOrder->status instanceof Pending) {
                Finance::cancel($activeOrder);
            } elseif (($activeOrder->status instanceof Completed || $activeOrder->status instanceof Comped)
                && $reservation->reserved_at->isFuture()
            ) {
                Finance::refund($activeOrder);
            } else {
                Log::info('Reservation cancelled but order not auto-refunded (reservation already started)', [
                    'reservation_id' => $reservation->id,
                    'order_id' => $activeOrder->id,
                    'order_status' => $activeOrder->status->getLabel(),
                ]);
            }
        } catch (\RuntimeException $e) {
            // Order may already be in a terminal state (e.g. cancelled by
            // another process). Log and continue — the reservation is
            // already cancelled, so this is informational.
            Log::info('Could not cancel/refund order for cancelled reservation', [
                'reservation_id' => $reservation->id,
                'order_id' => $activeOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
