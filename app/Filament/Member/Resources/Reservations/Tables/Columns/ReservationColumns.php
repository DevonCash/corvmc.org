<?php

namespace App\Filament\Member\Resources\Reservations\Tables\Columns;

use App\Filament\Staff\Resources\Orders\OrderResource;
use App\Models\EventReservation;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\States\OrderState;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ReservationColumns
{
    public static function id(): TextColumn
    {
        return TextColumn::make('id')
            ->label('#')
            ->prefix('#')
            ->sortable()
            ->width(0)
            ->grow(false);
    }

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
            ->grow(false)
            ->width(0)
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
            });
    }

    public static function timeRange(): TextColumn
    {
        return TextColumn::make('time_range')
            ->label('When')
            ->description(function (Reservation $record): string {
                return $record->reserved_at->format('M j, Y');
            })
            ->formatStateUsing(function (Reservation $record): string {
                return $record->reserved_at->format('g:i A') . ' - ' . $record->reserved_until->format('g:i A');
            })
            ->icon(fn($record) => $record->is_recurring ? 'tabler-repeat' : null)
            ->iconPosition(IconPosition::After)
            ->tooltip(fn($record) => $record->is_recurring ? 'Recurring reservation' : null)
            ->width(0)
            ->grow(false)
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
            ->tooltip(fn( $state ) => $state->getLabel())
            ->grow(true)
            ->width(0)
            ;
    }

    public static function costDisplay(): TextColumn
    {
        return TextColumn::make('cost_display')
            ->label('Cost')
            ->url(function (Reservation $record): ?string {
                try {
                    $order = Finance::findActiveOrder($record);

                    return $order ? OrderResource::getUrl('view', ['record' => $order]) : null;
                } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
                    return null;
                }
            })
            ->state(function (Reservation $record): string {
                $order = Finance::findActiveOrder($record);

                if (! $order) {
                    return number_format($record->duration, 1) . ' hrs';
                }

                if ($order->total_amount <= 0) {
                    return 'Free';
                }

                $amount = $order->formattedTotal();

                return match (true) {
                    $order->status instanceof OrderState\Pending => "{$amount} due " . $record->reserved_at->format('n/j'),
                    $order->status instanceof OrderState\Completed => "{$amount} paid " . ($order->settled_at?->format('n/j') ?? ''),
                    $order->status instanceof OrderState\Comped => 'Comped',
                    $order->status instanceof OrderState\Refunded => "{$amount} refunded",
                    $order->status instanceof OrderState\Cancelled => 'Cancelled',
                    default => $amount,
                };
            });
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
