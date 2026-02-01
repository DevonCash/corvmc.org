<?php

namespace App\Filament\Staff\Resources\SpaceClosures\Pages;

use App\Filament\Staff\Resources\SpaceClosures\Schemas\SpaceClosureCreateWizard;
use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Models\User;
use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSpaceClosures extends ListRecords
{
    protected static string $resource = SpaceClosureResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            SpaceManagementResource::getUrl('index') => 'Space Management',
            SpaceClosureResource::getUrl('index') => 'Closures',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Closure')
                ->icon('tabler-plus')
                ->modalWidth('lg')
                ->modalHeading('Create Space Closure')
                ->modalSubmitActionLabel('Create Closure')
                ->steps(SpaceClosureCreateWizard::getSteps())
                ->action(fn (array $data) => $this->createClosure($data)),
        ];
    }

    protected function createClosure(array $data): void
    {
        $cancelReservations = $data['cancel_affected_reservations'] ?? false;
        $affectedReservationIds = ! empty($data['affected_reservations_data'])
            ? json_decode($data['affected_reservations_data'], true)
            : [];

        // Create the closure
        $closure = SpaceClosure::create([
            'type' => $data['type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'notes' => $data['notes'] ?? null,
            'created_by_id' => User::me()->id,
        ]);

        // Cancel affected reservations if requested
        $cancelledCount = 0;
        if ($cancelReservations && ! empty($affectedReservationIds)) {
            $cancellationReason = "Space closure: {$closure->type->getLabel()}";

            foreach ($affectedReservationIds as $reservationId) {
                $reservation = Reservation::find($reservationId);
                if ($reservation && $reservation->status->isActive()) {
                    CancelReservation::run($reservation, $cancellationReason);
                    $cancelledCount++;
                }
            }
        }

        // Show success notification
        if ($cancelledCount > 0) {
            Notification::make()
                ->title('Closure created')
                ->body("Space closure created and {$cancelledCount} ".str('reservation')->plural($cancelledCount).' cancelled.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Closure created')
                ->success()
                ->send();
        }
    }
}
