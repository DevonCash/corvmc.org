<?php

namespace App\Filament\Staff\Resources\Volunteering\Positions\RelationManagers;

use CorvMC\Volunteering\Models\HourLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'shifts';

    protected static ?string $title = 'Upcoming Shifts';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('start_at', '>', now())->orderBy('start_at'))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Event')
                    ->placeholder('No event')
                    ->toggleable(),

                TextColumn::make('start_at')
                    ->label('Start')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                TextColumn::make('end_at')
                    ->label('End')
                    ->dateTime('g:i A')
                    ->sortable(),

                TextColumn::make('capacity')
                    ->label('Filled')
                    ->formatStateUsing(function ($record) {
                        $active = $record->hourLogs()->active()->count();

                        return "{$active}/{$record->capacity}";
                    }),
            ]);
    }
}
