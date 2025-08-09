<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use App\Models\MemberProfile;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMemberProfiles extends ListRecords
{
    protected static string $resource = MemberProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Profile'),
        ];
    }


    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No member profiles found';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Create your first member profile to get started building the music community directory.';
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-users';
    }
}
