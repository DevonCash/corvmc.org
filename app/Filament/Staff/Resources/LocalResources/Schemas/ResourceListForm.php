<?php

namespace App\Filament\Staff\Resources\LocalResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ResourceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('The name of this resource category (e.g., "Music Shops")'),

                                TextInput::make('slug')
                                    ->maxLength(255)
                                    ->helperText('URL-friendly identifier (auto-generated from name if left blank)')
                                    ->disabled(fn ($operation) => $operation === 'create'),
                            ]),

                        MarkdownEditor::make('description')
                            ->label('Description')
                            ->helperText('Brief description of this resource category')
                            ->columnSpanFull(),
                    ]),

                Section::make('Display Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('display_order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers display first'),

                                DateTimePicker::make('published_at')
                                    ->label('Publish Date')
                                    ->helperText('Leave empty to save as draft. Set to a future date to schedule publishing.'),
                            ]),
                    ]),
            ]);
    }
}
