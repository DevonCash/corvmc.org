<?php

namespace App\Filament\Staff\Resources\LocalResources\Schemas;

use App\Models\ResourceList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                Section::make('Resource Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('resource_list_id')
                                    ->label('Category')
                                    ->relationship('resourceList', 'name')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->searchable()
                                    ->preload(),
                            ]),

                        TextInput::make('website')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Full URL including https://'),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('contact_name')
                                    ->maxLength(255),

                                TextInput::make('contact_email')
                                    ->email()
                                    ->maxLength(255),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('contact_phone')
                                    ->tel()
                                    ->maxLength(255),

                                TextInput::make('address')
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Display Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers display first'),

                                DateTimePicker::make('published_at')
                                    ->label('Publish Date')
                                    ->helperText('Leave empty to save as draft'),
                            ]),
                    ]),
            ]);
    }
}
