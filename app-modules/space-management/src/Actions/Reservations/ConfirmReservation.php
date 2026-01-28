<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use App\Filament\Shared\Actions\Action;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Notifications\ReservationConfirmedNotification;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\{DB, Log};
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmReservation
{
    use AsAction;

    /**
     * Check if a reservation can be confirmed based on business rules.
     */
    public static function canConfirm(RehearsalReservation $reservation): bool
    {
        // Must be in a confirmable status
        if (!in_array($reservation->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
            return false;
        }

        // Can't confirm more than 5 days in advance (3 days before reservation)
        if (!$reservation->reserved_at->subDays(5)->isNowOrPast()) {
            return false;
        }

        return true;
    }

    /**
     * Confirm a scheduled or reserved reservation.
     *
     * For Scheduled reservations: Credits were already deducted at scheduling time.
     * For Reserved reservations: Credits are deducted now via ReservationConfirmed event.
     *
     * NOTE: Credit deduction for Reserved status is handled by Finance module via
     * ReservationConfirmed event listener.
     */
    public function handle(RehearsalReservation $reservation, bool $notify_user = true): RehearsalReservation
    {
        if (! self::canConfirm($reservation)) {
            return $reservation;
        }

        $user = $reservation->getResponsibleUser();

        // Capture previous status for event
        $previousStatus = $reservation->status;

        // Complete the database transaction first
        $reservation = DB::transaction(function () use ($reservation, $previousStatus) {
            // Update status to confirmed
            $reservation->update([
                'status' => ReservationStatus::Confirmed,
            ]);

            // Fire event for Finance module to deduct deferred credits (Reserved → Confirmed)
            ReservationConfirmed::dispatch($reservation, $previousStatus);

            return $reservation->fresh();
        });

        // Send notification outside transaction - don't let email failures affect the confirmation
        try {
            if ($notify_user) {
                $user->notify(new ReservationConfirmedNotification($reservation));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send reservation confirmation email', [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
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
            ->visible(fn (Reservation $record) => self::canConfirm($record))
            ->authorize('confirm')
            ->schema([
                Toggle::make('notify_user')
                    ->label('Notify User')
                    ->default(true)
                    ->helperText('Send a confirmation email to the user.'),
            ])
            ->requiresConfirmation()
            ->action(function (Reservation $record, array $data) {
                static::run($record);

                \Filament\Notifications\Notification::make()
                    ->title('Reservation confirmed and user notified')
                    ->success()
                    ->send();
            });
    }
}
