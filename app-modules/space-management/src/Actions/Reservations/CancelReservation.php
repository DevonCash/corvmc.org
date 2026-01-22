<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use App\Filament\Actions\Action;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Notifications\ReservationCancelledNotification;
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
     *
     * NOTE: Credit refunds are handled by Finance module via
     * ReservationCancelled event listener. This action only handles status changes.
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
            'cancellation_reason' => $reason,
        ]);

        // Fire event for Finance module to handle credit refunds
        if ($reservation instanceof RehearsalReservation) {
            ReservationCancelled::dispatch($reservation, $originalStatus);
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
