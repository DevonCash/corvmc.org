<?php

namespace App\Listeners;

use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Events\ReservationUpdated;

class LogReservationActivity
{
    public function handleCreated(ReservationCreated $event): void
    {
        $reservation = $event->chargeable;
        $date = $reservation->reserved_at->format('M j, Y');
        $timeRange = $reservation->reserved_at->format('g:i A') . ' - ' . $reservation->reserved_until->format('g:i A');

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy($reservation->getResponsibleUser())
            ->event('created')
            ->withProperties([
                'reserved_at' => $reservation->reserved_at->toIso8601String(),
                'reserved_until' => $reservation->reserved_until->toIso8601String(),
                'hours_used' => $reservation->hours_used,
                'free_hours_used' => $reservation->free_hours_used,
                'status' => $reservation->status->value,
            ])
            ->log("Reservation created for {$date} {$timeRange}");
    }

    public function handleConfirmed(ReservationConfirmed $event): void
    {
        $reservation = $event->chargeable;
        $causer = auth()->user();

        $description = $causer
            ? 'Reservation confirmed'
            : 'Reservation auto-confirmed';

        $logger = activity('reservation')
            ->performedOn($reservation)
            ->event('confirmed')
            ->withProperties([
                'previous_status' => $event->previousStatus->value,
            ]);

        if ($causer) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }

    public function handleCancelled(ReservationCancelled $event): void
    {
        $reservation = $event->chargeable;
        $reason = $reservation->cancellation_reason ?? 'No reason provided';

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy(auth()->user())
            ->event('cancelled')
            ->withProperties([
                'original_status' => $event->originalStatus->value,
                'cancellation_reason' => $reason,
            ])
            ->log("Reservation cancelled: {$reason}");
    }

    public function handleUpdated(ReservationUpdated $event): void
    {
        $reservation = $event->chargeable;
        $date = $reservation->reserved_at->format('M j, Y');
        $timeRange = $reservation->reserved_at->format('g:i A') . ' - ' . $reservation->reserved_until->format('g:i A');

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy(auth()->user())
            ->event('rescheduled')
            ->withProperties([
                'reserved_at' => $reservation->reserved_at->toIso8601String(),
                'reserved_until' => $reservation->reserved_until->toIso8601String(),
                'old_billable_units' => $event->oldBillableUnits,
                'hours_used' => $reservation->hours_used,
            ])
            ->log("Reservation rescheduled to {$date} {$timeRange}");
    }
}
