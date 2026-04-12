<?php

namespace App\Filament\Band\Resources\BandMembersResource\Pages;

use App\Filament\Band\Resources\BandMembersResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListBandMembers extends ListRecords
{
    protected static string $resource = BandMembersResource::class;


    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['memberProfile.user']));
    }
}
