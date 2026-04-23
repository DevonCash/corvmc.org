<?php

namespace App\Filament\Staff\Resources\Orders\Actions;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\TransactionState\Cleared;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RefundOrderAction
{
    public static function make(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->icon('tabler-receipt-refund')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record) => static::description($record))
            ->visible(fn (Order $record) => $record->status instanceof Completed || $record->status instanceof Comped)
            ->action(function (Order $record) {
                Finance::refund($record);
                Notification::make()->title('Order refunded')->success()->send();
            });
    }

    public static function description(Order $order): string
    {
        $currencies = $order->transactions()
            ->where('type', 'payment')
            ->whereState('status', Cleared::class)
            ->pluck('currency')
            ->unique();

        if ($currencies->isEmpty()) {
            return 'This order has no settled payments to refund. It will be marked as refunded.';
        }

        $parts = [];
        if ($currencies->contains('stripe')) {
            $parts[] = 'Stripe payments will be refunded automatically via the Stripe API.';
        }
        if ($currencies->contains('cash')) {
            $parts[] = 'Cash payments are marked as refunded — return the cash to the patron.';
        }

        return 'This will refund the order. ' . implode(' ', $parts);
    }
}
