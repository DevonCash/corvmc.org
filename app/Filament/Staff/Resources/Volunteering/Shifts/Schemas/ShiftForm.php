<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Schemas;

use CorvMC\Events\Models\Event;
use CorvMC\Volunteering\Models\Position;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('position_id')
                    ->label('Position')
                    ->relationship('position', 'title')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),

                Select::make('event_id')
                    ->label('Event')
                    ->options(fn () => Event::query()
                        ->where('start_datetime', '>', now()->subMonth())
                        ->orderBy('start_datetime', 'desc')
                        ->pluck('title', 'id'))
                    ->searchable()
                    ->placeholder('No event (standalone shift)')
                    ->columnSpanFull(),

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

                SpatieTagsInput::make('tags')
                    ->label('Tags')
                    ->columnSpanFull(),
            ]);
    }
}
