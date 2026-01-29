<?php

namespace App\Filament\Staff\Resources\KioskDevices\Schemas;

use CorvMC\Kiosk\Models\KioskDevice;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KioskDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                static::nameField(),
                static::isActiveField(),
                static::apiKeySection(),
                static::capabilitiesSection(),
            ]);
    }

    protected static function nameField(): TextInput
    {
        return TextInput::make('name')
            ->label('Device Name')
            ->required()
            ->maxLength(255)
            ->placeholder('e.g., Front Door Tablet')
            ->helperText('A descriptive name to identify this device')
            ->columnSpanFull();
    }

    protected static function isActiveField(): Checkbox
    {
        return Checkbox::make('is_active')
            ->label('Active')
            ->helperText('Deactivated devices cannot access the API')
            ->default(true)
            ->columnSpanFull();
    }

    protected static function apiKeySection(): Section
    {
        return Section::make('API Key')
            ->description('The device uses this key to authenticate with the kiosk API')
            ->collapsible()
            ->schema([
                Placeholder::make('api_key_display')
                    ->label('API Key')
                    ->content(fn (?KioskDevice $record) => $record?->api_key ?? 'Will be generated on save')
                    ->helperText('Copy this key to configure the kiosk app. It cannot be changed after creation.')
                    ->columnSpanFull(),
                Placeholder::make('last_seen_at')
                    ->label('Last Activity')
                    ->content(fn (?KioskDevice $record) => $record?->last_seen_at?->diffForHumans() ?? 'Never')
                    ->columnSpanFull(),
            ]);
    }

    protected static function capabilitiesSection(): Section
    {
        return Section::make('Capabilities')
            ->description('Configure what this device can do')
            ->columns(1)
            ->schema([
                Checkbox::make('has_tap_to_pay')
                    ->label('Has Tap-to-Pay')
                    ->helperText('Enable if this device has NFC and can collect contactless card payments. Enables the Door Workflow and Payment Receiver mode.'),
                Select::make('payment_device_id')
                    ->label('Linked Payment Device')
                    ->relationship('paymentDevice', 'name', fn ($query) => $query->active()->withTapToPay())
                    ->searchable()
                    ->preload()
                    ->helperText('For devices without tap-to-pay: card payments will be pushed to this device for collection')
                    ->visible(fn ($get) => ! $get('has_tap_to_pay')),
                static::capabilitiesSummary(),
            ]);
    }

    protected static function capabilitiesSummary(): Placeholder
    {
        return Placeholder::make('capabilities_summary')
            ->label('Available Features')
            ->content(function ($get, ?KioskDevice $record) {
                $hasTapToPay = $get('has_tap_to_pay') ?? false;
                $hasLinkedDevice = $get('payment_device_id') !== null;

                $features = [];

                // Core features
                $features[] = 'Ticket Sales';
                $features[] = 'Check-in (QR Scan)';

                // Tap-to-pay features
                if ($hasTapToPay) {
                    $features[] = 'Door Workflow (quick tap sales)';
                    $features[] = 'Direct Card Collection';
                    $features[] = 'Payment Receiver Mode';
                } elseif ($hasLinkedDevice) {
                    $features[] = 'Card Payments (via linked device)';
                }

                $features[] = 'Cash Payments';

                return implode(' | ', $features);
            })
            ->columnSpanFull();
    }
}
