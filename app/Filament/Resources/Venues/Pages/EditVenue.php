<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to delete this venue? Any events associated with it will need to be reassigned.'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recalculate distance if address changed
        if ($this->record->isDirty(['address', 'city', 'state', 'zip'])) {
            $this->record->fill($data);
            $this->record->calculateDistance();
            $data['latitude'] = $this->record->latitude;
            $data['longitude'] = $this->record->longitude;
            $data['distance_from_corvallis'] = $this->record->distance_from_corvallis;
        }

        return $data;
    }
}
