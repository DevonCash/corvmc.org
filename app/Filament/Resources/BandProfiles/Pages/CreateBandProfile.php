<?php

namespace App\Filament\Resources\BandProfiles\Pages;

use App\Filament\Resources\BandProfiles\BandProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBandProfile extends CreateRecord
{
    protected static string $resource = BandProfileResource::class;

    protected function afterCreate(): void
    {
        // Automatically add the owner as an admin member
        $this->record->members()->attach(auth()->id(), [
            'role' => 'admin',
            'status' => 'active',
            'name' => auth()->user()->name,
            'invited_at' => now(),
        ]);
    }
}
