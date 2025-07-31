<?php

namespace App\Filament\Resources\BandProfiles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BandProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('hometown'),
                TextInput::make('owner_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('bio')
                    ->columnSpanFull(),
                Textarea::make('links')
                    ->columnSpanFull(),
                Textarea::make('contact')
                    ->columnSpanFull(),
                TextInput::make('visibility')
                    ->required()
                    ->default('private'),
            ]);
    }
}
