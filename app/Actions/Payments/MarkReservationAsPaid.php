<?php

namespace App\Actions\Payments;

use App\Actions\Reservations\ConfirmReservation;
use App\Concerns\AsFilamentAction;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsPaid
{
    use AsAction, AsFilamentAction;

    protected static ?string $actionLabel = 'Mark Paid';

    protected static ?string $actionIcon = 'tabler-check';

    protected static string $actionColor = 'success';

    protected static bool $actionConfirm = true;

    protected static string $actionSuccessMessage = 'Reservation confirmed and user notified';

    protected static function isActionVisible(...$args): bool
    {
        $record = $args[0] ?? null;

        return $record instanceof RehearsalReservation &&
            $record->payment_status == 'unpaid' &&
            User::me()->can('manage reservations');
    }

    public function handle(Reservation $reservation, ?string $paymentMethod = null, ?string $notes = null): void
    {
        // If the reservation is pending, confirm it first
        if ($reservation instanceof RehearsalReservation && $reservation->status === 'pending') {
            $reservation = ConfirmReservation::run($reservation);
            $reservation->refresh();
        }

        $reservation->update([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }
}
