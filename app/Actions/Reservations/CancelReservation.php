<?php

namespace App\Actions\Reservations;

use App\Actions\GoogleCalendar\SyncReservationToGoogleCalendar;
use App\Enums\CreditType;
use App\Enums\ReservationStatus;
use App\Filament\Actions\Action;
use App\Models\CreditTransaction;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Notifications\ReservationCancelledNotification;
use Filament\Tables\Columns\Concerns\HasRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelReservation
{
    use AsAction;
    use HasRecord;

    /**
     * Cancel a reservation.
     */
    public function handle(Reservation $reservation, ?string $reason = null): Reservation
    {
        if (! $reservation) {
            throw new \InvalidArgumentException('Reservation not found.');
        }

        // Capture original status before cancelling
        $originalStatus = $reservation->status;

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'notes' => $reservation->notes.($reason ? "\nCancellation reason: ".$reason : ''),
        ]);

        // Refund credits if this was a rehearsal reservation with free hours used
        // Only refund for Scheduled/Confirmed status (Reserved status never had credits deducted)
        if ($reservation instanceof RehearsalReservation &&
            $reservation->free_hours_used > 0 &&
            in_array($originalStatus->value, ['pending', 'confirmed'])) {
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
            try {
                $user->notify(new ReservationCancelledNotification($reservation));
            } catch (\Exception $e) {
                Log::error('Failed to send reservation cancellation notification', [
                    'reservation_id' => $reservation->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete from Google Calendar
        try {
            SyncReservationToGoogleCalendar::run($reservation, 'delete');
        } catch (\Exception $e) {
            Log::error('Failed to delete cancelled reservation from Google Calendar', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $reservation;
    }

    public static function filamentAction(): Action
    {
        return Action::make('cancelReservation')
            ->label('Cancel')
            ->modalSubmitActionLabel('Cancel Reservation')
            ->modalCancelActionLabel('Keep Reservation')
            ->modalDescription('Are you sure you want to cancel this reservation? This action cannot be undone. Free hours used will be refunded if applicable.')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->visible(
                fn (?Reservation $record) => $record?->status->isActive() && $record->reserved_until > now()
            )
            ->authorize('update')
            ->requiresConfirmation()
            ->action(function (?Reservation $record) {
                static::run($record);
                \Filament\Notifications\Notification::make()
                    ->title('Reservation cancelled')
                    ->success()
                    ->send();
            });
    }

    public static function filamentBulkAction(): Action
    {
        return Action::make('bulkCancelReservations')
            ->label('Cancel Reservations')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->authorize('manage reservations')
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
