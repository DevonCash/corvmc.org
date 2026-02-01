<?php

namespace App\Filament\Staff\Resources\SpaceClosures\Pages;

use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use App\Models\User;
use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSpaceClosure extends CreateRecord
{
    protected static string $resource = SpaceClosureResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $cancelReservations = $data['cancel_affected_reservations'] ?? false;
        $affectedReservationIds = ! empty($data['affected_reservations_data'])
            ? json_decode($data['affected_reservations_data'], true)
            : [];

        // Remove non-model fields
        unset(
            $data['cancel_affected_reservations'],
            $data['affected_reservations_data'],
            $data['affected_reservations_preview']
        );

        $data['created_by_id'] = User::me()->id;

        $closure = static::getModel()::create($data);

        // Cancel affected reservations if requested
        if ($cancelReservations && ! empty($affectedReservationIds)) {
            $cancellationReason = "Space closure: {$closure->type->getLabel()}";
            $cancelledCount = 0;

            foreach ($affectedReservationIds as $reservationId) {
                $reservation = Reservation::find($reservationId);
                if ($reservation && $reservation->status->isActive()) {
                    CancelReservation::run($reservation, $cancellationReason);
                    $cancelledCount++;
                }
            }

            if ($cancelledCount > 0) {
                Notification::make()
                    ->title('Reservations cancelled')
                    ->body("{$cancelledCount} " . str('reservation')->plural($cancelledCount) . ' cancelled and members notified.')
                    ->success()
                    ->send();
            }
        }

        return $closure;
    }
}
