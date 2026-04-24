<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a reservation is created.
 */
class ReservationCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Reservation  $reservation  The created reservation
     * @param  bool  $deferCredits  Whether credit deduction should be deferred
     */
    public function __construct(
        public Reservation $reservation,
        public bool $deferCredits = false,
    ) {}
}
