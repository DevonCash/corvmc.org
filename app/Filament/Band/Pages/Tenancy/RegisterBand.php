<?php

namespace App\Filament\Band\Pages\Tenancy;

use CorvMC\Membership\Actions\Bands\CreateBand;
use CorvMC\Bands\Models\Band;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterBand extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Band';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Band Name')
                ->required()
                ->maxLength(255)
                ->unique(Band::class, 'name'),
            TextInput::make('hometown')
                ->label('Location')
                ->placeholder('City, State/Country')
                ->maxLength(255),
            RichEditor::make('bio')
                ->label('Biography')
                ->toolbarButtons(['bold', 'italic', 'link'])
                ->maxLength(5000),
        ]);
    }

    protected function handleRegistration(array $data): Band
    {
        return CreateBand::run($data);
    }
}
