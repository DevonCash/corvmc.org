<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\Reservation;
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
     * @param  ReservationStatus  $previousStatus  Status before confirmation
     */
    public function __construct(
        public Reservation $reservation,
        public ReservationStatus $previousStatus,
    ) {}
}
