<?php

namespace App\Filament\Staff\Resources\Events\RelationManagers;

use CorvMC\Volunteering\Models\Position;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VolunteerShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'volunteerShifts';

    protected static ?string $title = 'Volunteer Shifts';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with('position')
                ->withCount(['hourLogs as active_volunteers_count' => fn ($q) => $q->active()])
                ->orderBy('start_at'))
            ->columns([
                TextColumn::make('position.title')
                    ->label('Position')
                    ->sortable(),

                TextColumn::make('start_at')
                    ->label('Start')
                    ->dateTime('g:i A')
                    ->sortable(),

                TextColumn::make('end_at')
                    ->label('End')
                    ->dateTime('g:i A'),

                TextColumn::make('capacity')
                    ->label('Filled')
                    ->formatStateUsing(fn ($record) => "{$record->active_volunteers_count}/{$record->capacity}"),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Shift')
                    ->schema([
                        Select::make('position_id')
                            ->label('Position')
                            ->options(Position::pluck('title', 'id'))
                            ->searchable()
                            ->required(),

                        DateTimePicker::make('start_at')
                            ->label('Start')
                            ->required()
                            ->native(true)
                            ->seconds(false),

                        DateTimePicker::make('end_at')
                            ->label('End')
                            ->required()
                            ->native(true)
                            ->seconds(false)
                            ->after('start_at'),

                        TextInput::make('capacity')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                    ]),
            ]);
    }
}
