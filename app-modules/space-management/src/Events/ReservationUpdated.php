<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a reservation is updated with time/duration changes.
 *
 * Listeners should:
 * - Recalculate pricing
 * - Adjust credits (deduct more or refund excess)
 * - Update Charge record
 */
class ReservationUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Reservation  $chargeable  The updated reservation
     * @param  float  $oldBillableUnits  Previous billable hours before update
     */
    public function __construct(
        public Reservation $chargeable,
        public float $oldBillableUnits,
    ) {}
}
