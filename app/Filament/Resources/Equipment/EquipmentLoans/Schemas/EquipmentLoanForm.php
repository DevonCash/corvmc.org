<?php

namespace App\Filament\Resources\Equipment\EquipmentLoans\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EquipmentLoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Loan Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('equipment_id')
                                    ->label('Equipment')
                                    ->relationship('equipment', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('borrower_id')
                                    ->label('Borrower')
                                    ->relationship('borrower', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('checked_out_at')
                                    ->label('Checked Out')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(1),

                                DateTimePicker::make('due_at')
                                    ->label('Due Date')
                                    ->default(now()->addDays(7))
                                    ->required()
                                    ->columnSpan(1),

                                DateTimePicker::make('returned_at')
                                    ->label('Returned')
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->columnSpan(2),

                Section::make('Status & Workflow')
                    ->schema([
                        Select::make('state')
                            ->label('Loan Status')
                            ->options([
                                'requested' => 'Requested - Member has requested loan',
                                'staff_preparing' => 'Staff Preparing - Checking condition',
                                'ready_for_pickup' => 'Ready for Pickup - Awaiting member',
                                'checked_out' => 'Checked Out - In member use',
                                'overdue' => 'Overdue - Past due date',
                                'dropoff_scheduled' => 'Dropoff Scheduled - Return planned',
                                'staff_processing_return' => 'Staff Processing - Inspecting return',
                                'damage_reported' => 'Damage Reported - Assessing damage',
                                'returned' => 'Returned - Complete',
                                'cancelled' => 'Cancelled - Request cancelled',
                            ])
                            ->default('requested')
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Select::make('status')
                            ->label('Legacy Status')
                            ->options([
                                'active' => 'Active',
                                'overdue' => 'Overdue',
                                'returned' => 'Returned',
                                'lost' => 'Lost',
                            ])
                            ->default('active')
                            ->required()
                            ->helperText('Legacy status field - will be deprecated')
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1),

                Section::make('Condition & Notes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('condition_out')
                                    ->label('Condition When Checked Out')
                                    ->options([
                                        'excellent' => 'Excellent',
                                        'good' => 'Good',
                                        'fair' => 'Fair',
                                        'poor' => 'Poor',
                                    ])
                                    ->default('good')
                                    ->required()
                                    ->columnSpan(1),

                                Select::make('condition_in')
                                    ->label('Condition When Returned')
                                    ->options([
                                        'excellent' => 'Excellent',
                                        'good' => 'Good',
                                        'fair' => 'Fair',
                                        'poor' => 'Poor',
                                        'damaged' => 'Damaged',
                                    ])
                                    ->columnSpan(1)
                                    ->visible(fn(Get $get) => in_array($get('state'), [
                                        'staff_processing_return',
                                        'damage_reported',
                                        'returned'
                                    ])),
                            ]),

                        Textarea::make('notes')
                            ->label('Loan Notes')
                            ->placeholder('Any special notes about this loan...')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('damage_notes')
                            ->label('Damage Notes')
                            ->placeholder('Details about any damage found...')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn(Get $get) => in_array($get('state'), [
                                'damage_reported',
                                'returned'
                            ]) || $get('condition_in') === 'damaged'),
                    ])
                    ->columnSpan(2),

                Section::make('Financial Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('security_deposit')
                                    ->label('Security Deposit')
                                    ->prefix('$')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(0.00)
                                    ->required()
                                    ->columnSpan(1),

                                TextInput::make('rental_fee')
                                    ->label('Rental Fee')
                                    ->prefix('$')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(0.00)
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->columnSpan(1)
                    ->collapsible()
                    ->collapsed(fn($record) => !$record || ($record->security_deposit == 0 && $record->rental_fee == 0)),
            ]);
    }
}
