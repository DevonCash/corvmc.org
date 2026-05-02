<?php

namespace App\Listeners;

use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Services\UltraloqService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeleteLockCodeOnReservationCancelled implements ShouldQueue
{
    public function __construct(
        protected UltraloqService $ultraloq,
    ) {}

    public function handle(ReservationCancelled $event): void
    {
        if ($event->reservation->lock_code) {
            $this->ultraloq->deleteUser($event->reservation);
        }
    }
}
