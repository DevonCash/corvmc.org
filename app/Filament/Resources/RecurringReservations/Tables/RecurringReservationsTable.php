<?php

namespace App\Filament\Resources\RecurringReservations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecurringReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('recurrence_rule')
                    ->label('Pattern')
                    ->formatStateUsing(fn ($state) => app(\App\Services\RecurringReservationService::class)->formatRuleForHumans($state))
                    ->wrap(),

                TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(fn ($record) => $record->start_time->format('g:i A') . ' - ' . $record->end_time->format('g:i A')),

                TextColumn::make('series_start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('series_end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->placeholder('Ongoing'),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => 'cancelled',
                        'gray' => 'completed',
                    ]),

                TextColumn::make('instances_count')
                    ->label('Instances')
                    ->counts('instances')
                    ->suffix(' total'),

                TextColumn::make('active_instances_count')
                    ->label('Active')
                    ->counts('activeInstances')
                    ->suffix(' active'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
            ])
            ->recordActions([
                Action::make('view_instances')
                    ->label('View Instances')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.member.resources.reservations.index', [
                        'tableFilters[recurring_reservation_id][value]' => $record->id
                    ])),

                EditAction::make(),

                Action::make('cancel')
                    ->label('Cancel Series')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'active')
                    ->action(fn ($record) => app(\App\Services\RecurringReservationService::class)->cancelSeries($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('series_start_date', 'desc');
    }
}
