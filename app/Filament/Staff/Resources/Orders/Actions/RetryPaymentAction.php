<?php

namespace App\Filament\Staff\Resources\Orders\Actions;

use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Pending as OrderPending;
use CorvMC\Finance\States\TransactionState\Failed as TransactionFailed;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class RetryPaymentAction
{
    /**
     * Create an action that retries a failed/expired Stripe payment
     * by creating a fresh Checkout Session for the existing Order.
     */
    public static function make(): Action
    {
        return Action::make('retry_payment')
            ->label('Retry Payment')
            ->icon('tabler-refresh')
            ->color('success')
            ->visible(fn (Order $record) => static::canRetry($record))
            ->action(function (Order $record) {
                $failedTxn = $record->transactions()
                    ->where('currency', 'stripe')
                    ->where('type', 'payment')
                    ->whereState('status', [TransactionFailed::class, TransactionCancelled::class])
                    ->first();

                if (! $failedTxn) {
                    Notification::make()
                        ->title('No failed payment to retry')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $user = $record->user;
                    $amount = $failedTxn->amount;

                    $newTxn = Transaction::create([
                        'order_id' => $record->id,
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
                                'product_data' => [
                                    'name' => "Order #{$record->id} — Retry Payment",
                                ],
                            ],
                            'quantity' => 1,
                        ]],
                        'metadata' => [
                            'type' => 'order',
                            'transaction_id' => $newTxn->id,
                            'order_id' => $record->id,
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
                } catch (\Exception $e) {
                    Log::error('Failed to create retry checkout session', [
                        'order_id' => $record->id,
                        'error' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->title('Payment Error')
                        ->body('Unable to create payment session: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private static function canRetry(Order $order): bool
    {
        if (! ($order->status instanceof OrderPending)) {
            return false;
        }

        return $order->transactions()
            ->where('currency', 'stripe')
            ->where('type', 'payment')
            ->whereState('status', [TransactionFailed::class, TransactionCancelled::class])
            ->exists();
    }
}
