<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use App\Models\MemberProfile;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

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

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Members')
                ->icon('heroicon-m-users')
                ->badge(MemberProfile::count()),

            'open_to_collaboration' => Tab::make('Open to Collaboration')
                ->icon('heroicon-m-hand-raised')
                ->modifyQueryUsing(fn (Builder $query) => $query->withFlag('open_to_collaboration'))
                ->badge(MemberProfile::withFlag('open_to_collaboration')->count()),

            'available_for_hire' => Tab::make('Available for Hire')
                ->icon('heroicon-m-currency-dollar')
                ->modifyQueryUsing(fn (Builder $query) => $query->withFlag('available_for_hire'))
                ->badge(MemberProfile::withFlag('available_for_hire')->count()),

            'looking_for_band' => Tab::make('Looking for Band')
                ->icon('heroicon-m-musical-note')
                ->modifyQueryUsing(fn (Builder $query) => $query->withFlag('looking_for_band'))
                ->badge(MemberProfile::withFlag('looking_for_band')->count()),

            'music_teacher' => Tab::make('Music Teachers')
                ->icon('heroicon-m-academic-cap')
                ->modifyQueryUsing(fn (Builder $query) => $query->withFlag('music_teacher'))
                ->badge(MemberProfile::withFlag('music_teacher')->count()),
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
