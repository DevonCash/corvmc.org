<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Filament\Resources\Bands\BandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBand extends CreateRecord
{
    protected static string $resource = BandResource::class;

    protected function beforeCreate(): void
    {
        // Check for claimable bands with the same name
        $bandService = \BandService::getFacadeRoot();
        $claimableBand = $bandService->findClaimableBand($this->data['name']);
        
        if ($claimableBand && $bandService->canClaimBand($claimableBand, auth()->user())) {
            // Redirect to claiming workflow instead of creating duplicate
            $this->halt();
            
            session()->flash('claimable_band', [
                'id' => $claimableBand->id,
                'name' => $claimableBand->name,
                'data' => $this->data
            ]);
            
            $this->redirect(static::getUrl('claim'));
        }
    }

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
