<?php

namespace App\Filament\Kiosk\Pages;

use App\Actions\CheckIns\CheckInUser;
use App\Actions\Reservations\CreateWalkInReservation;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;

class WalkInReservation extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'tabler-calendar-plus';

    protected string $view = 'filament.kiosk.pages.walk-in-reservation';

    protected static ?string $title = 'Walk-In Reservation';

    protected static ?string $navigationLabel = 'Walk-In';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public function mount(): void
    {
        // Set smart defaults
        $now = now();
        $nextQuarterHour = $now->copy()->addMinutes(15 - ($now->minute % 15));

        $userId = request()->query('user');

        $this->form->fill([
            'user_id' => $userId,
            'start_time' => $nextQuarterHour,
            'duration' => 2, // Default 2 hours
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Member')
                            ->required()
                            ->searchable()
                            ->autofocus()
                            ->getSearchResultsUsing(fn (string $search): array =>
                                User::where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])
                                    ->toArray()
                            )
                            ->getOptionLabelUsing(fn ($value): ?string =>
                                User::find($value)?->name
                            )
                            ->helperText('Search by name or email'),

                        Forms\Components\DateTimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->seconds(false)
                            ->minutesStep(15)
                            ->native(false)
                            ->helperText('Time can be in the past for retroactive reservations'),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('1hour')
                                        ->label('1 Hour')
                                        ->size(ActionSize::Large)
                                        ->action(fn () => $this->data['duration'] = 1),
                                    Forms\Components\Actions\Action::make('2hours')
                                        ->label('2 Hours')
                                        ->size(ActionSize::Large)
                                        ->action(fn () => $this->data['duration'] = 2),
                                    Forms\Components\Actions\Action::make('3hours')
                                        ->label('3 Hours')
                                        ->size(ActionSize::Large)
                                        ->action(fn () => $this->data['duration'] = 3),
                                    Forms\Components\Actions\Action::make('4hours')
                                        ->label('4 Hours')
                                        ->size(ActionSize::Large)
                                        ->action(fn () => $this->data['duration'] = 4),
                                ]),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('duration')
                            ->label('Duration (hours)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(8)
                            ->step(0.5)
                            ->helperText('Between 1 and 8 hours'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        try {
            $user = User::findOrFail($data['user_id']);
            $startTime = Carbon::parse($data['start_time'], config('app.timezone'));
            $endTime = $startTime->copy()->addHours($data['duration']);

            $reservation = CreateWalkInReservation::run(
                $user,
                $startTime,
                $endTime,
                ['notes' => $data['notes'] ?? null]
            );

            // Automatically check in the user for this walk-in reservation
            CheckInUser::run($user, $reservation);

            Notification::make()
                ->success()
                ->title('Walk-In Created & Checked In')
                ->body("{$user->name} has been checked in for their walk-in reservation.")
                ->send();

            // Redirect to dashboard
            $this->redirect(KioskDashboard::getUrl());

        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title('Reservation Failed')
                ->body($e->getMessage())
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('An unexpected error occurred. Please try again.')
                ->send();

            \Log::error('Walk-in reservation creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }
}
