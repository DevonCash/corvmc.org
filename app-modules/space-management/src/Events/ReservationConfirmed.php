<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\States\ReservationState;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a reservation is confirmed.
 */
class ReservationConfirmed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Reservation  $reservation  The confirmed reservation
     * @param  string  $previousStatus  State class of status before confirmation (e.g., Scheduled::class)
     */
    public function __construct(
        public Reservation $reservation,
        public string $previousStatus,
    ) {}
}
