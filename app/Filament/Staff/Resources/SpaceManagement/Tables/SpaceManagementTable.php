<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Tables;

use App\Filament\Member\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Member\Resources\Reservations\Tables\Columns\ReservationColumns;
use App\Filament\Shared\Actions\ViewAction;
use CorvMC\Finance\Actions\Payments\MarkReservationAsComped;
use CorvMC\Finance\Actions\Payments\MarkReservationAsPaid;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Actions\Reservations\ConfirmReservation;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;


class SpaceManagementTable
{
    private static ?Collection $closures = null;

    public static function configure(Table $table): Table
    {
        // Pre-load upcoming closures for row decoration
        static::$closures = SpaceClosure::query()
            ->where('ends_at', '>', now())
            ->get();

        return $table
            ->recordClasses(function (Reservation $record) {
                if (static::overlapsWithClosure($record)) {
                    return 'bg-danger-50 dark:bg-danger-950/20 border-l-4! border-l-danger-400 dark:border-l-danger-600';
                }

                return '';
            })
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
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(
                        function (Reservation $record) {
                            $reservedAt = $record->reserved_at;
                            $now = now();
                            $diffInDays = (int) $now->copy()->startOfDay()->diffInDays($reservedAt->copy()->startOfDay(), false);
                            $sameWeek = $now->isSameWeek($reservedAt);
                            $dateString = $reservedAt->format('M j Y');
                            if (!$sameWeek) {
                                return $dateString . ' (' . $reservedAt->format('l') . ')';
                            } else if ($diffInDays === 0) {
                                return $dateString . ' (today)';
                            } elseif ($diffInDays === 1) {
                                return $dateString . ' (tomorrow)';
                            } else {
                                return $dateString . ' (this ' . $reservedAt->format('l') . ')';
                            }
                        }
                    )
                    ->collapsible()
                    ->orderQueryUsing(
                        fn(Builder $query, string $direction) => $query->orderBy('reserved_at', $direction)
                    ),
            ])
            ->defaultGroup('reserved_at')
            ->filters([
                SelectFilter::make('status')
                    ->label('Reservation Status')
                    ->options(ReservationStatus::class)
                    ->multiple(),

                SelectFilter::make('charge.status')
                    ->label('Payment Status')
                    ->options(ChargeStatus::class)
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
                    ->modalHeading(fn(Reservation $record): string => 'Reservation #' . $record->id)
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

    private static function overlapsWithClosure(Reservation $record): bool
    {
        if (static::$closures === null || static::$closures->isEmpty()) {
            return false;
        }

        return static::$closures->contains(function (SpaceClosure $closure) use ($record) {
            return $closure->ends_at > $record->reserved_at
                && $closure->starts_at < $record->reserved_until;
        });
    }
}
