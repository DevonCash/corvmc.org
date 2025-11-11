<?php

namespace App\Actions\Payments;

use App\Actions\Reservations\ConfirmReservation;
use App\Concerns\AsFilamentAction;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsComped
{
    use AsAction, AsFilamentAction;

    protected static ?string $actionLabel = 'Mark Comped';

    protected static ?string $actionIcon = 'tabler-gift';

    protected static string $actionColor = 'info';

    protected static bool $actionConfirm = true;

    protected static string $actionSuccessMessage = 'Reservation marked as comped and user notified';

    protected static function isActionVisible(...$args): bool
    {
        $record = $args[0] ?? null;

        return $record instanceof RehearsalReservation &&
            $record->payment_status == 'unpaid' &&
            User::me()->can('manage reservations');
    }

    public function handle(Reservation $reservation, ?string $notes = null): void
    {
        // If the reservation is pending, confirm it first
        if ($reservation instanceof RehearsalReservation && $reservation->status === 'pending') {
            $reservation = ConfirmReservation::run($reservation);
            $reservation->refresh();
        }

        $reservation->update([
            'payment_status' => 'comped',
            'payment_method' => 'comp',
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }
}
