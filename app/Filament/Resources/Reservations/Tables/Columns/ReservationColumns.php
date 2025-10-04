<?php

namespace App\Filament\Resources\Reservations\Tables\Columns;

use App\Models\Reservation;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;

class ReservationColumns
{
    public static function type(): TextColumn
    {
        return TextColumn::make('type')
            ->label('Type')
            ->badge()
            ->getStateUsing(fn(Reservation $record) => $record->getReservationTypeLabel())
            ->color(fn(Reservation $record) => $record instanceof \App\Models\ProductionReservation ? 'warning' : 'primary')
            ->icon(fn(Reservation $record) => $record->getReservationIcon());
    }

    public static function responsibleUser(): TextColumn
    {
        return TextColumn::make('responsible_user')
            ->label('Responsible')
            ->getStateUsing(fn(Reservation $record) => $record->getResponsibleUser()?->name)
            ->description(fn(Reservation $record): string => $record->getResponsibleUser()?->email ?? 'N/A')
            ->searchable()
            ->sortable();
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

    public static function statusDisplay(): TextColumn
    {
        return TextColumn::make('status_display')
            ->label('Status')
            ->badge()
            ->formatStateUsing(function (Reservation $record): string {
                // For cancelled or pending, just show that
                if ($record->status === 'cancelled') {
                    return 'Cancelled';
                }
                if ($record->status === 'pending') {
                    return 'Pending';
                }

                // ProductionReservation doesn't have payment tracking
                if ($record instanceof \App\Models\ProductionReservation) {
                    return 'Confirmed';
                }

                // For RehearsalReservation with no cost, just show confirmed
                if ($record->cost->isZero() || $record->cost->isNegative()) {
                    return 'Confirmed';
                }

                // For confirmed with cost, show payment status
                return match ($record->payment_status) {
                    'paid' => 'Paid',
                    'comped' => 'Comped',
                    'refunded' => 'Refunded',
                    default => 'Unpaid',
                };
            })
            ->color(fn(Reservation $record): string => match (true) {
                $record->status === 'cancelled' => 'danger',
                $record->status === 'pending' => 'warning',
                $record instanceof \App\Models\ProductionReservation => 'success',
                $record->payment_status === 'paid' => 'success',
                $record->payment_status === 'comped' => 'info',
                $record->payment_status === 'refunded' => 'gray',
                $record->payment_status === 'unpaid' && $record->cost->isPositive() => 'danger',
                default => 'success',
            })
            ->searchable(['status', 'payment_status'])
            ->sortable(['status']);
    }

    public static function costDisplay(): TextColumn
    {
        return TextColumn::make('cost_display')
            ->label('Cost')
            ->getStateUsing(function (Reservation $record): string {
                // ProductionReservation has no cost
                if ($record instanceof \App\Models\ProductionReservation) {
                    return 'N/A';
                }

                // RehearsalReservation has cost
                if ($record instanceof \App\Models\RehearsalReservation) {
                    $display = $record->cost_display ?? 'Free';
                    if ($record->free_hours_used > 0) {
                        $display .= ' (' . number_format($record->free_hours_used, 1) . 'h free)';
                    }
                    return $display;
                }

                return 'N/A';
            })
            ->sortable(['cost']);
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
