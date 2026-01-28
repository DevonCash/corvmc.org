<?php

namespace App\Filament\Member\Resources\Reservations\Schemas;

use CorvMC\SpaceManagement\Actions\Reservations\CalculateReservationCost;
use CorvMC\SpaceManagement\Actions\Reservations\DetermineReservationStatus;
use CorvMC\SpaceManagement\Actions\Reservations\GetAvailableTimeSlotsForDate;
use CorvMC\SpaceManagement\Actions\Reservations\GetValidEndTimesForDate;
use CorvMC\Membership\Data\ContactData;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
                    ->steps(static::getSteps()),
            ]);
    }

    protected static function shouldShowCheckout(Get $get): bool
    {
        $cost = $get('cost');
        $reservationDate = $get('reservation_date');
        $isRecurring = $get('is_recurring');

        // Must have a positive cost
        if (! $cost || $cost <= 0) {
            return false;
        }

        // Cannot be recurring
        if ($isRecurring) {
            return false;
        }

        // Must have a reservation date
        if (! $reservationDate) {
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
            // Step 1: Contact Info
            Wizard\Step::make('Contact')
                ->icon('tabler-phone')
                ->schema(static::contactStep())
                ->afterValidation(function (Get $get) {
                    $phone = $get('contact_phone');
                    $smsOk = $get('sms_ok');
                    if ($phone || $smsOk !== null) {
                        static::saveContactInfo($phone, (bool) $smsOk);
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

    public static function contactStep(): array
    {
        $user = Auth::user();
        $contact = $user?->profile?->contact;
        $existingPhone = $contact?->phone;
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
                ->default($contact?->sms_ok ?? false)
                ->dehydrated(false),
        ];
    }

    protected static function saveContactInfo(?string $phone, bool $smsOk): void
    {
        $user = Auth::user();
        if ($user?->profile) {
            $contact = $user->profile->contact ?? new ContactData;
            if ($phone) {
                $contact->phone = $phone;
            }
            $contact->sms_ok = $smsOk;
            $user->profile->contact = $contact;
            $user->profile->save();
        }
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
                        ->color(fn (Get $get) => match (true) {
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

                            return GetAvailableTimeSlotsForDate::run(Carbon::parse($date, config('app.timezone')));
                        })
                        ->disabled(fn (Get $get) => ! $get('reservation_date'))
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

                            return GetValidEndTimesForDate::run(Carbon::parse($date, config('app.timezone')), $startTime);
                        })
                        ->required()
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            self::updateDateTimes($get, $set);
                            self::calculateCost($get, $set);
                        })
                        ->disabled(fn (Get $get) => ! $get('start_time')),
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

            ViewField::make('reservation_summary')
                ->label('Reservation Summary')
                ->view('space-management::filament.components.reservation-summary')
                ->columnSpanFull(),
        ];
    }

    private static function updateDateTimes(Get $get, callable $set): void
    {
        $date = $get('reservation_date');
        $startTime = $get('start_time');
        $endTime = $get('end_time');

        if ($date && $startTime) {
            $datetime = Carbon::parse($date.' '.$startTime, config('app.timezone'));
            $set('reserved_at', $datetime);
        }

        if ($date && $endTime) {
            $datetime = Carbon::parse($date.' '.$endTime, config('app.timezone'));
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

    private static function calculateCost(Get $get, callable $set): void
    {
        $user = Auth::user();
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
