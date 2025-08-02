<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = User::me();
        $isAdmin = $user && $user->can('manage practice space');

        return $schema
            ->components([
                Wizard::make([
                    // Step 1: Time Selection
                    Wizard\Step::make('When')
                        ->description('Choose your reservation time')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            // Admin-only member selection at the top if needed
                            ...$isAdmin ? [
                                Select::make('user_id')
                                    ->label('Member (Admin Only)')
                                    ->relationship('user', 'name')
                                    ->default(User::me()->id)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        self::calculateCost($state, $get, $set);
                                    })
                                    ->columnSpanFull(),
                            ] : [
                                Hidden::make('user_id')
                                    ->default($user?->id)
                                    ->required(),
                            ],

                            DatePicker::make('reservation_date')
                                ->label('Date')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    self::updateDateTimes($get, $set);
                                    self::calculateCost($get('user_id'), $get, $set);
                                })
                                ->minDate(now()->toDateString())
                                ->helperText('Select the day for your reservation'),

                            TimePicker::make('start_time')
                                ->label('Start Time')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    self::updateDateTimes($get, $set);
                                    self::calculateCost($get('user_id'), $get, $set);
                                })
                                ->seconds(false)
                                ->helperText('Practice space hours: 9 AM - 10 PM daily'),

                            TimePicker::make('end_time')
                                ->label('End Time')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    self::updateDateTimes($get, $set);
                                    self::calculateCost($get('user_id'), $get, $set);
                                })
                                ->after('start_time')
                                ->seconds(false)
                                ->helperText('Maximum 8 hours per session'),

                            TextEntry::make('duration_preview')
                                ->label('Duration')
                                ->state(function (Get $get): string {
                                    $date = $get('reservation_date');
                                    $startTime = $get('start_time');
                                    $endTime = $get('end_time');

                                    if (!$date || !$startTime || !$endTime) {
                                        return 'Select date and times to see duration';
                                    }

                                    $start = Carbon::parse($date . ' ' . $startTime);
                                    $end = Carbon::parse($date . ' ' . $endTime);

                                    $duration = $start->diffInMinutes($end) / 60;
                                    return number_format($duration, 1) . ' hours';
                                })
                                ->columnSpanFull(),

                            Toggle::make('is_recurring')
                                ->label('Make this a recurring reservation')
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    self::updateStatus($get, $set);
                                })
                                ->helperText(function () use ($user): string {
                                    if (!$user || !$user->isSustainingMember()) {
                                        return 'Available for sustaining members only';
                                    }
                                    return 'Weekly recurring reservations require approval';
                                })
                                ->disabled(fn() => !$user || !$user->isSustainingMember())
                                ->columnSpanFull(),

                            // Status indicator
                            TextEntry::make('confirmation_notice')
                                ->label('Confirmation Process')
                                ->state(function (Get $get): string {
                                    $date = $get('reservation_date');
                                    $isRecurring = $get('is_recurring');

                                    if (!$date) {
                                        return 'Select a date to see confirmation process';
                                    }

                                    $reservationDate = Carbon::parse($date);
                                    $isMoreThanWeekAway = $reservationDate->isAfter(Carbon::now()->addWeek());

                                    if ($isRecurring) {
                                        return '📅 Recurring reservations require manual approval';
                                    } elseif ($isMoreThanWeekAway) {
                                        $confirmationDate = $reservationDate->copy()->subWeek();
                                        return "📧 We'll send you a confirmation reminder on " . $confirmationDate->format('M j') . " to confirm you still need this time";
                                    } else {
                                        return '✅ This reservation will be immediately confirmed';
                                    }
                                })
                                ->columnSpanFull(),

                            // Hidden fields for the actual datetime values
                            Hidden::make('reserved_at'),
                            Hidden::make('reserved_until'),
                        ])
                        ->columns(2),

                    // Step 2: Billing & Review
                    Wizard\Step::make('Review')
                        ->description('Review cost and billing options')
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            TextEntry::make('time_summary')
                                ->label('Selected Time')
                                ->state(function (Get $get): string {
                                    $start = $get('reserved_at');
                                    $end = $get('reserved_until');

                                    if (!$start || !$end) {
                                        return 'No time selected';
                                    }

                                    $startFormatted = Carbon::parse($start)->format('l, M j, Y \a\t g:i A');
                                    $endFormatted = Carbon::parse($end)->format('g:i A');

                                    return "{$startFormatted} - {$endFormatted}";
                                })
                                ->columnSpanFull(),

                            Checkbox::make('use_free_hours')
                                ->label('Use my free hours')
                                ->default(true)
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    self::calculateCost($get('user_id'), $get, $set);
                                })
                                ->helperText(function () use ($user): string {
                                    if (!$user || !$user->isSustainingMember()) {
                                        return 'Available for sustaining members only';
                                    }
                                    return 'Check to use your monthly free hours first';
                                })
                                ->disabled(fn() => !$user || !$user->isSustainingMember())
                                ->columnSpanFull(),

                            TextEntry::make('cost_summary')
                                ->label('Cost Breakdown')
                                ->state(function (Get $get): string {
                                    $start = $get('reserved_at');
                                    $end = $get('reserved_until');
                                    $userId = $get('user_id');

                                    if (!$start || !$end || !$userId) {
                                        return 'Complete previous step to see cost breakdown';
                                    }

                                    $user = User::find($userId);
                                    if (!$user) {
                                        return 'User not found';
                                    }

                                    $duration = Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60;
                                    $reservationService = new ReservationService;
                                    $calculation = $reservationService->calculateCost(
                                        $user,
                                        Carbon::parse($start),
                                        Carbon::parse($end)
                                    );

                                    $summary = "Duration: " . number_format($duration, 1) . " hours\n";

                                    if ($user->isSustainingMember()) {
                                        $summary .= "Remaining free hours this month: TBD\n";
                                    }

                                    if ($calculation['free_hours'] > 0) {
                                        $summary .= "Free hours used: " . number_format($calculation['free_hours'], 1) . "\n";
                                    }

                                    $paidHours = $calculation['total_hours'] - $calculation['free_hours'];
                                    if ($paidHours > 0) {
                                        $summary .= "Paid hours: " . number_format($paidHours, 1) . "\n";
                                    }

                                    $summary .= "\nTotal cost: $" . number_format($calculation['cost'], 2);

                                    return $summary;
                                })
                                ->columnSpanFull(),
                        ]),

                    // Step 3: Final Details
                    Wizard\Step::make('Details')
                        ->description('Add notes and confirm reservation')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Textarea::make('notes')
                                ->label('Notes (Optional)')
                                ->placeholder('What will you be working on? Any special setup needed?')
                                ->rows(4)
                                ->columnSpanFull(),

                            // Admin-only status override
                            ...$isAdmin ? [
                                Select::make('status')
                                    ->label('Status Override (Admin Only)')
                                    ->options([
                                        'auto' => 'Automatic (based on date/type)',
                                        'pending' => 'Force Pending',
                                        'confirmed' => 'Force Confirmed',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('auto')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        if ($state === 'auto') {
                                            self::updateStatus($get, $set);
                                        }
                                    })
                                    ->helperText('Leave as "Automatic" to use normal confirmation rules')
                                    ->columnSpanFull(),
                            ] : [],

                            // Hidden status field that gets automatically set
                            Hidden::make('status'),

                            TextEntry::make('final_summary')
                                ->label('Reservation Summary')
                                ->state(function (Get $get): string {
                                    $start = $get('reserved_at');
                                    $end = $get('reserved_until');
                                    $userId = $get('user_id');
                                    $notes = $get('notes');

                                    if (!$start || !$end || !$userId) {
                                        return 'Complete previous steps to see summary';
                                    }

                                    $user = User::find($userId);
                                    if (!$user) {
                                        return 'User not found';
                                    }

                                    $startFormatted = Carbon::parse($start)->format('l, M j, Y \a\t g:i A');
                                    $endFormatted = Carbon::parse($end)->format('g:i A');
                                    $duration = Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60;

                                    $reservationService = new ReservationService;
                                    $calculation = $reservationService->calculateCost(
                                        $user,
                                        Carbon::parse($start),
                                        Carbon::parse($end)
                                    );

                                    $summary = "Member: {$user->name}\n";
                                    $summary .= "Time: {$startFormatted} - {$endFormatted}\n";
                                    $summary .= "Duration: " . number_format($duration, 1) . " hours\n";
                                    $summary .= "Cost: $" . number_format($calculation['cost'], 2) . "\n";

                                    if ($notes) {
                                        $summary .= "Notes: {$notes}";
                                    }

                                    return $summary;
                                })
                                ->columnSpanFull(),
                        ]),
                ])->columnSpanFull(),

                // Hidden fields for calculated values
                Hidden::make('cost')->default(0),
                Hidden::make('free_hours_used')->default(0),
                Hidden::make('hours_used')->default(0),
            ]);
    }

    private static function updateDateTimes(Get $get, callable $set): void
    {
        $date = $get('reservation_date');
        $startTime = $get('start_time');
        $endTime = $get('end_time');

        if ($date && $startTime) {
            $set('reserved_at', $date . ' ' . $startTime);
        }

        if ($date && $endTime) {
            $set('reserved_until', $date . ' ' . $endTime);
        }

        // Update status whenever dates change
        self::updateStatus($get, $set);
    }

    private static function updateStatus(Get $get, callable $set): void
    {
        // Check if admin has overridden status
        $adminStatus = $get('status');
        if ($adminStatus && $adminStatus !== 'auto') {
            return; // Don't auto-update if admin has set a specific status
        }

        $date = $get('reservation_date');
        $isRecurring = $get('is_recurring');

        if (!$date) {
            $set('status', 'pending');
            return;
        }

        $reservationService = new ReservationService;
        $status = $reservationService->determineInitialStatus(
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

        $reservationService = new ReservationService;
        $calculation = $reservationService->calculateCost(
            $user,
            Carbon::parse($start),
            Carbon::parse($end)
        );

        $set('cost', $calculation['cost']);
        $set('free_hours_used', $calculation['free_hours']);
        $set('hours_used', $calculation['total_hours']);
    }
}
