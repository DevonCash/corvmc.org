<?php

namespace App\Filament\Resources\SpaceManagement\Tables;

use App\Actions\Payments\MarkReservationAsComped;
use App\Actions\Payments\MarkReservationAsPaid;
use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\UpdateReservation;
use App\Filament\Resources\Reservations\Schemas\ReservationEditForm;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\Columns\ReservationColumns;
use App\Models\Reservation;
use Carbon\Carbon;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SpaceManagementTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ReservationColumns::statusDisplay(),
                ReservationColumns::responsibleUser(),
                ReservationColumns::timeRange(),
                ReservationColumns::costDisplay(),
                ReservationColumns::createdAt(),
                ReservationColumns::updatedAt(),
            ])
            ->defaultSort('reserved_at', 'desc')
            ->groups([
                Group::make('reserved_at')
                    ->label('Date')
                    ->date()
                    ->collapsible()
                    ->orderQueryUsing(
                        fn (Builder $query, string $direction) => $query->orderBy('reserved_at', $direction)
                    ),
            ])
            ->defaultGroup('reserved_at')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('reserved_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reserved_at', '<=', $date),
                            );
                    }),

                Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('reserved_at', today())),

                Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('reserved_at', [now()->startOfWeek(), now()->endOfWeek()])),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn (Builder $query): Builder => $query->whereMonth('reserved_at', now()->month)),

                Filter::make('recurring')
                    ->label('Recurring Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_recurring', true)),

                Filter::make('free_hours_used')
                    ->label('Used Free Hours')
                    ->query(fn (Builder $query): Builder => $query->where('free_hours_used', '>', 0)),

                Filter::make('needs_attention')
                    ->label('Needs Attention')
                    ->query(fn (Builder $query): Builder => $query->needsAttention()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->schema(fn ($infolist) => ReservationInfolist::configure($infolist))
                        ->modalHeading(fn (Reservation $record): string => 'Reservation Details')
                        ->modalWidth('3xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                    EditAction::make()
                        ->modalWidth('lg')
                        ->fillForm(function (Model $record): array {
                            // Convert datetime to date and time components for the form
                            $reservedAt = $record->reserved_at;
                            $reservedUntil = $record->reserved_until;

                            return [
                                'reservation_date' => $reservedAt->toDateString(),
                                'start_time' => $reservedAt->format('H:i'),
                                'end_time' => $reservedUntil->format('H:i'),
                                'status' => $record->status,
                                'payment_status' => $record->payment_status,
                                'notes' => $record->notes,
                            ];
                        })
                        ->using(function (Model $record, array $data): Model {
                            // Combine date and time fields in the app's timezone
                            $startTime = Carbon::parse($data['reservation_date'].' '.$data['start_time'], config('app.timezone'));
                            $endTime = Carbon::parse($data['reservation_date'].' '.$data['end_time'], config('app.timezone'));

                            $options = [
                                'notes' => $data['notes'] ?? null,
                                'status' => $data['status'] ?? $record->status,
                                'payment_status' => $data['payment_status'] ?? $record->payment_status,
                            ];

                            return UpdateReservation::run($record, $startTime, $endTime, $options);
                        })
                        ->form(fn ($form) => ReservationEditForm::configure($form)),
                    ConfirmReservation::filamentAction(),
                    MarkReservationAsPaid::filamentAction(),
                    MarkReservationAsComped::filamentAction(),
                    CancelReservation::filamentAction(),
                ]),
            ])
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('No reservations match your current filters.');
    }
}
