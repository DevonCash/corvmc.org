<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Actions;

use CorvMC\SpaceManagement\Services\UltraloqService;
use CorvMC\SpaceManagement\Settings\UltraloqSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Illuminate\Support\HtmlString;

class LockSetupAction
{
    public static function make(): Action
    {
        return Action::make('lock_setup')
            ->label('Lock Settings')
            ->icon('tabler-lock')
            ->color('gray')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Save')
            ->schema(fn () => static::getSchema())
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

    private static function getSchema(): array
    {
        $settings = app(UltraloqSettings::class);

        return [
            // Step 1: API Credentials
            Fieldset::make('API Credentials')
                ->columns(1)
                ->contained(false)
                ->schema([
                    Text::make(new HtmlString(
                        'Enable OpenAPI access in your Xthings Home app and create API credentials. '
                            . 'Set the OAuth redirect URI to: <code>' . e(route('ultraloq.callback')) . '</code>'
                    )),
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
            Fieldset::make('Connection')
                ->columns(1)
                ->contained(false)
                ->schema([
                    TextEntry::make('connection_status')
                        ->hiddenLabel()
                        ->state(function () {
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
            Fieldset::make('Lock Device')
                ->columns(1)
                ->contained(false)
                ->schema(function () {
                    $settings = app(UltraloqSettings::class);

                    if (! $settings->isConnected()) {
                        return [
                            TextEntry::make('device_status')
                                ->hiddenLabel()
                                ->state(new HtmlString(
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
