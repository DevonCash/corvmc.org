<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Filament\Resources\Bands\BandResource;
use App\Filament\Traits\HasCrudService;
use App\Models\Band;
use Filament\Resources\Pages\CreateRecord;

class CreateBand extends CreateRecord
{
    use HasCrudService;

    protected static string $resource = BandResource::class;
    protected static ?string $crudService = 'BandService';

    protected function handleRecordCreation(array $data): Band
    {
        // Check for claimable bands with the same name first
        $bandService = $this->getCrudService();
        $claimableBand = $bandService->findClaimableBand($data['name']);
        
        if ($claimableBand && $bandService->canClaimBand($claimableBand, auth()->user())) {
            // Redirect to claiming workflow instead of creating duplicate
            session()->flash('claimable_band', [
                'id' => $claimableBand->id,
                'name' => $claimableBand->name,
                'data' => $data
            ]);
            
            $this->redirect(static::getUrl('claim'));
        }

        // Use the parent trait method to create the band
        return parent::handleRecordCreation($data);
    }
}
