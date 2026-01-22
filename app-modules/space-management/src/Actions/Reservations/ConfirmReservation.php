<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use App\Filament\Actions\Action;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use CorvMC\SpaceManagement\Notifications\ReservationConfirmedNotification;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\{DB, Log};
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmReservation
{
    use AsAction;

    public static function allowed(RehearsalReservation $reservation, User $user): bool
    {
        // Must be in a confirmable status
        if (!in_array($reservation->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
            return false;
        }

        // Can't confirm more than 5 days in advance (3 days before reservation)
        if (!$reservation->reserved_at->subDays(5)->isNowOrPast()) {
            return false;
        }

        // User can confirm their own reservation OR have manage permission
        // Check both user_id (direct owner) and reservable_id (polymorphic owner)
        $isOwnReservation = (int) $reservation->user_id === (int) $user->getKey()
            || (int) $reservation->reservable_id === (int) $user->getKey();
        $canManage = $user->can('manage reservations');

        return $isOwnReservation || $canManage;
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
        if (! self::allowed($reservation, $reservation->getResponsibleUser())) {
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
            ->visible(fn(Reservation $record) => self::allowed($record, User::me()))
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
