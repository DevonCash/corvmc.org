<?php

namespace App\Filament\Resources\Venues\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_cmc')
                    ->label('CMC')
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formatted_address')
                    ->label('Address')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distance_from_corvallis')
                    ->label('Distance')
                    ->formatStateUsing(fn (?float $state) => $state ? number_format($state, 1) . ' mi' : 'N/A')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('events_count')
                    ->label('Events')
                    ->counts('events')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('cmc')
                    ->label('CMC Only')
                    ->query(fn (Builder $query) => $query->cmc()),
                Filter::make('external')
                    ->label('External Only')
                    ->query(fn (Builder $query) => $query->external()),
            ])
            ->defaultSort('name')
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
