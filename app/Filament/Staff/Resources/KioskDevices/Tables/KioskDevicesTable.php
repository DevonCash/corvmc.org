<?php

namespace App\Filament\Staff\Resources\KioskDevices\Tables;

use CorvMC\Kiosk\Models\KioskDevice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KioskDevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),
                IconColumn::make('has_tap_to_pay')
                    ->label('Tap-to-Pay')
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('paymentDevice.name')
                    ->label('Payment Device')
                    ->placeholder('N/A')
                    ->toggleable(),
                TextColumn::make('capabilities')
                    ->label('Features')
                    ->state(fn (KioskDevice $record) => static::getCapabilitiesBadges($record))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->active())
                    ->default(),
                Filter::make('tap_to_pay')
                    ->label('Tap-to-Pay Devices')
                    ->query(fn (Builder $query) => $query->withTapToPay()),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function getCapabilitiesBadges(KioskDevice $device): array
    {
        $badges = [];

        if ($device->canDoDoorWorkflow()) {
            $badges[] = 'Door';
        }

        if ($device->canPushPayments()) {
            $badges[] = 'Push';
        }

        if ($device->canAcceptCardPayments()) {
            $badges[] = 'Card';
        }

        return $badges;
    }
}
