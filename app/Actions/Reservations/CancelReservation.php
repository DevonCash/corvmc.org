<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Concerns\AsFilamentAction;
use App\Enums\CreditType;
use App\Enums\ReservationStatus;
use App\Models\CreditTransaction;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationCancelledNotification;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelReservation
{
    use AsAction, AsFilamentAction;

    protected static ?string $actionLabel = 'Cancel';

    protected static ?string $actionIcon = 'tabler-calendar-x';

    protected static string $actionColor = 'danger';

    protected static bool $actionConfirm = true;

    protected static string $actionSuccessMessage = 'Reservation cancelled';

    protected static function isActionVisible(...$args): bool
    {
        $record = $args[0] ?? null;

        if (! $record instanceof RehearsalReservation) {
            return false;
        }

        // Allow cancellation if user is the owner or has permission
        return ($record->reservable_id === User::me()?->id) ||
            User::me()?->can('manage reservations');
    }

    /**
     * Cancel a reservation.
     */
    public function handle(Reservation $reservation, ?string $reason = null): Reservation
    {
        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'notes' => $reservation->notes.($reason ? "\nCancellation reason: ".$reason : ''),
        ]);

        // Refund credits if this was a rehearsal reservation with free hours used
        if ($reservation instanceof RehearsalReservation && $reservation->free_hours_used > 0) {
            $user = $reservation->getResponsibleUser();

            if ($user) {
                // Find the original deduction transaction
                $deductionTransaction = CreditTransaction::where('user_id', $user->id)
                    ->where('credit_type', CreditType::FreeHours->value)
                    ->where('source', 'reservation_usage')
                    ->where('source_id', $reservation->id)
                    ->where('amount', '<', 0)
                    ->first();

                if ($deductionTransaction) {
                    // Refund the credits (add back the absolute value)
                    $blocksToRefund = abs($deductionTransaction->amount);
                    $user->addCredit(
                        $blocksToRefund,
                        CreditType::FreeHours,
                        'reservation_cancellation',
                        $reservation->id,
                        "Refund for cancelled reservation #{$reservation->id}"
                    );
                }
            }
        }

        // Send cancellation notification to responsible user
        $user = $reservation->getResponsibleUser();
        if ($user) {
            $user->notify(new ReservationCancelledNotification($reservation));
        }

        // Delete from Google Calendar
        SyncReservationToGoogleCalendar::run($reservation, 'delete');

        return $reservation;
    }

    public static function filamentAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->visible(fn (Reservation $record) => ($record instanceof RehearsalReservation && $record->reservable_id === User::me()?->id) ||
                User::me()?->can('manage reservations'))
            ->requiresConfirmation()
            ->action(function (Reservation $record) {
                static::run($record);

                \Filament\Notifications\Notification::make()
                    ->title('Reservation cancelled')
                    ->success()
                    ->send();
            });
    }

    public static function filamentBulkAction(): Action
    {
        return Action::make('bulk_cancel')
            ->label('Cancel Reservations')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->visible(fn () => User::me()->can('manage reservations'))
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                foreach ($records as $record) {
                    static::run($record);
                }
            })
            ->successNotificationTitle('Reservations cancelled')
            ->successNotification(fn (Collection $records) => $records->count().' reservations marked as cancelled');
    }
}
