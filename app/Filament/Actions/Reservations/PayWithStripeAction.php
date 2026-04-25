<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending as OrderPending;
use CorvMC\Finance\States\TransactionState\Failed as TransactionFailed;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PayWithStripeAction
{
    public static function make(): Action
    {
        return Action::make('pay_stripe')
            ->label('Pay Online')
            ->icon('tabler-credit-card')
            ->color('success')
            ->visible(fn (RehearsalReservation $record) => static::canPay($record))
            ->action(function (RehearsalReservation $record) {
                $existingOrder = Finance::findActiveOrder($record);

                // Retry: existing order with failed/cancelled stripe transaction
                if ($existingOrder && static::hasFailedStripePayment($existingOrder)) {
                    try {
                        $checkoutUrl = Finance::retryStripePayment($existingOrder);

                        if ($checkoutUrl) {
                            return redirect($checkoutUrl);
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Payment Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }
                }

                // Initial pay: create new order
                $user = $record->getResponsibleUser();
                $lineItems = Finance::price([$record], $user);
                $totalCents = (int) $lineItems->sum('amount');

                // Free reservation — no payment needed
                if ($totalCents <= 0) {
                    Notification::make()
                        ->title('No payment required')
                        ->success()
                        ->send();

                    return;
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'total_amount' => 0,
                ]);

                foreach ($lineItems as $lineItem) {
                    $lineItem->order_id = $order->id;
                    $lineItem->save();
                }

                $order->update(['total_amount' => $totalCents]);

                $committed = Finance::commit($order->fresh(), ['stripe' => $totalCents]);

                $checkoutUrl = $committed->checkoutUrl();
                if ($checkoutUrl) {
                    return redirect($checkoutUrl);
                }

                Notification::make()
                    ->title('Payment session created')
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

        // No order yet — initial pay (must be Confirmed)
        if (! $existingOrder) {
            return $record->status instanceof Confirmed;
        }

        // Has order with failed payment — retry
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
