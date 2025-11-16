<?php

namespace App\Filament\Resources\Equipment\EquipmentLoans\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquipmentLoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Loan #')
                    ->sortable(),

                TextColumn::make('equipment.name')
                    ->label('Equipment')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('borrower.name')
                    ->label('Borrower')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('state')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'requested' => 'Requested',
                        'staff_preparing' => 'Preparing',
                        'ready_for_pickup' => 'Ready',
                        'checked_out' => 'Checked Out',
                        'overdue' => 'Overdue',
                        'dropoff_scheduled' => 'Return Scheduled',
                        'staff_processing_return' => 'Processing Return',
                        'damage_reported' => 'Damage Reported',
                        'returned' => 'Returned',
                        'cancelled' => 'Cancelled',
                        default => ucfirst($state)
                    })
                    ->colors([
                        'info' => 'requested',
                        'warning' => ['staff_preparing', 'ready_for_pickup'],
                        'primary' => 'checked_out',
                        'danger' => ['overdue', 'damage_reported'],
                        'secondary' => ['dropoff_scheduled', 'staff_processing_return'],
                        'success' => 'returned',
                        'gray' => 'cancelled',
                    ]),

                TextColumn::make('checked_out_at')
                    ->label('Checked Out')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('due_at')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_at && $record->due_at->isPast() && ! $record->returned_at ? 'danger' : null),

                TextColumn::make('returned_at')
                    ->label('Returned')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('days_out')
                    ->label('Days Out')
                    ->badge()
                    ->color(fn ($state) => $state > 14 ? 'danger' : ($state > 7 ? 'warning' : 'success'))
                    ->sortable()
                    ->placeholder('—'),

                BadgeColumn::make('condition_out')
                    ->label('Condition Out')
                    ->colors([
                        'success' => 'excellent',
                        'primary' => 'good',
                        'warning' => 'fair',
                        'danger' => 'poor',
                    ])
                    ->placeholder('—'),

                BadgeColumn::make('condition_in')
                    ->label('Condition In')
                    ->colors([
                        'success' => 'excellent',
                        'primary' => 'good',
                        'warning' => 'fair',
                        'danger' => ['poor', 'damaged'],
                    ])
                    ->placeholder('—'),

                TextColumn::make('security_deposit')
                    ->label('Deposit')
                    ->prefix('$')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable()
                    ->placeholder('$0.00'),

                TextColumn::make('rental_fee')
                    ->label('Fee')
                    ->prefix('$')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable()
                    ->placeholder('$0.00'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        'requested' => 'Requested',
                        'staff_preparing' => 'Staff Preparing',
                        'ready_for_pickup' => 'Ready for Pickup',
                        'checked_out' => 'Checked Out',
                        'overdue' => 'Overdue',
                        'dropoff_scheduled' => 'Return Scheduled',
                        'staff_processing_return' => 'Processing Return',
                        'damage_reported' => 'Damage Reported',
                        'returned' => 'Returned',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('equipment_id')
                    ->label('Equipment')
                    ->relationship('equipment', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('borrower_id')
                    ->label('Borrower')
                    ->relationship('borrower', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('condition_out')
                    ->label('Condition Out')
                    ->options([
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('advance_state')
                    ->label('Advance Status')
                    ->icon('tabler-arrow-right')
                    ->color('primary')
                    ->visible(fn ($record) => in_array($record->state, [
                        'requested', 'staff_preparing', 'ready_for_pickup',
                        'dropoff_scheduled', 'staff_processing_return',
                    ]))
                    ->action(function ($record) {
                        $nextStates = [
                            'requested' => 'staff_preparing',
                            'staff_preparing' => 'ready_for_pickup',
                            'ready_for_pickup' => 'checked_out',
                            'dropoff_scheduled' => 'staff_processing_return',
                            'staff_processing_return' => 'returned',
                        ];

                        if (isset($nextStates[$record->state])) {
                            $record->update(['state' => $nextStates[$record->state]]);
                        }
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn ($record) => route('filament.member.resources.equipment.equipment-loans.edit', $record));
    }
}
