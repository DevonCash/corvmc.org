<?php

namespace App\Filament\Staff\Resources\EquipmentDamageReports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EquipmentDamageReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('equipment.name')
                    ->label('Equipment')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('severity')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => ['high', 'critical'],
                    ]),

                BadgeColumn::make('priority')
                    ->colors([
                        'gray' => 'low',
                        'info' => 'normal',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),

                BadgeColumn::make('status')
                    ->colors([
                        'info' => 'reported',
                        'warning' => ['in_progress', 'waiting_parts'],
                        'success' => 'completed',
                        'gray' => 'cancelled',
                    ]),

                TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned')
                    ->sortable(),

                TextColumn::make('reportedBy.name')
                    ->label('Reported By')
                    ->sortable(),

                TextColumn::make('estimated_cost')
                    ->label('Est. Cost')
                    ->prefix('$')
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('actual_cost')
                    ->label('Actual Cost')
                    ->prefix('$')
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('discovered_at')
                    ->label('Discovered')
                    ->date()
                    ->sortable(),

                TextColumn::make('days_open')
                    ->label('Days Open')
                    ->badge()
                    ->color(fn ($state) => $state > 30 ? 'danger' : ($state > 14 ? 'warning' : 'success'))
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderByRaw("
                            CASE 
                                WHEN completed_at IS NOT NULL THEN EXTRACT(EPOCH FROM (completed_at - discovered_at)) / 86400
                                ELSE EXTRACT(EPOCH FROM (NOW() - discovered_at)) / 86400
                            END {$direction}
                        ");
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'reported' => 'Reported',
                        'in_progress' => 'In Progress',
                        'waiting_parts' => 'Waiting for Parts',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->multiple(),

                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'normal' => 'Normal',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ])
                    ->multiple(),

                SelectFilter::make('assigned_to_id')
                    ->label('Assigned To')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn ($record) => route('filament.member.resources.equipment.equipment-damage-reports.edit', $record));
    }
}
