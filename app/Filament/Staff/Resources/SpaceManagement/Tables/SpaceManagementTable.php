<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Tables;

use App\Filament\Actions\Reservations\CancelReservationAction;
use App\Filament\Actions\Reservations\ReservationConfirmAction;
use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Member\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Member\Resources\Reservations\Tables\Columns\ReservationColumns;
use App\Filament\Shared\Actions\ViewAction;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Cancelled;
use CorvMC\SpaceManagement\States\ReservationState\Completed;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
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
                ReservationColumns::id(),
                ReservationColumns::statusDisplay(),
                ReservationColumns::timeRange(),
                ReservationColumns::responsibleUser(),
                ReservationColumns::costDisplay(),
                ReservationColumns::createdAt(),
                ReservationColumns::updatedAt(),
            ])
            ->defaultSort('reserved_at', 'desc')
            ->toolbarActions([
                Action::make('create_reservation')
                    ->label('Create Reservation')
                    ->icon('tabler-calendar-plus')
                    ->modalWidth('lg')
                    ->steps(ReservationForm::getStaffSteps())
                    ->action(function (array $data) {
                        try {
                            $user = User::find($data['user_id']);

                            $reservedAt = Carbon::parse($data['reserved_at']);
                            $reservedUntil = Carbon::parse($data['reserved_until']);

                            $reservation = RehearsalReservation::create([
                                'reservable_type' => 'user',
                                'reservable_id' => $user->id,
                                'reserved_at' => $reservedAt,
                                'reserved_until' => $reservedUntil,
                                'status' => $data['status'] ?? 'confirmed',
                                'notes' => $data['notes'] ?? null,
                                'is_recurring' => $data['is_recurring'] ?? false,
                                'hours_used' => $reservedAt->diffInMinutes($reservedUntil) / 60,
                            ]);

                            Notification::make()
                                ->title('Reservation Created')
                                ->body('The reservation has been created successfully.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
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
            ->groupingSettingsHidden()
            ->filters([
                SelectFilter::make('status')
                    ->label('Reservation Status')
                    ->options([
                        Scheduled::$name => 'Scheduled',
                        Confirmed::$name => 'Confirmed',
                        Completed::$name => 'Completed',
                        Cancelled::$name => 'Cancelled',
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
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema(fn($infolist) => ReservationInfolist::configure($infolist))
                    ->modalHeading(fn(Reservation $record): string => 'Reservation #' . $record->id)
                    ->modalWidth('sm')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                ReservationConfirmAction::make(),

                CancelReservationAction::make(),
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
