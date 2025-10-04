<?php

namespace App\Filament\Resources\SpaceManagement\Tables;

use App\Filament\Resources\Reservations\Actions\BulkCancelAction;
use App\Filament\Resources\Reservations\Actions\CancelAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedBulkAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidBulkAction;
use App\Filament\Resources\Reservations\Tables\Columns\ReservationColumns;
use App\Models\Reservation;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
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
                ReservationColumns::type(),
                ReservationColumns::responsibleUser(),
                ReservationColumns::timeRange(),
                ReservationColumns::duration(),
                ReservationColumns::statusDisplay(),
                ReservationColumns::costDisplay(),
                ReservationColumns::createdAt(),
                ReservationColumns::updatedAt(),
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
