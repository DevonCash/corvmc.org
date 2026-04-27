<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\RelationManagers;

use App\Filament\Staff\Resources\Volunteering\Shifts\Actions\CheckInAction;
use App\Filament\Staff\Resources\Volunteering\Shifts\Actions\CheckOutAction;
use App\Filament\Staff\Resources\Volunteering\Shifts\Actions\ConfirmVolunteerAction;
use App\Filament\Staff\Resources\Volunteering\Shifts\Actions\ReleaseVolunteerAction;
use App\Filament\Staff\Resources\Volunteering\Shifts\Actions\WalkInAction;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HourLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'hourLogs';

    protected static ?string $title = 'Volunteers';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Volunteer')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('started_at')
                    ->label('Checked In')
                    ->dateTime('g:i A')
                    ->placeholder('—'),

                TextColumn::make('ended_at')
                    ->label('Checked Out')
                    ->dateTime('g:i A')
                    ->placeholder('—'),

                TextColumn::make('minutes')
                    ->label('Minutes')
                    ->placeholder('—'),

                SpatieTagsColumn::make('tags')
                    ->limitList(3)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                WalkInAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ConfirmVolunteerAction::make(),
                    CheckInAction::make(),
                    CheckOutAction::make(),
                    ReleaseVolunteerAction::make(),
                ]),
            ]);
    }
}
