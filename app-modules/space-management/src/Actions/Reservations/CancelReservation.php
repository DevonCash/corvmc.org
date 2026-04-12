<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use App\Filament\Shared\Actions\Action;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Notifications\ReservationCancelledNotification;
use CorvMC\SpaceManagement\Services\ReservationService;
use Filament\Tables\Columns\Concerns\HasRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated Use ReservationService::cancel() instead
 * 
 * This action is maintained for backward compatibility.
 * New code should use the ReservationService directly.
 */
class CancelReservation
{
    use HasRecord;

    /**
     * @deprecated Use ReservationService::cancel() instead
     */
    public function handle(Reservation $reservation, ?string $reason = null): Reservation
    {
        return app(ReservationService::class)->cancel($reservation, $reason);
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
            ->authorize('cancel')
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
            ->authorize('manage')
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
