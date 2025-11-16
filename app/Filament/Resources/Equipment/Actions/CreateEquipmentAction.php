<?php

namespace App\Filament\Resources\Equipment\Actions;

use App\Models\Equipment;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;

class CreateEquipmentAction
{
    public static function make(): CreateAction
    {
        return CreateAction::make()
            ->label('Add Equipment')
            ->icon('tabler-circle-plus')
            ->color('primary')
            ->modalWidth('2xl')
            ->modalHeading('Add New Equipment')
            ->modalDescription('Add equipment to the lending library')
            ->model(Equipment::class)
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Group::make([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Fender Stratocaster, Pearl Drum Kit'),
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
                                ->maxLength(255)
                                ->placeholder('e.g., Fender, Gibson, Pearl'),
                            TextInput::make('model')
                                ->maxLength(255)
                                ->placeholder('e.g., Stratocaster, Les Paul'),
                        ])->columns(2),

                        Group::make([
                            TextInput::make('serial_number')
                                ->maxLength(255)
                                ->placeholder('Serial number if available'),
                            TextInput::make('location')
                                ->maxLength(255)
                                ->placeholder('e.g., Storage Room A, Shelf 2'),
                        ])->columns(2),

                        Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Detailed description of the equipment')
                            ->columnSpanFull(),
                    ]),

                Section::make('Kit Configuration')
                    ->schema([
                        Toggle::make('is_kit')
                            ->label('This is a kit/set')
                            ->helperText('Check if this represents multiple pieces (e.g., drum kit)')
                            ->reactive(),

                        Select::make('parent_equipment_id')
                            ->label('Parent Kit')
                            ->relationship('parent', 'name', fn ($query) => $query->where('is_kit', true))
                            ->searchable()
                            ->preload()
                            ->placeholder('Select parent kit if this is a component')
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

                Section::make('Condition & Valuation')
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
                                    'maintenance' => 'Under Maintenance',
                                    'retired' => 'Retired',
                                ])
                                ->default('available'),
                        ])->columns(2),

                        TextInput::make('estimated_value')
                            ->label('Estimated Value')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->placeholder('0.00'),

                        Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Any additional notes or special instructions')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Acquisition Information')
                    ->schema([
                        Group::make([
                            Select::make('acquisition_type')
                                ->required()
                                ->options([
                                    'donated' => 'Donated',
                                    'loaned_to_us' => 'Loaned to CMC',
                                    'purchased' => 'Purchased',
                                ])
                                ->default('donated')
                                ->reactive(),
                            DatePicker::make('acquisition_date')
                                ->required()
                                ->default(now()),
                        ])->columns(2),

                        Select::make('provider_id')
                            ->label('Provider (if member)')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select if donated/loaned by a member')
                            ->visible(fn (callable $get) => in_array($get('acquisition_type'), ['donated', 'loaned_to_us'])),

                        DatePicker::make('return_due_date')
                            ->label('Return Due Date')
                            ->minDate(now())
                            ->visible(fn (callable $get) => $get('acquisition_type') === 'loaned_to_us'),

                        Textarea::make('acquisition_notes')
                            ->label('Acquisition Notes')
                            ->rows(3)
                            ->placeholder('Details about how this equipment was acquired')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ])
            ->mutateFormDataUsing(function (array $data): array {
                // Set ownership status based on acquisition type
                $data['ownership_status'] = match ($data['acquisition_type']) {
                    'loaned_to_us' => 'on_loan_to_cmc',
                    default => 'cmc_owned',
                };

                return $data;
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Equipment Added')
                    ->body('Equipment has been successfully added to the library.')
            );
    }
}
