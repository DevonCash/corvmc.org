<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'sm' => 1,
                'md' => 3,
                'lg' => 3,
            ])
            ->components([
                Group::make()
                    ->columnSpan(1)
                    ->schema([
                        static::publishedAtField(),
                        static::posterField(),
                    ])->columnOrder(['sm' => 1, 'md' => 3]),
                Grid::make([
                    'sm' => 1,
                    'md' => 1,
                    'lg' => 3,
                ])
                    ->columnSpan([
                        'sm' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        static::titleField(),
                        static::descriptionField(),
                        static::timeFieldsGrid(),
                        static::ticketingGrid(),
                        static::locationFieldset(),
                    ]),
            ]);
    }

    protected static function titleField(): TextInput
    {
        return TextInput::make('title')
            ->columnSpanFull()
            ->required();
    }

    protected static function descriptionField(): RichEditor
    {
        return RichEditor::make('description')
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                ['blockquote', 'bulletList', 'orderedList'],
                ['table'],
                ['undo', 'redo'],
            ])
            ->columnSpanFull();
    }

    protected static function timeFieldsGrid(): Grid
    {
        return Grid::make([
            'sm' => 1,
            'md' => 4,
            'lg' => 4,
        ])
            ->columnSpanFull()
            ->schema([
                static::eventDateField(),
                static::doorsTimeField(),
                static::startTimeField(),
                static::endTimeField(),
            ]);
    }

    protected static function eventDateField(): DatePicker
    {
        return DatePicker::make('event_date')
            ->label('Event Date')
            ->timezone(config('app.timezone'))
            ->native(false)
            ->required()
            ->dehydrated(false)
            ->afterStateHydrated(function (DatePicker $component, $record) {
                if ($record?->start_time) {
                    $component->state($record->start_time->format('Y-m-d'));
                }
            });
    }

    protected static function doorsTimeField(): TimePicker
    {
        return TimePicker::make('doors_time_only')
            ->label('Doors Time')
            ->timezone(config('app.timezone'))
            ->seconds(false)
            ->dehydrated(false)
            ->afterStateHydrated(function (TimePicker $component, $record) {
                if ($record?->doors_time) {
                    $component->state($record->doors_time->format('H:i'));
                }
            });
    }

    protected static function startTimeField(): TimePicker
    {
        return TimePicker::make('start_time_only')
            ->label('Start Time')
            ->timezone(config('app.timezone'))
            ->seconds(false)
            ->required()
            ->dehydrated(false)
            ->afterStateHydrated(function (TimePicker $component, $record) {
                if ($record?->start_time) {
                    $component->state($record->start_time->format('H:i'));
                }
            });
    }

    protected static function endTimeField(): TimePicker
    {
        return TimePicker::make('end_time_only')
            ->label('End Time')
            ->timezone(config('app.timezone'))
            ->seconds(false)
            ->dehydrated(false)
            ->afterStateHydrated(function (TimePicker $component, $record) {
                if ($record?->end_time) {
                    $component->state($record->end_time->format('H:i'));
                }
            });
    }

    protected static function ticketingGrid(): Grid
    {
        return Grid::make([
            'sm' => 1,
            'md' => 2,
            'lg' => 2,
        ])
            ->columnSpanFull()
            ->schema([
                static::eventLinkField(),
                static::ticketPriceField(),
            ]);
    }

    protected static function eventLinkField(): TextInput
    {
        return TextInput::make('event_link')
            ->label('Event Link')
            ->placeholder('https://example.com/tickets')
            ->url()
            ->helperText('Link to tickets, Facebook event, or more info')
            ->columnSpan([
                'sm' => 1,
                'md' => 1,
                'lg' => 1,
            ]);
    }

    protected static function ticketPriceField(): TextInput
    {
        return TextInput::make('ticket_price')
            ->label('Ticket Price')
            ->prefix('$')
            ->numeric()
            ->step(0.01)
            ->helperText('Leave blank for multiple prices')
            ->hintIcon('tabler-info-circle')
            ->hintIconTooltip('Use the heart button to toggle NOTAFLOF')
            ->placeholder('15.00')
            ->live()
            ->suffixAction(
                Action::make('toggle_notaflof')
                    ->label('NOTAFLOF')
                    ->icon(function ($get) {
                        return $get('notaflof') ? 'tabler-heart-filled' : 'tabler-heart';
                    })
                    ->color(function ($get) {
                        return $get('notaflof') ? 'danger' : 'gray';
                    })
                    ->extraAttributes(function ($get) {
                        return $get('notaflof') ?
                            ['style' => '--gray-400: rgb(239 68 68);'] :
                            [];
                    })
                    ->tooltip('Toggle NOTAFLOF (No One Turned Away For Lack of Funds)')
                    ->action(function ($set, $get) {
                        $set('notaflof', ! $get('notaflof'));
                    })
            )
            ->columnSpan([
                'sm' => 1,
                'md' => 1,
                'lg' => 1,
            ]);
    }

    protected static function locationFieldset(): Fieldset
    {
        return Fieldset::make('Location')
            ->columnSpanFull()
            ->schema([
                static::locationCheckboxField(),
                static::locationDetailsField(),
            ]);
    }

    protected static function locationCheckboxField(): Checkbox
    {
        return Checkbox::make('at_cmc')
            ->label('At Corvallis Music Collective')
            ->hintIcon('tabler-building-circus')
            ->hintIconTooltip('Uncheck this box if the show is at an external venue')
            ->default(true)
            ->live()
            ->afterStateHydrated(function (Checkbox $component, $record) {
                if ($record && $record->location) {
                    // Invert the stored is_external value for display
                    $component->state(! $record->location->isExternal());
                }
            })
            ->dehydrated(false)
            ->columnSpanFull();
    }

    protected static function locationDetailsField(): Textarea
    {
        return Textarea::make('location.details')
            ->label('External Venue Details')
            ->placeholder('Venue name, address, contact info, special instructions...')
            ->visible(fn (Get $get) => ! $get('at_cmc'))
            ->columnSpanFull()
            ->rows(3);
    }

    protected static function publishedAtField(): DateTimePicker
    {
        return DateTimePicker::make('published_at')
            ->label('Publish At')
            ->withoutSeconds()
            ->prefixIconColor(function ($state) {
                if (! $state) {
                    return 'gray';
                }
                $date = is_string($state) ? \Carbon\Carbon::parse($state) : $state;

                return $date->isFuture() ? 'warning' : 'success';
            })
            ->prefixIcon(function ($state) {
                if (! $state) {
                    return 'tabler-circle-x';
                }
                $date = is_string($state) ? \Carbon\Carbon::parse($state) : $state;

                return $date->isFuture() ? 'tabler-clock' : 'tabler-circle-check';
            })
            ->suffixAction(
                Action::make('setNow')
                    ->label('Publish Now')
                    ->icon('tabler-clock-down')
                    ->action(function ($set, $livewire, $component) {
                        $now = now();
                        $formatted = $now->format('Y-m-d\TH:i');
                        $set('published_at', $now);
                        $component->state($formatted);
                        $livewire->validateOnly('published_at');
                    })
            )
            ->hint(function ($state) {
                if (! $state) {
                    return 'Not scheduled';
                }
                $date = is_string($state) ? \Carbon\Carbon::parse($state) : $state;

                return $date->isFuture() ? 'Publish in '.$date->shortAbsoluteDiffForHumans() : 'Published';
            })
            ->live();
    }

    protected static function posterField(): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make('poster')
            ->label('Event Poster')
            ->collection('poster')
            ->image()
            ->visibility('public')
            ->directory('posters')
            ->multiple(false)
            ->disk('r2')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->maxSize(4096) // 4MB for posters
            ->imageResizeMode('cover')
            ->imageCropAspectRatio('8.5:11') // Standard poster ratio
            ->imageResizeTargetWidth(850)
            ->imageResizeTargetHeight(1100)
            ->helperText('Upload a poster in 8.5:11 aspect ratio (letter size). Max 4MB.');
    }
}
