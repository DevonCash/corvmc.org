<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Filament\Resources\Bands\BandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBand extends CreateRecord
{
    protected static string $resource = BandResource::class;

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
