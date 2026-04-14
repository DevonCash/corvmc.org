<?php

namespace CorvMC\SpaceManagement\States\ReservationState\Transitions;

use CorvMC\SpaceManagement\Models\Reservation;
use Spatie\ModelStates\Transition;

class CompletedTransition extends Transition
{
    private Reservation $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    public function handle(): Reservation
    {
        // TODO: Add completed_at column to reservations table
        // $this->reservation->completed_at = now();
        // $this->reservation->save();

        // Log the completion
        activity('reservation')
            ->performedOn($this->reservation)
            ->event('completed')
            ->withProperties([
                'previous_status' => 'confirmed',
                // 'completed_at' => $this->reservation->completed_at->toDateTimeString(),
            ])
            ->log('Reservation completed');

        return $this->reservation;
    }
}