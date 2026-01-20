<?php

namespace App\Filament\Resources\Equipment\EquipmentDamageReports\Schemas;

use CorvMC\Equipment\Models\EquipmentLoan;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EquipmentDamageReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('equipment_id')
                                    ->label('Equipment')
                                    ->relationship('equipment', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($state, $set) => $set('equipment_loan_id', null)),

                                Select::make('equipment_loan_id')
                                    ->label('Related Loan')
                                    ->relationship('loan', 'id')
                                    ->options(function (Get $get) {
                                        $equipmentId = $get('equipment_id');
                                        if (! $equipmentId) {
                                            return [];
                                        }

                                        return EquipmentLoan::where('equipment_id', $equipmentId)
                                            ->with('borrower')
                                            ->get()
                                            ->mapWithKeys(fn (EquipmentLoan $loan) => [
                                                $loan->id => "#{$loan->id} - {$loan->borrower->name} ({$loan->checked_out_at->format('M j, Y')})",
                                            ]);
                                    })
                                    ->searchable()
                                    ->helperText('Optional - link to the loan that discovered this damage'),
                            ]),

                        TextInput::make('title')
                            ->required()
                            ->placeholder('Brief description of the damage')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->required()
                            ->placeholder('Detailed description of the damage, how it was discovered, etc.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Classification')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('severity')
                                    ->options([
                                        'low' => 'Low - Cosmetic damage',
                                        'medium' => 'Medium - Functional impact',
                                        'high' => 'High - Significant damage',
                                        'critical' => 'Critical - Equipment unusable',
                                    ])
                                    ->default('medium')
                                    ->required(),

                                Select::make('priority')
                                    ->options([
                                        'low' => 'Low',
                                        'normal' => 'Normal',
                                        'high' => 'High',
                                        'urgent' => 'Urgent',
                                    ])
                                    ->default('normal')
                                    ->required(),

                                Select::make('status')
                                    ->options([
                                        'reported' => 'Reported',
                                        'in_progress' => 'In Progress',
                                        'waiting_parts' => 'Waiting for Parts',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('reported')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Assignment & Timeline')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('reported_by_id')
                                    ->label('Reported By')
                                    ->relationship('reportedBy', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id())
                                    ->required(),

                                Select::make('assigned_to_id')
                                    ->label('Assigned To')
                                    ->relationship('assignedTo', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select staff member to assign'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('discovered_at')
                                    ->label('Discovered At')
                                    ->default(now())
                                    ->required(),

                                DateTimePicker::make('started_at')
                                    ->label('Work Started'),

                                DateTimePicker::make('completed_at')
                                    ->label('Completed'),
                            ]),
                    ]),

                Section::make('Cost & Repair Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('estimated_cost')
                                    ->label('Estimated Cost')
                                    ->prefix('$')
                                    ->numeric()
                                    ->step(0.01)
                                    ->placeholder('0.00'),

                                TextInput::make('actual_cost')
                                    ->label('Actual Cost')
                                    ->prefix('$')
                                    ->numeric()
                                    ->step(0.01)
                                    ->placeholder('0.00'),
                            ]),

                        Textarea::make('repair_notes')
                            ->label('Repair Notes')
                            ->placeholder('Notes about repair work, parts used, etc.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($record) => ! $record?->repair_notes),
            ]);
    }
}
