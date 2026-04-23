<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Events\OrderCancelled;
use CorvMC\Finance\States\OrderState;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;

class Cancelled extends OrderState
{
    public static $name = 'cancelled';

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'tabler-x';
    }

    public function getLabel(): string
    {
        return 'Cancelled';
    }

    public function getDescription(): string
    {
        return 'Terminated before settlement';
    }

    public function entering(): void
    {
        $order = $this->getModel();

        // Cancel any Pending Transactions
        $order->transactions()
            ->whereState('status', TransactionPending::class)
            ->each(function ($transaction) {
                $transaction->status->transitionTo(TransactionCancelled::class);
                $transaction->update(['cancelled_at' => now()]);
            });

        // Reverse credit deductions — service was not delivered
        if ($order->user) {
            foreach ($order->lineItems as $lineItem) {
                if (! $lineItem->isDiscount()) {
                    continue;
                }

                $walletKey = str_replace('_discount', '', $lineItem->product_type);
                $blocks = (int) abs((float) $lineItem->quantity);

                if ($blocks > 0) {
                    $creditType = CreditType::from($walletKey);
                    $order->user->addCredit(
                        amount: $blocks,
                        creditType: $creditType,
                        source: 'order_cancelled',
                        sourceId: $order->id,
                        description: "Reversed: order #{$order->id} cancelled",
                    );
                }
            }
        }
    }

    public function entered(): void
    {
        $order = $this->getModel();

        OrderCancelled::dispatch($order);
    }
}
