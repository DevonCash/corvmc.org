<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Actions\Payments\MarkReservationAsComped;
use App\Actions\Payments\MarkReservationAsPaid;
use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Actions\Reservations\ConfirmReservation;
use CorvMC\SpaceManagement\Actions\Reservations\CreateCheckoutSession;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\Columns\ReservationColumns;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (Builder $query) => User::me()->rehearsals())
            ->columns([
                Stack::make([
                    Split::make([
                        ReservationColumns::timeRange(),
                        Stack::make([
                            ReservationColumns::statusDisplay()->grow(false),
                            ReservationColumns::costDisplay()->grow(false),
                        ])->alignment(Alignment::End)->space(2),
                    ]),
                ]),
            ])
            ->contentGrid([
                'default' => 1,
                'sm' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->defaultSort('reserved_at', 'asc')
            ->recordAction('view')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Scheduled',
                        'reserved' => 'Reserved',
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
                                fn (Builder $query, $date): Builder => $query->whereDate('reserved_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('reserved_at', '<=', $date),
                            );
                    }),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn (Builder $query): Builder => $query->whereMonth('reserved_at', now()->month)),

                Filter::make('recurring')
                    ->label('Recurring Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_recurring', true)),

                Filter::make('free_hours_used')
                    ->label('Used Free Hours')
                    ->query(fn (Builder $query): Builder => $query->where('free_hours_used', '>', 0)),
            ])
            ->recordActionsAlignment('end')
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->schema(fn (Schema $infolist) => ReservationInfolist::configure($infolist))
                    ->modalHeading(fn (Reservation $record): string => "Reservation #{$record->id}")
                    ->modalFooterActions([
                        CancelReservation::filamentAction(),
                        CreateCheckoutSession::filamentAction(),
                        ConfirmReservation::filamentAction(),
                    ]),
                ConfirmReservation::filamentAction(),
                CreateCheckoutSession::filamentAction(),
                ActionGroup::make([
                    CancelReservation::filamentAction(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    MarkReservationAsPaid::filamentBulkAction(),
                    MarkReservationAsComped::filamentBulkAction(),
                    CancelReservation::filamentBulkAction(),
                ]),
            ])
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('Start by creating your first practice space reservation.');
    }
}
