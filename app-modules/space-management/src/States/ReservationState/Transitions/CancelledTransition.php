<?php

namespace CorvMC\SpaceManagement\States\ReservationState\Transitions;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\States\ReservationState;
use Spatie\ModelStates\Transition;

class CancelledTransition extends Transition
{
    public static $name = 'cancelled';

    private Reservation $reservation;
    private ?string $reason;

    public function __construct(Reservation $reservation, ?string $reason = null)
    {
        $this->reservation = $reservation;
        $this->reason = $reason;
    }

    public function handle(): Reservation
    {
        // Set cancellation reason
        $this->reservation->cancellation_reason = $this->reason ?? 'User cancelled';
        
        // TODO: Add cancelled_at column to reservations table
        // $this->reservation->cancelled_at = now();
        
        $this->reservation->save();

        // Log the cancellation
        activity('reservation')
            ->performedOn($this->reservation)
            ->event('cancelled')
            ->withProperties([
                'cancellation_reason' => $this->reservation->cancellation_reason,
                // 'cancelled_at' => $this->reservation->cancelled_at->toDateTimeString(),
            ])
            ->log('Reservation cancelled: ' . $this->reservation->cancellation_reason);

        return $this->reservation;
    }
}
