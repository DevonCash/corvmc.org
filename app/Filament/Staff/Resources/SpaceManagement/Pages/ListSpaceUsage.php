<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\SpaceStatsWidget;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\UpcomingClosuresWidget;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Services\UltraloqService;
use CorvMC\SpaceManagement\Settings\UltraloqSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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

            $this->getLockSetupAction(),

            Action::make('create_reservation')
                ->label('Create Reservation')
                ->icon('tabler-calendar-plus')
                ->modalWidth('lg')
                ->steps(ReservationForm::getStaffSteps())
                ->action(function (array $data) {
                    try {
                        $user = User::find($data['user_id']);

                        $reservedAt = Carbon::parse($data['reserved_at']);
                        $reservedUntil = Carbon::parse($data['reserved_until']);

                        $reservation = RehearsalReservation::create([
                            'reservable_type' => 'user',
                            'reservable_id' => $user->id,
                            'reserved_at' => $reservedAt,
                            'reserved_until' => $reservedUntil,
                            'status' => $data['status'] ?? 'confirmed',
                            'notes' => $data['notes'] ?? null,
                            'is_recurring' => $data['is_recurring'] ?? false,
                            'hours_used' => $reservedAt->diffInMinutes($reservedUntil) / 60,
                        ]);

                        Notification::make()
                            ->title('Reservation Created')
                            ->body('The reservation has been created successfully.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
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

    protected function getLockSetupAction(): Action
    {
        return Action::make('lock_setup')
            ->label('Lock Settings')
            ->icon('tabler-lock')
            ->color('gray')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Save')
            ->schema(fn () => $this->getLockSetupSchema())
            ->action(function (array $data) {
                $settings = app(UltraloqSettings::class);

                // Save credentials
                if (! empty($data['client_id'])) {
                    $settings->client_id = $data['client_id'];
                    $settings->client_secret = $data['client_secret'] ?? '';
                    $settings->save();
                }

                // Save device selection
                if (! empty($data['device'])) {
                    $service = app(UltraloqService::class);
                    $devices = $service->discoverDevices() ?? [];
                    $selected = collect($devices)->firstWhere('id', $data['device']);

                    $settings->device_id = $data['device'];
                    $settings->device_name = $selected['name'] ?? '';
                    $settings->save();
                }

                Notification::make()
                    ->title('Lock Settings Saved')
                    ->success()
                    ->send();
            });
    }

    private function getLockSetupSchema(): array
    {
        $settings = app(UltraloqSettings::class);

        return [
            // Step 1: API Credentials
            Section::make('API Credentials')
                ->icon('tabler-key')
                ->description(new HtmlString(
                    'Register at <a href="https://developer.u-tec.com" target="_blank" class="underline">developer.u-tec.com</a>, '
                    . 'create an application, and copy the credentials below. '
                    . 'Set the OAuth redirect URI to: <code>' . e(route('ultraloq.callback')) . '</code>'
                ))
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
                ]),

            // Step 2: Connection
            Section::make('Connection')
                ->icon($settings->isConnected() ? 'tabler-circle-check-filled' : 'tabler-plug-connected')
                ->description($settings->isConnected()
                    ? 'Connected to U-tec.'
                    : 'Save credentials first, then connect your U-tec account.')
                ->schema([
                    Placeholder::make('connection_status')
                        ->hiddenLabel()
                        ->content(function () {
                            $settings = app(UltraloqSettings::class);

                            if ($settings->isConnected()) {
                                return new HtmlString(
                                    '<div class="flex items-center gap-2">'
                                    . '<span class="inline-flex items-center gap-1.5 text-sm font-medium text-success-600 dark:text-success-400">'
                                    . '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.06l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>'
                                    . 'Connected</span>'
                                    . '<span class="text-gray-400">·</span>'
                                    . '<a href="' . e(route('ultraloq.authorize')) . '" target="_blank" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline">Reconnect</a>'
                                    . '</div>'
                                );
                            }

                            if ($settings->client_id === '') {
                                return new HtmlString(
                                    '<span class="text-sm text-gray-500 dark:text-gray-400">Enter API credentials above first.</span>'
                                );
                            }

                            return new HtmlString(
                                '<a href="' . e(route('ultraloq.authorize')) . '" target="_blank" '
                                . 'class="inline-flex items-center gap-1.5 font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">'
                                . '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">'
                                . '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>'
                                . 'Connect U-tec Account</a>'
                                . '<br><span class="text-xs text-gray-400 dark:text-gray-500">Opens in a new tab. This section updates automatically when connected.</span>'
                            );
                        })
                        ->poll('5s'),
                ]),

            // Step 3: Device Selection
            Section::make('Lock Device')
                ->icon('tabler-device-mobile')
                ->description('Choose which Ultraloq to control for practice space access.')
                ->schema(function () {
                    $settings = app(UltraloqSettings::class);

                    if (! $settings->isConnected()) {
                        return [
                            Placeholder::make('device_status')
                                ->hiddenLabel()
                                ->content(new HtmlString(
                                    '<span class="text-sm text-gray-500 dark:text-gray-400">Connect your U-tec account first.</span>'
                                )),
                        ];
                    }

                    $service = app(UltraloqService::class);
                    $devices = $service->discoverDevices() ?? [];

                    $lockDevices = collect($devices)->filter(
                        fn ($d) => ($d['category'] ?? '') === 'SmartLock'
                    );

                    $options = $lockDevices->pluck('name', 'id')->toArray();

                    if (empty($options)) {
                        return [
                            Placeholder::make('device_status')
                                ->hiddenLabel()
                                ->content('No locks found. Make sure your Ultraloq is registered in the U-tec app.'),
                        ];
                    }

                    return [
                        Select::make('device')
                            ->label('Lock')
                            ->options($options)
                            ->required()
                            ->default($settings->device_id),
                    ];
                })
                ->poll('5s'),
        ];
    }
}
