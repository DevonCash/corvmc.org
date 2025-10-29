<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Filament\Resources\Reservations\Actions\BulkCancelAction;
use App\Filament\Resources\Reservations\Actions\CancelAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedAction;
use App\Filament\Resources\Reservations\Actions\MarkCompedBulkAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidAction;
use App\Filament\Resources\Reservations\Actions\MarkPaidBulkAction;
use App\Filament\Resources\Reservations\Actions\PayStripeAction;
use App\Filament\Resources\Reservations\Tables\Columns\ReservationColumns;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
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
            ->columns([

                Stack::make([
                    Split::make([
                        ReservationColumns::timeRange(),
                        Stack::make([
                            ReservationColumns::statusDisplay()->grow(false),
                            ReservationColumns::costDisplay()->grow(false),
                        ])->alignment(Alignment::End)->space(2)
                    ])
                    // Split::make([
                    //     // ReservationColumns::duration()
                    //     //     ->visibleFrom('md'),

                    // ])->from('md'),
                ]),
            ])
            ->contentGrid([
                'default' => 1,
                'sm' => 1,
                'md' => 2,
                'xl' => 3,
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
            ->recordActionsAlignment('end')
            ->recordActions([
                PayStripeAction::make(),

                ActionGroup::make([
                    MarkPaidAction::make(),
                    MarkCompedAction::make(),
                    CancelAction::make(),
                    ViewAction::make(),
                ])->extraDropdownAttributes(['class' => 'ml-auto'])
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
