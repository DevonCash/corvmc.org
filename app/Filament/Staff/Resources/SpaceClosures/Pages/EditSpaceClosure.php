<?php

namespace App\Filament\Staff\Resources\SpaceClosures\Pages;

use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use App\Filament\Staff\Resources\SpaceClosures\Widgets\AffectedReservationsWidget;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSpaceClosure extends EditRecord
{
    protected static string $resource = SpaceClosureResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            SpaceManagementResource::getUrl('index') => 'Space Management',
            SpaceClosureResource::getUrl('index') => 'Closures',
            $this->getRecordTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to delete this space closure? This will allow reservations to be made during this time period.'),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            AffectedReservationsWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
