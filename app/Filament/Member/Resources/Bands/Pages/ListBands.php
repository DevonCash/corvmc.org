<?php

namespace App\Filament\Member\Resources\Bands\Pages;

use CorvMC\Membership\Actions\Bands\CreateBand;
use App\Filament\Member\Resources\Bands\BandResource;
use App\Filament\Member\Resources\Bands\Widgets\PendingBandInvitationsWidget;
use CorvMC\Bands\Models\Band;
use App\Models\User;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBands extends ListRecords
{
    protected static string $resource = BandResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            PendingBandInvitationsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateBand::filamentAction(),
        ];
    }


    protected function getPendingInvitationsCount(): int
    {
        return Band::whereHas('members', function ($query) {
            $query->where('user_id', User::me()->id)
                ->where('status', 'invited');
        })->count();
    }
}
