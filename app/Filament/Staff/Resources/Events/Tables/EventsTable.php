<?php

namespace App\Filament\Staff\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventsTable
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
                    ->label('Event')
                    ->description(fn ($record) => $record->organizer?->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query->where('title', 'ilike', "%{$search}%")
                                ->orWhereHas('organizer', fn (Builder $q) => $q->where('name', 'ilike', "%{$search}%"));
                        });
                    }),
                TextColumn::make('venue.name')
                    ->label('Venue')
                    ->searchable(),
                TextColumn::make('start_datetime')
                    ->sortable()
                    ->label('Start Time')
                    ->dateTime('M d, Y h:i A')
                    ->description(fn ($record) => $record->start_datetime->diffForHumans(parts: 1)),
                TextColumn::make('tickets_sold')
                    ->label('Tickets')
                    ->alignCenter()
                    ->formatStateUsing(function ($state, $record) {
                        if (! $record->ticketing_enabled) {
                            return '—';
                        }

                        if ($record->ticket_quantity === null) {
                            return (string) ($state ?? 0);
                        }

                        return ($state ?? 0).'/'.$record->ticket_quantity;
                    })
                    ->color(fn ($record) => $record->isSoldOut() ? 'danger' : null),
                IconColumn::make('publication_status')
                    ->label('Published')
                    ->alignCenter()
                    ->tooltip(fn (IconColumn $column, $record): ?string => match ($column->getState()) {
                        'scheduled' => 'This event is scheduled to be published at '.$column->getRecord()->published_at->format('M d, Y H:i A').'.',
                        default => $record->publication_status->getLabel()
                    }),
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
