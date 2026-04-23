<?php

namespace CorvMC\Finance\Listeners;

use CorvMC\Finance\Facades\Finance;
use CorvMC\SpaceManagement\Events\ReservationCancelled;

class CancelOrderOnReservationCancelled
{
    public function handle(ReservationCancelled $event): void
    {
        $reservation = $event->reservation;
        $activeOrder = Finance::findActiveOrder($reservation);

        if ($activeOrder) {
            Finance::cancel($activeOrder);
        }
    }
}
