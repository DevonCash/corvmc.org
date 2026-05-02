<?php

namespace App\Listeners;

use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Services\UltraloqService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateLockCodeOnReservationConfirmed implements ShouldQueue
{
    public function __construct(
        protected UltraloqService $ultraloq,
    ) {}

    public function handle(ReservationConfirmed $event): void
    {
        $this->ultraloq->createTemporaryUser($event->reservation);
    }
}
