<?php

namespace App\Filament\Staff\Resources\Volunteering\Positions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HourLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'hourLogs';

    protected static ?string $title = 'Recent Hour Logs';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user')->latest())
            ->columns([
                TextColumn::make('user.name')
                    ->label('Volunteer')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—'),

                TextColumn::make('ended_at')
                    ->label('Ended')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—'),

                TextColumn::make('minutes')
                    ->label('Minutes')
                    ->placeholder('—'),
            ]);
    }
}
