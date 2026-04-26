<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Reservation  $reservation  The cancelled reservation
     * @param  string|null  $previousStatus  State class of status before cancellation (e.g., Confirmed::class)
     */
    public function __construct(
        public Reservation $reservation,
        public ?string $previousStatus = null,
    ) {}
}
