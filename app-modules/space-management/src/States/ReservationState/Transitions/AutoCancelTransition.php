<?php

namespace CorvMC\SpaceManagement\States\ReservationState;

use CorvMC\SpaceManagement\Models\RehearsalReservation;

use Spatie\ModelStates\Transition;

class AutoCancelTransition extends Transition
{
    private string $reason;

    public function __construct(RehearsalReservation $reservation, string $reason = 'Not confirmed within 3-day window')
    {
        $this->reservation = $reservation;
        $this->reason = $reason;
    }

    public function handle(): RehearsalReservation
    {
        // Set cancellation reason
        $this->reservation->cancellation_reason = $this->reason;
        $this->reservation->save();

        // Log the auto-cancellation
        activity('reservation')
            ->performedOn($this->reservation)
            ->event('auto_cancelled')
            ->withProperties([
                'reason' => $this->reason,
                'original_status' => 'reserved',
            ])
            ->log('Reservation auto-cancelled: ' . $this->reason);

        return $this->reservation;
    }
}
