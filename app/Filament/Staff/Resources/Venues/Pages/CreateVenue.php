<?php

namespace App\Filament\Staff\Resources\Venues\Pages;

use App\Filament\Staff\Resources\Venues\VenueResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $venue = static::getModel()::create($data);

        // Automatically calculate distance if address is provided
        if ($data['address'] ?? null) {
            $venue->calculateDistance();
            $venue->save();
        }

        return $venue;
    }
}
