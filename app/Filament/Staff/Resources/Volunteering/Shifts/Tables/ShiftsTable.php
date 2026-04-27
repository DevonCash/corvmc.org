<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Tables;

use CorvMC\Volunteering\Models\Position;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['position', 'event']))
            ->columns([
                TextColumn::make('position.title')
                    ->label('Position')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('event.title')
                    ->label('Event')
                    ->placeholder('—')
                    ->searchable()
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

                SpatieTagsColumn::make('tags')
                    ->limitList(3)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('position_id')
                    ->label('Position')
                    ->relationship('position', 'title'),

                SelectFilter::make('has_event')
                    ->label('Event Status')
                    ->options([
                        'with_event' => 'Linked to event',
                        'standalone' => 'Standalone',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'with_event' => $query->whereNotNull('event_id'),
                            'standalone' => $query->whereNull('event_id'),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('start_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
