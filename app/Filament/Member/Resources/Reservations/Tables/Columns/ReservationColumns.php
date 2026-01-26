<?php

namespace App\Filament\Member\Resources\Reservations\Tables\Columns;

use App\Models\EventReservation;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ReservationColumns
{
    public static function type(): TextColumn
    {
        return TextColumn::make('type')
            ->label('Type')
            ->badge()
            ->getStateUsing(fn(Reservation $record) => $record->getReservationTypeLabel())
            ->color(fn(Reservation $record) => $record instanceof EventReservation ? 'warning' : 'primary')
            ->icon(fn(Reservation $record) => $record->getReservationIcon());
    }

    public static function responsibleUser(): TextColumn
    {
        return TextColumn::make('responsible_user')
            ->label('Responsible')
            ->getStateUsing(function (Reservation $record) {
                if ($record instanceof EventReservation) {
                    return $record->getDisplayTitle();
                }

                return $record->getResponsibleUser()?->name;
            })
            ->description(function (Reservation $record): string {
                if ($record instanceof EventReservation) {
                    return $record->getResponsibleUser()?->name ?? 'N/A';
                }

                if ($record->isFirstReservationForUser()) {
                    return 'First reservation!';
                }

                return $record->getResponsibleUser()?->email ?? 'N/A';
            })
            ->icon(function (Reservation $record) {
                if ($record instanceof EventReservation) {
                    return 'tabler-calendar';
                } elseif ($record instanceof RehearsalReservation) {
                    return 'tabler-metronome';
                }

                return null;
            })
            ->searchable();
    }

    public static function timeRange(): TextColumn
    {
        return TextColumn::make('time_range')
            ->label('When')
            ->formatStateUsing(function (Reservation $record): string {
                return $record->reserved_at->format('M j, Y');
            })
            ->description(function (Reservation $record): string {
                return $record->reserved_at->format('g:i A') . ' - ' . $record->reserved_until->format('g:i A');
            })
            ->icon(fn($record) => $record->is_recurring ? 'tabler-repeat' : null)
            ->iconPosition(IconPosition::After)
            ->tooltip(fn($record) => $record->is_recurring ? 'Recurring reservation' : null)
            ->searchable()
            ->sortable(['reserved_at']);
    }

    public static function duration(): TextColumn
    {
        return TextColumn::make('duration')
            ->label('Duration')
            ->getStateUsing(function (Reservation $record): string {
                return number_format($record->duration, 1) . ' hrs';
            })
            ->sortable(['reserved_at', 'reserved_until']);
    }

    public static function statusDisplay(): IconColumn
    {
        return IconColumn::make('status')
            ->label('')
            ->grow(false)
            ->width('1%');
    }

    public static function costDisplay(): TextColumn
    {
        return  TextColumn::make('charge.status');
    }

    public static function createdAt(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label('Created')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function updatedAt(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label('Updated')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
