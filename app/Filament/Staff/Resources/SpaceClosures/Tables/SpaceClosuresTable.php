<?php

namespace App\Filament\Staff\Resources\SpaceClosures\Tables;

use CorvMC\SpaceManagement\Enums\ClosureType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SpaceClosuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('notes')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ClosureType::class),
                TernaryFilter::make('upcoming')
                    ->label('Time Period')
                    ->placeholder('All')
                    ->trueLabel('Upcoming')
                    ->falseLabel('Past')
                    ->queries(
                        true: fn (Builder $query) => $query->where('ends_at', '>', now()),
                        false: fn (Builder $query) => $query->where('ends_at', '<=', now()),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->defaultSort('starts_at', 'desc')
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
