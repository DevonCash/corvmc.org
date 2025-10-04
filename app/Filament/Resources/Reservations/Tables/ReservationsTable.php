<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Filament\Resources\Reservations\Actions\BulkCancelAction;
use App\Filament\Resources\Reservations\Actions\CancelAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedBulkAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidBulkAction;
use App\Filament\Resources\Reservations\Actions\PayStripeAction;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('time_range')
                    ->label('Time Range')
                    ->state(function (Reservation $record): string {
                        return $record->time_range;
                    })
                    ->icon(fn($record) => $record->is_recurring ? 'tabler-repeat' : null)
                    ->iconPosition(IconPosition::After)
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
                    ->color(fn(Reservation $record): string => match ($record->status) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(['status'])
                    ->sortable(['status']),

                TextColumn::make('cost_display')
                    ->label('Cost')
                    ->getStateUsing(function (Reservation $record): string {
                        return $record->cost_display;
                    })
                    ->sortable(['cost']),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(function (string $state, Reservation $record): string {
                        return $record->payment_status_badge['label'];
                    })
                    ->color(function (string $state, Reservation $record): string {
                        return $record->payment_status_badge['color'];
                    })
                    ->sortable(),

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
            ->defaultSort('reserved_at', 'asc')
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

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('reserved_at', now()->month)),

                Filter::make('recurring')
                    ->label('Recurring Only')
                    ->query(fn(Builder $query): Builder => $query->where('is_recurring', true)),

                Filter::make('free_hours_used')
                    ->label('Used Free Hours')
                    ->query(fn(Builder $query): Builder => $query->where('free_hours_used', '>', 0)),
            ])

            ->recordActions([
                PayStripeAction::make(),

                ActionGroup::make([
                    MarkPaidAction::make(),
                    MarkCompedAction::make(),
                    CancelAction::make(),
                    ViewAction::make(),
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
            ->emptyStateDescription('Start by creating your first practice space reservation.');
    }
}
