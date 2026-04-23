<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\Events\OrderCancelled;
use CorvMC\Finance\Facades\Finance;
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
        Finance::reverseDiscountCredits($order, 'order_cancelled');
    }

    public function entered(): void
    {
        $order = $this->getModel();

        OrderCancelled::dispatch($order);
    }
}
