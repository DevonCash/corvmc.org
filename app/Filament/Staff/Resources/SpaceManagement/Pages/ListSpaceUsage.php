<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\SpaceStatsWidget;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\UpcomingClosuresWidget;
use App\Models\User;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Services\UltraloqService;
use CorvMC\SpaceManagement\Settings\UltraloqSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSpaceUsage extends ListRecords
{
    protected static string $resource = SpaceManagementResource::class;

    protected static ?string $title = 'Space Management';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('space_closures')
                ->label('Space Closures')
                ->icon('tabler-calendar-off')
                ->color('gray')
                ->url(SpaceClosureResource::getUrl('index')),

            Action::make('recurring_reservations')
                ->label('Recurring Rehearsals')
                ->icon('tabler-calendar-repeat')
                ->color('gray')
                ->url(RecurringReservationResource::getUrl('index')),

            ActionGroup::make([
                $this->getUltraloqCredentialsAction(),
                $this->getUltraloqConnectAction(),
                $this->getUltraloqDeviceAction(),
            ])
                ->label('Lock Settings')
                ->icon('tabler-lock')
                ->color('gray')
                ->button(),

            Action::make('create_reservation')
                ->label('Create Reservation')
                ->icon('tabler-calendar-plus')
                ->modalWidth('lg')
                ->steps(ReservationForm::getStaffSteps())
                ->action(function (array $data) {
                    $user = User::find($data['user_id']);

                    // reserved_at and reserved_until are already Carbon instances from ReservationForm
                    $reservedAt = $data['reserved_at'];
                    $reservedUntil = $data['reserved_until'];

                    // Create reservation using Eloquent
                    $reservation = RehearsalReservation::create([
                        'reservable_type' => User::class,
                        'reservable_id' => $user->id,
                        'reserved_at' => $reservedAt,
                        'reserved_until' => $reservedUntil,
                        'status' => $data['status'] ?? 'confirmed',
                        'notes' => $data['notes'] ?? null,
                        'is_recurring' => $data['is_recurring'] ?? false,
                        'payment_status' => $data['payment_status'] ?? 'unpaid',
                        'hours_used' => $reservedAt->diffInMinutes($reservedUntil) / 60,
                    ]);

                    Notification::make()
                        ->title('Reservation Created')
                        ->body('The reservation has been created successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UpcomingClosuresWidget::class,
            SpaceStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-clock')
                ->badge(function () {
                    return Reservation::where('reserved_until', '>', now())
                        ->where('status', '!=', 'cancelled')
                        ->count();
                })
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('reserved_until', '>', now())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'needs_attention' => Tab::make('Needs Attention')
                ->icon('tabler-alert-circle')
                ->badge(fn () => Reservation::needsAttention()->count())
                ->badgeColor('warning')
                /** @phpstan-ignore method.notFound */
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->needsAttention()
                    ->orderBy('reserved_at', 'asc')),

            'all' => Tab::make('All')
                ->icon('tabler-calendar')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->orderBy('reserved_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

    protected function getUltraloqCredentialsAction(): Action
    {
        $settings = app(UltraloqSettings::class);

        return Action::make('ultraloq_credentials')
            ->label('API Credentials')
            ->icon('tabler-key')
            ->schema([
                TextInput::make('client_id')
                    ->label('Client ID')
                    ->required()
                    ->default($settings->client_id),
                TextInput::make('client_secret')
                    ->label('Client Secret')
                    ->required()
                    ->password()
                    ->revealable()
                    ->default($settings->client_secret),
            ])
            ->action(function (array $data) {
                $settings = app(UltraloqSettings::class);
                $settings->client_id = $data['client_id'];
                $settings->client_secret = $data['client_secret'];
                $settings->save();

                Notification::make()
                    ->title('Credentials Saved')
                    ->body('U-tec API credentials have been saved. Now connect your account.')
                    ->success()
                    ->send();
            });
    }

    protected function getUltraloqConnectAction(): Action
    {
        $settings = app(UltraloqSettings::class);

        return Action::make('ultraloq_connect')
            ->label($settings->isConnected() ? 'Reconnect U-tec' : 'Connect U-tec')
            ->icon($settings->isConnected() ? 'tabler-refresh' : 'tabler-plug-connected')
            ->color($settings->isConnected() ? 'gray' : 'primary')
            ->visible(fn () => $settings->client_id !== '')
            ->url(route('ultraloq.authorize'));
    }

    protected function getUltraloqDeviceAction(): Action
    {
        $settings = app(UltraloqSettings::class);

        return Action::make('ultraloq_device')
            ->label('Select Lock')
            ->icon('tabler-device-mobile')
            ->visible(fn () => $settings->isConnected())
            ->schema(function () {
                $service = app(UltraloqService::class);
                $devices = $service->discoverDevices() ?? [];

                $lockDevices = collect($devices)->filter(
                    fn ($d) => ($d['category'] ?? '') === 'SmartLock'
                );

                $options = $lockDevices->pluck('name', 'id')->toArray();

                if (empty($options)) {
                    $options = ['' => 'No locks found — check your U-tec account'];
                }

                return [
                    Select::make('device')
                        ->label('Lock Device')
                        ->options($options)
                        ->required()
                        ->default(app(UltraloqSettings::class)->device_id),
                ];
            })
            ->action(function (array $data) {
                $service = app(UltraloqService::class);
                $devices = $service->discoverDevices() ?? [];
                $selected = collect($devices)->firstWhere('id', $data['device']);

                $settings = app(UltraloqSettings::class);
                $settings->device_id = $data['device'];
                $settings->device_name = $selected['name'] ?? '';
                $settings->save();

                Notification::make()
                    ->title('Lock Selected')
                    ->body("Selected lock: {$settings->device_name}")
                    ->success()
                    ->send();
            });
    }
}
