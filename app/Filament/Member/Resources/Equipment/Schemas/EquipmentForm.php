<?php

namespace App\Filament\Member\Resources\Equipment\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Group::make([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('type')
                                ->required()
                                ->options([
                                    'guitar' => 'Guitar',
                                    'bass' => 'Bass',
                                    'drum_kit' => 'Drum Kit',
                                    'drums' => 'Drums',
                                    'cymbals' => 'Cymbals',
                                    'amplifier' => 'Amplifier',
                                    'microphone' => 'Microphone',
                                    'recording' => 'Recording Equipment',
                                    'pa_system' => 'PA System',
                                    'keyboard' => 'Keyboard',
                                    'hardware' => 'Hardware/Stands',
                                    'specialty' => 'Specialty',
                                ])
                                ->searchable(),
                        ])->columns(2),

                        Group::make([
                            TextInput::make('brand')
                                ->maxLength(255),
                            TextInput::make('model')
                                ->maxLength(255),
                        ])->columns(2),

                        Group::make([
                            TextInput::make('serial_number')
                                ->maxLength(255),
                            TextInput::make('location')
                                ->maxLength(255)
                                ->placeholder('e.g., Storage Room A, Shelf 2'),
                        ])->columns(2),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Kit Management')
                    ->schema([
                        Toggle::make('is_kit')
                            ->label('This is a kit/set')
                            ->helperText('Check if this equipment represents a collection of multiple pieces')
                            ->reactive(),

                        Select::make('parent_equipment_id')
                            ->label('Parent Kit')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select parent kit if this is a component')
                            ->helperText('Leave empty if this is a standalone item or kit')
                            ->hidden(fn (callable $get) => $get('is_kit')),

                        Group::make([
                            Toggle::make('can_lend_separately')
                                ->label('Can be lent separately')
                                ->helperText('Allow this component to be borrowed without the full kit')
                                ->default(true),
                            TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->numeric()
                                ->default(0)
                                ->helperText('Order within the kit (0 = first)'),
                        ])->columns(2)
                            ->hidden(fn (callable $get) => $get('is_kit')),
                    ])
                    ->collapsible(),

                Section::make('Condition & Status')
                    ->schema([
                        Group::make([
                            Select::make('condition')
                                ->required()
                                ->options([
                                    'excellent' => 'Excellent',
                                    'good' => 'Good',
                                    'fair' => 'Fair',
                                    'poor' => 'Poor',
                                    'needs_repair' => 'Needs Repair',
                                ])
                                ->default('good'),
                            Select::make('status')
                                ->required()
                                ->options([
                                    'available' => 'Available',
                                    'checked_out' => 'Checked Out',
                                    'maintenance' => 'Under Maintenance',
                                    'retired' => 'Retired',
                                ])
                                ->default('available'),
                        ])->columns(2),

                        TextInput::make('estimated_value')
                            ->label('Estimated Value')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
