<?php

namespace App\Filament\Staff\Resources\Users\Pages;

use App\Actions\Invitations\InviteUser;
use App\Filament\Staff\Resources\Users\UserResource;
use App\Filament\Staff\Resources\Users\Widgets\UserStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            UserStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            InviteUser::filamentAction(),
        ];
    }
}
