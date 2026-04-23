<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
    ) {}
}
