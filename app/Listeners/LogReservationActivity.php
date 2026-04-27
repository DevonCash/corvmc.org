<?php

namespace App\Listeners;

use CorvMC\Finance\Events\OrderSettled;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Events\ReservationUpdated;
use CorvMC\SpaceManagement\Models\Reservation;

class LogReservationActivity
{
    public function handleCreated(ReservationCreated $event): void
    {
        $reservation = $event->reservation;
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
                'status' => $reservation->status instanceof \Spatie\ModelStates\State 
                    ? $reservation->status->getValue() 
                    : $reservation->status,
            ])
            ->log("Reservation created for {$date} {$timeRange}");
    }

    public function handleConfirmed(ReservationConfirmed $event): void
    {
        $reservation = $event->reservation;
        $causer = auth()->user();

        $description = $causer
            ? 'Reservation confirmed'
            : 'Reservation auto-confirmed';

        // Extract the short state name from the class string (e.g., 'Scheduled' from 'CorvMC\...\Scheduled')
        $previousStatusName = class_basename($event->previousStatus);
        $previousStatusValue = strtolower($previousStatusName);

        $logger = activity('reservation')
            ->performedOn($reservation)
            ->event('confirmed')
            ->withProperties([
                'previous_status' => $previousStatusValue,
            ]);

        if ($causer) {
            $logger->causedBy($causer);
        }

        $logger->log($description);
    }

    public function handleCancelled(ReservationCancelled $event): void
    {
        $reservation = $event->reservation;
        $reason = $reservation->cancellation_reason ?? 'No reason provided';

        $properties = [
            'cancellation_reason' => $reason,
        ];

        // Include the previous status if provided
        if ($event->previousStatus) {
            $previousStatusName = class_basename($event->previousStatus);
            $properties['original_status'] = strtolower($previousStatusName);
        }

        activity('reservation')
            ->performedOn($reservation)
            ->causedBy(auth()->user())
            ->event('cancelled')
            ->withProperties($properties)
            ->log("Reservation cancelled: {$reason}");
    }

    public function handleUpdated(ReservationUpdated $event): void
    {
        $reservation = $event->reservation;
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

    public function handleOrderSettled(OrderSettled $event): void
    {
        $order = $event->order;

        // Find reservation line items in this order
        $reservationItems = $order->lineItems->filter(
            fn ($item) => $item->product_type === 'rehearsal_time' && $item->product_id
        );

        if ($reservationItems->isEmpty()) {
            return;
        }

        $isComped = $order->status instanceof Comped;

        foreach ($reservationItems as $lineItem) {
            $reservation = Reservation::find($lineItem->product_id);

            if (! $reservation) {
                continue;
            }

            if ($isComped) {
                $reason = $order->notes ?? 'No reason provided';

                activity('payment')
                    ->performedOn($reservation)
                    ->causedBy(auth()->user())
                    ->event('comped')
                    ->withProperties([
                        'order_id' => $order->id,
                        'reason' => $reason,
                    ])
                    ->log("Charge comped: {$reason}");
            } else {
                // Determine payment method from the settled transaction
                $paymentMethod = $order->transactions
                    ->where('type', 'payment')
                    ->first()
                    ?->currency ?? 'unknown';

                activity('payment')
                    ->performedOn($reservation)
                    ->causedBy(auth()->user())
                    ->event('payment_recorded')
                    ->withProperties([
                        'order_id' => $order->id,
                        'payment_method' => $paymentMethod,
                        'amount' => $order->total_amount,
                    ])
                    ->log("Payment recorded via {$paymentMethod}");
            }
        }
    }
}
