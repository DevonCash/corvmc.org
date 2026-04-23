<?php

namespace App\Filament\Member\Resources\Reservations\Schemas;

use App\Models\User;
use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->steps(static::getSteps())
                    ->submitAction(view('space-management::filament.components.reservation-submit-actions')),
            ]);
    }

    protected static function shouldShowCheckout(Get $get): bool
    {
        return static::shouldShowPaymentChoice($get);
    }

    public static function getSteps(): array
    {
        return [
            // Step 1: Contact Info
            Wizard\Step::make('Contact')
                ->icon('tabler-phone')
                ->schema(static::contactStep())
                ->afterValidation(function (Get $get) {
                    $phone = $get('contact_phone');
                    if ($phone) {
                        static::saveContactInfo($phone);
                    }
                }),

            // Step 2: Reservation Details
            Wizard\Step::make('Schedule')
                ->icon('tabler-calendar-time')
                ->schema(static::reservationStep())
                ->columns(2),

            // Step 3: Confirmation
            Wizard\Step::make('Confirm')
                ->icon('tabler-circle-check')
                ->schema(static::confirmationStep()),

        ];
    }

    public static function getStaffSteps(): array
    {
        return [
            // Step 1: Select Member
            Wizard\Step::make('Member')
                ->icon('tabler-user')
                ->schema([
                    Select::make('user_id')
                        ->label('Member')
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(function (string $search) {
                            return User::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck('name', 'id');
                        })
                        ->getOptionLabelUsing(fn($value) => User::find($value)?->name),
                ]),

            // Step 2: Reservation Details (no hidden user_id — set in step 1)
            Wizard\Step::make('Schedule')
                ->icon('tabler-calendar-time')
                ->schema(static::staffScheduleStep())
                ->columns(2),

            // Step 3: Confirmation
            Wizard\Step::make('Confirm')
                ->icon('tabler-circle-check')
                ->schema(static::confirmationStep()),
        ];
    }

    protected static function staffScheduleStep(): array
    {
        return [
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
                            // Clear time selections when date changes
                            $set('start_time', null);
                            $set('end_time', null);
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get, $set);
                        }),

                    Select::make('start_time')
                        ->label('Start Time')
                        ->options(function (Get $get) {
                            $date = $get('reservation_date');
                            if (! $date) {
                                return [];
                            }

                            $slots = ReservationService::getAvailableTimeSlots(Carbon::parse($date, config('app.timezone')));

                            return collect($slots)->mapWithKeys(fn ($slot) => [
                                $slot['start']->format('H:i') => $slot['start']->format('g:i A'),
                            ])->all();
                        })
                        ->disabled(fn(Get $get) => ! $get('reservation_date'))
                        ->required()
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            // Clear end time when start time changes
                            $set('end_time', null);
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get, $set);
                        }),

                    Select::make('end_time')
                        ->label('End Time')
                        ->options(function (Get $get) {
                            $date = $get('reservation_date');
                            $startTime = $get('start_time');
                            if (! $date || ! $startTime) {
                                return [];
                            }

                            return ReservationService::getValidEndTimesForDate(Carbon::parse($date, config('app.timezone')), $startTime);
                        })
                        ->required()
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get, $set);
                        })
                        ->disabled(fn(Get $get) => ! $get('start_time')),
                ])->columnSpanFull(),

            Textarea::make('notes')
                ->label('Notes (Optional)')
                ->placeholder('What will you be working on? Any special setup needed?')
                ->rows(3)
                ->columnSpanFull(),

            // Hidden fields for the actual datetime values and status
            Hidden::make('reserved_at'),
            Hidden::make('reserved_until'),
            Hidden::make('status'),
        ];
    }

    public static function contactStep(): array
    {
        $user = Auth::user();
        $existingPhone = $user?->phone ?? $user?->profile?->contact?->phone;
        // Remove '+1' prefix if present
        if ($existingPhone && str_starts_with($existingPhone, '+1')) {
            $existingPhone = substr($existingPhone, 2);
        }

        return [


            TextInput::make('contact_phone')
                ->default($existingPhone)
                ->label('Phone Number')
                ->prefix('+1')
                ->mask('(999) 999-9999')
                ->helperText("We'll use this to send you reservation updates.")
                ->tel()
                ->required()
                ->dehydrated(false),

            Checkbox::make('sms_ok')
                ->label('This number can receive text messages')
                ->default($user?->profile?->contact?->sms_ok ?? false)
                ->dehydrated(false),
        ];
    }

    protected static function saveContactInfo(string $phone): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->phone = $phone;
        $user->save();
    }

    public static function reservationStep(): array
    {
        return [
            Hidden::make('user_id')
                ->default(Auth::user()?->id)
                ->required(),

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
                            // Clear time selections when date changes
                            $set('start_time', null);
                            $set('end_time', null);
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get, $set);
                        })
                        ->minDate(now()->addDay()->toDateString()),

                    Select::make('start_time')
                        ->label('Start Time')
                        ->options(function (Get $get) {
                            $date = $get('reservation_date');
                            if (! $date) {
                                return [];
                            }

                            $slots = ReservationService::getAvailableTimeSlots(Carbon::parse($date, config('app.timezone')));

                            return collect($slots)->mapWithKeys(fn ($slot) => [
                                $slot['start']->format('H:i') => $slot['start']->format('g:i A'),
                            ])->all();
                        })
                        ->disabled(fn(Get $get) => ! $get('reservation_date'))
                        ->required()
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            // Clear end time when start time changes
                            $set('end_time', null);
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get, $set);
                        }),

                    Select::make('end_time')
                        ->label('End Time')
                        ->options(function (Get $get) {
                            $date = $get('reservation_date');
                            $startTime = $get('start_time');
                            if (! $date || ! $startTime) {
                                return [];
                            }

                            return ReservationService::getValidEndTimesForDate(Carbon::parse($date, config('app.timezone')), $startTime);
                        })
                        ->required()
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get, $set);
                        })
                        ->disabled(fn(Get $get) => ! $get('start_time')),
                ])->columnSpanFull(),

            Textarea::make('notes')
                ->label('Notes (Optional)')
                ->placeholder('What will you be working on? Any special setup needed?')
                ->rows(3)
                ->columnSpanFull(),

            // Hidden fields for the actual datetime values and status
            Hidden::make('reserved_at'),
            Hidden::make('reserved_until'),
            Hidden::make('status'),
        ];
    }

    public static function confirmationStep(): array
    {
        return [
            // Hidden fields for calculated values
            Hidden::make('cost')->default(0),
            Hidden::make('free_hours_used')->default(0),
            Hidden::make('hours_used')->default(0),
            Hidden::make('payment_method')->default('stripe'),

            ViewField::make('reservation_summary')
                ->label('Reservation Summary')
                ->view('space-management::filament.components.reservation-summary')
                ->columnSpanFull(),

        ];
    }

    /**
     * Whether to show the payment method choice in the confirmation step.
     * True when within the confirmation window and the reservation has a cost.
     */
    protected static function shouldShowPaymentChoice(Get $get): bool
    {
        $cost = $get('cost');
        if (! $cost || $cost <= 0) {
            return false;
        }

        return static::isWithinConfirmationWindow($get);
    }

    /**
     * Whether the reservation date is within the confirmation window (next 7 days).
     */
    protected static function isWithinConfirmationWindow(Get $get): bool
    {
        $reservationDate = $get('reservation_date');
        if (! $reservationDate) {
            return false;
        }

        $resDate = Carbon::parse($reservationDate, config('app.timezone'));

        return $resDate->lte(now()->addWeek());
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

        $status = RehearsalReservation::determineStatusForDate(
            Carbon::parse($date),
            (bool) $isRecurring
        );

        $set('status', $status);
    }

    private static function calculateCost(Get $get, callable $set): void
    {
        $userId = $get('user_id');
        $user = $userId ? User::find($userId) : Auth::user();
        if (! $user) {
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

        $reservation = new RehearsalReservation([
            'reservable_id' => $user->id,
            'reservable_type' => User::class,
            'reserved_at' => Carbon::parse($start),
            'reserved_until' => Carbon::parse($end),
        ]);

        $lineItems = Finance::price([$reservation], $user);
        $totalCents = (int) $lineItems->sum('amount');
        $hours = Carbon::parse($start)->floatDiffInHours(Carbon::parse($end));

        // Count discount blocks as free hours used
        $discountBlocks = (int) abs($lineItems->filter->isDiscount()->sum('quantity'));

        $set('cost', max(0, $totalCents));
        $set('free_hours_used', $discountBlocks);
        $set('hours_used', $hours);
    }
}
