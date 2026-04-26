<?php

namespace App\Filament\Staff\Resources\LocalResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ResourceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(5)
            ->components([
                Grid::make()
                    ->columnSpan(3)
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->hint('Line 1')
                            ->maxLength(255),
                        TextInput::make('website')
                            ->label('Link')
                            ->url()
                            ->placeholder('https://....')
                            ->maxLength(255),

                        TextInput::make('description')
                            ->hint('Line 2')
                            ->label('Note'),

                        TextInput::make('address')
                            ->hint('Line 3')
                            ->maxLength(255),
                    ]),
                Fieldset::make('Settings')
                    ->columnSpan(2)
                    ->columns(1)
                    ->schema([
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

                        ToggleButtons::make('publish_status')
                            ->label('Status')
                            ->grouped()
                            ->inline()
                            ->options([
                                'draft' => 'Draft',
                                'publish' => 'Published',
                            ])
                            ->icons([
                                'draft' => 'tabler-pencil',
                                'publish' => 'tabler-check',
                            ])
                            ->colors([
                                'draft' => 'gray',
                                'publish' => 'success',
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

                        DateTimePicker::make('published_at')
                            ->label('Publish Date')
                            ->visible(fn(Get $get) => $get('publish_status') === 'schedule')
                            ->required(fn(Get $get) => $get('publish_status') === 'schedule'),
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
