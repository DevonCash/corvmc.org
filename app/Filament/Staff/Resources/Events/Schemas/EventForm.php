<?php

namespace App\Filament\Staff\Resources\Events\Schemas;

use App\Actions\Events\SyncEventSpaceReservation;
use App\Settings\ReservationSettings;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use App\Filament\Staff\Resources\Events\Actions\RescheduleEventAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->extraAttributes(['class' => 'px-8'])
            ->components([
                // Left side: Title, then Description
                Group::make()
                    ->columnSpan(3)
                    ->schema([
                        static::titleField(),
                        static::descriptionField(),
                    ]),

                // Right side: Poster
                static::posterField()->columnSpan(1),

                // Remaining fields span full width
                static::timeFieldsGrid()->columnSpanFull(),
                static::venueField()->columnSpanFull(),
                static::ticketingGrid()->columnSpanFull(),
            ]);
    }

    protected static function titleField(): TextInput
    {
        return TextInput::make('title')
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
            ->required()
            ->disabled(fn($livewire) => filled($livewire->record ?? null))
            ->helperText(fn($livewire) => filled($livewire->record ?? null)
                ? 'Use the Reschedule action to change the date/time'
                : null);
    }

    protected static function doorsTimeField(): TimePicker
    {
        return TimePicker::make('doors_time')
            ->label('Doors Time')
            ->seconds(false);
    }

    protected static function endTimeField(): TimePicker
    {
        return TimePicker::make('end_time')
            ->label('End Time')
            ->seconds(false)
            ->disabled(fn($livewire) => filled($livewire->record ?? null) && $livewire->record->spaceReservation);
    }

    protected static function ticketingGrid(): Section
    {
        return Section::make('Tickets')
            ->columns(3)
            ->columnSpanFull()
            ->compact()
            ->afterHeader(
                [
                    Toggle::make('ticketing_enabled')
                        ->label('CMC Ticketing')
                        ->live()
                        ->afterStateUpdated(function ($state, $set) {
                            if ($state) {
                                $set('event_link', null);
                            }
                        })
                ]
            )
            ->schema([
                // External ticketing fields (visible when CMC ticketing is disabled)
                static::eventLinkField()
                    ->visible(fn($get) => ! $get('ticketing_enabled')),
                Grid::make()
                    ->columns(1)
                    ->visible(fn($get) => ! $get('ticketing_enabled'))
                    ->schema([
                        static::ticketPriceField(),
                        static::notaflofField(),
                    ]),

                // Native CMC ticketing fields (visible when CMC ticketing is enabled)
                static::nativeTicketingFieldset(),
            ]);
    }

    protected static function nativeTicketingFieldset(): Grid
    {
        return Grid::make(3)
            ->columnSpanFull()
            ->visible(fn($get) => $get('ticketing_enabled'))
            ->schema([
                TextInput::make('ticket_quantity')
                    ->label('Tickets Available')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Unlimited')
                    ->helperText('Leave blank for unlimited'),

                TextInput::make('ticket_price_override')
                    ->label('Ticket Price Override')
                    ->prefix('$')
                    ->numeric()
                    ->step(0.01)
                    ->placeholder(number_format(config('ticketing.default_price', 1000) / 100, 2))
                    ->helperText('Default: $' . number_format(config('ticketing.default_price', 1000) / 100, 2)),

                TextInput::make('tickets_sold')
                    ->label('Tickets Sold')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false),
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
        $settings = app(ReservationSettings::class);

        return Select::make('venue_id')
            ->label('Venue')
            ->relationship('venue', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->default(fn() => Venue::cmc()->first()?->id)
            ->disabled(fn($livewire) => filled($livewire->record ?? null))
            ->helperText(fn($livewire) => filled($livewire->record ?? null)
                ? 'Use the Reschedule action to change the venue'
                : null)
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
            ->prefixAction(
                Action::make('configureSpaceReservation')
                    ->label('Configure Space Reservation')
                    ->icon('tabler-clock-cog')
                    ->visible(function (callable $get) {
                        $venueId = $get('venue_id');
                        if (! $venueId) {
                            return false;
                        }

                        return Venue::find($venueId)?->is_cmc ?? false;
                    })
                    ->fillForm(function (callable $get, $livewire) {
                        // If editing an existing event with a reservation, calculate from it
                        if (isset($livewire->record) && $livewire->record instanceof Event) {
                            $event = $livewire->record;
                            if ($event->usesPracticeSpace() && $event->spaceReservation) {
                                $reservation = $event->spaceReservation;
                                $endTime = $event->end_datetime ?? $event->start_datetime->copy()->addHours(3);

                                return [
                                    'setup_minutes' => $event->start_datetime->diffInMinutes($reservation->reserved_at),
                                    'teardown_minutes' => $reservation->reserved_until->diffInMinutes($endTime),
                                ];
                            }
                        }

                        // Fall back to form state (for create page or events without reservations)
                        return [
                            'setup_minutes' => $get('setup_minutes'),
                            'teardown_minutes' => $get('teardown_minutes'),
                        ];
                    })
                    ->schema([
                        TextInput::make('setup_minutes')
                            ->label('Setup Time')
                            ->helperText('Minutes before event start to block for setup')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(480)
                            ->placeholder($settings->default_event_setup_minutes)
                            ->suffix('minutes'),

                        TextInput::make('teardown_minutes')
                            ->label('Teardown Time')
                            ->helperText('Minutes after event end to block for teardown')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(480)
                            ->placeholder($settings->default_event_teardown_minutes)
                            ->suffix('minutes'),

                        Toggle::make('force_override')
                            ->label('Override conflicts (admin only)')
                            ->helperText('Force the reservation even if it conflicts with other bookings')
                            ->visible(fn() => auth()->user()?->hasRole('admin'))
                            ->default(false),
                    ])
                    ->action(function (array $data, callable $set, $livewire) {
                        $setupMinutes = $data['setup_minutes'] !== '' && $data['setup_minutes'] !== null
                            ? (int) $data['setup_minutes']
                            : null;
                        $teardownMinutes = $data['teardown_minutes'] !== '' && $data['teardown_minutes'] !== null
                            ? (int) $data['teardown_minutes']
                            : null;
                        $forceOverride = $data['force_override'] ?? false;

                        // If editing an existing event, directly sync the reservation
                        if (isset($livewire->record) && $livewire->record instanceof Event) {
                            $result = SyncEventSpaceReservation::run(
                                $livewire->record,
                                $setupMinutes,
                                $teardownMinutes,
                                $forceOverride
                            );

                            if (! $result['success']) {
                                $conflicts = $result['conflicts'];
                                $messages = [];

                                foreach ($conflicts['reservations'] as $reservation) {
                                    $time = $reservation->reserved_at->format('g:i A') . ' - ' . $reservation->reserved_until->format('g:i A');
                                    $messages[] = "Reservation: {$time}";
                                }

                                foreach ($conflicts['productions'] as $production) {
                                    $messages[] = "Production: {$production->title}";
                                }

                                foreach ($conflicts['closures'] as $closure) {
                                    $messages[] = "Closure: {$closure->type->getLabel()}";
                                }

                                Notification::make()
                                    ->title('Space reservation conflicts detected')
                                    ->body(implode("\n", $messages))
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Space reservation updated')
                                ->success()
                                ->send();

                            return;
                        }

                        // On create page, just store values in form state for later
                        $set('setup_minutes', $data['setup_minutes']);
                        $set('teardown_minutes', $data['teardown_minutes']);
                    })
            )
            ->columnSpanFull();
    }

    protected static function posterField(): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make('poster')
            ->label('Event Poster')
            ->collection('poster')
            ->image()
            ->directory('posters')
            ->multiple(false)
            ->disk('r2')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->maxSize(4096) // 4MB for posters
            ->imageAspectRatio('8.5:11') // Standard poster ratio
            ->automaticallyResizeImagesMode('cover')
            ->automaticallyResizeImagesToWidth(850)
            ->automaticallyResizeImagesToHeight(1100)
            ->helperText('Upload a poster in 8.5:11 aspect ratio (letter size). Max 4MB.')
            ->extraAttributes(['class' => 'h-full [&_.filepond--root]:h-full [&_.filepond--drop-label]:h-full']);
    }
}
