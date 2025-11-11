<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Concerns\AsFilamentAction;
use App\Enums\CreditType;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationConfirmedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmReservation
{
    use AsAction;
    use AsFilamentAction;

    protected static ?string $actionLabel = 'Confirm';

    protected static ?string $actionIcon = 'tabler-check';

    protected static string $actionColor = 'success';

    protected static bool $actionConfirm = true;

    protected static string $actionSuccessMessage = 'Reservation confirmed and user notified';

    protected static function isActionVisible(...$args): bool
    {
        $record = $args[0] ?? null;

        return $record instanceof RehearsalReservation &&
            $record->status === 'pending' &&
            User::me()?->can('manage reservations');
    }

    /**
     * Confirm a pending reservation.
     *
     * This recalculates the cost with current credit balance and deducts credits.
     * Should be called when user confirms a reservation within the confirmation window.
     */
    public function handle(RehearsalReservation $reservation): RehearsalReservation
    {
        if ($reservation->status !== 'pending') {
            return $reservation;
        }

        return DB::transaction(function () use ($reservation) {
            $user = $reservation->getResponsibleUser();

            // Recalculate cost with current credit balance
            $costCalculation = CalculateReservationCost::run(
                $user,
                $reservation->reserved_at,
                $reservation->reserved_until
            );

            // Deduct credits if any free hours are available
            $freeBlocks = Reservation::hoursToBlocks($costCalculation['free_hours']);
            if ($freeBlocks > 0) {
                $user->deductCredit(
                    $freeBlocks,
                    CreditType::FreeHours,
                    'reservation_usage',
                    $reservation->id,
                    "Deducted {$freeBlocks} blocks for reservation confirmation"
                );
            }

            // Update reservation with calculated values
            $reservation->update([
                'status' => 'confirmed',
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
            ]);

            // Auto-confirm if cost is zero
            if ($reservation->cost->isZero()) {
                $reservation->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            // Send confirmation notification
            $user->notify(new ReservationConfirmedNotification($reservation));

            $reservation = $reservation->fresh();

            // Sync to Google Calendar (update from pending yellow to confirmed green)
            SyncReservationToGoogleCalendar::run($reservation, 'update');

            return $reservation;
        });
    }
}
