<?php

namespace App\Filament\Member\Resources\Equipment\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Equipment Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->weight('bold')
                                    ->size('lg'),
                                TextEntry::make('type')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))
                                    ),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('brand')
                                    ->placeholder('Not specified'),
                                TextEntry::make('model')
                                    ->placeholder('Not specified'),
                                TextEntry::make('serial_number')
                                    ->placeholder('Not specified'),
                            ]),

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('condition')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))
                                    )
                                    ->color(fn (string $state): string => match ($state) {
                                        'excellent' => 'success',
                                        'good' => 'primary',
                                        'fair' => 'warning',
                                        'poor', 'needs_repair' => 'danger',
                                    }),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))
                                    )
                                    ->color(fn (string $state): string => match ($state) {
                                        'available' => 'success',
                                        'checked_out' => 'warning',
                                        'maintenance' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('location')
                                    ->placeholder('Location not specified'),
                            ]),

                        TextEntry::make('estimated_value')
                            ->money('USD')
                            ->placeholder('Not appraised'),

                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No additional notes'),
                    ]),

                Section::make('Kit Components')
                    ->schema([
                        RepeatableEntry::make('children')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->weight('bold'),
                                        TextEntry::make('type')
                                            ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))
                                            ),
                                        TextEntry::make('condition')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))
                                            )
                                            ->color(fn (string $state): string => match ($state) {
                                                'excellent' => 'success',
                                                'good' => 'primary',
                                                'fair' => 'warning',
                                                'poor', 'needs_repair' => 'danger',
                                            }),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))
                                            )
                                            ->color(fn (string $state): string => match ($state) {
                                                'available' => 'success',
                                                'checked_out' => 'warning',
                                                'maintenance' => 'danger',
                                                default => 'gray',
                                            }),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('brand_model')
                                            ->label('Brand/Model')
                                            ->getStateUsing(fn ($record) => collect([$record->brand, $record->model])->filter()->join(' ')
                                            )
                                            ->placeholder('Not specified'),
                                        TextEntry::make('can_lend_separately')
                                            ->label('Available for separate lending')
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                    ]),

                                TextEntry::make('description')
                                    ->columnSpanFull()
                                    ->placeholder('No description')
                                    ->visible(fn ($record) => ! empty($record->description)),
                            ])
                            ->contained(false),
                    ])
                    ->visible(fn ($record) => $record->is_kit && $record->children->isNotEmpty()),

                Section::make('Acquisition Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('acquisition_type')
                                    ->label('Acquisition Type')
                                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))
                                    )
                                    ->badge(),
                                TextEntry::make('acquisition_date')
                                    ->date(),
                            ]),

                        TextEntry::make('provider_display')
                            ->label('Provider')
                            ->visible(fn ($record) => $record->acquisition_type !== 'purchased'),

                        TextEntry::make('return_due_date')
                            ->label('Return Due Date')
                            ->date()
                            ->visible(fn ($record) => $record->isOnLoanToCmc()),

                        TextEntry::make('acquisition_notes')
                            ->columnSpanFull()
                            ->placeholder('No acquisition notes'),
                    ])
                    ->collapsible(),
            ]);
    }
}
