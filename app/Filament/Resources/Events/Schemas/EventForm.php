<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
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
                        static::venueField(),
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
            'sm' => 2,
            'md' => 4,
            'lg' => 4,
        ])
            ->columnSpanFull()
            ->schema([
                static::eventDateField(),
                static::doorsTimeField(),
                static::endTimeField(),
            ]);
    }

    protected static function eventDateField(): DateTimePicker
    {
        return DateTimePicker::make('start_datetime')
            ->label('Start Time')
            ->native(true)
            ->seconds(false)
            ->columnSpan(2)
            ->required();
    }

    protected static function doorsTimeField(): TimePicker
    {
        return TimePicker::make('doors_datetime')
            ->label('Doors Time')
            ->seconds(false);
    }


    protected static function endTimeField(): TimePicker
    {
        return TimePicker::make('end_datetime')
            ->label('End Time')
            ->seconds(false);
    }

    protected static function ticketingGrid(): Grid
    {
        return Grid::make([])
            ->columns(3)
            ->columnSpanFull()
            ->schema([
                static::eventLinkField(),
                Grid::make()
                    ->columns(1)
                    ->schema([
                        static::ticketPriceField(),
                        static::notaflofField(),
                    ])
            ]);
    }

    protected static function eventLinkField(): TextInput
    {
        return TextInput::make('event_link')
            ->label('Event Link')
            ->placeholder('https://example.com/tickets')
            ->url()
            ->helperText('Link to tickets, Facebook event, or more info')
            ->afterStateUpdated(function (TextInput $component, $state) {
                // Ensure the URL starts with http:// or https://
                if ($state && ! preg_match('/^https?:\/\//', $state)) {
                    $component->state('https://' . ltrim($state, '/'));
                }
            })
            ->columnSpan([
                'sm' => 2,
                'md' => 2,
                'lg' => 2,
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
            ->placeholder('15.00')
            ->live();
    }

    protected static function notaflofField(): Checkbox
    {
        return Checkbox::make('notaflof')
            ->label('NOTAFLOF')
            ->hintIcon('tabler-info-circle')
            ->hintIconTooltip('No One Turned Away For Lack of Funds')
            ->default(false);
    }

    protected static function venueField(): Select
    {
        return Select::make('venue_id')
            ->label('Venue')
            ->relationship('venue', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->default(fn() => Venue::cmc()->first()?->id)
            ->getOptionLabelFromRecordUsing(fn(Venue $venue) => $venue->is_cmc
                ? $venue->name
                : $venue->name . ' - ' . $venue->city)
            ->createOptionForm([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->maxLength(255),
                TextInput::make('city')
                    ->default('Corvallis')
                    ->maxLength(100),
                TextInput::make('state')
                    ->default('OR')
                    ->maxLength(2),
                TextInput::make('zip')
                    ->maxLength(10),
            ])
            ->columnSpanFull();
    }

    protected static function publishedAtField(): DateTimePicker
    {
        return DateTimePicker::make('published_at')
            ->label('Publish At')
            ->seconds(false)
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

                return $date->isFuture() ? 'Publish in ' . $date->shortAbsoluteDiffForHumans() : 'Published';
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
