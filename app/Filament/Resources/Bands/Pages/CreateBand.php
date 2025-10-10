<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Actions\Bands\CanClaimBand;
use App\Actions\Bands\CreateBand as CreateBandAction;
use App\Actions\Bands\FindClaimableBand;
use App\Filament\Resources\Bands\BandResource;
use App\Models\Band;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateBand extends CreateRecord
{
    protected static string $resource = BandResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Check for claimable bands with the same name first
        $claimableBand = FindClaimableBand::run($data['name']);

        if ($claimableBand && CanClaimBand::run($claimableBand, Auth::user())) {
            // Redirect to claiming workflow instead of creating duplicate
            session()->flash('claimable_band', [
                'id' => $claimableBand->id,
                'name' => $claimableBand->name,
                'data' => $data
            ]);

            $this->redirect(static::getUrl(['claim']));
        }

        return CreateBandAction::run($data);
    }
}
