<?php

namespace App\Filament\Staff\Resources\Events\Schemas;

use App\Actions\Events\CheckEventConflicts;
use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\Events\Models\Venue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;

class EventCreateWizard
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make()
                ->columnSpanFull()
                ->steps(static::getSteps())
                ->skippable(false),
        ]);
    }

    public static function getSteps(): array
    {
        return [
            Wizard\Step::make('Basic Info')
                ->icon('tabler-calendar-event')
                ->schema(static::basicInfoStep())
                ->afterValidation(fn (Get $get, callable $set) => static::checkConflicts($get, $set)),

            Wizard\Step::make('Space Reservation')
                ->icon('tabler-home')
                ->visible(fn (Get $get) => static::isCmcVenue($get))
                ->schema(static::spaceReservationStep()),

            Wizard\Step::make('Confirm')
                ->icon('tabler-circle-check')
                ->schema(static::confirmationStep()),
        ];
    }

    protected static function basicInfoStep(): array
    {
        return [
            TextInput::make('title')
                ->label('Event Title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Grid::make(2)
                ->schema([
                    DateTimePicker::make('start_datetime')
                        ->label('Start Time')
                        ->native(true)
                        ->seconds(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('end_time', null)),

                    TimePicker::make('end_time')
                        ->label('End Time')
                        ->seconds(false)
                        ->live()
                        ->required(fn (Get $get) => static::isCmcVenue($get))
                        ->helperText(fn (Get $get) => static::isCmcVenue($get)
                            ? 'Required for CMC events to check space availability'
                            : 'Optional for external venues'),
                ]),

            static::venueField(),

            // Hidden fields for conflict data
            Hidden::make('conflict_status'),
            Hidden::make('conflict_data'),
            Hidden::make('setup_minutes'),
            Hidden::make('teardown_minutes'),
        ];
    }

    protected static function venueField(): Select
    {
        return Select::make('venue_id')
            ->label('Venue')
            ->relationship('venue', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->default(fn () => Venue::cmc()->first()?->id)
            ->getOptionLabelFromRecordUsing(fn (Venue $venue) => $venue->is_cmc
                ? $venue->name
                : $venue->name.' - '.$venue->city)
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

    protected static function spaceReservationStep(): array
    {
        $settings = app(ReservationSettings::class);

        return [
            // Conflict status display
            ViewField::make('conflict_status_display')
                ->view('filament.components.event-conflict-status'),

            Section::make('Setup & Teardown')
                ->compact()
                ->description('Adjust the time blocked before and after your event.')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('setup_minutes')
                                ->label('Setup Time')
                                ->helperText('Minutes before event start')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(480)
                                ->default($settings->default_event_setup_minutes)
                                ->suffix('minutes')
                                ->live()
                                ->afterStateUpdated(fn (Get $get, callable $set) => static::recheckConflicts($get, $set)),

                            TextInput::make('teardown_minutes')
                                ->label('Teardown Time')
                                ->helperText('Minutes after event end')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(480)
                                ->default($settings->default_event_teardown_minutes)
                                ->suffix('minutes')
                                ->live()
                                ->afterStateUpdated(fn (Get $get, callable $set) => static::recheckConflicts($get, $set)),
                        ]),
                ]),

            // Admin override section
            Section::make('Admin Override')
                ->compact()
                ->visible(fn () => auth()->user()?->hasRole('admin'))
                ->schema([
                    Toggle::make('force_override')
                        ->label('Override conflicts')
                        ->helperText('Force the reservation even if it conflicts with other bookings. Use with caution.')
                        ->default(false),
                ]),

            // Show warning for non-admins with event conflicts
            Placeholder::make('conflict_warning')
                ->content('You cannot proceed with this time slot due to conflicts. Please go back and choose a different time.')
                ->visible(fn (Get $get) => $get('conflict_status') === 'event_conflict' && ! auth()->user()?->hasRole('admin'))
                ->extraAttributes(['class' => 'text-danger-600']),

            Hidden::make('force_override')->default(false),
        ];
    }

    protected static function confirmationStep(): array
    {
        return [
            Section::make('Event Summary')
                ->compact()
                ->schema([
                    Placeholder::make('summary_title')
                        ->label('Title')
                        ->content(fn (Get $get) => $get('title')),

                    Placeholder::make('summary_datetime')
                        ->label('When')
                        ->content(function (Get $get) {
                            $start = $get('start_datetime');
                            $end = $get('end_time');

                            if (! $start) {
                                return 'Not set';
                            }

                            $startCarbon = $start instanceof Carbon ? $start : Carbon::parse($start);
                            $formatted = $startCarbon->format('l, F j, Y \a\t g:i A');

                            if ($end) {
                                $formatted .= ' - '.$end;
                            }

                            return $formatted;
                        }),

                    Placeholder::make('summary_venue')
                        ->label('Venue')
                        ->content(function (Get $get) {
                            $venueId = $get('venue_id');
                            if (! $venueId) {
                                return 'Not set';
                            }

                            return Venue::find($venueId)?->name ?? 'Unknown venue';
                        }),
                ]),

            Section::make('Space Reservation')
                ->compact()
                ->visible(fn (Get $get) => static::isCmcVenue($get))
                ->schema([
                    Placeholder::make('reservation_status')
                        ->label('Status')
                        ->content(function (Get $get) {
                            $status = $get('conflict_status');
                            $forceOverride = $get('force_override');

                            return match ($status) {
                                'available' => '✓ Space is available - reservation will be created',
                                'setup_conflict' => $forceOverride
                                    ? '⚠ Setup/teardown conflicts will be overridden'
                                    : '⚠ Setup/teardown has conflicts - reservation will be created with reduced buffer',
                                'event_conflict' => $forceOverride
                                    ? '⚠ Event time conflicts will be overridden'
                                    : '✗ Event time has conflicts - reservation cannot be created',
                                default => 'Checking availability...',
                            };
                        }),

                    Placeholder::make('reservation_times')
                        ->label('Reserved Period')
                        ->content(function (Get $get) {
                            $start = $get('start_datetime');
                            $end = $get('end_time');
                            $setupMinutes = (int) ($get('setup_minutes') ?? 0);
                            $teardownMinutes = (int) ($get('teardown_minutes') ?? 0);

                            if (! $start || ! $end) {
                                return 'Not calculated';
                            }

                            $startCarbon = $start instanceof Carbon ? $start : Carbon::parse($start);
                            $endTime = Carbon::parse($startCarbon->toDateString().' '.$end);

                            $reservedAt = $startCarbon->copy()->subMinutes($setupMinutes);
                            $reservedUntil = $endTime->copy()->addMinutes($teardownMinutes);

                            return $reservedAt->format('g:i A').' - '.$reservedUntil->format('g:i A').
                                ' (includes '.$setupMinutes.' min setup, '.$teardownMinutes.' min teardown)';
                        }),
                ]),
        ];
    }

    protected static function isCmcVenue(Get $get): bool
    {
        $venueId = $get('venue_id');
        if (! $venueId) {
            return true; // Default to CMC if not set
        }

        return Venue::find($venueId)?->is_cmc ?? false;
    }

    protected static function checkConflicts(Get $get, callable $set): void
    {
        // Only check conflicts for CMC venues
        if (! static::isCmcVenue($get)) {
            $set('conflict_status', 'available');
            $set('conflict_data', null);

            return;
        }

        $startDatetime = $get('start_datetime');
        $endTime = $get('end_time');

        if (! $startDatetime || ! $endTime) {
            $set('conflict_status', null);
            $set('conflict_data', null);

            return;
        }

        $startCarbon = $startDatetime instanceof Carbon
            ? $startDatetime
            : Carbon::parse($startDatetime, config('app.timezone'));

        // Build end datetime from start date + end time
        $endCarbon = Carbon::parse($startCarbon->toDateString().' '.$endTime, config('app.timezone'));

        // Get setup/teardown defaults
        $settings = app(ReservationSettings::class);
        $setupMinutes = (int) ($get('setup_minutes') ?? $settings->default_event_setup_minutes);
        $teardownMinutes = (int) ($get('teardown_minutes') ?? $settings->default_event_teardown_minutes);

        // Set defaults if not already set
        if (! $get('setup_minutes')) {
            $set('setup_minutes', $settings->default_event_setup_minutes);
        }
        if (! $get('teardown_minutes')) {
            $set('teardown_minutes', $settings->default_event_teardown_minutes);
        }

        $result = CheckEventConflicts::run($startCarbon, $endCarbon, $setupMinutes, $teardownMinutes);

        $set('conflict_status', $result['status']);
        $set('conflict_data', json_encode([
            'event_conflicts' => static::formatConflictsForStorage($result['event_conflicts']),
            'setup_conflicts' => static::formatConflictsForStorage($result['setup_conflicts']),
            'all_conflicts' => static::formatConflictsForStorage($result['all_conflicts']),
        ]));
    }

    protected static function recheckConflicts(Get $get, callable $set): void
    {
        static::checkConflicts($get, $set);
    }

    protected static function formatConflictsForStorage(array $conflicts): array
    {
        return [
            'reservations' => $conflicts['reservations']->map(fn ($r) => [
                'id' => $r->id,
                'reserved_at' => $r->reserved_at->toIso8601String(),
                'reserved_until' => $r->reserved_until->toIso8601String(),
                'type' => class_basename($r),
                'display_title' => $r->getDisplayTitle(),
            ])->values()->all(),
            'productions' => $conflicts['productions']->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'start_datetime' => $p->start_datetime->toIso8601String(),
            ])->values()->all(),
            'closures' => $conflicts['closures']->map(fn ($c) => [
                'id' => $c->id,
                'reason' => $c->type->getLabel(),
                'starts_at' => $c->starts_at->toIso8601String(),
                'ends_at' => $c->ends_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
