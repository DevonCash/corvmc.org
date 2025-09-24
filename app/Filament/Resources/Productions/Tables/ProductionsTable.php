<?php

namespace App\Filament\Resources\Productions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->state(fn ($record) => $record->start_time)
                    ->date(),
                TextColumn::make('start_time')
                    ->description(fn ($record) => 'Doors '.$record->start_time->format('g:i A'))
                    ->dateTime('g:i A')
                    ->suffix(fn ($record) => ' - '.$record->end_time?->format('g:i A'))
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                IconColumn::make('published_at')
                    ->default(false)
                    ->label('Published')
                    ->alignCenter()
                    ->icon(function ($state) {
                        if (! $state) {
                            return 'tabler-clock-edit';
                        }
                        if ($state->isFuture()) {
                            return 'tabler-clock';
                        }

                        return 'tabler-circle-check';
                    })
                    ->color(function ($state) {
                        if (! $state) {
                            return 'gray';
                        }
                        if ($state->isFuture()) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->tooltip(fn ($state) => $state ? 'Published on '.$state->format('M j, Y H:i A') : 'Not published'),
                TextColumn::make('manager.name')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
