<?php

namespace App\Filament\Resources\SpaceManagement\Tables;

use App\Filament\Resources\Reservations\Actions\BulkCancelAction;
use App\Filament\Resources\Reservations\Actions\CancelAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedBulkAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidBulkAction;
use App\Models\Reservation;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SpaceManagementTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn(Reservation $record) => $record->getReservationTypeLabel())
                    ->color(fn(Reservation $record) => $record instanceof \App\Models\ProductionReservation ? 'warning' : 'primary')
                    ->icon(fn(Reservation $record) => $record->getReservationIcon()),

                TextColumn::make('responsible_user')
                    ->label('Responsible')
                    ->getStateUsing(fn(Reservation $record) => $record->getResponsibleUser()?->name)
                    ->description(fn(Reservation $record): string => $record->getResponsibleUser()?->email ?? 'N/A')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('time_range')
                    ->label('When')
                    ->formatStateUsing(function (Reservation $record): string {
                        $date = $record->reserved_at->format('M j, Y');
                        $time = $record->reserved_at->format('g:i A') . ' - ' . $record->reserved_until->format('g:i A');
                        return $date;
                    })
                    ->description(function (Reservation $record): string {
                        return $record->reserved_at->format('g:i A') . ' - ' . $record->reserved_until->format('g:i A');
                    })
                    ->icon(fn($record) => $record->is_recurring ? 'tabler-repeat' : null)
                    ->iconPosition(IconPosition::After)
                    ->tooltip(fn($record) => $record->is_recurring ? 'Recurring reservation' : null)
                    ->searchable()
                    ->sortable(['reserved_at']),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function (Reservation $record): string {
                        return number_format($record->duration, 1) . ' hrs';
                    })
                    ->sortable(['reserved_at', 'reserved_until']),

                TextColumn::make('status_display')
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
                    ->sortable(['status']),

                TextColumn::make('cost_display')
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
                    ->sortable(['cost']),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('reserved_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'comped' => 'Comped',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                SelectFilter::make('type')
                    ->label('Reservation Type')
                    ->options([
                        'App\\Models\\RehearsalReservation' => 'Rehearsal',
                        'App\\Models\\ProductionReservation' => 'Production',
                    ])
                    ->multiple(),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reserved_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reserved_at', '<=', $date),
                            );
                    }),

                Filter::make('today')
                    ->label('Today')
                    ->query(fn(Builder $query): Builder => $query->whereDate('reserved_at', today())),

                Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('reserved_at', [now()->startOfWeek(), now()->endOfWeek()])),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('reserved_at', now()->month)),

                Filter::make('recurring')
                    ->label('Recurring Only')
                    ->query(fn(Builder $query): Builder => $query->where('is_recurring', true)),

                Filter::make('free_hours_used')
                    ->label('Used Free Hours')
                    ->query(fn(Builder $query): Builder => $query->where('free_hours_used', '>', 0)),

                Filter::make('needs_attention')
                    ->label('Needs Attention')
                    ->query(fn(Builder $query): Builder => $query
                        ->where(function ($query) {
                            $query->where('status', 'pending')
                                ->orWhere('payment_status', 'unpaid');
                        })),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    MarkPaidAction::make(),
                    MarkCompedAction::make(),
                    CancelAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    MarkPaidBulkAction::make(),
                    MarkCompedBulkAction::make(),
                    BulkCancelAction::make(),
                ]),
            ])
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('No reservations match your current filters.');
    }
}
