<?php

namespace CorvMC\SpaceManagement\Events;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a reservation is created.
 *
 * Listeners should:
 * - Create Charge record for pricing/payment tracking
 * - Deduct credits (unless deferred)
 */
class ReservationCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Reservation  $chargeable  The created reservation (implements Chargeable)
     * @param  bool  $deferCredits  Whether credit deduction should be deferred
     */
    public function __construct(
        public Reservation $chargeable,
        public bool $deferCredits = false,
    ) {}
}
