<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a reservation is cancelled.
 *
 * Listeners should:
 * - Refund credits (if they were deducted based on original status)
 * - Update Charge status to refunded
 */
class ReservationCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Reservation  $chargeable  The cancelled reservation
     * @param  ReservationStatus  $originalStatus  Status before cancellation
     */
    public function __construct(
        public Reservation $chargeable,
        public ReservationStatus $originalStatus,
    ) {}
}
