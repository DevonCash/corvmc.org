<?php

namespace App\Filament\Staff\Resources\Venues\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                static::nameField(),
                static::isCmcField(),
                static::addressField(),
                static::cityField(),
                static::stateField(),
                static::zipField(),
                static::coordinatesGrid(),
                static::distanceField(),
            ]);
    }

    protected static function nameField(): TextInput
    {
        return TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->columnSpanFull();
    }

    protected static function isCmcField(): Checkbox
    {
        return Checkbox::make('is_cmc')
            ->label('Is CMC Venue')
            ->helperText('Check if this is the Corvallis Music Collective practice space')
            ->columnSpanFull();
    }

    protected static function addressField(): TextInput
    {
        return TextInput::make('address')
            ->maxLength(255)
            ->columnSpanFull();
    }

    protected static function cityField(): TextInput
    {
        return TextInput::make('city')
            ->default('Corvallis')
            ->maxLength(100);
    }

    protected static function stateField(): TextInput
    {
        return TextInput::make('state')
            ->default('OR')
            ->maxLength(2)
            ->placeholder('OR');
    }

    protected static function zipField(): TextInput
    {
        return TextInput::make('zip')
            ->maxLength(10)
            ->columnSpanFull();
    }

    protected static function coordinatesGrid(): Grid
    {
        return Grid::make(3)
            ->columnSpanFull()
            ->schema([
                TextInput::make('latitude')
                    ->numeric()
                    ->step(0.000001)
                    ->helperText('Automatically calculated via Google Maps API'),
                TextInput::make('longitude')
                    ->numeric()
                    ->step(0.000001)
                    ->helperText('Automatically calculated via Google Maps API'),
                static::calculateDistanceAction(),
            ]);
    }

    protected static function calculateDistanceAction(): Placeholder
    {
        return Placeholder::make('calculate_distance')
            ->label('Distance')
            ->content(function ($record) {
                if (! $record) {
                    return 'Save venue to calculate distance';
                }

                if ($record->distance_from_corvallis !== null) {
                    return number_format($record->distance_from_corvallis, 1).' miles from Corvallis';
                }

                return 'Not calculated';
            })
            ->suffixAction(
                Action::make('calculateDistance')
                    ->label('Calculate')
                    ->icon('tabler-map-pin')
                    ->visible(fn ($record) => $record && $record->latitude && $record->longitude)
                    ->action(function ($record) {
                        $record->calculateDistance();
                        $record->save();
                    })
            );
    }

    protected static function distanceField(): Placeholder
    {
        return Placeholder::make('driving_time')
            ->label('Driving Time from Corvallis')
            ->content(function ($record) {
                if (! $record || ! $record->driving_time_display) {
                    return 'N/A';
                }

                return $record->driving_time_display;
            })
            ->columnSpanFull();
    }
}
