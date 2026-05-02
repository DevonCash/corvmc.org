<?php

namespace App\Filament\Band\Resources\BandReservationsResource\Pages;

use CorvMC\SpaceManagement\Facades\ReservationService;
use App\Filament\Band\Resources\BandReservationsResource;
use CorvMC\Bands\Models\Band;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateBandReservation extends CreateRecord
{
    protected static string $resource = BandReservationsResource::class;

    protected static ?string $title = 'Reserve Practice Space';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make()
                    ->columnSpanFull()
                    ->steps($this->getSteps()),
            ]);
    }

    protected function getSteps(): array
    {
        return [
            Wizard\Step::make('Schedule')
                ->icon('tabler-calendar-time')
                ->schema($this->reservationStep())
                ->columns(2),

            Wizard\Step::make('Confirm')
                ->icon('tabler-circle-check')
                ->schema($this->confirmationStep()),
        ];
    }

    protected function reservationStep(): array
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
                            $set('start_time', null);
                            $set('end_time', null);
                            $this->updateDateTimes($get, $set);
                            $this->calculateCost($get, $set);
                        })
                        ->minDate(now()->addDay()->toDateString()),

                    Select::make('start_time')
                        ->label('Start Time')
                        ->options(function (Get $get) {
                            $date = $get('reservation_date');
                            if (! $date) {
                                return [];
                            }

                            return ReservationService::getAvailableTimeSlots(Carbon::parse($date, config('app.timezone')));
                        })
                        ->disabled(fn(Get $get) => ! $get('reservation_date'))
                        ->required()
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            $set('end_time', null);
                            $this->updateDateTimes($get, $set);
                            $this->calculateCost($get, $set);
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
                            $this->updateDateTimes($get, $set);
                            $this->calculateCost($get, $set);
                        })
                        ->disabled(fn(Get $get) => ! $get('start_time')),
                ])->columnSpanFull(),

            Textarea::make('notes')
                ->label('Notes (Optional)')
                ->placeholder('What will you be working on? Any special setup needed?')
                ->rows(3)
                ->columnSpanFull(),

            Hidden::make('reserved_at'),
            Hidden::make('reserved_until'),
            Hidden::make('status'),
        ];
    }

    protected function confirmationStep(): array
    {
        return [
            Hidden::make('cost')->default(0),
            Hidden::make('free_hours_used')->default(0),
            Hidden::make('hours_used')->default(0),

            ViewField::make('reservation_summary')
                ->label('Reservation Summary')
                ->view('bands::filament.components.band-reservation-summary')
                ->columnSpanFull(),
        ];
    }

    protected function updateDateTimes(Get $get, callable $set): void
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

        $this->updateStatus($get, $set);
    }

    protected function updateStatus(Get $get, callable $set): void
    {
        $date = $get('reservation_date');

        if (! $date) {
            $set('status', 'pending');

            return;
        }

        $status = RehearsalReservation::determineStatusForDate(Carbon::parse($date), false);
        $set('status', $status);
    }

    protected function calculateCost(Get $get, callable $set): void
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

        $reservation = new RehearsalReservation([
            'reservable_type' => Band::class,
            'reservable_id' => Filament::getTenant()->id,
            'reserved_at' => Carbon::parse($start),
            'reserved_until' => Carbon::parse($end),
        ]);

        // Calculate cost using Finance::price() which applies credit discounts
        $lineItems = Finance::price([$reservation], $user);
        $totalCents = (int) $lineItems->sum('amount');
        $hours = $reservation->reserved_at->floatDiffInHours($reservation->reserved_until);
        $discountBlocks = (int) abs($lineItems->filter->isDiscount()->sum('quantity'));

        $set('cost', max(0, $totalCents));
        $set('free_hours_used', $discountBlocks);
        $set('hours_used', $hours);
    }

    protected function handleRecordCreation(array $data): RehearsalReservation
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        // Simply create the reservation - validation happens automatically
        $reservation = RehearsalReservation::create([
            'reservable_type' => Band::class,
            'reservable_id' => $band->id,
            'reserved_at' => $data['reserved_at'],
            'reserved_until' => $data['reserved_until'],
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? null, // Will be set by model if null
        ]);

        return $reservation;
    }

    protected function getRedirectUrl(): string
    {
        return BandReservationsResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Reservation created for ' . Filament::getTenant()->name;
    }
}
