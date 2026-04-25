<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Pending as OrderPending;
use CorvMC\Finance\States\TransactionState\Failed as TransactionFailed;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PayWithCashAction
{
    public static function make(): Action
    {
        return Action::make('pay_cash')
            ->label('Pay with Cash')
            ->icon('tabler-cash')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('You\'ll need to pay in cash at the space before your reservation. Your booking will be confirmed now.')
            ->visible(fn (RehearsalReservation $record) => static::canPay($record))
            ->action(function (RehearsalReservation $record) {
                $existingOrder = Finance::findActiveOrder($record);

                // Retry: switch failed stripe order to cash
                if ($existingOrder && static::hasFailedStripePayment($existingOrder)) {
                    $amount = $existingOrder->total_amount;

                    Transaction::create([
                        'order_id' => $existingOrder->id,
                        'user_id' => $existingOrder->user_id,
                        'currency' => 'cash',
                        'amount' => $amount,
                        'type' => 'payment',
                        'metadata' => [],
                    ]);

                    Notification::make()
                        ->title('Switched to cash payment')
                        ->body('Please bring ' . $existingOrder->formattedTotal() . ' in cash to the space.')
                        ->success()
                        ->send();

                    return;
                }

                // Initial pay: create new order with cash
                $user = $record->getResponsibleUser();
                $lineItems = Finance::price([$record], $user);
                $totalCents = (int) $lineItems->sum('amount');

                $order = Order::create([
                    'user_id' => $user->id,
                    'total_amount' => 0,
                ]);

                foreach ($lineItems as $lineItem) {
                    $lineItem->order_id = $order->id;
                    $lineItem->save();
                }

                $order->update(['total_amount' => $totalCents]);

                Finance::commit($order->fresh(), ['cash' => $totalCents]);
                $record->status->transitionTo(Confirmed::class);

                Notification::make()
                    ->title('Reservation confirmed')
                    ->body('Please bring ' . $order->formattedTotal() . ' in cash to the space.')
                    ->success()
                    ->send();
            });
    }

    private static function canPay(RehearsalReservation $record): bool
    {
        // Must be within the confirmation window
        if ($record->reserved_at->gt(now()->addWeek())) {
            return false;
        }

        $existingOrder = Finance::findActiveOrder($record);

        // No order yet — initial pay (must be Scheduled and non-free)
        if (! $existingOrder) {
            if (! $record->status instanceof Scheduled) {
                return false;
            }

            $total = (int) Finance::price([$record], $record->getResponsibleUser())->sum('amount');

            return $total > 0;
        }

        // Has order with failed payment — can switch to cash
        return static::hasFailedStripePayment($existingOrder);
    }

    private static function hasFailedStripePayment(Order $order): bool
    {
        return $order->status instanceof OrderPending
            && $order->transactions()
                ->where('currency', 'stripe')
                ->where('type', 'payment')
                ->whereState('status', [TransactionFailed::class, TransactionCancelled::class])
                ->exists();
    }
}
