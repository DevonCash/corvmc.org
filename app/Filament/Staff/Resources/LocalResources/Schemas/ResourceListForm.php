<?php

namespace App\Filament\Staff\Resources\LocalResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
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

                        TextInput::make('address')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Note')
                            ->helperText('Short context shown on the public page (e.g., \'Third Thursday at Common Fields\')')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Publishing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers display first'),

                                ToggleButtons::make('publish_status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'publish' => 'Publish now',
                                        'schedule' => 'Schedule',
                                    ])
                                    ->icons([
                                        'draft' => 'tabler-pencil',
                                        'publish' => 'tabler-check',
                                        'schedule' => 'tabler-clock',
                                    ])
                                    ->colors([
                                        'draft' => 'gray',
                                        'publish' => 'success',
                                        'schedule' => 'warning',
                                    ])
                                    ->default('draft')
                                    ->inline()
                                    ->afterStateHydrated(function (ToggleButtons $component, $record) {
                                        if (! $record) {
                                            return;
                                        }

                                        if (! $record->published_at) {
                                            $component->state('draft');
                                        } elseif ($record->published_at->isPast()) {
                                            $component->state('publish');
                                        } else {
                                            $component->state('schedule');
                                        }
                                    })
                                    ->live(),
                            ]),

                        DateTimePicker::make('published_at')
                            ->label('Publish Date')
                            ->visible(fn (Get $get) => $get('publish_status') === 'schedule')
                            ->required(fn (Get $get) => $get('publish_status') === 'schedule'),
                    ]),
            ]);
    }

    /**
     * Translate the virtual publish_status field into the actual published_at value.
     *
     * Call this from mutateFormDataBeforeCreate / mutateFormDataBeforeSave
     * on the CreateAction and EditAction.
     */
    public static function mutatePublishStatus(array $data): array
    {
        $status = $data['publish_status'] ?? 'draft';

        $data['published_at'] = match ($status) {
            'publish' => now(),
            'schedule' => $data['published_at'] ?? now(),
            default => null,
        };

        unset($data['publish_status']);

        return $data;
    }
}
