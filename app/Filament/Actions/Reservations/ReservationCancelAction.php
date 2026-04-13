<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Filament Action for cancelling reservations.
 *
 * This action handles the UI concerns for reservation cancellation
 * and delegates business logic to the ReservationService.
 */
class ReservationCancelAction
{
    public static function make(): Action
    {
        return Action::make('cancel_reservation')
            ->label('Cancel')
            ->icon('tabler-x')
            ->color('danger')
            ->authorize('cancel')
            ->schema([
                Textarea::make('cancellation_reason')
                    ->label('Cancellation Reason')
                    ->placeholder('Optional: Provide a reason for cancellation')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('Cancel Reservation')
            ->modalDescription(fn (Reservation $record) =>
                "Are you sure you want to cancel the reservation for {$record->reserved_at->format('M j, g:i A')}?"
            )
            ->modalIcon('tabler-alert-triangle')
            ->action(function (Reservation $record, array $data) {
                try {
                    $service = app(ReservationService::class);
                    $service->cancel($record, $data['cancellation_reason'] ?? null);

                    Notification::make()
                        ->title('Reservation cancelled')
                        ->body('The reservation has been cancelled successfully')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to cancel reservation')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
