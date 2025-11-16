<?php

namespace App\Filament\Resources\Equipment\Actions;

use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;

class EditEquipmentAction
{
    public static function make(): EditAction
    {
        return EditAction::make()
            ->label('Edit Equipment')
            ->icon('tabler-edit')
            ->color('primary')
            ->modalWidth('2xl')
            ->modalHeading(fn ($record) => "Edit {$record->name}")
            ->form([
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
                                ->maxLength(255),
                        ])->columns(2),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Current Status')
                    ->schema([
                        Placeholder::make('current_status')
                            ->label('Current Status')
                            ->content(fn ($record) => match ($record->status) {
                                'available' => '✅ Available for checkout',
                                'checked_out' => '⚠️ Currently checked out'.
                                    ($record->currentLoan ? " to {$record->currentLoan->borrower->name}" : ''),
                                'maintenance' => '🔧 Under maintenance',
                                'retired' => '📦 Retired from service',
                                default => $record->status,
                            }),

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
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (in_array($state, ['needs_repair'])) {
                                        $set('status', 'maintenance');
                                    }
                                }),
                            Select::make('status')
                                ->required()
                                ->options([
                                    'available' => 'Available',
                                    'maintenance' => 'Under Maintenance',
                                    'retired' => 'Retired',
                                ])
                                ->disabled(fn ($record) => $record->is_checked_out)
                                ->helperText(fn ($record) => $record->is_checked_out ? 'Cannot change status while checked out' : null
                                ),
                        ])->columns(2),

                        TextInput::make('estimated_value')
                            ->label('Estimated Value')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),

                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Kit Configuration')
                    ->schema([
                        Toggle::make('is_kit')
                            ->label('This is a kit/set')
                            ->disabled(fn ($record) => $record->children()->count() > 0)
                            ->helperText(fn ($record) => $record->children()->count() > 0
                                    ? 'Cannot change - this kit has components'
                                    : 'Check if this represents multiple pieces'
                            )
                            ->reactive(),

                        Select::make('parent_equipment_id')
                            ->label('Parent Kit')
                            ->relationship('parent', 'name', fn ($query) => $query->where('is_kit', true))
                            ->searchable()
                            ->preload()
                            ->placeholder('Select parent kit if this is a component')
                            ->disabled(fn ($record) => $record->is_checked_out)
                            ->hidden(fn (callable $get) => $get('is_kit')),

                        Group::make([
                            Toggle::make('can_lend_separately')
                                ->label('Can be lent separately')
                                ->disabled(fn ($record) => $record->is_checked_out),
                            TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->numeric()
                                ->default(0),
                        ])->columns(2)
                            ->hidden(fn (callable $get) => $get('is_kit')),
                    ])
                    ->visible(fn ($record) => $record->parent_equipment_id || $record->is_kit)
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
                                ->reactive(),
                            DatePicker::make('acquisition_date')
                                ->required(),
                        ])->columns(2),

                        Select::make('provider_id')
                            ->label('Provider (if member)')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => in_array($get('acquisition_type'), ['donated', 'loaned_to_us'])),

                        DatePicker::make('return_due_date')
                            ->label('Return Due Date')
                            ->minDate(now())
                            ->visible(fn (callable $get) => $get('acquisition_type') === 'loaned_to_us'),

                        Textarea::make('acquisition_notes')
                            ->label('Acquisition Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ])
            ->mutateFormDataUsing(function (array $data, $record): array {
                // Update ownership status if acquisition type changed
                if (isset($data['acquisition_type'])) {
                    $data['ownership_status'] = match ($data['acquisition_type']) {
                        'loaned_to_us' => 'on_loan_to_cmc',
                        default => 'cmc_owned',
                    };
                }

                return $data;
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Equipment Updated')
                    ->body('Equipment information has been successfully updated.')
            )
            ->visible(fn ($record) => ! $record->is_checked_out || Auth::user()->can('manage equipment'));
    }
}
