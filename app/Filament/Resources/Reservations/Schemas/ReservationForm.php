<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Actions\Reservations\CalculateReservationCost;
use App\Actions\Reservations\DetermineReservationStatus;
use App\Actions\Reservations\GetAvailableTimeSlotsForDate;
use App\Actions\Reservations\GetValidEndTimesForDate;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make()
                    ->columnSpanFull()
                    ->steps(static::getSteps()),
            ]);
    }

    protected static function shouldShowCheckout(Get $get): bool
    {
        $cost = $get('cost');
        $reservationDate = $get('reservation_date');
        $isRecurring = $get('is_recurring');

        // Must have a positive cost
        if (!$cost || $cost <= 0) {
            return false;
        }

        // Cannot be recurring
        if ($isRecurring) {
            return false;
        }

        // Must have a reservation date
        if (!$reservationDate) {
            return false;
        }

        // Must be within auto-confirm range (next 7 days)
        // Parse the date string with explicit timezone
        $resDate = Carbon::parse($reservationDate, config('app.timezone'));
        $oneWeekFromNow = now()->addWeek();

        return $resDate->lte($oneWeekFromNow);
    }

    public static function getSteps(): array
    {
        return [
            // Step 1: Reservation Details
            Wizard\Step::make('Details')
                ->description('Set up your reservation')
                ->icon('tabler-calendar-time')
                ->schema(static::reservationStep())
                ->columns(2),

            // Step 2: Confirmation
            Wizard\Step::make('Confirm')
                ->description('Review and confirm your reservation')
                ->icon('tabler-circle-check')
                ->schema(static::confirmationStep())

        ];
    }
    public static function reservationStep(): array
    {
        $isAdmin = User::me()?->can('manage practice space');

        return [
            // Admin-only member selection at the top if needed
            ...$isAdmin ? [
                Select::make('user_id')
                    ->label('Member (Admin Only)')
                    ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                    ->default(Auth::user()->id)
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                        self::calculateCost($state, $get, $set);
                    }),
                Select::make('status_override')
                    ->label('Status (Admin Only)')
                    ->hintIcon('tabler-circle-info')
                    ->hintIconTooltip('Override the default status based on your selection.')
                    ->options([
                        'auto' => 'Default (based on date/type)',
                        'pending' => 'Force Pending',
                        'confirmed' => 'Force Confirmed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('auto')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                        if ($state === 'auto') {
                            self::updateStatus($get, $set);
                        } else {
                            $set('status', $state);
                        }
                    }),
            ] : [
                Hidden::make('user_id')
                    ->default(Auth::user()?->id)
                    ->required(),
            ],

            Section::make('Date & Time')
                ->compact()
                ->afterHeader([
                    Icon::make(function ($get) {
                        $date = $get('reservation_date');
                        $startTime = $get('start_time');
                        $endTime = $get('end_time');
                        if (! $date) {
                            return 'tabler-calendar';
                        }
                        if (! $startTime) {
                            return 'tabler-clock-play';
                        }
                        if (! $endTime) {
                            return 'tabler-clock-pause';
                        }

                        return 'tabler-circle-check';
                    })
                        ->color(fn(Get $get) => match (true) {
                            ! $get('reservation_date') => 'gray',
                            ! $get('start_time') => 'primary',
                            ! $get('end_time') => 'primary',
                            default => 'success',
                        }),
                ])
                ->columns(2)
                ->schema([
                    DatePicker::make('reservation_date')
                        ->label('Date')
                        ->required()
                        ->live()
                        ->columnSpanFull()
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get('user_id'), $get, $set);
                        })
                        ->hint(fn($state) => $state ? '' : 'Select a date')
                        ->minDate(now()->toDateString()),

                    Select::make('start_time')
                        ->label('Start Time')
                        ->options(function (Get $get) {
                            $date = $get('reservation_date');
                            if (! $date) {
                                return [];
                            }

                            return GetAvailableTimeSlotsForDate::run(Carbon::parse($date));
                        })
                        ->disabled(fn(Get $get) => ! $get('reservation_date'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            // Clear end time when start time changes
                            $set('end_time', null);
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get('user_id'), $get, $set);
                        })
                        ->hint(fn(Get $get, $state) => ! $get('reservation_date') || $state ? '' : 'Select a start time'),

                    Select::make('end_time')
                        ->label('End Time')
                        ->hint(fn(Get $get, $state) => ! $get('start_time') || $state ? '' : 'Select an end time')
                        ->options(function (Get $get) {
                            $date = $get('reservation_date');
                            $startTime = $get('start_time');
                            if (! $date || ! $startTime) {
                                return [];
                            }

                            return GetValidEndTimesForDate::run(Carbon::parse($date), $startTime);
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get('user_id'), $get, $set);
                        })
                        ->disabled(fn(Get $get) => ! $get('start_time')),
                ])->columnSpanFull(),

            Textarea::make('notes')
                ->label('Notes (Optional)')
                ->placeholder('What will you be working on? Any special setup needed?')
                ->rows(3)
                ->columnSpanFull(),

            // Admin-only payment controls
            ...$isAdmin ? [
                Section::make('Payment Management (Admin)')
                    ->compact()
                    ->schema([
                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'paid' => 'Paid',
                                'comped' => 'Comped',
                                'refunded' => 'Refunded',
                            ])
                            ->default('unpaid')
                            ->live(),

                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'cash' => 'Cash',
                                'card' => 'Credit/Debit Card',
                                'venmo' => 'Venmo',
                                'paypal' => 'PayPal',
                                'zelle' => 'Zelle',
                                'check' => 'Check',
                                'comp' => 'Comped',
                                'other' => 'Other',
                            ])
                            ->visible(fn(Get $get) => $get('payment_status') !== 'unpaid'),

                        DateTimePicker::make('paid_at')
                            ->label('Payment Date')
                            ->visible(fn(Get $get) => in_array($get('payment_status'), ['paid', 'comped', 'refunded']))
                            ->default(now()),

                        Textarea::make('payment_notes')
                            ->label('Payment Notes')
                            ->placeholder('Notes about payment, comp reason, etc.')
                            ->rows(2)
                            ->visible(fn(Get $get) => $get('payment_status') !== 'unpaid'),
                    ])
                    ->columns(2),
            ] : [],

            // Hidden fields for the actual datetime values and status
            Hidden::make('reserved_at'),
            Hidden::make('reserved_until'),
            Hidden::make('status')
        ];
    }
    public static function confirmationStep(): array
    {
        return [

            // Hidden fields for calculated values
            Hidden::make('cost')->default(0),
            Hidden::make('free_hours_used')->default(0),
            Hidden::make('hours_used')->default(0),

            ViewField::make('reservation_summary')
                ->label('Reservation Summary')
                ->view('filament.components.reservation-summary')
                ->columnSpanFull(),
        ];
    }

    private static function updateDateTimes(Get $get, callable $set): void
    {
        $date = $get('reservation_date');
        $startTime = $get('start_time');
        $endTime = $get('end_time');

        if ($date && $startTime) {
            $datetime = Carbon::parse($date . ' ' . $startTime, config('app.timezone'));
            $set('reserved_at', $datetime);
        }

        if ($date && $endTime) {
            $datetime = Carbon::parse($date . ' ' . $endTime, config('app.timezone'));
            $set('reserved_until', $datetime);
        }

        // Update status whenever dates change
        self::updateStatus($get, $set);
    }

    private static function updateStatus(Get $get, callable $set): void
    {
        // Check if admin has overridden status
        $adminStatus = $get('status_override');
        if ($adminStatus && $adminStatus !== 'auto') {
            return; // Don't auto-update if admin has set a specific status
        }

        $date = $get('reservation_date');
        $isRecurring = $get('is_recurring');

        if (! $date) {
            $set('status', 'pending');

            return;
        }

        $status = DetermineReservationStatus::run(
            Carbon::parse($date),
            (bool) $isRecurring
        );

        $set('status', $status);
    }

    private static function calculateCost(?int $userId, Get $get, callable $set): void
    {
        if (! $userId) {
            $set('cost', 0);
            $set('free_hours_used', 0);
            $set('hours_used', 0);

            return;
        }

        $start = $get('reserved_at');
        $end = $get('reserved_until');

        if (! $start || ! $end) {
            $set('cost', 0);
            $set('free_hours_used', 0);
            $set('hours_used', 0);

            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $calculation = CalculateReservationCost::run(
            $user,
            Carbon::parse($start),
            Carbon::parse($end)
        );

        // Store cost as cents (integer) for Livewire compatibility
        $set('cost', $calculation['cost']->getMinorAmount()->toInt());
        $set('free_hours_used', $calculation['free_hours']);
        $set('hours_used', $calculation['total_hours']);
    }
}
