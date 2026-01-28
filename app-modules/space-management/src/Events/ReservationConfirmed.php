<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a reservation is confirmed.
 *
 * This is relevant for deferred credit deduction - when a reservation
 * was created with Reserved status and is now being confirmed.
 *
 * Listeners should:
 * - Deduct credits if they were deferred at creation
 */
class ReservationConfirmed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Reservation  $chargeable  The confirmed reservation
     * @param  ReservationStatus  $previousStatus  Status before confirmation
     */
    public function __construct(
        public Reservation $chargeable,
        public ReservationStatus $previousStatus,
    ) {}
}
