<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\User;
use App\Services\ReservationService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = User::me();
        $isAdmin = $user && $user->can('manage practice space');

        return $schema
            ->components([
                Wizard::make([
                    // Step 1: Reservation Details
                    Wizard\Step::make('Details')
                        ->description('Set up your reservation')
                        ->icon('heroicon-o-calendar-days')
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
                                    }),
                                Select::make('status_override')
                                    ->label('Status (Admin Only)')
                                    ->hintIcon('heroicon-o-information-circle')
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
                                    ->default($user?->id)
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
                                            return 'heroicon-o-calendar';
                                        }
                                        if (! $startTime) {
                                            return 'heroicon-o-clock';
                                        }
                                        if (! $endTime) {
                                            return 'heroicon-o-clock';
                                        }

                                        return 'heroicon-o-check';
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
                                            self::updateDateTimes($get, $set);
                                            self::calculateCost($get('user_id'), $get, $set);
                                        })
                                        ->hint(fn ($state) => $state ? '' : 'Select a date')
                                        ->minDate(now()->toDateString()),

                                    Select::make('start_time')
                                        ->label('Start Time')
                                        ->options(function (Get $get) {
                                            $date = $get('reservation_date');
                                            if (! $date) {
                                                return [];
                                            }

                                            $service = new ReservationService;

                                            return $service->getAvailableTimeSlotsForDate(Carbon::parse($date));
                                        })
                                        ->disabled(fn (Get $get) => ! $get('reservation_date'))
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            // Clear end time when start time changes
                                            $set('end_time', null);
                                            self::updateDateTimes($get, $set);
                                            self::calculateCost($get('user_id'), $get, $set);
                                        })
                                        ->hint(fn (Get $get, $state) => ! $get('reservation_date') || $state ? '' : 'Select a start time'),

                                    Select::make('end_time')
                                        ->label('End Time')
                                        ->hint(fn (Get $get, $state) => ! $get('start_time') || $state ? '' : 'Select an end time')
                                        ->options(function (Get $get) {
                                            $date = $get('reservation_date');
                                            $startTime = $get('start_time');
                                            if (! $date || ! $startTime) {
                                                return [];
                                            }

                                            $service = new ReservationService;

                                            return $service->getValidEndTimesForDateAndStart(Carbon::parse($date), $startTime);
                                        })
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            self::updateDateTimes($get, $set);
                                            self::calculateCost($get('user_id'), $get, $set);
                                        })
                                        ->disabled(fn (Get $get) => ! $get('start_time')),
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
                                            ->visible(fn (Get $get) => $get('payment_status') !== 'unpaid'),

                                        DateTimePicker::make('paid_at')
                                            ->label('Payment Date')
                                            ->visible(fn (Get $get) => in_array($get('payment_status'), ['paid', 'comped', 'refunded']))
                                            ->default(now()),

                                        Textarea::make('payment_notes')
                                            ->label('Payment Notes')
                                            ->placeholder('Notes about payment, comp reason, etc.')
                                            ->rows(2)
                                            ->visible(fn (Get $get) => $get('payment_status') !== 'unpaid'),
                                    ])
                                    ->columns(2),
                            ] : [],

                            // Hidden fields for the actual datetime values and status
                            Hidden::make('reserved_at'),
                            Hidden::make('reserved_until'),
                            Hidden::make('status'),
                        ])
                        ->columns(2),

                    // Step 2: Confirmation
                    Wizard\Step::make('Confirm')
                        ->description('Review and confirm your reservation')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            TextEntry::make('final_summary')
                                ->label('Reservation Summary')
                                ->state(function (Get $get): string {
                                    $start = $get('reserved_at');
                                    $end = $get('reserved_until');
                                    $userId = $get('user_id');
                                    $notes = $get('notes');
                                    $isRecurring = $get('is_recurring');

                                    if (! $start || ! $end || ! $userId) {
                                        return 'Complete previous step to see summary';
                                    }

                                    $user = User::find($userId);
                                    if (! $user) {
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

                                    $summary = "📅 {$startFormatted} - {$endFormatted}\n";
                                    $summary .= '⏱️ Duration: '.number_format($duration, 1)." hours\n";

                                    if ($calculation['free_hours'] > 0) {
                                        $summary .= '🎁 Free hours: '.number_format($calculation['free_hours'], 1)."\n";
                                    }

                                    $paidHours = $calculation['total_hours'] - $calculation['free_hours'];
                                    if ($paidHours > 0) {
                                        $summary .= '💳 Paid hours: '.number_format($paidHours, 1)."\n";
                                    }

                                    $summary .= '💰 Total cost: $'.number_format($calculation['cost'], 2)."\n";

                                    if ($isRecurring) {
                                        $summary .= "🔄 Recurring weekly reservation\n";
                                    }

                                    if ($notes) {
                                        $summary .= "📝 Notes: {$notes}\n";
                                    }

                                    // Add confirmation process info
                                    $reservationDate = Carbon::parse($start);
                                    if ($isRecurring) {
                                        $summary .= "\n📋 This recurring reservation requires manual approval.";
                                    } elseif ($reservationDate->isAfter(Carbon::now()->addWeek())) {
                                        $confirmationDate = $reservationDate->copy()->subWeek();
                                        $summary .= "\n📧 We'll send you a confirmation reminder on ".$confirmationDate->format('M j').'.';
                                    } else {
                                        $summary .= "\n✅ This reservation will be immediately confirmed.";
                                    }

                                    return $summary;
                                })
                                ->columnSpanFull(),
                        ]),
                ])->columnSpanFull()
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
    <x-filament::button
        type="submit"
        size="sm"
    >
        Submit
    </x-filament::button>
BLADE))),

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
            $set('reserved_at', $date.' '.$startTime);
        }

        if ($date && $endTime) {
            $set('reserved_until', $date.' '.$endTime);
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

        if (! $date) {
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
