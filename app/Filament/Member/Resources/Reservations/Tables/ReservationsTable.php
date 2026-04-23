<?php

namespace App\Filament\Member\Resources\Reservations\Tables;

use App\Filament\Actions\Reservations\CancelReservationAction;
use App\Filament\Actions\Reservations\PayWithCashAction;
use App\Filament\Actions\Reservations\PayWithStripeAction;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending as OrderPending;
use CorvMC\Finance\States\TransactionState\Failed as TransactionFailed;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use App\Filament\Member\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Member\Resources\Reservations\Tables\Columns\ReservationColumns;
use CorvMC\SpaceManagement\Models\Reservation;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
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
                    TextColumn::make('title')
                        ->getStateUsing(fn () => 'Rehearsal Reservation')
                        ->weight('bold')
                        ->icon('tabler-metronome')
                        ->size('lg'),
                    Split::make([
                        ReservationColumns::timeRange(),
                        Stack::make([
                            ReservationColumns::statusDisplay()->grow(false)
                            ->extraAttributes(['style' => 'margin-top: -0.1rem; margin-bottom: -0.1rem;']),
                            ReservationColumns::costDisplay()->grow(false),
                        ])->alignment(Alignment::End)->space(2),
                    ])->extraAttributes(['style' => 'align-items:flex-start;']),
                ])->space(2),
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
                        PayWithStripeAction::make(),
                        PayWithCashAction::make(),
                        CancelReservationAction::make(),
                    ]),
                PayWithStripeAction::make(),
                PayWithCashAction::make(),
                \Filament\Actions\Action::make('retry_payment')
                    ->label('Retry Payment')
                    ->icon('tabler-refresh')
                    ->color('success')
                    ->visible(function (Reservation $record) {
                        $order = Finance::findActiveOrder($record);

                        return $order
                            && $order->status instanceof OrderPending
                            && $order->transactions()
                                ->where('currency', 'stripe')
                                ->where('type', 'payment')
                                ->whereState('status', [TransactionFailed::class, TransactionCancelled::class])
                                ->exists();
                    })
                    ->action(function (Reservation $record) {
                        $order = Finance::findActiveOrder($record);
                        if (! $order) {
                            return;
                        }

                        $failedTxn = $order->transactions()
                            ->where('currency', 'stripe')
                            ->where('type', 'payment')
                            ->whereState('status', [TransactionFailed::class, TransactionCancelled::class])
                            ->first();

                        if (! $failedTxn) {
                            return;
                        }

                        $user = $record->getResponsibleUser();
                        $amount = $failedTxn->amount;

                        $newTxn = \CorvMC\Finance\Models\Transaction::create([
                            'order_id' => $order->id,
                            'user_id' => $user->id,
                            'currency' => 'stripe',
                            'amount' => $amount,
                            'type' => 'payment',
                            'metadata' => [],
                        ]);

                        $checkout = \Laravel\Cashier\Cashier::stripe()->checkout->sessions->create([
                            'mode' => 'payment',
                            'customer' => $user->stripeId() ?? $user->createAsStripeCustomer()->id,
                            'line_items' => [[
                                'price_data' => [
                                    'currency' => 'usd',
                                    'unit_amount' => $amount,
                                    'product_data' => ['name' => "Order #{$order->id} — Retry Payment"],
                                ],
                                'quantity' => 1,
                            ]],
                            'metadata' => [
                                'type' => 'order',
                                'transaction_id' => $newTxn->id,
                                'order_id' => $order->id,
                            ],
                            'success_url' => route('checkout.success') . '?user_id=' . $user->id . '&session_id={CHECKOUT_SESSION_ID}',
                            'cancel_url' => route('checkout.cancel') . '?type=order',
                        ]);

                        $newTxn->update([
                            'metadata' => [
                                'session_id' => $checkout->id,
                                'checkout_url' => $checkout->url,
                                'retry_of' => $failedTxn->id,
                            ],
                        ]);

                        return redirect($checkout->url);
                    }),
                ActionGroup::make([
                    CancelReservationAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CancelReservationAction::bulkAction(),
                ]),
            ])
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('Start by creating your first practice space reservation.');
    }
}
