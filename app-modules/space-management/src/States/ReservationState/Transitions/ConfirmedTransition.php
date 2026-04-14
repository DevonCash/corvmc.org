<?php

namespace CorvMC\SpaceManagement\States\ReservationState\Transitions;

use CorvMC\SpaceManagement\Models\Reservation;
use Spatie\ModelStates\Transition;

class ConfirmedTransition extends Transition
{
    private Reservation $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    public function handle(): Reservation
    {
        // TODO: Add confirmed_at column to reservations table
        // $this->reservation->confirmed_at = now();
        // $this->reservation->save();

        // Log the confirmation
        activity('reservation')
            ->performedOn($this->reservation)
            ->event('confirmed')
            ->withProperties([
                'previous_status' => 'scheduled',
                // 'confirmed_at' => $this->reservation->confirmed_at->toDateTimeString(),
            ])
            ->log('Reservation confirmed');

        return $this->reservation;
    }
}