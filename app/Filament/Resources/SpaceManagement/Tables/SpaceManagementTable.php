<?php

namespace App\Filament\Resources\SpaceManagement\Tables;

use App\Actions\Payments\MarkReservationAsComped;
use App\Actions\Payments\MarkReservationAsPaid;
use App\Actions\Reservations\CancelReservation;
use App\Actions\Reservations\ConfirmReservation;
use App\Actions\Reservations\UpdateReservation;
use App\Enums\PaymentStatus;
use App\Filament\Actions\Action;
use App\Filament\Actions\ViewAction;
use App\Filament\Resources\Reservations\Schemas\ReservationEditForm;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\Columns\ReservationColumns;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use Carbon\Carbon;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
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
                        fn(Builder $query, string $direction) => $query->orderBy('reserved_at', $direction)
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
                    ->options(PaymentStatus::class)
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
                        ->needsAttention()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema(fn($infolist) => ReservationInfolist::configure($infolist))
                    ->modalHeading(fn(Reservation $record): string => 'Reservation Details')
                    ->modalWidth('sm')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                MarkReservationAsComped::filamentAction(),
                MarkReservationAsPaid::filamentAction(),
                ConfirmReservation::filamentAction(),

                CancelReservation::filamentAction(),
            ])
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('No reservations match your current filters.');
    }
}
