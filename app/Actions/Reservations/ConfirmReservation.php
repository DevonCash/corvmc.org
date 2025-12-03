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
     * Confirm a scheduled reservation.
     *
     * This is just an acknowledgement from the user that they remember their reservation.
     * Credits were already deducted at scheduling time.
     * This recalculates cost in case pricing changed, but does NOT re-deduct credits.
     */
    public function handle(RehearsalReservation $reservation): RehearsalReservation
    {
        if ($reservation->status !== ReservationStatus::Scheduled) {
            return $reservation;
        }

        $user = $reservation->getResponsibleUser();

        // Complete the database transaction first
        $reservation = DB::transaction(function () use ($reservation, $user) {
            // Note: Credits were already deducted at scheduling time
            // We just update the status to confirmed
            $reservation->update([
                'status' => ReservationStatus::Confirmed,
            ]);

            // Mark payment as not applicable if cost is zero
            if ($reservation->cost->isZero()) {
                $reservation->update([
                    'payment_status' => PaymentStatus::NotApplicable,
                ]);
            }

            return $reservation->fresh();
        });

        // Send notification outside transaction - don't let email failures affect the confirmation
        try {
            $user->notify(new ReservationConfirmedNotification($reservation));
        } catch (\Exception $e) {
            \Log::error('Failed to send reservation confirmation email', [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Sync to Google Calendar outside transaction - don't let sync failures affect the confirmation
        try {
            SyncReservationToGoogleCalendar::run($reservation, 'update');
        } catch (\Exception $e) {
            \Log::error('Failed to sync reservation to Google Calendar', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $reservation;
    }

    public static function filamentAction(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon('tabler-check')
            ->color('success')
            ->visible(fn (Reservation $record) => $record instanceof RehearsalReservation &&
                $record->status === ReservationStatus::Scheduled &&
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
