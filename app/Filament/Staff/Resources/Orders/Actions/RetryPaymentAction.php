<?php

namespace App\Filament\Staff\Resources\Orders\Actions;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending as OrderPending;
use CorvMC\Finance\States\TransactionState\Failed as TransactionFailed;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class RetryPaymentAction
{
    public static function make(): Action
    {
        return Action::make('retry_payment')
            ->label('Retry Payment')
            ->icon('tabler-refresh')
            ->color('success')
            ->visible(fn (Order $record) => static::canRetry($record))
            ->action(function (Order $record) {
                try {
                    $checkoutUrl = Finance::retryStripePayment($record);

                    if (! $checkoutUrl) {
                        Notification::make()
                            ->title('No failed payment to retry')
                            ->warning()
                            ->send();

                        return;
                    }

                    return redirect($checkoutUrl);
                } catch (\Exception $e) {
                    Log::error('Failed to retry payment', [
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
