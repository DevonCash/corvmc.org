<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Invitations\InviteUser;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InviteUser::filamentAction(),
        ];
    }
}
