<?php

namespace App\Filament\Band\Resources\BandReservationsResource\Pages;

use CorvMC\SpaceManagement\Actions\Reservations\CalculateReservationCost;
use CorvMC\SpaceManagement\Actions\Reservations\DetermineReservationStatus;
use CorvMC\SpaceManagement\Actions\Reservations\GetAvailableTimeSlotsForDate;
use CorvMC\SpaceManagement\Actions\Reservations\GetValidEndTimesForDate;
use CorvMC\SpaceManagement\Actions\Reservations\ValidateReservation;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use App\Filament\Band\Resources\BandReservationsResource;
use App\Models\Band;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Carbon\Carbon;
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

                            return GetAvailableTimeSlotsForDate::run(Carbon::parse($date, config('app.timezone')));
                        })
                        ->disabled(fn (Get $get) => ! $get('reservation_date'))
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

                            return GetValidEndTimesForDate::run(Carbon::parse($date, config('app.timezone')), $startTime);
                        })
                        ->required()
                        ->live(debounce: 300)
                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                            $this->updateDateTimes($get, $set);
                            $this->calculateCost($get, $set);
                        })
                        ->disabled(fn (Get $get) => ! $get('start_time')),
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
                ->view('filament.band.components.band-reservation-summary')
                ->columnSpanFull(),
        ];
    }

    protected function updateDateTimes(Get $get, callable $set): void
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

        $this->updateStatus($get, $set);
    }

    protected function updateStatus(Get $get, callable $set): void
    {
        $date = $get('reservation_date');

        if (! $date) {
            $set('status', 'pending');

            return;
        }

        $status = DetermineReservationStatus::run(Carbon::parse($date), false);
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

        // Calculate cost using the booking user's credits
        $calculation = CalculateReservationCost::run(
            $user,
            Carbon::parse($start),
            Carbon::parse($end)
        );

        $set('cost', $calculation['cost']->getMinorAmount()->toInt());
        $set('free_hours_used', $calculation['free_hours']);
        $set('hours_used', $calculation['total_hours']);
    }

    protected function handleRecordCreation(array $data): RehearsalReservation
    {
        /** @var Band $band */
        $band = Filament::getTenant();
        $user = Auth::user();

        $startTime = Carbon::parse($data['reserved_at']);
        $endTime = Carbon::parse($data['reserved_until']);

        // Validate the reservation
        $errors = ValidateReservation::run($user, $startTime, $endTime);
        if (! empty($errors)) {
            Notification::make()
                ->title('Validation Error')
                ->body(implode(' ', $errors))
                ->danger()
                ->send();

            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $status = $data['status'] ?? ReservationStatus::Scheduled;

        return DB::transaction(function () use ($band, $user, $startTime, $endTime, $data, $status) {
            $costCalculation = CalculateReservationCost::run($user, $startTime, $endTime);

            $reservation = RehearsalReservation::create([
                'user_id' => $user->id,
                'reservable_type' => Band::class,
                'reservable_id' => $band->id,
                'reserved_at' => $startTime,
                'reserved_until' => $endTime,
                'cost' => $costCalculation['cost'],
                'hours_used' => $costCalculation['total_hours'],
                'free_hours_used' => $costCalculation['free_hours'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'is_recurring' => false,
            ]);

            // Mark payment as not applicable if free
            if ($reservation->cost->isZero()) {
                $reservation->update([
                    'payment_status' => 'n/a',
                ]);
            }

            return $reservation;
        });
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
