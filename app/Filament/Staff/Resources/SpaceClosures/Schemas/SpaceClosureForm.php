<?php

namespace App\Filament\Staff\Resources\SpaceClosures\Schemas;

use CorvMC\SpaceManagement\Enums\ClosureType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class SpaceClosureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                static::typeField(),
                static::dateTimeGrid(),
                static::notesField(),
            ]);
    }

    protected static function typeField(): Select
    {
        return Select::make('type')
            ->label('Closure Type')
            ->options(ClosureType::class)
            ->required()
            ->default(ClosureType::Other)
            ->columnSpanFull();
    }

    protected static function dateTimeGrid(): Grid
    {
        return Grid::make(2)
            ->columnSpanFull()
            ->schema([
                DateTimePicker::make('starts_at')
                    ->label('Starts At')
                    ->required()
                    ->native(true)
                    ->seconds(false),

                DateTimePicker::make('ends_at')
                    ->label('Ends At')
                    ->required()
                    ->native(true)
                    ->seconds(false)
                    ->after('starts_at'),
            ]);
    }

    protected static function notesField(): Textarea
    {
        return Textarea::make('notes')
            ->label('Notes')
            ->rows(3)
            ->placeholder('Additional details about the closure...')
            ->columnSpanFull();
    }
}
