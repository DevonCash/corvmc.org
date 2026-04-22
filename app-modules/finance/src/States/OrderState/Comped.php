<?php

namespace CorvMC\Finance\States\OrderState;

use CorvMC\Finance\Events\OrderSettled;
use CorvMC\Finance\States\OrderState;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;

class Comped extends OrderState
{
    public static $name = 'comped';

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'tabler-gift';
    }

    public function getLabel(): string
    {
        return 'Comped';
    }

    public function getDescription(): string
    {
        return 'Order waived — no payment';
    }

    public function entering(): void
    {
        $order = $this->getModel();

        // Cancel any pending Transactions — comped means no payment needed
        $order->transactions()
            ->whereState('status', TransactionPending::class)
            ->each(function ($transaction) {
                $transaction->status->transitionTo(TransactionCancelled::class);
                $transaction->update(['cancelled_at' => now()]);
            });
    }

    public function entered(): void
    {
        $order = $this->getModel();
        $order->update(['settled_at' => now()]);

        OrderSettled::dispatch($order);
    }
}
