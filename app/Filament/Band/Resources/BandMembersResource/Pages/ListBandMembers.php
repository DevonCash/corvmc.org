<?php

namespace App\Filament\Band\Resources\BandMembersResource\Pages;

use App\Filament\Band\Resources\BandMembersResource;
use App\Models\Band;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBandMembers extends ListRecords
{
    protected static string $resource = BandMembersResource::class;

    protected function getTableQuery(): Builder
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        return parent::getTableQuery()
            ->where('band_profile_id', $band->id);
    }
}
