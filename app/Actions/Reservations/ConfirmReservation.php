<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Enums\CreditType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationConfirmedNotification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmReservation
{
    use AsAction;

    /**
     * Confirm a pending reservation.
     *
     * This recalculates the cost with current credit balance and deducts credits.
     * Should be called when user confirms a reservation within the confirmation window.
     */
    public function handle(RehearsalReservation $reservation): RehearsalReservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
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
                    $reservation->id
                );
            }

            // Update reservation with calculated values
            $reservation->update([
                'status' => ReservationStatus::Confirmed,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
            ]);

            // Auto-confirm if cost is zero
            if ($reservation->cost->isZero()) {
                $reservation->update([
                    'payment_status' => PaymentStatus::Paid,
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

    public static function filamentAction(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon('tabler-check')
            ->color('success')
            ->visible(fn (Reservation $record) => $record instanceof RehearsalReservation &&
                $record->status === ReservationStatus::Pending &&
                User::me()?->can('manage reservations'))
            ->requiresConfirmation()
            ->action(function (Reservation $record) {
                static::run($record);

                \Filament\Notifications\Notification::make()
                    ->title('Reservation confirmed and user notified')
                    ->success()
                    ->send();
            });
    }
}
