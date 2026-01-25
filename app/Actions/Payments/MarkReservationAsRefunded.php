<?php

namespace App\Actions\Payments;

use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsRefunded
{
    use AsAction;

    public function handle(RehearsalReservation $reservation, ?string $notes = null): void
    {
        $reservation->charge?->markAsRefunded($notes);
    }
}
