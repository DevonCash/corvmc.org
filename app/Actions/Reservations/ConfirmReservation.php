<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Enums\CreditType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Actions\Action;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationConfirmedNotification;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmReservation
{
    use AsAction;

    /**
     * Confirm a scheduled or reserved reservation.
     *
     * For Scheduled reservations: Credits were already deducted at scheduling time.
     * For Reserved reservations: Credits are deducted now at confirmation time.
     */
    public function handle(RehearsalReservation $reservation): RehearsalReservation
    {
        if (! in_array($reservation->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
            return $reservation;
        }

        $user = $reservation->getResponsibleUser();

        // Complete the database transaction first
        $reservation = DB::transaction(function () use ($reservation, $user) {
            // For Reserved status, deduct credits now (they weren't deducted at creation)
            if ($reservation->status === ReservationStatus::Reserved && $reservation->free_hours_used > 0) {
                $freeBlocks = Reservation::hoursToBlocks($reservation->free_hours_used);
                if ($freeBlocks > 0) {
                    $user->deductCredit(
                        $freeBlocks,
                        CreditType::FreeHours,
                        'reservation_usage',
                        $reservation->id
                    );
                }
            }

            // Update status to confirmed
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
                in_array($record->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved]) &&
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
