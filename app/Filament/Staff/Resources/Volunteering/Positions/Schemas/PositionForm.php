<?php

namespace App\Filament\Staff\Resources\Volunteering\Positions\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                RichEditor::make('description')
                    ->columnSpanFull(),

                SpatieTagsInput::make('tags')
                    ->label('Tags')
                    ->columnSpanFull(),
            ]);
    }
}
