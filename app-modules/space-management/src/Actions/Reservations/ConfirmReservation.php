<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Enums\CreditType;
use CorvMC\SpaceManagement\Enums\PaymentStatus;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
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
        return in_array($reservation->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved]) &&
            $user->can('manage reservations')
            && $reservation->reserved_at->subDays(5)->isNowOrPast(); // Can't confirm more than 3 days in advance
    }

    /**
     * Confirm a scheduled or reserved reservation.
     *
     * For Scheduled reservations: Credits were already deducted at scheduling time.
     * For Reserved reservations: Credits are deducted now at confirmation time.
     */
    public function handle(RehearsalReservation $reservation, bool $notify_user = true): RehearsalReservation
    {
        if (! self::allowed($reservation, $reservation->getResponsibleUser())) {
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

        // Sync to Google Calendar outside transaction - don't let sync failures affect the confirmation
        try {
            SyncReservationToGoogleCalendar::run($reservation, 'update');
        } catch (\Exception $e) {
            Log::error('Failed to sync reservation to Google Calendar', [
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
